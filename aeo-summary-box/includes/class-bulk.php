<?php
namespace AEO_Summary_Box;

defined( 'ABSPATH' ) || exit;

/**
 * Sinh tóm tắt AI hàng loạt từ danh sách Posts.
 *
 * Tính năng:
 *  - Cột "Tóm tắt AI" trong màn hình quản lý bài viết (hiện trạng thái ✅/⚪).
 *  - Bulk action "✨ Tạo tóm tắt AI" để thêm các bài đã chọn vào hàng đợi.
 *  - Hàng đợi WP-Cron xử lý từng bài một (5 giây/bài) tránh timeout.
 *  - Admin notice sau khi thêm vào hàng đợi.
 */
class Bulk {

	private static ?Bulk $instance = null;

	/** Option key lưu hàng đợi (mảng post IDs). */
	private const QUEUE_OPTION = 'aeo_sb_bulk_queue';

	/** Tên sự kiện WP-Cron. */
	private const CRON_HOOK = 'aeo_sb_process_queue';

	/** Khoảng cách giữa các lần xử lý (giây). */
	private const PROCESS_INTERVAL = 5;

	/** Số bài xử lý mỗi lần cron fire. */
	private const BATCH_SIZE = 5;

	/** Option key lưu trạng thái progress. */
	private const STATUS_OPTION = 'aeo_sb_bulk_status';

	public static function get_instance(): Bulk {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Hook cron phải đăng ký BẤT KỂ context (admin hay frontend),
		// vì WP-Cron chạy ở frontend — không dùng is_admin() guard ở đây.
		add_action( self::CRON_HOOK, [ $this, 'process_queue' ] );

		if ( ! is_admin() ) {
			return;
		}

		// Gọi trực tiếp thay vì add_action('init') vì init_components() đã chạy trong 'init'.
		$this->init_hooks();
	}

