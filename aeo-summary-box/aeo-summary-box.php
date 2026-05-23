<?php
/**
 * Plugin Name:       AEO Summary Box
 * Plugin URI:        https://tpiland.com
 * Description:       Tự động sinh hộp tóm tắt bài viết bằng AI (Gemini, Claude, OpenAI, Custom), tối ưu AEO/GEO cho Google SGE, ChatGPT, Perplexity. Hỗ trợ Elementor Pro, Schema.org FAQPage + Article, meta description tự động, render server-side và file llms.txt.
 * Version:           1.7.2
 * Requires at least: 6.6
 * Requires PHP:      8.0
 * Author:            TPI Land
 * Author URI:        https://tpiland.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aeo-summary-box
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'AEO_SB_VERSION',  '1.7.2' );
define( 'AEO_SB_FILE',     __FILE__ );
define( 'AEO_SB_DIR',      plugin_dir_path( __FILE__ ) );
define( 'AEO_SB_URL',      plugin_dir_url( __FILE__ ) );
define( 'AEO_SB_META_KEY',      '_aeo_summary_json' );
define( 'AEO_SB_META_PREV_KEY', '_aeo_summary_json_prev' );

require_once AEO_SB_DIR . 'includes/class-plugin.php';

register_activation_hook(   __FILE__, [ 'AEO_Summary_Box\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'AEO_Summary_Box\Plugin', 'deactivate' ] );

AEO_Summary_Box\Plugin::get_instance();
