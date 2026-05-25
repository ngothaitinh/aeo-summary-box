<?php
namespace AEO_Summary_Box;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
	return;
}

class Elementor_Widget extends \Elementor\Widget_Base {

	public function get_name(): string {
		return 'aeo_summary_box';
	}

	public function get_title(): string {
		return __( 'AEO Summary Box', 'aeo-summary-box' );
	}

	public function get_icon(): string {
		return 'eicon-document-file';
	}

	public function get_categories(): array {
		return [ 'general' ];
	}

	public function get_keywords(): array {
		return [ 'summary', 'tóm tắt', 'aeo', 'geo', 'bất động sản', 'seo' ];
	}

	protected function register_controls(): void {
		$this->start_controls_section( 'section_content', [
			'label' => __( 'Nội dung', 'aeo-summary-box' ),
		] );

		$this->add_control( 'header_text', [
			'label'   => __( 'Tiêu đề header hộp', 'aeo-summary-box' ),
			'type'    => \Elementor\Controls_Manager::TEXT,
			'default' => __( 'Tóm tắt bởi AI', 'aeo-summary-box' ),
		] );

		$this->add_control( 'source', [
			'label'   => __( 'Nguồn dữ liệu', 'aeo-summary-box' ),
			'type'    => \Elementor\Controls_Manager::SELECT,
			'default' => 'auto',
			'options' => [
				'auto' => __( 'Tự động từ meta bài viết hiện tại', 'aeo-summary-box' ),
			],
		] );

		$this->end_controls_section();

		// Style section.
		$this->start_controls_section( 'section_style', [
			'label' => __( 'Kiểu dáng', 'aeo-summary-box' ),
			'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
		] );

		$this->add_control( 'accent_color', [
			'label'   => __( 'Màu nhấn (viền + header)', 'aeo-summary-box' ),
			'type'    => \Elementor\Controls_Manager::COLOR,
			'default' => '#F4A300',
			'selectors' => [
				'{{WRAPPER}} .aeo-sb-box'        => 'border-color: {{VALUE}};',
				'{{WRAPPER}} .aeo-sb-header'     => 'background-color: {{VALUE}};',
			],
		] );

		$this->add_control( 'bg_color', [
			'label'   => __( 'Màu nền hộp', 'aeo-summary-box' ),
			'type'    => \Elementor\Controls_Manager::COLOR,
			'default' => '#FFF6E5',
			'selectors' => [
				'{{WRAPPER}} .aeo-sb-box' => 'background-color: {{VALUE}};',
			],
		] );

		$this->end_controls_section();
	}

	protected function render(): void {
		$settings    = $this->get_settings_for_display();
		$post_id     = get_the_ID();
		$raw         = get_post_meta( $post_id, AEO_SB_META_KEY, true );
		$summary     = $raw ? json_decode( $raw, true ) : null;

		if ( ! $summary || empty( $summary['bullets'] ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div class="aeo-sb-placeholder">'
					. esc_html__( 'AEO Summary Box: Chưa có tóm tắt. Vào Edit Post → metabox để tạo.', 'aeo-summary-box' )
					. '</div>';
			}
			return;
		}

		$summary['header_text'] = $settings['header_text'];
		Renderer::get_instance()->render_html( $summary );
	}
}
