<?php
namespace AEO_Summary_Box;

defined( 'ABSPATH' ) || exit;

class Renderer {

	private static ?Renderer $instance = null;

	public static function get_instance(): Renderer {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_footer',          [ $this, 'print_template' ] );
		add_shortcode( 'aeo_summary',     [ $this, 'render_shortcode' ] );

		// the_content filter LUÔN bật. Việc chèn server-side hay nhường cho JS
		// được quyết định theo TỪNG bài trong maybe_inject_content() —
		// không phụ thuộc vào việc Elementor có active toàn site hay không.
		add_filter( 'the_content', [ $this, 'maybe_inject_content' ], 20 );
	}

	public function enqueue_assets(): void {
		if ( ! $this->should_render() || ! $this->get_summary() ) {
			return;
		}

		wp_enqueue_style(
			'aeo-sb-frontend',
			AEO_SB_URL . 'assets/css/frontend.css',
			[],
			AEO_SB_VERSION
		);

		$settings = Settings::get_instance();

		// JS cần cho: (a) auto-insert trên bài Elementor, (b) thu gọn mobile.
		wp_enqueue_script(
			'aeo-sb-frontend',
			AEO_SB_URL . 'assets/js/frontend.js',
			[],
			AEO_SB_VERSION,
			true
		);
		wp_localize_script( 'aeo-sb-frontend', 'aeoSbFront', [
			'position'      => $settings->get( 'insert_position', 'after_toc' ),
			'postId'        => get_the_ID(),
			'compactMobile' => (bool) $settings->get( 'compact_mobile', true ),
		] );
	}

	/**
	 * In HTML hộp tóm tắt vào wp_footer dưới dạng <template> để JS clone.
	 * CHỈ áp dụng cho bài dựng bằng Elementor — bài thường đã được chèn
	 * server-side qua the_content nên không cần template (tránh chèn 2 lần).
	 */
	public function print_template(): void {
		if ( ! $this->should_render() || ! $this->should_js_insert() ) {
			return;
		}

		$summary = $this->get_summary();
		if ( ! $summary ) {
			return;
		}

		echo '<template id="aeo-summary-template">';
		$this->render_html( $summary );
		echo '</template>';
	}

	/**
	 * Chèn hộp tóm tắt trực tiếp vào the_content (server-side) cho bài
	 * KHÔNG dựng bằng Elementor. Nhờ vậy crawler/bot AI không chạy JS vẫn
	 * đọc được nội dung hộp như văn bản thật của trang — tốt cho AEO/GEO.
	 */
	public function maybe_inject_content( string $content ): string {
		// Chỉ chạy trong vòng lặp chính của bài đơn — tránh widget, excerpt,
		// shortcode lồng nhau hoặc query phụ.
		if ( ! $this->should_render() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$settings = Settings::get_instance();
		if ( ! $settings->get( 'auto_insert' ) || 'off' === $settings->get( 'insert_position' ) ) {
			return $content;
		}

		// Bài dựng bằng Elementor → JS frontend.js xử lý chèn đúng vị trí.
		if ( $this->is_elementor_post( (int) get_the_ID() ) ) {
			return $content;
		}

		// Nội dung đã có shortcode [aeo_summary] → không chèn tự động (tránh trùng).
		if ( has_shortcode( $content, 'aeo_summary' ) ) {
			return $content;
		}

		$summary = $this->get_summary();
		if ( ! $summary ) {
			return $content;
		}

		ob_start();
		$this->render_html( $summary );
		$box_html = ob_get_clean();

		// Chèn sau đoạn <p> đầu tiên (ngay sau sapo); fallback: đầu bài.
		$first_p = strpos( $content, '</p>' );
		if ( false !== $first_p ) {
			return substr( $content, 0, $first_p + 4 ) . $box_html . substr( $content, $first_p + 4 );
		}

		return $box_html . $content;
	}

	/**
	 * Render shortcode [aeo_summary].
	 */
	public function render_shortcode( array $atts ): string {
		$summary = $this->get_summary();
		if ( ! $summary ) {
			return '';
		}
		ob_start();
		$this->render_html( $summary );
		return ob_get_clean();
	}

	public function render_html( array $summary ): void {
		require AEO_SB_DIR . 'templates/summary-box.php';
	}

	private function get_summary(): ?array {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return null;
		}
		$raw = get_post_meta( $post_id, AEO_SB_META_KEY, true );
		if ( ! $raw ) {
			return null;
		}
		$data = json_decode( $raw, true );
		return ( is_array( $data ) && ! empty( $data['bullets'] ) ) ? $data : null;
	}

	/**
	 * Bài hiện tại có dùng JS để chèn hộp không?
	 * Đúng khi: auto-insert bật, vị trí ≠ off, VÀ bài dựng bằng Elementor.
	 */
	private function should_js_insert(): bool {
		$settings = Settings::get_instance();
		if ( ! $settings->get( 'auto_insert' ) || 'off' === $settings->get( 'insert_position' ) ) {
			return false;
		}
		return $this->is_elementor_post( (int) get_the_ID() );
	}

	/**
	 * Kiểm tra theo TỪNG bài: bài này có được dựng bằng Elementor builder không.
	 * Một site cài Elementor vẫn có thể có bài viết classic/Gutenberg thuần.
	 */
	private function is_elementor_post( int $post_id ): bool {
		if ( ! $post_id || ! defined( 'ELEMENTOR_VERSION' ) ) {
			return false;
		}
		return 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true );
	}

	private function should_render(): bool {
		if ( ! is_singular() ) {
			return false;
		}
		$settings   = Settings::get_instance();
		$post_types = (array) $settings->get( 'post_types', [ 'post', 'page' ] );
		return in_array( get_post_type(), $post_types, true );
	}
}
