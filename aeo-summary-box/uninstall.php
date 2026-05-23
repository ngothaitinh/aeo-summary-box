<?php
/**
 * Chạy khi user xoá plugin khỏi WordPress. Dọn sạch toàn bộ dữ liệu.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// ── Single site ──────────────────────────────────────────────────────────────

delete_option( 'aeo_sb_settings' );
delete_option( 'aeo_sb_bulk_queue' );
delete_post_meta_by_key( '_aeo_summary_json' );
delete_post_meta_by_key( '_aeo_schema_cache' );    // legacy (pretty-print)
delete_post_meta_by_key( '_aeo_schema_cache_v2' ); // minified
delete_post_meta_by_key( '_aeo_summary_tokens' );

// Xoá cron job còn pending nếu có.
wp_clear_scheduled_hook( 'aeo_sb_process_queue' );

// ── Multisite: dọn từng blog ─────────────────────────────────────────────────

if ( is_multisite() ) {
	$sites = get_sites( [ 'fields' => 'ids', 'number' => 0 ] );
	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		delete_option( 'aeo_sb_settings' );
		delete_option( 'aeo_sb_bulk_queue' );
		delete_post_meta_by_key( '_aeo_summary_json' );
		delete_post_meta_by_key( '_aeo_schema_cache' );    // legacy
		delete_post_meta_by_key( '_aeo_schema_cache_v2' ); // minified
		delete_post_meta_by_key( '_aeo_summary_tokens' );
		wp_clear_scheduled_hook( 'aeo_sb_process_queue' );
		restore_current_blog();
	}
}
