<?php
namespace AEO_Summary_Box;

defined( 'ABSPATH' ) || exit;

class Settings {

	private static ?Settings $instance = null;
	private array $options;

	public static function get_instance(): Settings {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Lọc bỏ NULL trước khi merge — tránh trường hợp giá trị NULL
		// ghi đè default hợp lệ khi wp_parse_args() xử lý.
		$stored = array_filter(
			(array) get_option( 'aeo_sb_settings', [] ),
			fn( $v ) => $v !== null
		);
		$this->options = wp_parse_args( $stored, self::defaults() );
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public static function defaults(): array {
		return [
			// Provider chính
			'provider'        => 'gemini',       // gemini | claude | openai | custom

			// Gemini
			'gemini_api_key'  => '',
			'gemini_model'    => 'gemini-2.0-flash',

			// Claude (Anthropic)
			'claude_api_key'  => '',
			'claude_model'    => 'claude-haiku-4-5-20251001',

			// OpenAI
			'openai_api_key'  => '',
			'openai_model'    => 'gpt-4o-mini',

			// Custom endpoint (OpenAI-compatible)
			'custom_endpoint' => '',
			'custom_api_key'  => '',
			'custom_model'    => '',

			// Chung
			'language'        => 'vi',
			'bullet_count'    => 6,
			'auto_insert'     => true,
			'insert_position' => 'after_toc', // after_toc | after_sapo | after_h1 | off
			'post_types'      => [ 'post', 'page' ],

			// Prompt template (advanced) — để trống = dùng prompt mặc định.
			'prompt_template' => '',

			// UI: thu gọn hộp trên mobile (hiện 3 bullets đầu, còn lại ẩn).
			'compact_mobile'  => true,

			// AEO: phục vụ file /llms.txt cho AI crawler.
			'enable_llms_txt' => true,

			// Bulk generation — intent mặc định ('' = AI tự phân loại).
			'bulk_default_intent' => '',

			// Schema LocalBusiness (dùng khi intent = go).
			'contact_org_name' => '',  // Tên tổ chức / thương hiệu (để trống = dùng Site Title)
			'contact_address'  => '',  // Địa chỉ chi nhánh hoặc showroom
			'contact_phone'    => '',  // Số điện thoại (vd: +84-28-1234-5678)
			'contact_hours'    => '',  // Giờ mở cửa (vd: Mo-Fr 08:00-20:00)
		];
	}

	/**
	 * Trả về prompt mặc định, dùng khi prompt_template để trống.
	 * Các placeholder: {language}, {bullet_count}, {post_title}, {post_content}.
	 */
	public static function default_prompt(): string {
		return 'Bạn là chuyên gia SEO và tối ưu nội dung cho các AI search engine (Google SGE, ChatGPT, Perplexity, Gemini, Bing Copilot).

Nhiệm vụ: Đọc bài viết bên dưới và tạo hộp tóm tắt nhanh tối ưu cho AEO (Answer Engine Optimization) và GEO (Generative Engine Optimization).

BƯỚC 1 — PHÂN LOẠI SEARCH INTENT:
Dựa vào tiêu đề bài và nội dung, xác định intent chính và ghi vào trường "intent":
- "know"   → bài thông tin/kiến thức: giới thiệu, tổng quan, phân tích, quy hoạch về một dự án/sản phẩm/địa danh/chủ đề; review; so sánh; tin tức.
- "do"     → bài hướng dẫn hành động: "cách làm", "hướng dẫn", "các bước", "thủ tục", "quy trình", "checklist".
- "go"     → bài về địa điểm/liên hệ: "địa chỉ", "showroom", "văn phòng", "liên hệ", "đường đi", "tọa độ".
- "hybrid" → CHỈ dùng khi bài trộn lẫn nhiều intent ngang nhau. KHÔNG chọn "hybrid" cho an toàn khi phân vân — hãy chọn intent chiếm tỷ trọng lớn nhất. Phần lớn bài giới thiệu/thông tin là "know".

QUY TẮC BẮT BUỘC:
- Trả về JSON đúng schema, KHÔNG kèm markdown fence (```), KHÔNG giải thích thêm bất kỳ điều gì.
- Tự xác định chủ đề/lĩnh vực bài viết rồi chọn nhãn bullet phù hợp nhất với lĩnh vực đó.
- Tạo đúng {bullet_count} bullets — mỗi bullet là 1 thông tin cốt lõi mà AI search có thể trích dẫn trực tiếp.
- Mỗi bullet gồm 3 trường:
  • "label": nhãn ngắn gọn 2–4 từ. Nếu intent là "do" thì dùng "Bước 1", "Bước 2", ... thay vì nhãn danh mục. Các label PHẢI khác biệt rõ về khía cạnh — KHÔNG để 2 bullet cùng nói một khía cạnh (vd "Hạ tầng" và "Kết nối" đều về giao thông → gộp lại, dùng slot còn lại cho khía cạnh khác có dữ kiện).
  • "question": câu hỏi người dùng thật sự gõ vào Google/ChatGPT — ngắn, khẩu ngữ, KHÔNG dùng văn phong báo cáo/hành chính.
    Phải kết thúc bằng "?", phải có tên entity cụ thể (dự án, địa danh, sản phẩm).
    DÙNG các mẫu tự nhiên: "X có ... không?", "X ... bao nhiêu?", "X ở đâu?", "X mất bao lâu?", "Có nên ... X không?", "X ... như thế nào?" (chủ động, ngắn).
    CẤM: cấu trúc bị động dài ("được ... thế nào?", "được tổ chức như thế nào?", "được triển khai ra sao?").
    VÍ DỤ ĐÚNG: "Giá biệt thự Izumi City bao nhiêu?", "Izumi City có bảo vệ 24/7 không?", "Tiến độ bàn giao Izumi City khi nào?"
    VÍ DỤ SAI: "An ninh biệt thự đơn lập Izumi City được tổ chức thế nào?" (bị động, văn phong hành chính).
  • "content": 1 câu HOÀN CHỈNH 12–28 từ, tự đứng độc lập (trích riêng vẫn hiểu rõ chủ thể).
    - PHẢI chứa tên định danh entity ở dạng đầy đủ HOẶC rút gọn rõ ràng (vd "The Metropolis", "Dự án The Metropolis"). Entity đặt ở đầu hoặc giữa câu đều được.
    - KHÔNG lặp y nguyên cùng một chuỗi tên ở đầu mọi bullet — luân phiên cách diễn đạt cho tự nhiên; chỉ bullet đầu nên dùng tên đầy đủ nhất.
    - KHÔNG dùng đại từ trống ("dự án này", "nơi đây") làm chủ ngữ nếu trong câu không còn chi tiết định danh entity.
    - PHẢI có ít nhất 1 dữ kiện kiểm chứng được: số liệu, ngày tháng, tên riêng hoặc địa danh. KHÔNG tạo bullet chỉ nêu "định hướng / tầm nhìn / mục tiêu" chung chung.
    - KHÔNG dùng ngôn ngữ quảng cáo ("đẳng cấp", "hoàn hảo", "tuyệt vời").
    VÍ DỤ ĐÚNG: "The Metropolis có giá tham khảo từ 50 triệu đồng/m²."
    VÍ DỤ SAI (thiếu fact): "The Metropolis hướng tới phong cách sống hiện đại."
    VÍ DỤ SAI (thiếu entity): "Khoảng 50 triệu/m²."
- tldr: 1 câu tóm tắt trả lời thẳng GÓC ĐỘ CỐT LÕI CỦA BÀI VIẾT NÀY (không phải mô tả chung entity), ≤120 ký tự, bắt đầu bằng tên entity chính.
  QUAN TRỌNG: tldr phải phản ánh CHỦ ĐỀ CỤ THỂ của bài (phân tích layout, hướng dẫn mua, so sánh giá, v.v.) — không dùng câu giới thiệu entity kiểu "X là dự án..." nếu bài không phải bài tổng quan.
  • intent "know"   → trả lời insight/fact chính mà bài cung cấp: "Nhà phố vườn Izumi City có layout 1 trệt 3 lầu (5×12m–6×15m), diện tích 72–150m², tiện ích riêng từng căn."
  • intent "do"     → nêu kết quả đạt được sau khi làm: "Quy trình mua căn hộ The Metropolis gồm 5 bước, từ đặt cọc đến nhận nhà."
  • intent "go"     → địa chỉ + thông tin liên hệ ngắn gọn: "Showroom The Metropolis tại 235 Đồng Khởi, Q.1, mở cửa 8h–20h."
  • intent "hybrid" → tóm tắt fact nổi bật nhất của bài, entity-first.
- note: 1 câu lưu ý quan trọng, phù hợp với intent.
  • "know"   → cảnh báo độ chính xác/cập nhật: "Thông tin có thể thay đổi — xác nhận lại trước khi quyết định."
  • "do"     → điều kiện tiên quyết/rủi ro: "Cần chuẩn bị đầy đủ hồ sơ pháp lý trước khi đặt cọc."
  • "go"     → đặt lịch/giờ mở cửa: "Nên đặt lịch hẹn trước qua hotline để được tư vấn trực tiếp."
  • "hybrid" → lưu ý chung phù hợp với nội dung bài.
- cta: 1 câu kêu gọi hành động phù hợp với intent VÀ target audience (persona).
  Intent "know":
    → (chung)    "Cuộn xuống để xem phân tích chi tiết."
    → buyer      "Cuộn xuống để xem thông tin pháp lý và điều kiện vay."
    → investor   "Cuộn xuống để xem phân tích tiềm năng và lợi suất đầu tư."
    → renter     "Cuộn xuống để xem chi phí thuê và tiện ích thực tế."
  Intent "do":
    → (chung)    "Cuộn xuống để xem hướng dẫn từng bước."
    → buyer      "Cuộn xuống để xem từng bước trong quy trình mua nhà."
    → investor   "Cuộn xuống để xem các bước đầu tư và lưu ý pháp lý."
    → renter     "Cuộn xuống để xem quy trình thuê và lưu ý hợp đồng."
  Intent "go":
    → (chung)    "Cuộn xuống để xem bản đồ và thông tin liên hệ."
    → buyer      "Cuộn xuống để xem địa chỉ showroom và lịch hẹn tư vấn."
    → investor   "Cuộn xuống để xem thông tin liên hệ và đặt lịch tư vấn đầu tư."
    → renter     "Cuộn xuống để xem địa chỉ và thông tin thuê trực tiếp."
  Intent "hybrid":
    → (chung)    "Cuộn xuống để xem thông tin đầy đủ."
    → buyer      "Cuộn xuống để xem đầy đủ thông tin mua và pháp lý."
    → investor   "Cuộn xuống để xem đầy đủ thông tin đầu tư."
    → renter     "Cuộn xuống để xem đầy đủ thông tin thuê và tiện ích."
  Chọn câu phù hợp nhất với persona được chỉ định (nếu không có persona, dùng hàng "(chung)").
- title: bắt đầu bằng "Tóm tắt nhanh:" rồi nêu chủ đề chính của bài.
- Ngôn ngữ output: {language}.

GỢI Ý NHÃN BULLET THEO LĨNH VỰC (chọn và điều chỉnh cho phù hợp):
- Bất động sản: Vị trí, Chủ đầu tư, Loại hình, Quy mô, Giá tham khảo, Tiện ích, Pháp lý, Tiến độ
- Du lịch: Điểm đến, Thời điểm, Di chuyển, Lưu trú, Chi phí, Ăn uống, Hoạt động, Lưu ý
- Công nghệ / Sản phẩm: Tính năng chính, Cấu hình, Giá bán, Ưu điểm, Nhược điểm, Đối tượng, Bảo hành
- Sức khỏe / Y tế: Triệu chứng, Nguyên nhân, Điều trị, Phòng ngừa, Đối tượng, Thời gian, Lưu ý
- Tài chính: Lãi suất, Điều kiện, Thời hạn, Phí, Ưu đãi, Rủi ro, Đơn vị cung cấp
- Ẩm thực / Recipe: Nguyên liệu chính, Thời gian, Độ khó, Khẩu phần, Calories, Bảo quản
- Giáo dục / Khóa học: Nội dung, Thời lượng, Học phí, Đối tượng, Giảng viên, Chứng chỉ
- Tin tức / Sự kiện: Thời gian, Địa điểm, Diễn biến, Tác động, Nguồn, Cập nhật

Schema JSON (chỉ trả về object này, không có gì khác):
{"intent":"know|do|go|hybrid","title":"...","tldr":"...","bullets":[{"label":"...","question":"...","content":"..."}],"note":"...","cta":"..."}

Tiêu đề bài: {post_title}
Nội dung bài:
{post_content}';
	}

	public function get( string $key, mixed $fallback = null ): mixed {
		return $this->options[ $key ] ?? $fallback;
	}

	public function add_menu(): void {
		add_options_page(
			__( 'AEO Summary Box', 'aeo-summary-box' ),
			__( 'AEO Summary', 'aeo-summary-box' ),
			'manage_options',
			'aeo-summary-box',
			[ $this, 'render_page' ]
		);
	}

	public function register_settings(): void {
		register_setting(
			'aeo_sb_settings_group',
			'aeo_sb_settings',
			[ 'sanitize_callback' => [ $this, 'sanitize' ] ]
		);
	}

	public function sanitize( array $input ): array {
		$clean = self::defaults();

		$clean['provider']       = in_array( $input['provider'] ?? 'gemini', [ 'gemini', 'claude', 'openai', 'custom' ], true )
			? $input['provider'] : 'gemini';

		// Gemini
		$clean['gemini_api_key'] = sanitize_text_field( $input['gemini_api_key'] ?? '' );
		$clean['gemini_model']   = sanitize_text_field( $input['gemini_model'] ?? 'gemini-2.0-flash' );

		// Claude
		$clean['claude_api_key'] = sanitize_text_field( $input['claude_api_key'] ?? '' );
		$clean['claude_model']   = sanitize_text_field( $input['claude_model'] ?? 'claude-haiku-4-5-20251001' );

		// OpenAI
		$clean['openai_api_key'] = sanitize_text_field( $input['openai_api_key'] ?? '' );
		$clean['openai_model']   = sanitize_text_field( $input['openai_model'] ?? 'gpt-4o-mini' );

		// Custom endpoint
		$clean['custom_endpoint'] = esc_url_raw( $input['custom_endpoint'] ?? '' );
		$clean['custom_api_key']  = sanitize_text_field( $input['custom_api_key'] ?? '' );
		$clean['custom_model']    = sanitize_text_field( $input['custom_model'] ?? '' );

		// Chung
		$lang                     = $input['language'] ?? 'vi';
		$clean['language']        = in_array( $lang, [ 'vi', 'en' ], true ) ? $lang : 'vi';
		$clean['bullet_count']    = (int) min( 7, max( 4, $input['bullet_count'] ?? 6 ) );
		$clean['auto_insert']     = ! empty( $input['auto_insert'] );
		$clean['insert_position'] = in_array(
			$input['insert_position'] ?? 'after_toc',
			[ 'after_toc', 'after_sapo', 'after_h1', 'off' ],
			true
		) ? $input['insert_position'] : 'after_toc';
		// Lấy tất cả public post types hợp lệ để whitelist.
		$valid_post_types    = array_keys( get_post_types( [ 'public' => true ] ) );
		$raw_post_types      = array_map( 'sanitize_key', (array) ( $input['post_types'] ?? [ 'post' ] ) );
		$clean['post_types'] = array_values( array_intersect( $raw_post_types, $valid_post_types ) );
		if ( empty( $clean['post_types'] ) ) {
			$clean['post_types'] = [ 'post' ];
		}

		// Prompt template: cho phép dùng wp_kses_post để giữ ký tự đặc biệt, loại thẻ HTML.
		$clean['prompt_template'] = sanitize_textarea_field( $input['prompt_template'] ?? '' );

		$clean['compact_mobile']  = ! empty( $input['compact_mobile'] );
		$clean['enable_llms_txt'] = ! empty( $input['enable_llms_txt'] );

		// Bulk intent default.
		$clean['bulk_default_intent'] = in_array(
			$input['bulk_default_intent'] ?? '',
			[ '', 'know', 'do', 'go', 'hybrid' ],
			true
		) ? ( $input['bulk_default_intent'] ?? '' ) : '';

		// Contact info for LocalBusiness schema.
		$clean['contact_org_name'] = sanitize_text_field( $input['contact_org_name'] ?? '' );
		$clean['contact_address']  = sanitize_text_field( $input['contact_address']  ?? '' );
		$clean['contact_phone']    = sanitize_text_field( $input['contact_phone']    ?? '' );
		$clean['contact_hours']    = sanitize_text_field( $input['contact_hours']    ?? '' );

		return $clean;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$opts = $this->options;
		$provider = $opts['provider'];

		// Inline JS để toggle provider panels
		$toggle_js = <<<'JS'
		<script>
		(function(){
			function showProvider(val){
				['gemini','claude','openai','custom'].forEach(function(p){
					var row = document.getElementById('aeo-provider-' + p);
					if(row) row.style.display = (p === val) ? '' : 'none';
				});
			}
			document.addEventListener('DOMContentLoaded', function(){
				var sel = document.getElementById('aeo_sb_provider');
				if(!sel) return;
				showProvider(sel.value);
				sel.addEventListener('change', function(){ showProvider(this.value); });
			});
		})();
		</script>
		JS;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'AEO Summary Box — Cài đặt', 'aeo-summary-box' ); ?></h1>
			<?php echo $toggle_js; // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'aeo_sb_settings_group' ); ?>

				<h2><?php esc_html_e( 'AI Provider', 'aeo-summary-box' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="aeo_sb_provider"><?php esc_html_e( 'Provider', 'aeo-summary-box' ); ?></label></th>
						<td>
							<select id="aeo_sb_provider" name="aeo_sb_settings[provider]">
								<option value="gemini" <?php selected( $provider, 'gemini' ); ?>>Google Gemini</option>
								<option value="claude" <?php selected( $provider, 'claude' ); ?>>Anthropic Claude</option>
								<option value="openai" <?php selected( $provider, 'openai' ); ?>>OpenAI</option>
								<option value="custom" <?php selected( $provider, 'custom' ); ?>>🔧 Custom (OpenAI-compatible)</option>
							</select>
						</td>
					</tr>

					<?php /* ── Gemini ── */ ?>
					<tbody id="aeo-provider-gemini">
					<tr>
						<th scope="row"><label for="gemini_api_key">Gemini API Key</label></th>
						<td>
							<input type="password" id="gemini_api_key" name="aeo_sb_settings[gemini_api_key]"
								value="<?php echo esc_attr( $opts['gemini_api_key'] ); ?>" class="regular-text" autocomplete="off">
							<?php if ( ! empty( $opts['gemini_api_key'] ) ) : ?>
								<span style="color:#2e7d32;margin-left:8px;">✅ Đã cấu hình</span>
							<?php endif; ?>
							<p class="description">
								Lấy tại <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">Google AI Studio</a>.
									<?php if ( defined( 'AEO_SB_GEMINI_KEY' ) ) : ?>
										<br><span style="color:#2e7d32;">✅ <?php esc_html_e( 'Đang dùng hằng số AEO_SB_GEMINI_KEY từ wp-config.php.', 'aeo-summary-box' ); ?></span>
									<?php endif; ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="gemini_model">Gemini Model</label></th>
						<td>
							<select id="gemini_model" name="aeo_sb_settings[gemini_model]">
								<?php foreach ( [
									'gemini-2.0-flash'   => 'gemini-2.0-flash (nhanh, rẻ)',
									'gemini-1.5-flash'   => 'gemini-1.5-flash',
									'gemini-1.5-pro'     => 'gemini-1.5-pro (chất lượng cao)',
								] as $val => $label ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $opts['gemini_model'], $val ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					</tbody>

					<?php /* ── Claude ── */ ?>
					<tbody id="aeo-provider-claude">
					<tr>
						<th scope="row"><label for="claude_api_key">Anthropic API Key</label></th>
						<td>
							<input type="password" id="claude_api_key" name="aeo_sb_settings[claude_api_key]"
								value="<?php echo esc_attr( $opts['claude_api_key'] ); ?>" class="regular-text" autocomplete="off">
							<?php if ( ! empty( $opts['claude_api_key'] ) ) : ?>
								<span style="color:#2e7d32;margin-left:8px;">✅ Đã cấu hình</span>
							<?php endif; ?>
							<p class="description">
								Lấy tại <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">Anthropic Console</a>.
									<?php if ( defined( 'AEO_SB_CLAUDE_KEY' ) ) : ?>
										<br><span style="color:#2e7d32;">✅ <?php esc_html_e( 'Đang dùng hằng số AEO_SB_CLAUDE_KEY từ wp-config.php.', 'aeo-summary-box' ); ?></span>
									<?php endif; ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="claude_model">Claude Model</label></th>
						<td>
							<select id="claude_model" name="aeo_sb_settings[claude_model]">
								<?php foreach ( [
									'claude-haiku-4-5-20251001' => 'claude-haiku-4-5 (nhanh, rẻ)',
									'claude-sonnet-4-5-20251001' => 'claude-sonnet-4-5 (cân bằng)',
									'claude-opus-4-5-20251001'  => 'claude-opus-4-5 (chất lượng cao)',
								] as $val => $label ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $opts['claude_model'], $val ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					</tbody>

					<?php /* ── OpenAI ── */ ?>
					<tbody id="aeo-provider-openai">
					<tr>
						<th scope="row"><label for="openai_api_key">OpenAI API Key</label></th>
						<td>
							<input type="password" id="openai_api_key" name="aeo_sb_settings[openai_api_key]"
								value="<?php echo esc_attr( $opts['openai_api_key'] ); ?>" class="regular-text" autocomplete="off">
							<?php if ( ! empty( $opts['openai_api_key'] ) ) : ?>
								<span style="color:#2e7d32;margin-left:8px;">✅ Đã cấu hình</span>
							<?php endif; ?>
							<p class="description">
								Lấy tại <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">OpenAI Platform</a>.
									<?php if ( defined( 'AEO_SB_OPENAI_KEY' ) ) : ?>
										<br><span style="color:#2e7d32;">✅ <?php esc_html_e( 'Đang dùng hằng số AEO_SB_OPENAI_KEY từ wp-config.php.', 'aeo-summary-box' ); ?></span>
									<?php endif; ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="openai_model">OpenAI Model</label></th>
						<td>
							<select id="openai_model" name="aeo_sb_settings[openai_model]">
								<?php foreach ( [
									'gpt-4o-mini' => 'gpt-4o-mini (nhanh, rẻ)',
									'gpt-4o'      => 'gpt-4o (cân bằng)',
									'gpt-4-turbo' => 'gpt-4-turbo (chất lượng cao)',
								] as $val => $label ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $opts['openai_model'], $val ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					</tbody>

					<?php /* ── Custom endpoint ── */ ?>
					<tbody id="aeo-provider-custom">
					<tr>
						<td colspan="2">
							<div style="background:#fff8e1;border-left:4px solid #f9a825;padding:10px 14px;border-radius:4px;font-size:13px;">
								💡 <strong>Custom endpoint</strong> — dùng cho API key mua bên ngoài, dịch vụ proxy hoặc bất kỳ provider nào tương thích định dạng OpenAI Chat Completions.
							</div>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="custom_endpoint"><?php esc_html_e( 'Endpoint URL', 'aeo-summary-box' ); ?></label></th>
						<td>
							<input type="url" id="custom_endpoint" name="aeo_sb_settings[custom_endpoint]"
								value="<?php echo esc_attr( $opts['custom_endpoint'] ); ?>"
								class="large-text" placeholder="https://api.example.com/v1/chat/completions">
							<p class="description">
								<?php esc_html_e( 'URL đầy đủ đến endpoint Chat Completions. Ví dụ: https://openrouter.ai/api/v1/chat/completions', 'aeo-summary-box' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="custom_api_key"><?php esc_html_e( 'API Key', 'aeo-summary-box' ); ?></label></th>
						<td>
							<input type="password" id="custom_api_key" name="aeo_sb_settings[custom_api_key]"
								value="<?php echo esc_attr( $opts['custom_api_key'] ); ?>" class="regular-text" autocomplete="off">
							<?php if ( ! empty( $opts['custom_api_key'] ) ) : ?>
								<span style="color:#2e7d32;margin-left:8px;">✅ Đã cấu hình</span>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'Gửi qua header: Authorization: Bearer {key}', 'aeo-summary-box' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="custom_model"><?php esc_html_e( 'Tên Model', 'aeo-summary-box' ); ?></label></th>
						<td>
							<input type="text" id="custom_model" name="aeo_sb_settings[custom_model]"
								value="<?php echo esc_attr( $opts['custom_model'] ); ?>"
								class="regular-text" placeholder="gpt-4o-mini / anthropic/claude-3-haiku / ...">
							<p class="description">
								<?php esc_html_e( 'Tên model chính xác theo provider. Ví dụ OpenRouter: "anthropic/claude-3-haiku", "google/gemini-flash-1.5"', 'aeo-summary-box' ); ?>
							</p>
						</td>
					</tr>
					</tbody>
				</table>

				<p>
					<button type="button" id="aeo-test-connection" class="button button-secondary">
						🔌 <?php esc_html_e( 'Kiểm tra kết nối API', 'aeo-summary-box' ); ?>
					</button>
					<span id="aeo-test-result" style="margin-left:12px;font-weight:600;"></span>
				</p>
				<script>
				(function(){
					document.getElementById('aeo-test-connection').addEventListener('click', function(){
						var btn = this;
						var out = document.getElementById('aeo-test-result');
						btn.disabled = true;
						out.style.color = '#666';
						out.textContent = '⏳ Đang kiểm tra...';
						fetch(<?php echo wp_json_encode( rest_url( 'aeo-summary/v1/test-connection' ) ); ?>, {
							method: 'GET',
							headers: { 'X-WP-Nonce': <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?> }
						})
						.then(function(r){ return r.json(); })
						.then(function(d){
							out.style.color = d.ok ? '#2e7d32' : '#c62828';
							out.textContent = (d.ok ? '✅ ' : '❌ ') + d.message;
						})
						.catch(function(e){
							out.style.color = '#c62828';
							out.textContent = '❌ Lỗi không xác định: ' + e.message;
						})
						.finally(function(){ btn.disabled = false; });
					});
				})();
				</script>

				<h2><?php esc_html_e( 'Cài đặt chung', 'aeo-summary-box' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="aeo_language"><?php esc_html_e( 'Ngôn ngữ tóm tắt', 'aeo-summary-box' ); ?></label></th>
						<td>
							<select id="aeo_language" name="aeo_sb_settings[language]">
								<option value="vi" <?php selected( $opts['language'], 'vi' ); ?>>🇻🇳 Tiếng Việt</option>
								<option value="en" <?php selected( $opts['language'], 'en' ); ?>>🇬🇧 English</option>
							</select>
							<p class="description"><?php esc_html_e( 'Ngôn ngữ AI dùng để viết tóm tắt.', 'aeo-summary-box' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bullet_count"><?php esc_html_e( 'Số bullets', 'aeo-summary-box' ); ?></label></th>
						<td>
							<input type="number" id="bullet_count" name="aeo_sb_settings[bullet_count]"
								value="<?php echo esc_attr( $opts['bullet_count'] ); ?>" min="4" max="7" class="small-text">
							<span class="description"><?php esc_html_e( '(4–7 bullets)', 'aeo-summary-box' ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Post types áp dụng', 'aeo-summary-box' ); ?></th>
						<td>
							<?php
							$all_types       = get_post_types( [ 'public' => true ], 'objects' );
							$active_types    = (array) $opts['post_types'];
							foreach ( $all_types as $pt ) :
								$checked = in_array( $pt->name, $active_types, true );
							?>
							<label style="display:inline-block;margin-right:16px;margin-bottom:6px;">
								<input type="checkbox"
									name="aeo_sb_settings[post_types][]"
									value="<?php echo esc_attr( $pt->name ); ?>"
									<?php checked( $checked ); ?>>
								<?php echo esc_html( $pt->labels->singular_name ); ?>
								<code style="font-size:11px;color:#666;">(<?php echo esc_html( $pt->name ); ?>)</code>
							</label>
							<?php endforeach; ?>
							<p class="description"><?php esc_html_e( 'Chọn loại bài sẽ hiển thị hộp tóm tắt và inject schema.', 'aeo-summary-box' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Tự động chèn', 'aeo-summary-box' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="aeo_sb_settings[auto_insert]" value="1" <?php checked( $opts['auto_insert'] ); ?>>
								<?php esc_html_e( 'Bật auto-insert (chèn hộp tóm tắt vào frontend)', 'aeo-summary-box' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Thu gọn trên mobile', 'aeo-summary-box' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="aeo_sb_settings[compact_mobile]" value="1" <?php checked( $opts['compact_mobile'] ?? true ); ?>>
								<?php esc_html_e( 'Hiển thị 3 bullets đầu trên mobile, ẩn phần còn lại (có nút "Xem thêm")', 'aeo-summary-box' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Giúp giảm scroll trên màn hình nhỏ. Desktop luôn hiển thị đầy đủ.', 'aeo-summary-box' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'File llms.txt', 'aeo-summary-box' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="aeo_sb_settings[enable_llms_txt]" value="1" <?php checked( $opts['enable_llms_txt'] ?? true ); ?>>
								<?php esc_html_e( 'Phục vụ file /llms.txt cho AI crawler (ChatGPT, Perplexity, Claude, Gemini)', 'aeo-summary-box' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Sinh danh sách bài viết + tóm tắt theo chuẩn llmstxt.org, giúp AI search khám phá nội dung site nhanh hơn.', 'aeo-summary-box' ); ?>
								<?php if ( ! empty( $opts['enable_llms_txt'] ) ) : ?>
									<br><a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( home_url( '/llms.txt' ) ); ?></a>
								<?php endif; ?>
							</p>
						</td>
					</tr>
					<?php if ( ! empty( $opts['enable_llms_txt'] ) ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Thao tác llms.txt', 'aeo-summary-box' ); ?></th>
						<td>
							<button type="button" id="aeo-flush-llms" class="button">
								🔄 <?php esc_html_e( 'Làm mới cache ngay', 'aeo-summary-box' ); ?>
							</button>
							<a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" rel="noopener" class="button" style="margin-left:6px;">
								📄 <?php esc_html_e( 'Xem file', 'aeo-summary-box' ); ?>
							</a>
							<span id="aeo-flush-llms-msg" style="margin-left:10px;font-size:13px;"></span>
							<script>
							(function(){
								document.addEventListener('DOMContentLoaded', function(){
									var btn   = document.getElementById('aeo-flush-llms');
									var msg   = document.getElementById('aeo-flush-llms-msg');
									var rest  = <?php echo wp_json_encode( rest_url( 'aeo-summary/v1/flush-llms' ), JSON_UNESCAPED_UNICODE ); ?>;
									var nonce = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
									if ( !btn ) return;
									btn.addEventListener('click', function() {
										btn.disabled = true;
										msg.style.color   = '#856404';
										msg.textContent   = '⏳ Đang làm mới...';
										fetch( rest, {
											method: 'POST',
											headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
											body: '{}',
										}).then(function(r){ return r.json(); })
										.then(function(){
											msg.style.color = '#2e7d32';
											msg.textContent = '✅ Cache đã xoá. File sẽ tái tạo ở lần truy cập tiếp theo.';
										}).catch(function(){
											msg.style.color = '#d32f2f';
											msg.textContent = '❌ Lỗi — thử lại.';
										}).finally(function(){ btn.disabled = false; });
									});
								});
							})();
							</script>
						</td>
					</tr>
					<?php endif; ?>
					<tr>
						<th scope="row"><label for="insert_position"><?php esc_html_e( 'Vị trí chèn mặc định', 'aeo-summary-box' ); ?></label></th>
						<td>
							<select id="insert_position" name="aeo_sb_settings[insert_position]">
								<option value="after_toc"  <?php selected( $opts['insert_position'], 'after_toc' ); ?>><?php esc_html_e( 'Sau mục lục (TOC)', 'aeo-summary-box' ); ?></option>
								<option value="after_sapo" <?php selected( $opts['insert_position'], 'after_sapo' ); ?>><?php esc_html_e( 'Sau sapo (đoạn đầu)', 'aeo-summary-box' ); ?></option>
								<option value="after_h1"   <?php selected( $opts['insert_position'], 'after_h1' ); ?>><?php esc_html_e( 'Sau tiêu đề H1', 'aeo-summary-box' ); ?></option>
								<option value="off"         <?php selected( $opts['insert_position'], 'off' ); ?>><?php esc_html_e( 'Tắt (dùng shortcode/widget)', 'aeo-summary-box' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bulk_default_intent"><?php esc_html_e( 'Intent mặc định (Bulk)', 'aeo-summary-box' ); ?></label></th>
						<td>
							<select id="bulk_default_intent" name="aeo_sb_settings[bulk_default_intent]">
								<option value="" <?php selected( $opts['bulk_default_intent'] ?? '', '' ); ?>><?php esc_html_e( 'Tự động phân loại (mặc định)', 'aeo-summary-box' ); ?></option>
								<option value="know"   <?php selected( $opts['bulk_default_intent'] ?? '', 'know' ); ?>><?php esc_html_e( 'Know — thông tin / kiến thức', 'aeo-summary-box' ); ?></option>
								<option value="do"     <?php selected( $opts['bulk_default_intent'] ?? '', 'do' ); ?>><?php esc_html_e( 'Do — hướng dẫn / quy trình', 'aeo-summary-box' ); ?></option>
								<option value="go"     <?php selected( $opts['bulk_default_intent'] ?? '', 'go' ); ?>><?php esc_html_e( 'Go — địa điểm / liên hệ', 'aeo-summary-box' ); ?></option>
								<option value="hybrid" <?php selected( $opts['bulk_default_intent'] ?? '', 'hybrid' ); ?>><?php esc_html_e( 'Hybrid', 'aeo-summary-box' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Intent áp dụng khi sinh tóm tắt hàng loạt qua Bulk action. Chọn "Tự động" để AI tự phân loại từng bài.', 'aeo-summary-box' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Tạo tóm tắt hàng loạt', 'aeo-summary-box' ); ?></th>
						<td>
							<?php
							// Đếm bài chưa có tóm tắt.
							$unprocessed = get_posts( [
								'post_type'        => (array) $opts['post_types'],
								'post_status'      => 'publish',
								'posts_per_page'   => -1,
								'fields'           => 'ids',
								// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
								'meta_query'       => [ [ 'key' => AEO_SB_META_KEY, 'compare' => 'NOT EXISTS' ] ],
								'no_found_rows'    => true,
								'suppress_filters' => true,
							] );
							$unprocessed_count = count( $unprocessed );
							?>
							<button type="button" id="aeo-queue-all" class="button button-primary"
								<?php echo $unprocessed_count === 0 ? 'disabled' : ''; ?>>
								🚀 <?php
								if ( $unprocessed_count > 0 ) {
									printf(
										/* translators: %d: number of posts without summary */
										esc_html__( 'Tạo tóm tắt cho %d bài chưa có', 'aeo-summary-box' ),
										$unprocessed_count
									);
								} else {
									esc_html_e( 'Tất cả bài đã có tóm tắt ✅', 'aeo-summary-box' );
								}
								?>
							</button>
							<span id="aeo-queue-all-msg" style="margin-left:10px;font-size:13px;"></span>
							<p class="description" style="margin-top:6px;">
								<?php esc_html_e( 'Thêm tất cả bài publish chưa có tóm tắt vào hàng đợi. Bài đã có tóm tắt sẽ được bỏ qua. Cron sẽ xử lý tuần tự (5 giây/bài).', 'aeo-summary-box' ); ?>
							</p>
							<script>
							(function(){
								document.addEventListener('DOMContentLoaded', function(){
									var btn   = document.getElementById('aeo-queue-all');
									var msg   = document.getElementById('aeo-queue-all-msg');
									var rest  = <?php echo wp_json_encode( rest_url( 'aeo-summary/v1/queue-all' ), JSON_UNESCAPED_UNICODE ); ?>;
									var nonce = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
									if ( !btn ) return;
									btn.addEventListener('click', function() {
										if ( !confirm('<?php echo esc_js( __( 'Xác nhận thêm tất cả bài chưa có tóm tắt vào hàng đợi AI?', 'aeo-summary-box' ) ); ?>') ) return;
										btn.disabled = true;
										msg.style.color  = '#856404';
										msg.textContent  = '⏳ Đang thêm vào hàng đợi...';
										fetch( rest, {
											method: 'POST',
											headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
											body: '{}',
										}).then(function(r){ return r.json(); })
										.then(function(data){
											if ( data.queued > 0 ) {
												msg.style.color = '#2e7d32';
												msg.textContent = '✅ Đã thêm ' + data.queued + ' bài vào hàng đợi. Thanh tiến trình sẽ xuất hiện ở trang danh sách bài viết.';
												btn.textContent = '🚀 Đã thêm ' + data.queued + ' bài — đang xử lý...';
											} else {
												msg.style.color = '#2e7d32';
												msg.textContent = data.message || '✅ Không có bài mới cần xử lý.';
											}
										}).catch(function(){
											msg.style.color = '#d32f2f';
											msg.textContent = '❌ Lỗi — thử lại.';
											btn.disabled = false;
										});
									});
								});
							})();
							</script>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Prompt Template (nâng cao)', 'aeo-summary-box' ); ?></h2>
				<p class="description" style="margin-bottom:12px;">
					<?php esc_html_e( 'Tùy chỉnh prompt gửi cho AI. Để trống để dùng prompt mặc định được tối ưu sẵn.', 'aeo-summary-box' ); ?><br>
					<?php esc_html_e( 'Các placeholder có thể dùng:', 'aeo-summary-box' ); ?>
					<code>{language}</code>, <code>{bullet_count}</code>, <code>{post_title}</code>, <code>{post_content}</code>.
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="prompt_template"><?php esc_html_e( 'Prompt', 'aeo-summary-box' ); ?></label>
						</th>
						<td>
							<textarea id="prompt_template" name="aeo_sb_settings[prompt_template]"
								rows="16" class="large-text code"
								placeholder="<?php esc_attr_e( 'Để trống = dùng prompt mặc định.', 'aeo-summary-box' ); ?>"
								style="font-family:monospace;font-size:12px;line-height:1.6;"
							><?php echo esc_textarea( $opts['prompt_template'] ); ?></textarea>
							<p class="description">
								<a href="#" id="aeo-load-default-prompt"><?php esc_html_e( '↩ Nạp prompt mặc định vào textarea để xem / sửa', 'aeo-summary-box' ); ?></a>
							</p>
							<script>
							(function(){
								var defaultPrompt = <?php echo wp_json_encode( self::default_prompt(), JSON_UNESCAPED_UNICODE ); ?>;
								document.addEventListener('DOMContentLoaded', function(){
									var link = document.getElementById('aeo-load-default-prompt');
									var ta   = document.getElementById('prompt_template');
									if (!link || !ta) return;
									link.addEventListener('click', function(e){
										e.preventDefault();
										if (ta.value.trim() === '' || confirm('<?php echo esc_js( __( 'Ghi đè nội dung textarea bằng prompt mặc định?', 'aeo-summary-box' ) ); ?>')) {
											ta.value = defaultPrompt;
										}
									});
								});
							})();
							</script>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'LocalBusiness Schema (Search Intent = Go)', 'aeo-summary-box' ); ?></h2>
				<p class="description" style="margin-bottom:12px;">
					<?php esc_html_e( 'Khi bài viết có Search Intent = "go", plugin inject thêm schema LocalBusiness để Google Maps / AI search hiển thị thông tin địa chỉ, giờ, phone. Để trống toàn bộ nếu không cần.', 'aeo-summary-box' ); ?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="contact_org_name"><?php esc_html_e( 'Tên tổ chức', 'aeo-summary-box' ); ?></label></th>
						<td>
							<input type="text" id="contact_org_name" name="aeo_sb_settings[contact_org_name]"
								value="<?php echo esc_attr( $opts['contact_org_name'] ); ?>"
								class="regular-text" placeholder="<?php esc_attr_e( 'Để trống = dùng Site Title', 'aeo-summary-box' ); ?>">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="contact_address"><?php esc_html_e( 'Địa chỉ', 'aeo-summary-box' ); ?></label></th>
						<td>
							<input type="text" id="contact_address" name="aeo_sb_settings[contact_address]"
								value="<?php echo esc_attr( $opts['contact_address'] ); ?>"
								class="large-text" placeholder="235 Đồng Khởi, Quận 1, TP.HCM">
							<p class="description"><?php esc_html_e( 'Địa chỉ đầy đủ (schema streetAddress). Cần điền để kích hoạt LocalBusiness schema.', 'aeo-summary-box' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="contact_phone"><?php esc_html_e( 'Số điện thoại', 'aeo-summary-box' ); ?></label></th>
						<td>
							<input type="text" id="contact_phone" name="aeo_sb_settings[contact_phone]"
								value="<?php echo esc_attr( $opts['contact_phone'] ); ?>"
								class="regular-text" placeholder="+84-28-1234-5678">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="contact_hours"><?php esc_html_e( 'Giờ mở cửa', 'aeo-summary-box' ); ?></label></th>
						<td>
							<input type="text" id="contact_hours" name="aeo_sb_settings[contact_hours]"
								value="<?php echo esc_attr( $opts['contact_hours'] ); ?>"
								class="large-text" placeholder="Mo-Fr 08:00-20:00, Sa-Su 09:00-18:00">
							<p class="description"><?php esc_html_e( 'Dùng định dạng schema.org: "Mo-Fr 08:00-20:00". Nhiều khoảng thời gian ngăn cách bằng dấu phẩy.', 'aeo-summary-box' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Lưu cài đặt', 'aeo-summary-box' ) ); ?>
			</form>

			<?php $this->render_stats(); ?>
		</div>
		<?php
	}

	/** Phần thống kê token usage (nằm ngoài form để không gửi lên). */
	private function render_stats(): void {
		global $wpdb;

		// ── Thống kê token ──────────────────────────────────────────────────
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 10000",
				'_aeo_summary_tokens'
			)
		);

		$stats       = [];
		$total_posts = 0;
		foreach ( $rows as $row ) {
			$t = json_decode( $row, true );
			if ( ! is_array( $t ) ) {
				continue;
			}
			$p = sanitize_key( $t['provider'] ?? 'unknown' );
			if ( ! isset( $stats[ $p ] ) ) {
				$stats[ $p ] = [ 'posts' => 0, 'input' => 0, 'output' => 0 ];
			}
			$stats[ $p ]['posts']++;
			$stats[ $p ]['input']  += (int) ( $t['input']  ?? 0 );
			$stats[ $p ]['output'] += (int) ( $t['output'] ?? 0 );
			$total_posts++;
		}

		// Giá tham khảo (USD / 1M tokens) — cập nhật theo bảng giá nhà cung cấp.
		$price_per_m = [
			'gemini' => [ 'in' => 0.075, 'out' => 0.30  ],
			'claude' => [ 'in' => 0.80,  'out' => 4.00  ],
			'openai' => [ 'in' => 0.15,  'out' => 0.60  ],
			'custom' => [ 'in' => 0.15,  'out' => 0.60  ],
		];

		// ── Đếm bài có / chưa có tóm tắt ───────────────────────────────────
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$with_summary = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s",
				AEO_SB_META_KEY
			)
		);
		?>
		<hr style="margin:32px 0 24px;">
		<h2 style="display:flex;align-items:center;gap:8px;">📊 <?php esc_html_e( 'Thống kê sử dụng', 'aeo-summary-box' ); ?></h2>

		<table class="widefat striped" style="max-width:700px;margin-bottom:16px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Chỉ số', 'aeo-summary-box' ); ?></th>
					<th><?php esc_html_e( 'Giá trị', 'aeo-summary-box' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Bài đã có tóm tắt AI', 'aeo-summary-box' ); ?></td>
					<td><strong><?php echo number_format( $with_summary ); ?></strong></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Tổng lần gọi API (token records)', 'aeo-summary-box' ); ?></td>
					<td><strong><?php echo number_format( $total_posts ); ?></strong></td>
				</tr>
			</tbody>
		</table>

		<?php if ( ! empty( $stats ) ) : ?>
		<table class="widefat striped" style="max-width:700px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Provider', 'aeo-summary-box' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Lần gọi', 'aeo-summary-box' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Input tokens', 'aeo-summary-box' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Output tokens', 'aeo-summary-box' ); ?></th>
					<th style="text-align:right;"><?php esc_html_e( 'Chi phí ước tính', 'aeo-summary-box' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$total_cost = 0.0;
				foreach ( $stats as $provider => $s ) :
					$pm   = $price_per_m[ $provider ] ?? $price_per_m['custom'];
					$cost = ( $s['input'] / 1_000_000 ) * $pm['in']
					      + ( $s['output'] / 1_000_000 ) * $pm['out'];
					$total_cost += $cost;
					$labels = [
						'gemini'  => 'Google Gemini',
						'claude'  => 'Anthropic Claude',
						'openai'  => 'OpenAI',
						'custom'  => 'Custom',
						'unknown' => '(unknown)',
					];
				?>
				<tr>
					<td><?php echo esc_html( $labels[ $provider ] ?? $provider ); ?></td>
					<td style="text-align:right;"><?php echo number_format( $s['posts'] ); ?></td>
					<td style="text-align:right;"><?php echo number_format( $s['input'] ); ?></td>
					<td style="text-align:right;"><?php echo number_format( $s['output'] ); ?></td>
					<td style="text-align:right;">$<?php echo number_format( $cost, 4 ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr style="font-weight:600;">
					<td><?php esc_html_e( 'Tổng cộng', 'aeo-summary-box' ); ?></td>
					<td style="text-align:right;"><?php echo number_format( $total_posts ); ?></td>
					<td style="text-align:right;"><?php echo number_format( array_sum( array_column( $stats, 'input' ) ) ); ?></td>
					<td style="text-align:right;"><?php echo number_format( array_sum( array_column( $stats, 'output' ) ) ); ?></td>
					<td style="text-align:right;">$<?php echo number_format( $total_cost, 4 ); ?></td>
				</tr>
			</tfoot>
		</table>
		<p class="description" style="max-width:700px;">
			<?php esc_html_e( '* Chi phí ước tính theo bảng giá tham khảo, có thể chênh lệch so với thực tế. Gemini 2.0 Flash: $0.075/$0.30 / 1M tokens; Claude Haiku: $0.80/$4.00; GPT-4o-mini: $0.15/$0.60.', 'aeo-summary-box' ); ?>
		</p>
		<?php else : ?>
		<p class="description"><?php esc_html_e( 'Chưa có dữ liệu token. Hãy sinh tóm tắt ít nhất một bài để xem thống kê.', 'aeo-summary-box' ); ?></p>
		<?php endif; ?>
		<?php
	}
}