	/**
	 * Đăng ký hooks theo post type (chạy trong init để settings đã sẵn sàng).
	 */
	public function init_hooks(): void {
		$post_types = (array) Settings::get_instance()->get( 'post_types', [ 'post', 'page' ] );

		foreach ( $post_types as $pt ) {
			$pt = sanitize_key( $pt );

			add_filter( "bulk_actions-edit-{$pt}",              [ $this, 'register_bulk_action' ] );
			add_filter( "handle_bulk_actions-edit-{$pt}",       [ $this, 'handle_bulk_action' ], 10, 3 );
			add_filter( "manage_{$pt}_posts_columns",           [ $this, 'add_column' ] );
			add_action( "manage_{$pt}_posts_custom_column",     [ $this, 'render_column' ], 10, 2 );
		}

		add_action( 'admin_notices', [ $this, 'admin_notice' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_bulk_script' ] );
	}

	// ── Bulk action ──────────────────────────────────────────────────────────

	public function register_bulk_action( array $actions ): array {
		$actions['aeo_sb_generate'] = '✨ ' . __( 'Tạo tóm tắt AI', 'aeo-summary-box' );
		return $actions;
	}

	/**
	 * Xử lý khi người dùng submit bulk action.
	 *
	 * @param string $redirect_url URL chuyển hướng sau khi xử lý.
	 * @param string $action       Tên action.
	 * @param int[]  $post_ids     Danh sách post ID đã chọn.
	 */
	public function handle_bulk_action( string $redirect_url, string $action, array $post_ids ): string {
		if ( 'aeo_sb_generate' !== $action ) {
			return $redirect_url;
		}

		$current  = (array) get_option( self::QUEUE_OPTION, [] );
		$new_ids  = array_values(
			array_diff(
				array_map( 'absint', $post_ids ),
				array_map( 'absint', $current )
			)
		);

		if ( $new_ids ) {
			$merged = array_values( array_merge( array_map( 'absint', $current ), $new_ids ) );
			update_option( self::QUEUE_OPTION, $merged, false );

			// Khởi tạo / cập nhật progress status.
			$prev_status = (array) get_option( self::STATUS_OPTION, [] );
			update_option( self::STATUS_OPTION, [
				'total'   => count( $merged ),
				'done'    => (int) ( $prev_status['done'] ?? 0 ),
				'current' => '',
				'running' => true,
				'started' => $prev_status['started'] ?? time(),
			], false );

			// Lên lịch cron nếu chưa có + kích hoạt ngay.
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_single_event( time() + 2, self::CRON_HOOK );
			}
			spawn_cron();
		}

		return add_query_arg( 'aeo_sb_queued', count( $new_ids ), remove_query_arg( 'paged', $redirect_url ) );
	}

	public function admin_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$count = isset( $_GET['aeo_sb_queued'] ) ? (int) $_GET['aeo_sb_queued'] : 0;
		if ( $count <= 0 ) {
			return;
		}
		$msg = sprintf(
			/* translators: %d: number of posts added to queue */
			_n(
				'Đã thêm %d bài vào hàng đợi sinh tóm tắt AI. Cron sẽ xử lý trong vài giây.',
				'Đã thêm %d bài vào hàng đợi sinh tóm tắt AI. Cron sẽ xử lý trong vài giây.',
				$count,
				'aeo-summary-box'
			),
			$count
		);
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	// ── Admin column ─────────────────────────────────────────────────────────

	public function add_column( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['aeo_sb_summary'] = __( 'Tóm tắt AI', 'aeo-summary-box' );
			}
		}
		return $new;
	}

	public function render_column( string $column, int $post_id ): void {
		if ( 'aeo_sb_summary' !== $column ) {
			return;
		}

		$meta = get_post_meta( $post_id, AEO_SB_META_KEY, true );
		if ( $meta ) {
			$data  = json_decode( $meta, true );
			$count = count( (array) ( $data['bullets'] ?? [] ) );
			printf(
				'<span style="color:#2e7d32;" title="%s">✅</span> <small style="color:#666;">%s</small>',
				esc_attr__( 'Có tóm tắt AI', 'aeo-summary-box' ),
				/* translators: %d: bullet count */
				esc_html( sprintf( __( '%d bullets', 'aeo-summary-box' ), $count ) )
			);
		} else {
			echo '<span style="color:#aaa;" title="' . esc_attr__( 'Chưa có tóm tắt', 'aeo-summary-box' ) . '">⚪</span>';
		}
	}

	// ── WP-Cron: xử lý hàng đợi ─────────────────────────────────────────────

	/**
	 * Lấy BATCH_SIZE bài từ đầu hàng đợi, sinh tóm tắt, lưu vào post_meta.
	 * Sau đó lên lịch lần tiếp theo nếu còn bài trong hàng đợi.
	 */
	public function process_queue(): void {
		$queue = array_map( 'absint', (array) get_option( self::QUEUE_OPTION, [] ) );
		if ( empty( $queue ) ) {
			return;
		}

		$client      = new AI_Client();
		$bulk_intent = (string) Settings::get_instance()->get( 'bulk_default_intent', '' );
		if ( $bulk_intent && in_array( $bulk_intent, [ 'know', 'do', 'go', 'hybrid' ], true ) ) {
			$client->set_intent( $bulk_intent );
		}

		$batch = array_splice( $queue, 0, self::BATCH_SIZE );
		update_option( self::QUEUE_OPTION, $queue, false );

		foreach ( $batch as $post_id ) {
			$post = get_post( $post_id );

			// Cập nhật progress: bài đang xử lý.
			$status            = (array) get_option( self::STATUS_OPTION, [] );
			$status['current'] = $post ? get_the_title( $post_id ) : "(#{$post_id})";
			$status['running'] = true;
			update_option( self::STATUS_OPTION, $status, false );

			// Bỏ qua nếu bài không tồn tại hoặc chưa publish.
			if ( ! $post || 'publish' !== $post->post_status ) {
				$this->tick_progress();
				continue;
			}

			$result = $client->generate( $post->post_title, $post->post_content );

			if ( ! is_wp_error( $result ) ) {
				// Backup phiên bản hiện tại trước khi ghi đè (cho tính năng Hoàn tác).
				$old_raw = get_post_meta( $post_id, AEO_SB_META_KEY, true );
				if ( $old_raw ) {
					$backup = [
						'saved_at' => time(),
						'data'     => json_decode( $old_raw, true ),
					];
					update_post_meta( $post_id, AEO_SB_META_PREV_KEY, wp_json_encode( $backup, JSON_UNESCAPED_UNICODE ) );
				}

				update_post_meta(
					$post_id,
					AEO_SB_META_KEY,
					wp_json_encode( $result, JSON_UNESCAPED_UNICODE )
				);

				// Lưu token usage.
				$tokens = $client->get_last_tokens();
				if ( $tokens ) {
					$tokens['provider'] = Settings::get_instance()->get( 'provider', 'gemini' );
					$tokens['time']     = time();
					update_post_meta( $post_id, '_aeo_summary_tokens', wp_json_encode( $tokens ) );
				}

				// Xoá schema cache — sẽ được tái tạo tự động.
				Schema::get_instance()->clear_cache( $post_id );
			}

			$this->tick_progress();
		}

		// Tiếp tục với batch tiếp theo.
		if ( ! empty( $queue ) ) {
			wp_schedule_single_event( time() + self::PROCESS_INTERVAL, self::CRON_HOOK );
		} else {
			$this->finish_progress();
		}
	}

	/** Tăng done +1, xoá current. */
	private function tick_progress(): void {
		$status            = (array) get_option( self::STATUS_OPTION, [] );
		$status['done']    = (int) ( $status['done'] ?? 0 ) + 1;
		$status['current'] = '';
		update_option( self::STATUS_OPTION, $status, false );
	}

	/** Đánh dấu queue đã xử lý xong. */
	private function finish_progress(): void {
		$status            = (array) get_option( self::STATUS_OPTION, [] );
		$status['running'] = false;
		$status['current'] = '';
		update_option( self::STATUS_OPTION, $status, false );
	}

	/** Enqueue script progress bar trên màn hình danh sách bài viết. */
	public function enqueue_bulk_script(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		$post_types = (array) Settings::get_instance()->get( 'post_types', [ 'post', 'page' ] );
		$screens    = array_map( static fn( $pt ) => 'edit-' . sanitize_key( $pt ), $post_types );
		if ( ! in_array( $screen->id, $screens, true ) ) {
			return;
		}
		wp_enqueue_script(
			'aeo-sb-bulk',
			AEO_SB_URL . 'assets/js/admin-bulk.js',
			[ 'jquery' ],
			AEO_SB_VERSION,
			true
		);
		wp_localize_script( 'aeo-sb-bulk', 'aeoBulk', [
			'restBase' => rest_url( 'aeo-summary/v1' ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
		] );
	}
}
