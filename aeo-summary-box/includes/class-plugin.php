<?php
namespace AEO_Summary_Box;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	private static ?Plugin $instance = null;

	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	private function load_dependencies(): void {
		require_once AEO_SB_DIR . 'includes/class-settings.php';
		require_once AEO_SB_DIR . 'includes/class-ai-client.php';
		require_once AEO_SB_DIR . 'includes/class-metabox.php';
		require_once AEO_SB_DIR . 'includes/class-renderer.php';
		require_once AEO_SB_DIR . 'includes/class-rest-api.php';
		require_once AEO_SB_DIR . 'includes/class-schema.php';
		require_once AEO_SB_DIR . 'includes/class-llms-txt.php';
		require_once AEO_SB_DIR . 'includes/class-bulk.php';
		// class-elementor-widget.php được load lazy trong register_elementor_widget()
		// sau khi Elementor đã khởi động đầy đủ — KHÔNG load ở đây để tránh lỗi
		// "Class Elementor\Widget_Base not found" khi plugin của chúng ta load trước Elementor.
	}

	private function init_hooks(): void {
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_action( 'init',           [ $this, 'init_components' ] );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'aeo-summary-box',
			false,
			dirname( plugin_basename( AEO_SB_FILE ) ) . '/languages'
		);
	}

	public function init_components(): void {
		Settings::get_instance();
		Metabox::get_instance();
		Renderer::get_instance();
		REST_API::get_instance();
		Schema::get_instance();
		LLMS_Txt::get_instance();
		Bulk::get_instance();

		// Elementor widget đăng ký sau khi Elementor sẵn sàng.
		add_action( 'elementor/widgets/register', [ $this, 'register_elementor_widget' ] );
	}

	public function register_elementor_widget( $widgets_manager ): void {
		require_once AEO_SB_DIR . 'includes/class-elementor-widget.php';
		$widgets_manager->register( new Elementor_Widget() );
	}

	public static function activate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		// Đặt option mặc định khi kích hoạt lần đầu.
		if ( false === get_option( 'aeo_sb_settings' ) ) {
			add_option( 'aeo_sb_settings', Settings::defaults() );
		}
	}

	public static function deactivate(): void {
		// Không xoá dữ liệu khi deactivate, chỉ xoá khi uninstall.
	}
}
