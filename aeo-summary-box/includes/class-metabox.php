<?php
namespace AEO_Summary_Box;

defined( 'ABSPATH' ) || exit;

class Metabox {

	private static ?Metabox $instance = null;

	public static function get_instance(): Metabox {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'register' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function register(): void {
		$settings   = Settings::get_instance();
		$post_types = (array) $settings->get( 'post_types', [ 'post', 'page' ] );

		foreach ( $post_types as $pt ) {
			add_meta_box(
				'aeo-summary-box',
				__( '🏠 AEO Summary Box — Tóm tắt bất động sản', 'aeo-summary-box' ),
				[ $this, 'render' ],
				$pt,
				'normal',
				'high'
			);
		}
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}
		wp_enqueue_style(
			'aeo-sb-admin',
			AEO_SB_URL . 'assets/css/admin.css',
			[],
			AEO_SB_VERSION
		);
		wp_enqueue_script(
			'aeo-sb-admin',
			AEO_SB_URL . 'assets/js/admin.js',
			[ 'wp-api-fetch', 'jquery' ],
			AEO_SB_VERSION,
			true
		);
		$settings  = Settings::get_instance();
		$provider  = (string) $settings->get( 'provider', 'gemini' );
		$provider_labels = [
			'gemini' => 'Gemini',
			'claude' => 'Claude',
			'openai' => 'OpenAI',
			'custom' => 'Custom',
		];
		wp_localize_script( 'aeo-sb-admin', 'aeoSb', [
			'postId'      => get_the_ID(),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'restBase'    => rest_url( 'aeo-summary/v1' ),
			'provider'    => $provider_labels[ $provider ] ?? $provider,
			'i18n'        => [
				'generating'     => __( 'Đang sinh tóm tắt...', 'aeo-summary-box' ),
				'saving'         => __( 'Đang lưu...', 'aeo-summary-box' ),
				'saved'          => __( 'Đã lưu!', 'aeo-summary-box' ),
				'error'          => __( 'Lỗi: ', 'aeo-summary-box' ),
				'addBullet'      => __( '+ Thêm dòng', 'aeo-summary-box' ),
				'chars'          => __( 'ký tự', 'aeo-summary-box' ),
				'restoring'      => __( 'Đang hoàn tác...', 'aeo-summary-box' ),
				'restored'       => __( 'Đã hoàn tác! Kiểm tra lại và nhấn "Lưu tóm tắt" nếu hài lòng.', 'aeo-summary-box' ),
				'confirmRestore' => __( 'Khôi phục phiên bản trước? Phiên bản hiện tại sẽ được giữ làm bản backup mới (vẫn có thể undo undo).', 'aeo-summary-box' ),
			],
		] );
	}

