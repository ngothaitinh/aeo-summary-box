<?php
namespace AEO_Summary_Box;

defined( 'ABSPATH' ) || exit;

class AI_Client {

	private string $provider;
	private string $api_key;
	private string $model;
	private string $language;
	private int    $bullet_count;
	private string $custom_endpoint;

	/** Token usage từ lần gọi API gần nhất: [input, output, total]. */
	private array $last_tokens = [];

	/** Intent override — set bởi biên tập viên; khi forced = true AI không tự phân loại lại. */
	private string $intent        = 'hybrid';
	private bool   $intent_forced = false;

	/** Persona độc giả — điều chỉnh trường "note" theo từng đối tượng. */
	private string $persona = '';

	/**
	 * Hằng số wp-config có thể ghi đè API key trong DB:
	 *   AEO_SB_GEMINI_KEY, AEO_SB_CLAUDE_KEY, AEO_SB_OPENAI_KEY, AEO_SB_CUSTOM_KEY
	 */
	public function __construct() {
		$settings              = Settings::get_instance();
		$this->provider        = (string) $settings->get( 'provider', 'gemini' );
		$this->language        = (string) $settings->get( 'language', 'vi' );
		$this->bullet_count    = (int)    $settings->get( 'bullet_count', 6 );
		$this->custom_endpoint = '';

		switch ( $this->provider ) {
			case 'claude':
				$this->api_key = defined( 'AEO_SB_CLAUDE_KEY' )
					? AEO_SB_CLAUDE_KEY
					: (string) $settings->get( 'claude_api_key' );
				$this->model   = (string) $settings->get( 'claude_model', 'claude-haiku-4-5-20251001' );
				break;
			case 'openai':
				$this->api_key = defined( 'AEO_SB_OPENAI_KEY' )
					? AEO_SB_OPENAI_KEY
					: (string) $settings->get( 'openai_api_key' );
				$this->model   = (string) $settings->get( 'openai_model', 'gpt-4o-mini' );
				break;
			case 'custom':
				$this->api_key         = defined( 'AEO_SB_CUSTOM_KEY' )
					? AEO_SB_CUSTOM_KEY
					: (string) $settings->get( 'custom_api_key' );
				$this->model           = (string) $settings->get( 'custom_model', '' );
				$this->custom_endpoint = (string) $settings->get( 'custom_endpoint', '' );
				break;
			default: // gemini
				$this->provider = 'gemini';
				$this->api_key  = defined( 'AEO_SB_GEMINI_KEY' )
					? AEO_SB_GEMINI_KEY
					: (string) $settings->get( 'gemini_api_key' );
				$this->model    = (string) $settings->get( 'gemini_model', 'gemini-2.0-flash' );
		}
	}

	/**
	 * Ghi đè ngôn ngữ — dùng khi cần sinh tóm tắt theo ngôn ngữ bài cụ thể
	 * (ví dụ: site đa ngôn ngữ WPML/Polylang, bài EN sinh tóm tắt EN).
	 */
	public function set_language( string $lang ): void {
		$this->language = sanitize_key( $lang ) ?: $this->language;
	}

	/** Trả về thông tin token dùng trong lần gọi AI gần nhất. */
	public function get_last_tokens(): array {
		return $this->last_tokens;
	}

	/**
	 * Override intent — dùng khi biên tập viên đã xác nhận intent thủ công trong metabox.
	 * Khi forced = true, AI giữ nguyên intent này, không tự phân loại lại.
	 */
	public function set_intent( string $intent ): void {
		$allowed = [ 'know', 'do', 'go', 'hybrid' ];
		if ( in_array( $intent, $allowed, true ) ) {
			$this->intent        = $intent;
			$this->intent_forced = true;
		}
	}

	/**
	 * Set persona độc giả để AI điều chỉnh trường "note" phù hợp với từng đối tượng.
	 * '' = chung (không phân biệt) | 'buyer' | 'investor' | 'renter'
	 */
	public function set_persona( string $persona ): void {
		$allowed = [ '', 'buyer', 'investor', 'renter' ];
		if ( in_array( $persona, $allowed, true ) ) {
			$this->persona = $persona;
		}
	}

