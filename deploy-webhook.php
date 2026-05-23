<?php
/**
 * Deploy Webhook — AEO Summary Box
 * Upload file này lên: /home/tpilandc/public_html/deploy-webhook.php
 */

// ── Xác thực token ──────────────────────────────────────────────────────────
// Token được GitHub Actions gửi qua header X-Deploy-Token.
// Giá trị token lấy từ GitHub Secret DEPLOY_SECRET (bạn tự đặt, ví dụ: "tpiland-deploy-2026").

define( 'DEPLOY_TOKEN', 'tpiland-deploy-2026' );  // ← đổi thành chuỗi bí mật của bạn

$token = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '';
if ( ! hash_equals( DEPLOY_TOKEN, $token ) ) {
	http_response_code( 403 );
	die( json_encode( [ 'error' => 'Unauthorized' ] ) );
}

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
	http_response_code( 405 );
	die( json_encode( [ 'error' => 'Method Not Allowed' ] ) );
}

// ── Thực thi deploy ─────────────────────────────────────────────────────────

header( 'Content-Type: application/json' );

$repo_dir   = '/home/tpilandc/repos/aeo-summary-box';
$plugin_src = $repo_dir . '/aeo-summary-box';
$plugin_dir = '/home/tpilandc/public_html/wp-content/plugins/aeo-summary-box';

$log = [];

// 1. Git pull
$out   = shell_exec( 'cd ' . escapeshellarg( $repo_dir ) . ' && git pull 2>&1' );
$log[] = 'git pull: ' . trim( $out );

// 2. Copy plugin files
if ( ! is_dir( $plugin_dir ) ) {
	mkdir( $plugin_dir, 0755, true );
}
$out   = shell_exec( 'cp -Rf ' . escapeshellarg( $plugin_src . '/.' ) . ' ' . escapeshellarg( $plugin_dir . '/' ) . ' 2>&1' );
$log[] = 'cp: ' . ( trim( $out ) ?: 'OK' );

echo json_encode( [ 'deployed' => true, 'time' => date( 'Y-m-d H:i:s' ), 'log' => $log ] );