	public function render( \WP_Post $post ): void {
		$raw     = get_post_meta( $post->ID, AEO_SB_META_KEY, true );
		$summary = $raw ? json_decode( $raw, true ) : null;

		// Phiên bản backup (cho tính năng Hoàn tác).
		$prev_raw  = get_post_meta( $post->ID, AEO_SB_META_PREV_KEY, true );
		$prev      = $prev_raw ? json_decode( $prev_raw, true ) : null;
		$prev_time = isset( $prev['saved_at'] ) ? (int) $prev['saved_at'] : 0;

		// Token usage từ lần sinh gần nhất.
		$tokens_raw = get_post_meta( $post->ID, '_aeo_summary_tokens', true );
		$tokens     = $tokens_raw ? json_decode( $tokens_raw, true ) : null;

		$settings = Settings::get_instance();
		$provider = (string) $settings->get( 'provider', 'gemini' );
		$provider_labels = [
			'gemini' => 'Gemini',
			'claude' => 'Claude',
			'openai' => 'OpenAI',
			'custom' => 'Custom',
		];
		$provider_label = $provider_labels[ $provider ] ?? $provider;
		?>
		<div id="aeo-sb-metabox" data-post-id="<?php echo esc_attr( $post->ID ); ?>">

			<div id="aeo-sb-status" class="aeo-sb-notice" style="display:none;"></div>

			<div class="aeo-sb-actions">
				<button type="button" id="aeo-sb-generate" class="button button-primary">
					✨ <?php printf( esc_html__( 'Tạo tóm tắt bằng AI (%s)', 'aeo-summary-box' ), esc_html( $provider_label ) ); ?>
				</button>
				<button type="button" id="aeo-sb-save" class="button button-secondary">
					💾 <?php esc_html_e( 'Lưu tóm tắt', 'aeo-summary-box' ); ?>
				</button>
				<?php if ( $prev_time ) : ?>
					<button type="button" id="aeo-sb-restore" class="button aeo-sb-restore-btn"
						data-prev-time="<?php echo esc_attr( (string) $prev_time ); ?>">
						↩ <?php printf(
							/* translators: %s: formatted date & time of the backup */
							esc_html__( 'Hoàn tác (bản %s)', 'aeo-summary-box' ),
							esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $prev_time ) )
						); ?>
					</button>
				<?php endif; ?>
				<?php if ( $summary ) : ?>
					<span class="aeo-sb-indicator has-data">✅ <?php esc_html_e( 'Đã có tóm tắt', 'aeo-summary-box' ); ?></span>
				<?php else : ?>
					<span class="aeo-sb-indicator no-data">⚪ <?php esc_html_e( 'Chưa có tóm tắt', 'aeo-summary-box' ); ?></span>
				<?php endif; ?>
				<?php if ( $tokens && ! empty( $tokens['total'] ) ) : ?>
					<span class="aeo-sb-tokens" style="font-size:12px;color:#666;margin-left:12px;" title="<?php esc_attr_e( 'Token usage lần sinh gần nhất', 'aeo-summary-box' ); ?>">
						🔢 <?php printf(
							/* translators: 1: input tokens 2: output tokens 3: provider name */
							esc_html__( '%1$d in + %2$d out tokens (%3$s)', 'aeo-summary-box' ),
							(int) $tokens['input'],
							(int) $tokens['output'],
							esc_html( $provider_labels[ $tokens['provider'] ?? '' ] ?? ( $tokens['provider'] ?? '' ) )
						); ?>
					</span>
				<?php endif; ?>
			</div>

			<div id="aeo-sb-editor" class="aeo-sb-editor" style="<?php echo $summary ? '' : 'display:none;'; ?>">

				<div class="aeo-sb-field">
					<label><?php esc_html_e( 'Tiêu đề hộp', 'aeo-summary-box' ); ?></label>
					<input type="text" id="aeo-sb-title" class="large-text"
						value="<?php echo esc_attr( $summary['title'] ?? '' ); ?>"
						placeholder="Tóm tắt nhanh về [tên dự án]">
				</div>

				<div class="aeo-sb-field">
					<label><?php esc_html_e( 'TL;DR (≤120 ký tự — dùng cho meta description và AI Overview)', 'aeo-summary-box' ); ?></label>
					<input type="text" id="aeo-sb-tldr" class="large-text"
						value="<?php echo esc_attr( $summary['tldr'] ?? '' ); ?>"
						maxlength="120"
						placeholder="<?php esc_attr_e( 'Câu tóm tắt ngắn nhất, bắt đầu bằng tên entity chính...', 'aeo-summary-box' ); ?>">
					<div id="aeo-sb-tldr-count" style="font-size:12px;color:#666;margin-top:2px;"></div>
				</div>

				<div class="aeo-sb-field">
					<label for="aeo-sb-intent"><?php esc_html_e( 'Search Intent', 'aeo-summary-box' ); ?></label>
					<select id="aeo-sb-intent">
						<?php
						$current_intent = $summary['intent'] ?? 'hybrid';
						$intent_options = [
							'hybrid' => __( 'Hybrid (mặc định)', 'aeo-summary-box' ),
							'know'   => __( 'Know — thông tin/kiến thức', 'aeo-summary-box' ),
							'do'     => __( 'Do — hướng dẫn/quy trình', 'aeo-summary-box' ),
							'go'     => __( 'Go — địa điểm/liên hệ', 'aeo-summary-box' ),
						];
						foreach ( $intent_options as $val => $label ) :
						?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_intent, $val ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description" style="margin-top:4px;">
						<?php esc_html_e( 'AI tự phân loại. Đổi lại nếu sai (dựa trên SERP analysis), rồi bấm "Tạo tóm tắt bằng AI" để tái sinh với intent đã chọn.', 'aeo-summary-box' ); ?>
					</p>
				</div>

				<div class="aeo-sb-field">
					<label for="aeo-sb-persona"><?php esc_html_e( 'Persona độc giả', 'aeo-summary-box' ); ?></label>
					<select id="aeo-sb-persona">
						<?php
						$current_persona = $summary['persona'] ?? '';
						$persona_options = [
							''         => __( 'Chung (không phân biệt)', 'aeo-summary-box' ),
							'buyer'    => __( 'Người mua ở thực', 'aeo-summary-box' ),
							'investor' => __( 'Nhà đầu tư', 'aeo-summary-box' ),
							'renter'   => __( 'Người thuê', 'aeo-summary-box' ),
						];
						foreach ( $persona_options as $val => $label ) :
						?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_persona, $val ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description" style="margin-top:4px;">
						<?php esc_html_e( 'AI điều chỉnh trường "Lưu ý" và "CTA" phù hợp với mối quan tâm của từng đối tượng. Chọn xong rồi bấm "Tạo tóm tắt bằng AI".', 'aeo-summary-box' ); ?>
					</p>
				</div>

				<div class="aeo-sb-field">
					<label><?php esc_html_e( 'Bullets (thông tin chính)', 'aeo-summary-box' ); ?></label>
					<div class="aeo-sb-bullets-header">
						<span><?php esc_html_e( 'Nhãn', 'aeo-summary-box' ); ?></span>
						<span><?php esc_html_e( 'Câu hỏi (FAQ schema)', 'aeo-summary-box' ); ?></span>
						<span><?php esc_html_e( 'Nội dung', 'aeo-summary-box' ); ?></span>
						<span></span>
					</div>
					<div id="aeo-sb-bullets">
						<?php if ( ! empty( $summary['bullets'] ) ) : ?>
							<?php foreach ( $summary['bullets'] as $i => $bullet ) : ?>
								<div class="aeo-sb-bullet-row">
									<input type="text" class="aeo-sb-label" placeholder="<?php esc_attr_e( 'Nhãn (vd: Vị trí)', 'aeo-summary-box' ); ?>"
										value="<?php echo esc_attr( $bullet['label'] ?? '' ); ?>">
									<input type="text" class="aeo-sb-question" placeholder="<?php esc_attr_e( 'Câu hỏi người dùng?', 'aeo-summary-box' ); ?>"
										value="<?php echo esc_attr( $bullet['question'] ?? '' ); ?>">
									<input type="text" class="aeo-sb-content" placeholder="<?php esc_attr_e( 'Nội dung ngắn', 'aeo-summary-box' ); ?>"
										value="<?php echo esc_attr( $bullet['content'] ?? '' ); ?>">
									<button type="button" class="aeo-sb-remove-bullet button-link" title="<?php esc_attr_e( 'Xoá', 'aeo-summary-box' ); ?>">✕</button>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
					<button type="button" id="aeo-sb-add-bullet" class="button button-link">
						+ <?php esc_html_e( 'Thêm dòng', 'aeo-summary-box' ); ?>
					</button>
				</div>

				<div class="aeo-sb-field">
					<label><?php esc_html_e( 'Lưu ý (*)', 'aeo-summary-box' ); ?></label>
					<input type="text" id="aeo-sb-note" class="large-text"
						value="<?php echo esc_attr( $summary['note'] ?? '' ); ?>"
						placeholder="Cần xác minh pháp lý, giá có thể thay đổi...">
				</div>

				<div class="aeo-sb-field">
					<label><?php esc_html_e( 'Call-to-action', 'aeo-summary-box' ); ?></label>
					<input type="text" id="aeo-sb-cta" class="large-text"
						value="<?php echo esc_attr( $summary['cta'] ?? '' ); ?>"
						placeholder="Cuộn xuống để xem chi tiết về dự án...">
				</div>

				<?php
				$current_contact = $summary['contact'] ?? [];
				$go_visible      = 'go' === ( $summary['intent'] ?? '' );
				?>
				<div class="aeo-sb-field" id="aeo-sb-go-contact" style="<?php echo $go_visible ? '' : 'display:none;'; ?>">
					<label>📍 <?php esc_html_e( 'Thông tin địa điểm (Search Intent = Go)', 'aeo-summary-box' ); ?></label>
					<p class="description" style="margin-bottom:8px;">
						<?php esc_html_e( 'Ghi đè thông tin LocalBusiness schema cho bài này. Để trống = dùng thông tin trong Settings → LocalBusiness Schema.', 'aeo-summary-box' ); ?>
					</p>
					<input type="text" id="aeo-sb-contact-org-name" class="large-text"
						value="<?php echo esc_attr( $current_contact['org_name'] ?? '' ); ?>"
						placeholder="<?php esc_attr_e( 'Tên tổ chức (để trống = dùng tên site)', 'aeo-summary-box' ); ?>">
					<input type="text" id="aeo-sb-contact-address" class="large-text"
						value="<?php echo esc_attr( $current_contact['address'] ?? '' ); ?>"
						placeholder="235 Đồng Khởi, Quận 1, TP.HCM">
					<input type="text" id="aeo-sb-contact-phone" class="regular-text"
						value="<?php echo esc_attr( $current_contact['phone'] ?? '' ); ?>"
						placeholder="+84-28-1234-5678">
					<input type="text" id="aeo-sb-contact-hours" class="large-text"
						value="<?php echo esc_attr( $current_contact['hours'] ?? '' ); ?>"
						placeholder="Mo-Fr 08:00-20:00">
				</div>

				<div class="aeo-sb-preview-wrap">
					<label><?php esc_html_e( 'Xem trước hộp tóm tắt', 'aeo-summary-box' ); ?></label>
					<div id="aeo-sb-preview">
						<?php if ( $summary ) : ?>
							<?php $this->render_preview( $summary ); ?>
						<?php endif; ?>
					</div>
				</div>

			</div><!-- /#aeo-sb-editor -->

			<!-- Diff panel: hiển thị khi user click Hoàn tác, ẩn mặc định -->
			<div id="aeo-sb-diff" style="display:none;margin-top:14px;border-top:2px solid #e0a800;padding-top:14px;">
				<h4 style="margin:0 0 10px;font-size:13px;color:#7a5200;">
					🔍 <?php esc_html_e( 'So sánh phiên bản — xác nhận trước khi hoàn tác', 'aeo-summary-box' ); ?>
				</h4>
				<div class="aeo-sb-diff-cols">
					<div class="aeo-sb-diff-col aeo-sb-diff-current">
						<div class="aeo-sb-diff-col-head"><?php esc_html_e( '📝 Phiên bản hiện tại', 'aeo-summary-box' ); ?></div>
						<div class="aeo-sb-diff-body" id="aeo-sb-diff-current-body"></div>
					</div>
					<div class="aeo-sb-diff-col aeo-sb-diff-backup">
						<div class="aeo-sb-diff-col-head" id="aeo-sb-diff-backup-head"><?php esc_html_e( '🕐 Phiên bản backup', 'aeo-summary-box' ); ?></div>
						<div class="aeo-sb-diff-body" id="aeo-sb-diff-backup-body"></div>
					</div>
				</div>
				<div style="margin-top:10px;display:flex;gap:8px;">
					<button type="button" id="aeo-sb-diff-confirm" class="button button-primary">
						✅ <?php esc_html_e( 'Xác nhận hoàn tác', 'aeo-summary-box' ); ?>
					</button>
					<button type="button" id="aeo-sb-diff-cancel" class="button">
						<?php esc_html_e( 'Huỷ', 'aeo-summary-box' ); ?>
					</button>
				</div>
			</div>

		</div><!-- /#aeo-sb-metabox -->
		<?php
	}

	private function render_preview( array $summary ): void {
		require AEO_SB_DIR . 'templates/summary-box.php';
	}
}