	/**
	 * Sinh tóm tắt từ nội dung bài viết.
	 *
	 * @param string $post_title
	 * @param string $post_content Nội dung thuần (đã strip_tags).
	 * @return array{title:string,bullets:array,note:string,cta:string}|\WP_Error
	 */
	public function generate( string $post_title, string $post_content ): array|\WP_Error {
		if ( empty( $this->api_key ) ) {
			/* translators: %s: provider name */
			return new \WP_Error(
				'no_api_key',
				sprintf(
					__( 'Chưa cấu hình API Key cho provider "%s" trong Settings.', 'aeo-summary-box' ),
					$this->provider
				)
			);
		}

		$prompt = $this->build_prompt( $post_title, $post_content );

		switch ( $this->provider ) {
			case 'claude':
				return $this->call_claude( $prompt );
			case 'openai':
				return $this->call_openai( $prompt );
			case 'custom':
				return $this->call_custom( $prompt );
			default:
				return $this->call_gemini( $prompt );
		}
	}

	// ── Gemini ──────────────────────────────────────────────────────────────

	private function call_gemini( string $prompt ): array|\WP_Error {
		$url  = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->api_key}";
		$body = wp_json_encode( [
			'contents'         => [ [ 'parts' => [ [ 'text' => $prompt ] ] ] ],
			'generationConfig' => [
				'temperature'      => 0.3,
				'maxOutputTokens'  => 800,
				'responseMimeType' => 'application/json',
			],
		] );

		$response = wp_remote_post( $url, [
			'headers' => [ 'Content-Type' => 'application/json' ],
			'body'    => $body,
			'timeout' => 60,
		] );

		if ( is_wp_error( $response ) ) {
			return $this->wrap_connection_error( $response, 'generativelanguage.googleapis.com' );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new \WP_Error(
				'gemini_error',
				/* translators: %d: HTTP status code */
				sprintf( __( 'Gemini API trả lỗi HTTP %d.', 'aeo-summary-box' ), $code ),
				wp_remote_retrieve_body( $response )
			);
		}

		$body_data = json_decode( wp_remote_retrieve_body( $response ), true );
		$this->last_tokens = $this->extract_tokens( $body_data ?? [] );
		$text              = $body_data['candidates'][0]['content']['parts'][0]['text'] ?? '';

		if ( empty( $text ) ) {
			return new \WP_Error( 'empty_response', __( 'Gemini không trả về nội dung.', 'aeo-summary-box' ) );
		}

