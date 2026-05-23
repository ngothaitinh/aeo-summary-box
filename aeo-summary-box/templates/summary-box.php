<?php
/**
 * Template: Summary Box HTML
 * Biến $summary phải được set trước khi include file này.
 *
 * @var array $summary { title, tldr?, bullets[], note, cta, header_text? }
 */
defined( 'ABSPATH' ) || exit;

$header_text  = $summary['header_text'] ?? __( 'Sổ tay bất động sản', 'aeo-summary-box' );
$bullet_count = count( $summary['bullets'] ?? [] );
$modified     = get_the_modified_date( 'd/m/Y' );

// Intent badge — chỉ hiện cho DO và GO để không làm rối bố cục.
$intent      = $summary['intent'] ?? '';
$intent_badge = '';
if ( 'do' === $intent ) {
	$intent_badge = '<span class="aeo-sb-intent-badge aeo-sb-intent-do">📋 ' . esc_html__( 'Hướng dẫn', 'aeo-summary-box' ) . '</span>';
} elseif ( 'go' === $intent ) {
	$intent_badge = '<span class="aeo-sb-intent-badge aeo-sb-intent-go">📍 ' . esc_html__( 'Địa điểm', 'aeo-summary-box' ) . '</span>';
}
?>
<div class="aeo-sb-box"
	role="region"
	aria-label="<?php echo esc_attr( $header_text ); ?>"
	data-bullets="<?php echo esc_attr( $bullet_count ); ?>">

	<div class="aeo-sb-header" aria-hidden="true">
		<span class="aeo-sb-header-icon">🏠</span>
		<span class="aeo-sb-header-text"><?php echo esc_html( $header_text ); ?></span>
		<?php if ( $intent_badge ) : ?>
			<?php echo $intent_badge; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — built from safe constants ?>
		<?php endif; ?>
	</div>

	<div class="aeo-sb-body">

		<?php if ( ! empty( $summary['tldr'] ) ) : ?>
			<p class="aeo-sb-tldr"><?php echo esc_html( $summary['tldr'] ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $summary['title'] ) ) : ?>
			<p class="aeo-sb-title">
				<strong><?php echo esc_html( $summary['title'] ); ?></strong>
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $summary['bullets'] ) ) : ?>
			<ul class="aeo-sb-bullets">
				<?php foreach ( $summary['bullets'] as $i => $bullet ) : ?>
					<?php if ( empty( $bullet['label'] ) && empty( $bullet['content'] ) ) continue; ?>
					<li id="aeo-sb-fact-<?php echo esc_attr( $i ); ?>" class="aeo-sb-bullet<?php echo $i >= 3 ? ' aeo-sb-bullet--extra' : ''; ?>">
						<?php if ( ! empty( $bullet['label'] ) ) : ?>
							<strong class="aeo-sb-label"><?php echo esc_html( $bullet['label'] ); ?>:</strong>
						<?php endif; ?>
						<span class="aeo-sb-content"><?php echo esc_html( $bullet['content'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( ! empty( $summary['note'] ) ) : ?>
			<p class="aeo-sb-note">
				<em>(*) <?php echo esc_html( $summary['note'] ); ?></em>
			</p>
		<?php endif; ?>

		<?php if ( ! empty( $summary['cta'] ) ) : ?>
			<p class="aeo-sb-cta" data-nosnippet>
				<span class="aeo-sb-cta-arrow">👇</span> <?php echo esc_html( $summary['cta'] ); ?>
			</p>
		<?php endif; ?>

		<?php if ( $modified ) : ?>
			<p class="aeo-sb-updated" data-nosnippet>
				<small><?php
					/* translators: %s: last modified date */
					printf( esc_html__( 'Cập nhật: %s', 'aeo-summary-box' ), esc_html( $modified ) );
				?></small>
			</p>
		<?php endif; ?>

	</div><!-- /.aeo-sb-body -->

	<?php
	// Nút toggle nằm NGOÀI .aeo-sb-body — vì trên mobile thân hộp bị giới hạn
	// max-height + overflow:hidden, để trong body nút sẽ bị cắt mất.
	?>
	<?php if ( ! empty( $summary['bullets'] ) && $bullet_count > 3 ) : ?>
	<button class="aeo-sb-toggle" aria-expanded="false" hidden data-nosnippet>
		<span class="aeo-sb-toggle-more"><?php
			printf(
				/* translators: %d: number of hidden bullets */
				esc_html( _n( 'Xem thêm %d mục ▼', 'Xem thêm %d mục ▼', $bullet_count - 3, 'aeo-summary-box' ) ),
				$bullet_count - 3
			);
		?></span>
		<span class="aeo-sb-toggle-less"><?php esc_html_e( 'Thu gọn ▲', 'aeo-summary-box' ); ?></span>
	</button>
	<?php endif; ?>
</div><!-- /.aeo-sb-box -->
