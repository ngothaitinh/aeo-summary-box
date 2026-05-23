<?php
namespace AEO_Summary_Box;

defined( 'ABSPATH' ) || exit;

/**
 * Phục vụ file ảo /llms.txt — chuẩn đang nổi (llmstxt.org) giúp các AI crawler
 * (ChatGPT, Perplexity, Claude, Gemini, Google AI) khám phá nhanh nội dung site
 * qua một danh sách bài viết kèm tóm tắt ngắn.
 *
 * File được sinh động (virtual), cache bằng transient, tự làm mới khi có bài
 * mới / tóm tắt thay đổi. Không cần tạo file vật lý hay flush rewrite rules.
 */
class LLMS_Txt {

	private static ?LLMS_Txt $instance = null;

	/** Khoá transient cache nội dung llms.txt. */
	private const CACHE_KEY = 'aeo_sb_llms_txt';

	/** Thời gian cache (giây). */
	private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/** Số bài tối đa liệt kê trong llms.txt. */
	private const MAX_POSTS = 300;

	public static function get_instance(): LLMS_Txt {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'parse_request', [ $this, 'maybe_serve' ] );

		// Làm mới cache khi nội dung thay đổi.
		add_action( 'save_post',    [ $this, 'flush_cache' ] );
		add_action( 'deleted_post', [ $this, 'flush_cache' ] );
		add_action( 'trashed_post', [ $this, 'flush_cache' ] );
		add_action( 'update_option_aeo_sb_settings', [ $this, 'flush_cache' ] );

		// Tóm tắt lưu qua REST chỉ cập nhật post_meta — bắt riêng các hook meta.
		add_action( 'added_post_meta',   [ $this, 'on_meta_change' ], 10, 3 );
		add_action( 'updated_post_meta', [ $this, 'on_meta_change' ], 10, 3 );
		add_action( 'deleted_post_meta', [ $this, 'on_meta_change' ], 10, 3 );
	}

	/**
	 * Bắt request tới /llms.txt và xuất nội dung dạng text/plain.
	 *
	 * @param \WP $wp Đối tượng WP hiện tại.
	 */
	public function maybe_serve( \WP $wp ): void {
		$request = isset( $wp->request ) ? trim( (string) $wp->request, '/' ) : '';
		if ( 'llms.txt' !== $request ) {
			return;
		}

		if ( ! Settings::get_instance()->get( 'enable_llms_txt', true ) ) {
			return;
		}

		$body = get_transient( self::CACHE_KEY );
		if ( false === $body ) {
			$body = $this->build();
			set_transient( self::CACHE_KEY, $body, self::CACHE_TTL );
		}

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/plain; charset=utf-8' );
			header( 'X-Robots-Tag: noindex' );
		}

		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — text/plain thuần.
		exit;
	}

	/**
	 * Xoá cache khi post_meta tóm tắt thay đổi (trường hợp lưu qua REST API).
	 *
	 * @param int    $meta_id   ID meta (không dùng).
	 * @param int    $object_id ID bài viết (không dùng).
	 * @param string $meta_key  Khoá meta.
	 */
	public function on_meta_change( $meta_id, $object_id, $meta_key ): void {
		if ( AEO_SB_META_KEY === $meta_key ) {
			$this->flush_cache();
		}
	}

	public function flush_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Dựng nội dung llms.txt (Markdown) từ các bài đã publish có tóm tắt.
	 */
	private function build(): string {
		$settings   = Settings::get_instance();
		$post_types = (array) $settings->get( 'post_types', [ 'post', 'page' ] );

		$lines   = [];
		$lines[] = '# ' . $this->one_line( get_bloginfo( 'name' ) );
		$lines[] = '';

		$tagline = $this->one_line( get_bloginfo( 'description' ) );
		if ( '' !== $tagline ) {
			$lines[] = '> ' . $tagline;
			$lines[] = '';
		}

		$lines[] = sprintf(
			/* translators: %s: site URL */
			__( 'Danh sách bài viết có hộp tóm tắt cấu trúc, tối ưu cho AI search. Nguồn: %s', 'aeo-summary-box' ),
			home_url( '/' )
		);
		$lines[] = '';

		$posts = get_posts( [
			'post_type'        => $post_types,
			'post_status'      => 'publish',
			'posts_per_page'   => self::MAX_POSTS,
			'meta_key'         => AEO_SB_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'          => 'modified',
			'order'            => 'DESC',
			'no_found_rows'    => true,
			'suppress_filters' => true,
		] );

		$items = [];
		foreach ( $posts as $post ) {
			$raw  = get_post_meta( $post->ID, AEO_SB_META_KEY, true );
			$data = $raw ? json_decode( $raw, true ) : null;
			if ( ! is_array( $data ) || empty( $data['bullets'] ) ) {
				continue;
			}

			$summary = ! empty( $data['tldr'] )
				? $this->one_line( (string) $data['tldr'] )
				: $this->one_line( (string) ( $data['title'] ?? '' ) );

			$title  = $this->one_line( get_the_title( $post ) );
			$line   = '- [' . $title . '](' . get_permalink( $post ) . ')';
			if ( '' !== $summary ) {
				$line .= ': ' . $summary;
			}

			// Thêm tag intent để AI crawler hiểu loại nội dung.
			$intent = $data['intent'] ?? '';
			if ( $intent && in_array( $intent, [ 'know', 'do', 'go', 'hybrid' ], true ) ) {
				$line .= ' [intent:' . $intent . ']';
			}

			$items[] = $line;
		}

		if ( $items ) {
			$lines[] = '## ' . __( 'Bài viết', 'aeo-summary-box' );
			$lines[] = '';
			$lines   = array_merge( $lines, $items );
			$lines[] = '';
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Làm sạch text về 1 dòng: bỏ HTML, gộp khoảng trắng, cắt 2 đầu.
	 */
	private function one_line( string $text ): string {
		$text = wp_strip_all_tags( $text );
		$text = (string) preg_replace( '/\s+/u', ' ', $text );
		return trim( $text );
	}
}