		return $this->parse_json_text( $text );
	}

	// ── Claude (Anthropic) ──────────────────────────────────────────────────

	private function call_claude( string $prompt ): array|\WP_Error {
		$url  = 'https://api.anthropic.com/v1/messages';
		$body = wp_json_encode( [
			'model'      => $this->model,
			'max_tokens' => 800,
			'messages'   => [ [ 'role' => 'user', 'content' => $prompt ] ],
		] );

		$response = wp_remote_post( $url, [
			'headers' => [
				'Content-Type'      => 'application/json',
				'x-api-key'         => $this->api_key,
				'anthropic-version' => '2023-06-01',
			],
			'body'    => $body,
			'timeout' => 60,
		] );

		if ( is_wp_error( $response ) ) {
			return $this->wrap_connection_error( $response, 'api.anthropic.com' );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new \WP_Error(
				'claude_error',
				/* translators: %d: HTTP status code */
				sprintf( __( 'Claude API trả lỗi HTTP %d.', 'aeo-summary-box' ), $code ),
				wp_remote_retrieve_body( $response )
			);
		}

		$body_data = json_decode( wp_remote_retrieve_body( $response ), true );
		$this->last_tokens = $this->extract_tokens( $body_data ?? [] );
		$text              = $body_data['content'][0]['text'] ?? '';

		if ( empty( $text ) ) {
			return new \WP_Error( 'empty_response', __( 'Claude không trả về nội dung.', 'aeo-summary-box' ) );
		}

		return $this->parse_json_text( $text );
	}

	// ── OpenAI ──────────────────────────────────────────────────────────────

	private function call_openai( string $prompt ): array|\WP_Error {
		$url  = 'https://api.openai.com/v1/chat/completions';
		$body = wp_json_encode( [
			'model'       => $this->model,
			'max_tokens'  => 800,
			'temperature' => 0.3,
			'messages'    => [ [ 'role' => 'user', 'content' => $prompt ] ],
			'response_format' => [ 'type' => 'json_object' ],
		] );

		$response = wp_remote_post( $url, [
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			],
			'body'    => $body,
			'timeout' => 60,
		] );

		if ( is_wp_error( $response ) ) {
			return $this->wrap_connection_error( $response, 'api.openai.com' );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new \WP_Error(
				'openai_error',
				/* translators: %d: HTTP status code */
				sprintf( __( 'OpenAI API trả lỗi HTTP %d.', 'aeo-summary-box' ), $code ),
				wp_remote_retrieve_body( $response )
			);
		}

		$body_data = json_decode( wp_remote_retrieve_body( $response ), true );
		$this->last_tokens = $this->extract_tokens( $body_data ?? [] );
		$text              = $body_data['choices'][0]['message']['content'] ?? '';

		if ( empty( $text ) ) {
			return new \WP_Error( 'empty_response', __( 'OpenAI không trả về nội dung.', 'aeo-summary-box' ) );
		}

		return $this->parse_json_text( $text );
	}

	// ── Custom endpoint (OpenAI-compatible) ────────────────────────────────

	private function call_custom( string $prompt ): array|\WP_Error {
		if ( empty( $this->custom_endpoint ) ) {
			return new \WP_Error( 'no_endpoint', __( 'Chưa nhập Custom Endpoint URL trong Settings.', 'aeo-summary-box' ) );
		}

		$body_args = [
			'messages'    => [ [ 'role' => 'user', 'content' => $prompt ] ],
			'max_tokens'  => 800,
			'temperature' => 0.3,
		];

		// Chỉ thêm model nếu đã điền.
		if ( ! empty( $this->model ) ) {
			$body_args['model'] = $this->model;
		}

		// KHÔNG gửi response_format — nhiều custom/proxy provider không hỗ trợ
		// và sẽ trả lỗi hoặc content rỗng khi nhận tham số này.

		$response = wp_remote_post( $this->custom_endpoint, [
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			],
			'body'    => wp_json_encode( $body_args ),
			'timeout' => 60,
		] );

		if ( is_wp_error( $response ) ) {
			return $this->wrap_connection_error( $response, (string) wp_parse_url( $this->custom_endpoint, PHP_URL_HOST ) );
		}

		$raw_body  = wp_remote_retrieve_body( $response );
		$code      = (int) wp_remote_retrieve_response_code( $response );

		// Phát hiện response là HTML thay vì JSON — endpoint URL sai.
		if ( str_starts_with( ltrim( $raw_body ), '<' ) ) {
			return new \WP_Error(
				'custom_html_response',
				__( 'Endpoint URL trả về trang HTML thay vì JSON. Kiểm tra lại URL — phải trỏ thẳng vào API endpoint (vd: https://your-provider.com/v1/chat/completions), không phải trang web.', 'aeo-summary-box' )
			);
		}

		$body_data = json_decode( $raw_body, true );
		if ( is_array( $body_data ) ) {
			$this->last_tokens = $this->extract_tokens( $body_data );
		}

		// Lỗi HTTP rõ ràng (4xx / 5xx).
		if ( $code < 200 || $code >= 300 ) {
			$msg = $body_data['error']['message']
				?? $body_data['message']
				?? mb_substr( $raw_body, 0, 300 );
			return new \WP_Error(
				'custom_http_error',
				/* translators: 1: HTTP code 2: error message */
				sprintf( __( 'Custom API lỗi HTTP %1$d: %2$s', 'aeo-summary-box' ), $code, $msg )
			);
		}

		// Một số provider trả HTTP 200 nhưng body là error object.
		if ( isset( $body_data['error'] ) ) {
			$msg = $body_data['error']['message'] ?? wp_json_encode( $body_data['error'] );
			return new \WP_Error(
				'custom_api_error',
				/* translators: %s: error message from provider */
				sprintf( __( 'Custom API báo lỗi: %s', 'aeo-summary-box' ), $msg )
			);
		}

		// Lấy text từ response — thử lần lượt các format phổ biến.
		$text = $body_data['choices'][0]['message']['content']  // OpenAI / OpenRouter
			?? $body_data['choices'][0]['text']                  // một số proxy cũ
			?? $body_data['content'][0]['text']                  // Anthropic-compatible
			?? $body_data['output'][0]['content'][0]['text']     // format mới hơn
			?? null;

		// Nếu content là array (streaming object), thử lấy text bên trong.
		if ( is_array( $text ) ) {
			$text = $text['text'] ?? $text[0]['text'] ?? null;
		}

		if ( empty( $text ) ) {
			// Trả raw body để user/developer dễ debug.
			$preview = mb_substr( $raw_body, 0, 500 );
			return new \WP_Error(
				'custom_empty',
				__( 'Custom endpoint không trả về nội dung. Response nhận được:', 'aeo-summary-box' ) . ' ' . $preview
			);
		}

		return $this->parse_json_text( (string) $text );
	}

	// ── Helpers ─────────────────────────────────────────────────────────────

	/**
	 * Trích xuất thông tin token từ response body của các provider.
	 * Trả về: ['input' => int, 'output' => int, 'total' => int]
	 */
	private function extract_tokens( array $data ): array {
		// Gemini
		if ( isset( $data['usageMetadata'] ) ) {
			$m = $data['usageMetadata'];
			return [
				'input'  => (int) ( $m['promptTokenCount'] ?? 0 ),
				'output' => (int) ( $m['candidatesTokenCount'] ?? 0 ),
				'total'  => (int) ( $m['totalTokenCount'] ?? 0 ),
			];
		}
		// Claude
		if ( isset( $data['usage']['input_tokens'] ) ) {
			$in  = (int) $data['usage']['input_tokens'];
			$out = (int) ( $data['usage']['output_tokens'] ?? 0 );
			return [ 'input' => $in, 'output' => $out, 'total' => $in + $out ];
		}
		// OpenAI / Custom OpenAI-compatible
		if ( isset( $data['usage']['prompt_tokens'] ) ) {
			return [
				'input'  => (int) $data['usage']['prompt_tokens'],
				'output' => (int) ( $data['usage']['completion_tokens'] ?? 0 ),
				'total'  => (int) ( $data['usage']['total_tokens'] ?? 0 ),
			];
		}
		return [];
	}

	private function build_prompt( string $post_title, string $post_content ): string {
		$content_excerpt = mb_substr( wp_strip_all_tags( $post_content ), 0, 4000 );

		// Kiểm tra nếu user đã tùy chỉnh prompt template trong Settings.
		$settings        = Settings::get_instance();
		$custom_template = trim( (string) $settings->get( 'prompt_template', '' ) );

		if ( ! empty( $custom_template ) ) {
			return strtr( $custom_template, [
				'{language}'     => $this->language,
				'{bullet_count}' => (string) $this->bullet_count,
				'{post_title}'   => $post_title,
				'{post_content}' => $content_excerpt,
			] );
		}

		// Default prompt (được tối ưu sẵn, dùng khi template trống).
		$lang         = $this->language;
		$bullet_count = $this->bullet_count;

		// Khối override intent — inject nếu biên tập viên đã chọn thủ công.
		$intent_override_block = '';
		if ( $this->intent_forced ) {
			$fi                    = $this->intent;
			$intent_override_block = "NOTE QUAN TRỌNG — INTENT ĐÃ XÁC NHẬN BỞI BIÊN TẬP VIÊN: \"{$fi}\"\nGhi đúng \"{$fi}\" vào trường \"intent\" — KHÔNG tự phân loại lại dựa trên nội dung bài.\n";
		}

		// Khối persona — inject nếu được chọn.
		$persona_block = '';
		if ( ! empty( $this->persona ) ) {
			$persona_map = [
				'buyer'    => [ 'label' => 'Người mua ở thực',  'hint' => 'Tập trung vào pháp lý, tiến độ bàn giao, điều kiện vay ngân hàng.' ],
				'investor' => [ 'label' => 'Nhà đầu tư',        'hint' => 'Tập trung vào tiềm năng tăng giá, thanh khoản, lợi suất cho thuê.' ],
				'renter'   => [ 'label' => 'Người thuê',         'hint' => 'Tập trung vào giá thuê thực tế, điều khoản hợp đồng, tiện ích sử dụng hàng ngày.' ],
			];

			// CTA theo intent × persona — mỗi persona có 4 câu tương ứng với 4 intent.
			$cta_map = [
				'buyer'    => [
					'know'   => 'Cuộn xuống để xem thông tin pháp lý và điều kiện vay.',
					'do'     => 'Cuộn xuống để xem từng bước trong quy trình mua nhà.',
					'go'     => 'Cuộn xuống để xem địa chỉ showroom và lịch hẹn tư vấn.',
					'hybrid' => 'Cuộn xuống để xem đầy đủ thông tin mua và pháp lý.',
				],
				'investor' => [
					'know'   => 'Cuộn xuống để xem phân tích tiềm năng và lợi suất đầu tư.',
					'do'     => 'Cuộn xuống để xem các bước đầu tư và lưu ý pháp lý.',
					'go'     => 'Cuộn xuống để xem thông tin liên hệ và đặt lịch tư vấn đầu tư.',
					'hybrid' => 'Cuộn xuống để xem đầy đủ thông tin đầu tư.',
				],
				'renter'   => [
					'know'   => 'Cuộn xuống để xem chi phí thuê và tiện ích thực tế.',
					'do'     => 'Cuộn xuống để xem quy trình thuê và lưu ý hợp đồng.',
					'go'     => 'Cuộn xuống để xem địa chỉ và thông tin thuê trực tiếp.',
					'hybrid' => 'Cuộn xuống để xem đầy đủ thông tin thuê và tiện ích.',
				],
			];

			if ( isset( $persona_map[ $this->persona ] ) ) {
				$pl         = $persona_map[ $this->persona ]['label'];
				$hint       = $persona_map[ $this->persona ]['hint'];
				$cta_know   = $cta_map[ $this->persona ]['know'];
				$cta_do     = $cta_map[ $this->persona ]['do'];
				$cta_go     = $cta_map[ $this->persona ]['go'];
				$cta_hybrid = $cta_map[ $this->persona ]['hybrid'];

				$persona_block = "PERSONA ĐỘC GIẢ CHÍNH: {$pl}\n"
					. "→ Khi viết trường \"note\", ưu tiên thông tin quan trọng nhất với {$pl}. {$hint}\n"
					. "→ Khi viết trường \"cta\", dùng câu phù hợp với {$pl} theo intent:\n"
					. "  • \"know\"   → \"{$cta_know}\"\n"
					. "  • \"do\"     → \"{$cta_do}\"\n"
					. "  • \"go\"     → \"{$cta_go}\"\n"
					. "  • \"hybrid\" → \"{$cta_hybrid}\"\n";
			}
		}

		return <<<PROMPT
Bạn là chuyên gia SEO và tối ưu nội dung cho các AI search engine (Google SGE, ChatGPT, Perplexity, Gemini, Bing Copilot).

Nhiệm vụ: Đọc bài viết bên dưới và tạo hộp tóm tắt nhanh tối ưu cho AEO (Answer Engine Optimization) và GEO (Generative Engine Optimization).
{$intent_override_block}
BƯỚC 1 — PHÂN LOẠI SEARCH INTENT:
Dựa vào tiêu đề bài và nội dung, xác định intent chính và ghi vào trường "intent":
- "know"   → bài thông tin/kiến thức: giới thiệu, tổng quan, phân tích, quy hoạch về một dự án/sản phẩm/địa danh/chủ đề; review; so sánh; tin tức.
- "do"     → bài hướng dẫn hành động: "cách làm", "hướng dẫn", "các bước", "thủ tục", "quy trình", "checklist".
- "go"     → bài về địa điểm/liên hệ: "địa chỉ", "showroom", "văn phòng", "liên hệ", "đường đi", "tọa độ".
- "hybrid" → CHỈ dùng khi bài trộn lẫn nhiều intent ngang nhau. KHÔNG chọn "hybrid" cho an toàn khi phân vân — hãy chọn intent chiếm tỷ trọng lớn nhất. Phần lớn bài giới thiệu/thông tin là "know".
{$persona_block}
QUY TẮC BẮT BUỘC:
- Trả về JSON đúng schema, KHÔNG kèm markdown fence (```), KHÔNG giải thích thêm bất kỳ điều gì.
- Tự xác định chủ đề/lĩnh vực bài viết rồi chọn nhãn bullet phù hợp nhất với lĩnh vực đó.
- Tạo đúng {$bullet_count} bullets — mỗi bullet là 1 thông tin cốt lõi mà AI search có thể trích dẫn trực tiếp.
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
- cta: 1 câu kêu gọi hành động phù hợp với intent.
  • "know"   → "Cuộn xuống để xem phân tích chi tiết."
  • "do"     → "Cuộn xuống để xem hướng dẫn từng bước."
  • "go"     → "Cuộn xuống để xem bản đồ và thông tin liên hệ."
  • "hybrid" → "Cuộn xuống để xem thông tin đầy đủ."
- title: bắt đầu bằng "Tóm tắt nhanh:" rồi nêu chủ đề chính của bài.
- Ngôn ngữ output: {$lang}.

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

Tiêu đề bài: {$post_title}
Nội dung bài:
{$content_excerpt}
PROMPT;
	}

	/**
	 * Thêm hướng dẫn chi tiết vào lỗi kết nối để dễ debug.
	 */
	private function wrap_connection_error( \WP_Error $error, string $host ): \WP_Error {
		$msg  = $error->get_error_message();
		$hint = '';

		if ( str_contains( $msg, 'timed out' ) || str_contains( $msg, 'Operation timed out' ) ) {
			$hint = sprintf(
				/* translators: %s: hostname */
				__( ' → Có thể hosting chặn outbound HTTPS đến %s. Liên hệ nhà cung cấp hosting để mở cổng 443 ra ngoài, hoặc dùng VPS/proxy.', 'aeo-summary-box' ),
				$host
			);
		} elseif ( str_contains( $msg, 'Could not resolve host' ) ) {
			$hint = __( ' → Server không phân giải được DNS. Kiểm tra DNS resolver của hosting.', 'aeo-summary-box' );
		} elseif ( str_contains( $msg, 'SSL' ) || str_contains( $msg, 'certificate' ) ) {
			$hint = __( ' → Lỗi SSL. Hosting có thể dùng CA bundle cũ. Liên hệ hosting để cập nhật.', 'aeo-summary-box' );
		}

		return new \WP_Error(
			$error->get_error_code(),
			$msg . $hint,
			$error->get_error_data()
		);
	}

	/**
	 * Kiểm tra kết nối đến endpoint của provider — dùng cho trang Settings.
	 * Trả về array ['ok' => bool, 'message' => string, 'latency_ms' => int]
	 */
	public function test_connection(): array {
		$urls = [
			'gemini' => 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $this->api_key,
			'claude' => 'https://api.anthropic.com/v1/models',
			'openai' => 'https://api.openai.com/v1/models',
			'custom' => $this->custom_endpoint,
		];

		$url = $urls[ $this->provider ] ?? '';
		if ( empty( $url ) ) {
			return [ 'ok' => false, 'message' => 'Endpoint không hợp lệ.', 'latency_ms' => 0 ];
		}

		$headers = [ 'Content-Type' => 'application/json' ];
		if ( 'claude' === $this->provider ) {
			$headers['x-api-key']         = $this->api_key;
			$headers['anthropic-version']  = '2023-06-01';
		} elseif ( in_array( $this->provider, [ 'openai', 'custom' ], true ) ) {
			$headers['Authorization'] = 'Bearer ' . $this->api_key;
		}

		$start    = microtime( true );
		$response = wp_remote_get( $url, [
			'headers' => $headers,
			'timeout' => 15,
		] );
		$latency  = (int) round( ( microtime( true ) - $start ) * 1000 );

		if ( is_wp_error( $response ) ) {
			$msg = $response->get_error_message();
			if ( str_contains( $msg, 'timed out' ) ) {
				$msg .= ' → Hosting có thể chặn outbound HTTPS. Liên hệ nhà cung cấp hosting.';
			}
			return [ 'ok' => false, 'message' => $msg, 'latency_ms' => $latency ];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		// 200, 401 (invalid key nhưng kết nối OK), 404 đều chứng tỏ server trả về.
		$connected = $code > 0 && $code < 600;
		$msg       = $connected
			? sprintf( 'Kết nối thành công (HTTP %d, %d ms).', $code, $latency )
			: "Không nhận được response hợp lệ (HTTP {$code}).";

		if ( 401 === $code ) {
			$msg .= ' API Key không hợp lệ hoặc chưa có quyền.';
		}

		return [ 'ok' => $connected, 'message' => $msg, 'latency_ms' => $latency ];
	}

	private function parse_json_text( string $text ): array|\WP_Error {
		// Bước 1: Xóa markdown fence.
		$text = trim( preg_replace( '/^```(?:json)?\s*|\s*```$/m', '', $text ) );

		// Bước 2: Thử parse thẳng.
		$data = json_decode( $text, true );
		if ( is_array( $data ) ) {
			return $this->validate_structure( $data );
		}

		// Bước 3: Fallback — trích xuất JSON object đầu tiên trong chuỗi
		// (xử lý trường hợp model thêm text trước/sau JSON).
		if ( preg_match( '/\{[\s\S]*\}/u', $text, $m ) ) {
			$data = json_decode( $m[0], true );
			if ( is_array( $data ) ) {
				return $this->validate_structure( $data );
			}
		}

		return new \WP_Error(
			'parse_error',
			__( 'Không parse được JSON từ phản hồi AI. Nội dung nhận được: ', 'aeo-summary-box' ) . mb_substr( $text, 0, 300 )
		);
	}

	private function validate_structure( array $data ): array|\WP_Error {
		if ( empty( $data['bullets'] ) || ! is_array( $data['bullets'] ) ) {
			return new \WP_Error( 'invalid_structure', __( 'Cấu trúc JSON không hợp lệ (thiếu bullets).', 'aeo-summary-box' ) );
		}

		$allowed_intents = [ 'know', 'do', 'go', 'hybrid' ];
		$intent          = $data['intent'] ?? 'hybrid';
		$intent          = in_array( $intent, $allowed_intents, true ) ? $intent : 'hybrid';

		$allowed_personas = [ '', 'buyer', 'investor', 'renter' ];
		$persona          = $data['persona'] ?? '';
		$persona          = in_array( $persona, $allowed_personas, true ) ? $persona : '';

		return [
			'intent'  => $intent,
			'persona' => $persona,
			'title'   => sanitize_text_field( $data['title'] ?? '' ),
			'tldr'    => sanitize_text_field( $data['tldr'] ?? '' ),
			'bullets' => array_map( fn( $b ) => [
				'label'    => sanitize_text_field( $b['label'] ?? '' ),
				'question' => sanitize_text_field( $b['question'] ?? '' ),
				'content'  => sanitize_text_field( $b['content'] ?? '' ),
			], $data['bullets'] ),
			'note'    => sanitize_text_field( $data['note'] ?? '' ),
			'cta'     => sanitize_text_field( $data['cta'] ?? '' ),
		];
	}
}
