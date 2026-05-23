<?php
namespace AEO_Summary_Box;

defined( 'ABSPATH' ) || exit;

/**
 * Inject JSON-LD Schema.org markup vào <head> cho AEO/GEO.
 *
 * Schemas được inject:
 *  - Article  → description = TL;DR tóm tắt, speakable CSS selectors.
 *  - FAQPage  → mỗi bullet là 1 cặp Question/Answer.
 */
class Schema {

	private static ?Schema $instance = null;

	public static function get_instance(): Schema {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Meta key lưu cache HTML của JSON-LD. v2 = minified (không pretty-print). */
	private const CACHE_META_KEY = '_aeo_schema_cache_v2';

	private function __construct() {
		add_action( 'wp_head',   [ $this, 'inject' ], 5 );
		// Xoá cache khi bài viết cập nhật (tiêu đề, ngày, tác giả, ảnh đại diện).
		add_action( 'save_post', [ $this, 'clear_cache' ] );
	}

	/**
	 * Xoá cache JSON-LD của một bài (hoặc tất cả nếu $post_id = 0).
	 */
	public function clear_cache( int $post_id = 0 ): void {
		if ( $post_id ) {
			delete_post_meta( $post_id, self::CACHE_META_KEY );
		}
		// Được gọi với post_id từ hook save_post — xoá đúng bài.
	}

	public function inject(): void {
		if ( ! is_singular() ) {
			return;
		}

		$settings   = Settings::get_instance();
		$post_types = (array) $settings->get( 'post_types', [ 'post', 'page' ] );

		if ( ! in_array( get_post_type(), $post_types, true ) ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$raw = get_post_meta( $post_id, AEO_SB_META_KEY, true );
		if ( ! $raw ) {
			return;
		}

		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || empty( $data['bullets'] ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		// --- Meta description (luôn tính mới — phụ thuộc plugin SEO active) ---
		$this->maybe_inject_meta_description( $data );

		// --- JSON-LD: dùng cache nếu có, tránh json_encode lại mỗi request ---
		$cached = get_post_meta( $post_id, self::CACHE_META_KEY, true );
		if ( $cached ) {
			echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$schemas = [];
		$intent  = $data['intent'] ?? 'hybrid';

		// Article schema — nhường cho Yoast / RankMath / AIOSEO nếu đang active.
		if ( ! $this->has_seo_plugin() ) {
			$schemas[] = $this->build_article( $post, $data );
		}

		// HowTo schema — intent "do" (hướng dẫn/quy trình).
		if ( 'do' === $intent ) {
			$howto = $this->build_howto( $data );
			if ( $howto ) {
				$schemas[] = $howto;
			}
		}

		// FAQPage — tất cả intent TRỪ "do" (HowTo đã bao phủ Q&A cho "do",
		// inject thêm FAQPage gây trùng nội dung, tăng nguy cơ bị Google xem là spam).
		if ( 'do' !== $intent ) {
			$faq = $this->build_faq( $data );
			if ( $faq ) {
				$schemas[] = $faq;
			}
		}

		// LocalBusiness — intent "go" + có địa chỉ (settings hoặc per-post).
		if ( 'go' === $intent ) {
			$lb = $this->build_local_business( $post, $data );
			if ( $lb ) {
				$schemas[] = $lb;
			}
		}

		$output = '';
		foreach ( $schemas as $schema ) {
			$output .= '<script type="application/ld+json">' . "\n";
			$output .= wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$output .= "\n</script>\n";
		}

		// Lưu cache vào post_meta (xoá khi save_post hoặc cập nhật tóm tắt).
		update_post_meta( $post_id, self::CACHE_META_KEY, $output );
		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Kiểm tra xem có plugin SEO phổ biến nào đang active không.
	 * Được dùng để tránh inject Article schema hoặc meta description trùng lặp.
	 */
	private function has_seo_plugin(): bool {
		$seo_checks = [
			'WPSEO_VERSION',       // Yoast SEO
			'RANK_MATH_VERSION',   // RankMath
			'AIOSEO_VERSION',      // All in One SEO
			'SEOPRESS_VERSION',    // SEOPress
		];
		foreach ( $seo_checks as $constant ) {
			if ( defined( $constant ) ) {
				return true;
			}
		}
		// SEOPress dùng class thay vì constant trong một số phiên bản.
		return class_exists( 'SeoPress' );
	}

	/**
	 * Inject <meta name="description"> nếu chưa có plugin SEO nào làm.
	 * Nhường quyền cho Yoast, RankMath, AIOSEO nếu active.
	 */
	private function maybe_inject_meta_description( array $data ): void {
		if ( $this->has_seo_plugin() ) {
			return;
		}

		$description = ! empty( $data['tldr'] )
			? sanitize_text_field( $data['tldr'] )
			: $this->build_description( $data );

		if ( empty( $description ) ) {
			return;
		}

		// Cắt ≤160 ký tự cho meta description.
		if ( mb_strlen( $description ) > 160 ) {
			$description = mb_substr( $description, 0, 157 ) . '…';
		}

		echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
	}

	// -----------------------------------------------------------------------
	// Article
	// -----------------------------------------------------------------------

	private function build_article( \WP_Post $post, array $data ): array {
		// Ưu tiên tldr (ngắn, cụ thể) — fallback về description đầy đủ.
		$description = ! empty( $data['tldr'] )
			? sanitize_text_field( $data['tldr'] )
			: $this->build_description( $data );
		$author_id   = (int) $post->post_author;
		$author_name = get_the_author_meta( 'display_name', $author_id );
		$author_url  = get_author_posts_url( $author_id );

		$thumbnail_url = '';
		if ( has_post_thumbnail( $post->ID ) ) {
			$img = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'full' );
			if ( $img ) {
				$thumbnail_url = $img[0];
			}
		}

		$schema = [
			'@context'         => 'https://schema.org',
			'@type'            => 'Article',
			'headline'         => wp_strip_all_tags( $post->post_title ),
			'description'      => $description,
			'url'              => get_permalink( $post->ID ),
			'datePublished'    => get_the_date( 'c', $post ),
			'dateModified'     => get_the_modified_date( 'c', $post ),
			'author'           => [
				'@type' => 'Person',
				'name'  => $author_name,
				'url'   => $author_url,
			],
			'publisher'        => [
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url(),
			],
			// Speakable: Google Assistant đọc tldr + từng bullet content.
			'speakable'        => [
				'@type'       => 'SpeakableSpecification',
				'cssSelector' => [ '.aeo-sb-tldr', '.aeo-sb-title', '.aeo-sb-content' ],
			],
		];

		if ( $thumbnail_url ) {
			$schema['image'] = $thumbnail_url;
		}

		return $schema;
	}

	/**
	 * Xây description từ title + bullets (tối đa 160 ký tự như meta description).
	 */
	private function build_description( array $data ): string {
		$parts = [];

		if ( ! empty( $data['title'] ) ) {
			$parts[] = sanitize_text_field( $data['title'] );
		}

		foreach ( (array) $data['bullets'] as $bullet ) {
			$label   = sanitize_text_field( $bullet['label'] ?? '' );
			$content = sanitize_text_field( $bullet['content'] ?? '' );
			if ( $label && $content ) {
				$parts[] = $label . ': ' . $content;
			}
		}

		$desc = implode( '. ', $parts );

		// Cắt ngắn nếu quá 320 ký tự (Google đọc ~320 cho snippet dài).
		if ( mb_strlen( $desc ) > 320 ) {
			$desc = mb_substr( $desc, 0, 317 ) . '…';
		}

		return $desc;
	}

	// -----------------------------------------------------------------------
	// FAQPage
	// -----------------------------------------------------------------------

	private function build_faq( array $data ): ?array {
		$bullets = (array) $data['bullets'];
		if ( empty( $bullets ) ) {
			return null;
		}

		$entities = [];
		foreach ( $bullets as $bullet ) {
			$label   = sanitize_text_field( $bullet['label'] ?? '' );
			$content = sanitize_text_field( $bullet['content'] ?? '' );
			if ( ! $label || ! $content ) {
				continue;
			}

			// Dùng câu hỏi do AI sinh ra (field "question") nếu có,
			// fallback về heuristic PHP cho các tóm tắt cũ chưa có trường này.
			$question = ! empty( $bullet['question'] )
				? sanitize_text_field( $bullet['question'] )
				: $this->label_to_question( $label );

			$entities[] = [
				'@type'          => 'Question',
				'name'           => $question,
				'acceptedAnswer' => [
					'@type' => 'Answer',
					'text'  => $content,
				],
			];
		}

		// Thêm note như một Q&A cuối nếu có.
		if ( ! empty( $data['note'] ) ) {
			$note = sanitize_text_field( $data['note'] );
			// Bỏ tiền tố "Lưu ý:" nếu có, tách thành câu hỏi.
			$note_clean = preg_replace( '/^lưu\s*ý\s*:?\s*/iu', '', $note );
			if ( $note_clean ) {
				$entities[] = [
					'@type'          => 'Question',
					'name'           => __( 'Lưu ý quan trọng là gì?', 'aeo-summary-box' ),
					'acceptedAnswer' => [
						'@type' => 'Answer',
						'text'  => $note_clean,
					],
				];
			}
		}

		if ( empty( $entities ) ) {
			return null;
		}

		return [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		];
	}

	/**
	 * Chuyển nhãn bullet thành câu hỏi tự nhiên.
	 * Ví dụ: "Vị trí" → "Vị trí dự án ở đâu?"
	 *         "Giá tham khảo" → "Giá tham khảo là bao nhiêu?"
	 *         "Pháp lý" → "Pháp lý của dự án như thế nào?"
	 */
	private function label_to_question( string $label ): string {
		$label = trim( $label );

		// Nếu label đã là câu hỏi thì giữ nguyên.
		if ( str_ends_with( $label, '?' ) ) {
			return $label;
		}

		// Map nhãn phổ biến → câu hỏi tự nhiên.
		$map = [
			// BĐS
			'vị trí'           => 'Vị trí dự án ở đâu?',
			'chủ đầu tư'       => 'Chủ đầu tư dự án là ai?',
			'quy mô'           => 'Quy mô dự án như thế nào?',
			'loại hình'        => 'Loại hình sản phẩm của dự án là gì?',
			'giá tham khảo'    => 'Giá tham khảo của dự án là bao nhiêu?',
			'tiện ích'         => 'Tiện ích dự án có gì nổi bật?',
			'tiện ích nổi bật' => 'Tiện ích dự án có gì nổi bật?',
			'pháp lý'          => 'Pháp lý dự án ra sao?',
			'tiến độ'          => 'Tiến độ bàn giao dự án là khi nào?',
			'diện tích'        => 'Diện tích các căn hộ là bao nhiêu?',
			'mật độ xây dựng'  => 'Mật độ xây dựng dự án là bao nhiêu?',
			// Du lịch
			'di chuyển'        => 'Di chuyển đến đây như thế nào?',
			'thời điểm đẹp nhất' => 'Thời điểm đẹp nhất để đến là khi nào?',
			'ẩm thực'          => 'Ẩm thực nơi đây có gì đặc sắc?',
			'lưu trú'          => 'Nên lưu trú ở đâu?',
			'chi phí'          => 'Chi phí cho chuyến đi là bao nhiêu?',
			'địa điểm tham quan' => 'Những địa điểm tham quan nào đáng ghé?',
			// Công nghệ
			'tính năng chính'  => 'Tính năng chính của sản phẩm là gì?',
			'giá bán'          => 'Giá bán sản phẩm là bao nhiêu?',
			'cấu hình'         => 'Cấu hình kỹ thuật chi tiết như thế nào?',
			// Sức khỏe
			'triệu chứng'      => 'Các triệu chứng thường gặp là gì?',
			'điều trị'         => 'Phương pháp điều trị như thế nào?',
			'phòng ngừa'       => 'Cách phòng ngừa hiệu quả là gì?',
		];

		$key = mb_strtolower( $label, 'UTF-8' );
		if ( isset( $map[ $key ] ) ) {
			return $map[ $key ];
		}

		// Fallback: thêm "... là gì?" hoặc "... như thế nào?" tùy độ dài.
		if ( mb_strlen( $label ) <= 15 ) {
			return $label . ' là gì?';
		}

		return $label . ' như thế nào?';
	}

	// -----------------------------------------------------------------------
	// HowTo (intent = do)
	// -----------------------------------------------------------------------

	/**
	 * Xây HowTo schema từ bullets khi intent là "do".
	 * Mỗi bullet (label = "Bước N", content = mô tả bước) → HowToStep.
	 */
	private function build_howto( array $data ): ?array {
		$bullets = (array) $data['bullets'];
		if ( empty( $bullets ) ) {
			return null;
		}

		$steps = [];
		foreach ( $bullets as $i => $bullet ) {
			$name = sanitize_text_field( $bullet['label']   ?? '' ) ?: 'Bước ' . ( $i + 1 );
			$text = sanitize_text_field( $bullet['content'] ?? '' );
			if ( ! $text ) {
				continue;
			}
			$step = [
				'@type' => 'HowToStep',
				'name'  => $name,
				'text'  => $text,
			];
			// Thêm url anchor nếu có id trên li element (aeo-sb-fact-N).
			$step['url'] = get_permalink() . '#aeo-sb-fact-' . $i;
			$steps[]     = $step;
		}

		if ( empty( $steps ) ) {
			return null;
		}

		return [
			'@context'    => 'https://schema.org',
			'@type'       => 'HowTo',
			'name'        => sanitize_text_field( $data['title'] ?? '' ),
			'description' => sanitize_text_field( $data['tldr']  ?? '' ),
			'step'        => $steps,
		];
	}

	// -----------------------------------------------------------------------
	// LocalBusiness (intent = go)
	// -----------------------------------------------------------------------

	/**
	 * Xây LocalBusiness schema khi intent là "go".
	 *
	 * Thứ tự ưu tiên dữ liệu contact:
	 *  1. Per-post fields lưu trong summary JSON (contact.address / .phone / .hours / .org_name)
	 *  2. Site-wide fields trong Settings (contact_address …)
	 *
	 * Trả null nếu không có địa chỉ từ cả hai nguồn.
	 */
	private function build_local_business( \WP_Post $post, array $data ): ?array {
		$settings = Settings::get_instance();
		$pc       = $data['contact'] ?? []; // per-post contact

		// Địa chỉ — bắt buộc.
		$address = trim( (string) ( $pc['address'] ?? '' ) )
			?: trim( (string) $settings->get( 'contact_address', '' ) );

		if ( empty( $address ) ) {
			return null;
		}

		$org_name = trim( (string) ( $pc['org_name'] ?? '' ) )
			?: trim( (string) $settings->get( 'contact_org_name', '' ) )
			?: get_bloginfo( 'name' );

		$schema = [
			'@context' => 'https://schema.org',
			'@type'    => 'LocalBusiness',
			'name'     => $org_name,
			'url'      => get_permalink( $post->ID ),
			'address'  => [
				'@type'          => 'PostalAddress',
				'streetAddress'  => $address,
				'addressCountry' => 'VN',
			],
		];

		$phone = trim( (string) ( $pc['phone'] ?? '' ) )
			?: trim( (string) $settings->get( 'contact_phone', '' ) );
		if ( $phone ) {
			$schema['telephone'] = $phone;
		}

		$hours = trim( (string) ( $pc['hours'] ?? '' ) )
			?: trim( (string) $settings->get( 'contact_hours', '' ) );
		if ( $hours ) {
			$hours_parts = array_filter( array_map( 'trim', preg_split( '/[,\n]+/', $hours ) ) );
			$schema['openingHours'] = count( $hours_parts ) === 1 ? $hours : array_values( $hours_parts );
		}

		if ( has_post_thumbnail( $post->ID ) ) {
			$img = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'full' );
			if ( $img ) {
				$schema['image'] = $img[0];
			}
		}

		return $schema;
	}
}
