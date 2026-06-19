<?php
namespace AEO_Summary_Box;

defined( 'ABSPATH' ) || exit;

class REST_API {

	private static ?REST_API $instance = null;

	public static function get_instance(): REST_API {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route( 'aeo-summary/v1', '/generate/(?P<post_id>\d+)', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_generate' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'post_id' => [
					'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0,
					'sanitize_callback' => 'absint',
				],
			],
		] );

		register_rest_route( 'aeo-summary/v1', '/save/(?P<post_id>\d+)', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_save' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'post_id' => [
					'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0,
					'sanitize_callback' => 'absint',
				],
			],
		] );

		register_rest_route( 'aeo-summary/v1', '/queue-all', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_queue_all' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		] );

		register_rest_route( 'aeo-summary/v1', '/bulk-status', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_bulk_status' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		] );

		register_rest_route( 'aeo-summary/v1', '/backup/(?P<post_id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_get_backup' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'post_id' => [
					'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0,
					'sanitize_callback' => 'absint',
				],
			],
		] );

		register_rest_route( 'aeo-summary/v1', '/flush-llms', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_flush_llms' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		] );

		register_rest_route( 'aeo-summary/v1', '/test-connection', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_test_connection' ],
			'permission_callback' => [ $this, 'check_admin_permission' ],
		] );

		register_rest_route( 'aeo-summary/v1', '/restore/(?P<post_id>\d+)', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_restore' ],
			'permission_callback' => [ $this, 'check_permission' ],
			'args'                => [
				'post_id' => [
					'validate_callback' => fn( $v ) => is_numeric( $v ) && $v > 0,
					'sanitize_callback' => 'absint',
				],
			],
		] );
	}

	/** Permission cho các endpoint không gắn với post_id cụ thể. */
	public function check_admin_permission( \WP_REST_Request $request ): bool|\WP_Error {
		if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'Nonce không hợp lệ.', 'aeo-summary-box' ), [ 'status' => 403 ] );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'forbidden', __( 'Bạn không có quyền thực hiện thao tác này.', 'aeo-summary-box' ), [ 'status' => 403 ] );
		}
		return true;
	}

	public function check_permission( \WP_REST_Request $request ): bool|\WP_Error {
		$post_id = (int) $request->get_param( 'post_id' );

		if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'Nonce không hợp lệ.', 'aeo-summary-box' ), [ 'status' => 403 ] );
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'forbidden', __( 'Bạn không có quyền chỉnh sửa bài này.', 'aeo-summary-box' ), [ 'status' => 403 ] );
		}

		return true;
	}

	public function handle_generate( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_REST_Response( [ 'error' => __( 'Không tìm thấy bài viết.', 'aeo-summary-box' ) ], 404 );
		}

		$client = new AI_Client();

		// Ghi đè ngôn ngữ theo ngôn ngữ bài viết nếu WPML / Polylang active.
		$post_lang = $this->detect_post_language( $post_id );
		if ( $post_lang ) {
			$client->set_language( $post_lang );
		}

		// Intent override — biên tập viên gửi từ metabox để AI không tự phân loại lại.
		$body   = $request->get_json_params() ?? [];
		$intent = sanitize_key( $body['intent'] ?? '' );
		if ( $intent && in_array( $intent, [ 'know', 'do', 'go', 'hybrid' ], true ) ) {
			$client->set_intent( $intent );
		}

		// Persona hint — điều chỉnh trường "note" theo đối tượng độc giả.
		$persona = sanitize_key( $body['persona'] ?? '' );
		if ( $persona && in_array( $persona, [ 'buyer', 'investor', 'renter' ], true ) ) {
			$client->set_persona( $persona );
		}

		$result = $client->generate( $post->post_title, $post->post_content );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( [ 'error' => $result->get_error_message() ], 500 );
		}

		// Lưu token usage vào post_meta để hiển thị trong metabox.
		$tokens = $client->get_last_tokens();
		if ( $tokens ) {
			$tokens['provider'] = Settings::get_instance()->get( 'provider', 'gemini' );
			$tokens['time']     = time();
			update_post_meta( $post_id, '_aeo_summary_tokens', wp_json_encode( $tokens ) );
		}

		return new \WP_REST_Response( [ 'summary' => $result, 'tokens' => $tokens ], 200 );
	}

	public function handle_save( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id = (int) $request->get_param( 'post_id' );
		$summary = $request->get_json_params()['summary'] ?? null;

		if ( ! is_array( $summary ) || empty( $summary['bullets'] ) ) {
			return new \WP_REST_Response( [ 'error' => __( 'Dữ liệu summary không hợp lệ.', 'aeo-summary-box' ) ], 400 );
		}

		// Sanitize trước khi lưu.
		$allowed_intents = [ 'know', 'do', 'go', 'hybrid' ];
		$intent          = $summary['intent'] ?? 'hybrid';
		$intent          = in_array( $intent, $allowed_intents, true ) ? $intent : 'hybrid';

		$allowed_personas = [ '', 'buyer', 'investor', 'renter' ];
		$persona          = $summary['persona'] ?? '';
		$persona          = in_array( $persona, $allowed_personas, true ) ? $persona : '';

		// Per-post contact override (dùng cho LocalBusiness schema khi intent = go).
		$pc = $summary['contact'] ?? [];

		$clean = [
			'intent'  => $intent,
			'persona' => $persona,
			'title'   => sanitize_text_field( $summary['title'] ?? '' ),
			'tldr'    => sanitize_text_field( $summary['tldr'] ?? '' ),
			'bullets' => array_map( fn( $b ) => [
				'label'    => sanitize_text_field( $b['label'] ?? '' ),
				'question' => sanitize_text_field( $b['question'] ?? '' ),
				'content'  => sanitize_text_field( $b['content'] ?? '' ),
			], (array) $summary['bullets'] ),
			'note'    => sanitize_text_field( $summary['note'] ?? '' ),
			'cta'     => sanitize_text_field( $summary['cta'] ?? '' ),
			'contact' => [
				'org_name' => sanitize_text_field( $pc['org_name'] ?? '' ),
				'address'  => sanitize_text_field( $pc['address']  ?? '' ),
				'phone'    => sanitize_text_field( $pc['phone']    ?? '' ),
				'hours'    => sanitize_text_field( $pc['hours']    ?? '' ),
			],
		];

		// Backup phiên bản hiện tại trước khi ghi đè (cho tính năng Hoàn tác).
		$old_raw = get_post_meta( $post_id, AEO_SB_META_KEY, true );
		if ( $old_raw ) {
			$backup = [
				'saved_at' => time(),
				'data'     => json_decode( $old_raw, true ),
			];
			update_post_meta( $post_id, AEO_SB_META_PREV_KEY, wp_json_encode( $backup, JSON_UNESCAPED_UNICODE ) );
		}

		// JSON_UNESCAPED_UNICODE: lưu UTF-8 trực tiếp, tránh wp_unslash() strip \uXXXX escapes.
		update_post_meta( $post_id, AEO_SB_META_KEY, wp_json_encode( $clean, JSON_UNESCAPED_UNICODE ) );

		// Xoá cache schema JSON-LD — sẽ được tái tạo ở request kế tiếp.
		Schema::get_instance()->clear_cache( $post_id );

		// Xoá llms.txt cache vì nội dung tóm tắt thay đổi.
		LLMS_Txt::get_instance()->flush_cache();

		return new \WP_REST_Response( [
			'saved'     => true,
			'prev_time' => $old_raw ? time() : 0,  // >0 = có backup mới, JS có thể inject nút Hoàn tác.
		], 200 );
	}

	/**
	 * Thêm tất cả bài publish vào hàng đợi.
	 *
	 * Tham số body JSON:
	 *  - overwrite (bool, default false): nếu true → thêm cả bài đã có tóm tắt (tạo lại).
	 *  - post_type (string, optional): giới hạn theo post type cụ thể.
	 */
	public function handle_queue_all( \WP_REST_Request $request ): \WP_REST_Response {
		$settings   = Settings::get_instance();
		$post_types = (array) $settings->get( 'post_types', [ 'post', 'page' ] );

		$body      = $request->get_json_params() ?? [];
		$overwrite = ! empty( $body['overwrite'] );

		// Lọc theo post_type nếu client gửi kèm.
		if ( ! empty( $body['post_type'] ) ) {
			$requested_pt = sanitize_key( $body['post_type'] );
			if ( in_array( $requested_pt, $post_types, true ) ) {
				$post_types = [ $requested_pt ];
			}
		}

		$query_args = [
			'post_type'        => $post_types,
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
		];

		// Nếu KHÔNG overwrite → chỉ lấy bài chưa có meta tóm tắt.
		if ( ! $overwrite ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			$query_args['meta_query'] = [ [
				'key'     => AEO_SB_META_KEY,
				'compare' => 'NOT EXISTS',
			] ];
		}

		$post_ids = get_posts( $query_args );

		if ( empty( $post_ids ) ) {
			return new \WP_REST_Response( [
				'queued'  => 0,
				'message' => $overwrite
					? __( 'Không có bài publish nào để xử lý.', 'aeo-summary-box' )
					: __( 'Tất cả bài đã có tóm tắt AI — không có gì để thêm.', 'aeo-summary-box' ),
			], 200 );
		}

		// Khi overwrite: reset hàng đợi hoàn toàn, không merge.
		if ( $overwrite ) {
			$new_ids = array_values( array_map( 'absint', $post_ids ) );
			$merged  = $new_ids;
			update_option( 'aeo_sb_bulk_queue', $merged, false );
		} else {
			// Merge vào queue hiện tại, tránh trùng.
			$current = array_map( 'absint', (array) get_option( 'aeo_sb_bulk_queue', [] ) );
			$new_ids = array_values( array_diff( array_map( 'absint', $post_ids ), $current ) );

			if ( empty( $new_ids ) ) {
				return new \WP_REST_Response( [
					'queued'  => 0,
					'message' => __( 'Các bài chưa có tóm tắt đã có trong hàng đợi rồi.', 'aeo-summary-box' ),
				], 200 );
			}

			$merged = array_values( array_merge( $current, $new_ids ) );
			update_option( 'aeo_sb_bulk_queue', $merged, false );
		}

		// Khởi tạo / reset progress.
		update_option( 'aeo_sb_bulk_status', [
			'total'   => count( $merged ),
			'done'    => 0,
			'current' => '',
			'running' => true,
			'started' => time(),
		], false );

		// Lên lịch cron nếu chưa có.
		if ( ! wp_next_scheduled( 'aeo_sb_process_queue' ) ) {
			wp_schedule_single_event( time() + 2, 'aeo_sb_process_queue' );
		}

		// Kích hoạt cron ngay lập tức, không chờ request tiếp theo.
		spawn_cron();

		return new \WP_REST_Response( [
			'queued'        => count( $new_ids ),
			'total_pending' => count( $merged ),
		], 200 );
	}

	/** Trả về trạng thái tiến trình bulk. */
	public function handle_bulk_status( \WP_REST_Request $request ): \WP_REST_Response {
		$status    = get_option( 'aeo_sb_bulk_status', null );
		$remaining = count( (array) get_option( 'aeo_sb_bulk_queue', [] ) );
		return new \WP_REST_Response( [
			'status'    => $status,
			'remaining' => $remaining,
		], 200 );
	}

	/** Trả về nội dung backup để diff view. */
	public function handle_get_backup( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id    = (int) $request->get_param( 'post_id' );
		$backup_raw = get_post_meta( $post_id, AEO_SB_META_PREV_KEY, true );
		if ( ! $backup_raw ) {
			return new \WP_REST_Response( [ 'error' => __( 'Không có backup.', 'aeo-summary-box' ) ], 404 );
		}
		$backup = json_decode( $backup_raw, true );
		return new \WP_REST_Response( [
			'summary'  => $backup['data']     ?? null,
			'saved_at' => $backup['saved_at'] ?? null,
		], 200 );
	}

	/** Kiểm tra kết nối đến API provider. */
	public function handle_test_connection( \WP_REST_Request $request ): \WP_REST_Response {
		$result = ( new AI_Client() )->test_connection();
		$status = $result['ok'] ? 200 : 503;
		return new \WP_REST_Response( $result, $status );
	}

	/** Xoá cache llms.txt để tái tạo ngay. */
	public function handle_flush_llms( \WP_REST_Request $request ): \WP_REST_Response {
		LLMS_Txt::get_instance()->flush_cache();
		return new \WP_REST_Response( [ 'flushed' => true ], 200 );
	}

	/**
	 * Hoàn tác về phiên bản trước (swap current ↔ prev).
	 * Cho phép undo lần 2 vì current được lưu thành prev trước khi restore.
	 */
	public function handle_restore( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id    = (int) $request->get_param( 'post_id' );
		$backup_raw = get_post_meta( $post_id, AEO_SB_META_PREV_KEY, true );

		if ( ! $backup_raw ) {
			return new \WP_REST_Response( [ 'error' => __( 'Không có phiên bản backup để hoàn tác.', 'aeo-summary-box' ) ], 404 );
		}

		$backup = json_decode( $backup_raw, true );
		if ( ! is_array( $backup ) || empty( $backup['data'] ) ) {
			return new \WP_REST_Response( [ 'error' => __( 'Dữ liệu backup không hợp lệ.', 'aeo-summary-box' ) ], 400 );
		}

		// Lưu current vào prev (để có thể undo undo).
		$current_raw = get_post_meta( $post_id, AEO_SB_META_KEY, true );
		if ( $current_raw ) {
			$new_backup = [
				'saved_at' => time(),
				'data'     => json_decode( $current_raw, true ),
			];
			update_post_meta( $post_id, AEO_SB_META_PREV_KEY, wp_json_encode( $new_backup, JSON_UNESCAPED_UNICODE ) );
		} else {
			delete_post_meta( $post_id, AEO_SB_META_PREV_KEY );
		}

		// Khôi phục.
		update_post_meta( $post_id, AEO_SB_META_KEY, wp_json_encode( $backup['data'], JSON_UNESCAPED_UNICODE ) );
		Schema::get_instance()->clear_cache( $post_id );
		LLMS_Txt::get_instance()->flush_cache();

		return new \WP_REST_Response( [
			'summary'  => $backup['data'],
			'saved_at' => $backup['saved_at'],
			'has_prev' => ! empty( $current_raw ), // new prev = current
		], 200 );
	}

	/**
	 * Lấy ngôn ngữ của bài viết từ WPML hoặc Polylang nếu plugin đang active.
	 * Trả về mã ngôn ngữ (vd: 'en', 'vi') hoặc null nếu không xác định được.
	 */
	private function detect_post_language( int $post_id ): ?string {
		// WPML
		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			$details = apply_filters( 'wpml_post_language_details', null, $post_id );
			if ( is_array( $details ) && ! empty( $details['language_code'] ) ) {
				return (string) $details['language_code'];
			}
		}
		// Polylang
		if ( function_exists( 'pll_get_post_language' ) ) {
			$lang = pll_get_post_language( $post_id, 'slug' );
			return ( $lang && is_string( $lang ) ) ? $lang : null;
		}
		return null;
	}
}
