<?php
/**
 * Deploy Webhook — AEO Summary Box
 * Upload file này lên: /home/tpilandc/public_html/deploy-webhook.php
 */

define( 'DEPLOY_TOKEN', 'tpiland-deploy-2026' );
define( 'REPO_URL',     'https://github.com/ngothaitinh/aeo-summary-box.git' );
define( 'REPO_DIR',     '/home/tpilandc/repos/aeo-summary-box' );
define( 'PLUGIN_DIR',   '/home/tpilandc/public_html/wp-content/plugins/aeo-summary-box' );

$token = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '';
if ( ! hash_equals( DEPLOY_TOKEN, $token ) ) {
	http_response_code( 403 );
	die( json_encode( [ 'error' => 'Unauthorized' ] ) );
}

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
	http_response_code( 405 );
	die( json_encode( [ 'error' => 'Method Not Allowed' ] ) );
}

header( 'Content-Type: application/json' );

$log = [];

// 1. Clone nếu chưa có, pull nếu đã có
if ( ! is_dir( REPO_DIR . '/.git' ) ) {
	shell_exec( 'mkdir -p ' . escapeshellarg( REPO_DIR ) );
	$out   = shell_exec( 'git clone ' . escapeshellarg( REPO_URL ) . ' ' . escapeshellarg( REPO_DIR ) . ' 2>&1' );
	$log[] = 'git clone: ' . trim( $out ?: 'OK' );
} else {
	$out   = shell_exec( 'cd ' . escapeshellarg( REPO_DIR ) . ' && git pull 2>&1' );
	$log[] = 'git pull: ' . trim( $out ?: 'Already up to date' );
}

// 2. Tìm thư mục plugin trong repo
if ( is_dir( REPO_DIR . '/aeo-summary-box' ) ) {
	$plugin_src = REPO_DIR . '/aeo-summary-box';
} else {
	$plugin_src = REPO_DIR;
}
$log[] = 'source: ' . $plugin_src;

// 3. Copy vào thư mục plugin WordPress
if ( ! is_dir( PLUGIN_DIR ) ) {
	mkdir( PLUGIN_DIR, 0755, true );
}
$out   = shell_exec( 'cp -Rf ' . escapeshellarg( $plugin_src . '/.' ) . ' ' . escapeshellarg( PLUGIN_DIR . '/' ) . ' 2>&1' );
$log[] = 'cp: ' . ( trim( $out ) ?: 'OK' );

echo json_encode( [ 'deployed' => true, 'time' => date( 'Y-m-d H:i:s' ), 'log' => $log ] );
