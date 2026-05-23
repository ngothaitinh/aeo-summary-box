=== AEO Summary Box ===
Contributors: tpiland
Tags: aeo, geo, ai, summary, seo, elementor, schema, faq, gemini, openai
Requires at least: 6.6
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.7.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Tự động sinh hộp tóm tắt bài viết bằng AI, tối ưu AEO/GEO cho Google SGE, ChatGPT, Perplexity. Hỗ trợ Elementor Pro, Schema.org FAQPage + Article.

== Description ==

**AEO Summary Box** giúp tự động tạo hộp tóm tắt cấu trúc (structured summary box) cho mỗi bài viết, tối ưu để các AI search engine như Google AI Overview, Bing Copilot, ChatGPT, Perplexity, Gemini trích dẫn nội dung của bạn.

= Tính năng chính =

* **Sinh tóm tắt bằng AI** — Hỗ trợ 4 provider: Google Gemini, Anthropic Claude, OpenAI, và Custom endpoint (OpenAI-compatible).
* **Tự động nhận diện lĩnh vực** — AI tự chọn nhãn bullet phù hợp: Bất động sản, Du lịch, Công nghệ, Sức khỏe, Tài chính, Ẩm thực, Giáo dục, Tin tức.
* **Schema.org tự động** — Inject `Article` + `FAQPage` JSON-LD vào `<head>`. Mỗi bullet thành một cặp Question/Answer.
* **Meta description tự động** — Sinh `<meta name="description">` và `<og:description>` từ trường `tldr` (nếu chưa có plugin SEO).
* **Speakable specification** — Đánh dấu các phần nội dung cho Google Assistant.
* **Hỗ trợ Elementor Pro** — Widget kéo-thả riêng + auto-insert JS thông minh (sau TOC, sapo, hoặc H1).
* **Shortcode** `[aeo_summary]` — Chèn vào bất kỳ đâu.
* **Mobile collapse** — Thu gọn về 3 bullets đầu trên màn hình nhỏ, có nút "Xem thêm".
* **Prompt tùy chỉnh** — Thay thế prompt mặc định bằng prompt riêng của bạn.
* **Biên tập thủ công** — Chỉnh sửa tóm tắt trước khi lưu qua metabox trong editor.

= Tối ưu AEO / GEO =

* Mỗi bullet content là câu **đầy đủ, đứng độc lập** (entity-first) — dễ trích dẫn bởi AI.
* Trường `tldr` ≤160 ký tự — tối ưu cho Google snippet và AI Overview.
* FAQPage schema với câu hỏi tự nhiên — tăng xác suất xuất hiện trong Featured Snippet.
* Cấu trúc dữ liệu lưu dạng JSON trong `post_meta` — stable, không phụ thuộc markup.

= Providers hỗ trợ =

* **Google Gemini** — `gemini-2.0-flash`, `gemini-1.5-flash`, `gemini-1.5-pro`
* **Anthropic Claude** — `claude-haiku-4-5`, `claude-sonnet-4-5`, `claude-opus-4-5`
* **OpenAI** — `gpt-4o-mini`, `gpt-4o`, `gpt-4-turbo`
* **Custom** — Bất kỳ endpoint OpenAI-compatible (OpenRouter, LM Studio, proxy...)

== Installation ==

1. Upload thư mục `aeo-summary-box` vào `/wp-content/plugins/`.
2. Kích hoạt plugin trong **Plugins > Installed Plugins**.
3. Vào **Settings > AEO Summary** để cấu hình AI provider và API key.
4. Mở bất kỳ bài viết nào, cuộn xuống metabox **AEO Summary Box**, nhấn **Tạo tóm tắt bằng AI**.
5. Chỉnh sửa nếu cần, nhấn **Lưu tóm tắt**.

= Elementor Pro =

Kéo widget **AEO Summary Box** từ panel Elementor vào vị trí mong muốn trong template.
Hoặc để auto-insert JS tự chèn vào đúng vị trí (sau TOC hoặc sapo).

== Frequently Asked Questions ==

= Plugin hỗ trợ ngôn ngữ nào? =

Tiếng Việt (mặc định) và Tiếng Anh. Chọn trong **Settings > AEO Summary > Ngôn ngữ tóm tắt**.

= Tôi cần API key của dịch vụ nào? =

Ít nhất một trong bốn provider: Google Gemini (miễn phí tier), Anthropic Claude, OpenAI, hoặc Custom endpoint. Gemini có free tier khá rộng rãi cho bắt đầu.

= Plugin có xung đột với Yoast SEO / RankMath không? =

Không. Plugin tự động nhường `<meta name="description">` cho Yoast, RankMath, AIOSEO, SEOPress nếu phát hiện một trong các plugin này đang active.

= Tóm tắt AI sinh ra có lưu ở đâu không? =

Lưu trong `post_meta` với key `_aeo_summary_json` dưới dạng JSON. Xoá plugin sẽ xoá sạch toàn bộ dữ liệu này.

= Shortcode dùng như thế nào? =

Thêm `[aeo_summary]` vào bất kỳ vị trí nào trong nội dung bài hoặc trong Text Editor widget của Elementor.

= Tôi có thể tùy chỉnh prompt không? =

Có. Vào **Settings > AEO Summary > Prompt Template (nâng cao)**, nhập prompt của bạn với các placeholder `{language}`, `{bullet_count}`, `{post_title}`, `{post_content}`.

== Screenshots ==

1. Hộp tóm tắt trên frontend (theme mặc định)
2. Metabox chỉnh sửa tóm tắt trong Classic Editor
3. Settings page — cấu hình AI provider
4. Elementor widget kéo-thả
5. Schema.org FAQPage trong Google Rich Results Test

== Changelog ==

= 1.7.0 =
* Schema — defer Article schema khi Yoast / RankMath / AIOSEO đang active (tránh duplicate), tách hàm has_seo_plugin() dùng chung với meta description.
* Schema — intent "do": chỉ inject HowTo, bỏ FAQPage (cùng nội dung trong 2 schema tăng rủi ro spam).
* Schema — LocalBusiness: hỗ trợ per-post contact override (lưu trong summary JSON), fallback về Settings.
* Metabox — thêm section "Thông tin địa điểm" (org_name, address, phone, hours) hiện khi intent = Go; toggle tự động qua JS.
* Metabox — bullet row chuyển sang 4-column grid (Nhãn / Câu hỏi FAQ / Nội dung / Xoá) kèm header labels.
* Frontend — badge intent hiển thị trong header hộp: "📋 Hướng dẫn" (DO) và "📍 Địa điểm" (GO).
* llms.txt — thêm tag `[intent:know|do|go|hybrid]` vào mỗi dòng bài viết.
* Bulk — Settings: thêm tuỳ chọn "Intent mặc định (Bulk)" để áp dụng cố định intent khi sinh tóm tắt hàng loạt.

= 1.6.2 =
* Prompt — CTA linh hoạt theo intent × persona: mỗi tổ hợp (know/do/go/hybrid) × (buyer/investor/renter/chung) có câu CTA riêng, thay vì dùng chung 4 câu theo intent.

= 1.6.1 =
* Prompt — chống lặp entity máy móc: bullet content vẫn phải định danh entity (đầy đủ hoặc rút gọn) nhưng KHÔNG lặp y nguyên một chuỗi tên ở đầu mọi bullet; entity được phép nằm giữa câu.
* Prompt — fact bắt buộc: mỗi bullet phải có ít nhất 1 dữ kiện kiểm chứng được (số liệu/ngày/tên riêng/địa danh); cấm bullet "định hướng/tầm nhìn" chung chung kiểu marketing.
* Prompt — label khác biệt: cấm 2 bullet cùng nói một khía cạnh (vd "Hạ tầng" + "Kết nối"); buộc gộp và dùng slot cho khía cạnh có dữ kiện.
* Prompt — phân loại intent chặt hơn: "hybrid" chỉ dùng khi bài thực sự trộn lẫn, không dùng làm lựa chọn an toàn; bài giới thiệu/thông tin mặc định là "know".
* Đồng bộ default_prompt() trong Settings với prompt thật.

= 1.6.0 =
* Phase 2 — Intent override: nút "Tạo tóm tắt bằng AI" gửi intent hiện tại trong metabox; AI giữ nguyên intent đã chọn, không tự phân loại lại.
* Phase 2 — Persona field: dropdown "Người mua ở thực / Nhà đầu tư / Người thuê" trong metabox; AI điều chỉnh trường "note" theo mối quan tâm từng đối tượng. Persona lưu vào post_meta để restore khi mở lại bài.
* Phase 3 — HowTo schema: inject `HowTo` JSON-LD khi intent = "do" (bullets → HowToStep, anchor deep-link per step). Thêm kèm FAQPage — không thay thế.
* Phase 3 — LocalBusiness schema: inject `LocalBusiness` JSON-LD khi intent = "go" và đã điền địa chỉ trong Settings. Hỗ trợ tên tổ chức, địa chỉ, phone, giờ mở cửa nhiều ca (tách bằng dấu phẩy).
* Settings: thêm section "LocalBusiness Schema" với 4 trường contact_org_name / contact_address / contact_phone / contact_hours.
* Field round-trip: `persona` đi qua AI_Client::set_persona → generate → collectSummary → handle_save → post_meta → metabox restore.

= 1.5.0 =
* Search Intent: AI tự phân loại bài theo intent know/do/go/hybrid — tldr, note, cta được sinh khác nhau tùy intent.
* Trường `question` per-bullet — câu hỏi tự nhiên người dùng thật sự gõ; FAQPage schema dùng trực tiếp thay vì heuristic PHP (vẫn fallback cho tóm tắt cũ).
* Prompt "Bước N" cho intent "do" — label bullet tự động dạng "Bước 1", "Bước 2" khi bài là hướng dẫn quy trình.
* Metabox: thêm dropdown Search Intent (know/do/go/hybrid) và input "Câu hỏi người dùng" cho từng bullet.
* TL;DR counter và maxlength thu hẹp xuống 120 ký tự (từ 160) — khớp với giới hạn AI Overview.
* Round-trip field integrity: intent + question đi qua toàn bộ luồng AI → validate → populateEditor → collectSummary → handle_save → post_meta.
* Đồng bộ default_prompt() trong Settings với prompt thật đang chạy (cả hai cùng phiên bản intent/question).

= 1.4.2 =
* Tối ưu GEO/AEO: prompt bullet mới yêu cầu câu hoàn chỉnh 12–28 từ, bắt đầu bằng tên entity chính — FAQPage `acceptedAnswer` tự đứng độc lập, không mất ngữ cảnh khi AI trích dẫn.
* Đồng bộ prompt mặc định trong Settings với prompt thật đang chạy (trước đây 2 phiên bản khác nhau).
* ARIA role hộp tóm tắt: `complementary` → `region` — readability extractor không bỏ qua hộp.
* JSON-LD Schema minified (bỏ pretty-print) — tiết kiệm ~25% token khi AI crawler đọc; cache key đổi sang `_aeo_schema_cache_v2` để buộc tái tạo sạch.
* `data-nosnippet` trên CTA và nút toggle — Google bỏ qua khi dựng snippet.
* Per-bullet anchor id (`aeo-sb-fact-0`, `aeo-sb-fact-1`...) — AI engine có thể deep-link trích dẫn từng fact.
* Hiển thị ngày cập nhật bài viết trong hộp tóm tắt — tín hiệu freshness cho AI search.

= 1.4.1 =
* Sửa lỗi nghiêm trọng (critical error) khi kích hoạt trên site thật: widget Elementor chỉ load lazy trong hook `elementor/widgets/register`, không require sớm khi plugin load trước Elementor.
* Sửa bulk generation: hook WP-Cron đăng ký ở mọi context (cron chạy ở frontend); `init_hooks()` gọi trực tiếp thay vì add_action('init') đã trễ.
* Sửa hộp tóm tắt che mất TOC trên mobile: bỏ qua bước tìm TOC trên màn hình ≤600px, chèn thẳng sau sapo.
* GA4 tracking: 4 sự kiện `aeo_summary_view`, `aeo_summary_expand`, `aeo_summary_collapse`, `aeo_summary_cta_click` qua gtag() hoặc GTM dataLayer.
* Tối ưu mobile: hộp thu gọn cao tối đa ~50% màn hình (max-height + overflow), bullet về layout 1 dòng, nén padding/font; prompt sinh câu súc tích hơn (bullet ≤16 từ, TL;DR ≤120 ký tự).

= 1.4.0 =
* Bulk generation: cột "Tóm tắt AI" trong danh sách bài, bulk action "Tạo tóm tắt AI" thêm bài vào hàng đợi WP-Cron (xử lý 5 giây/bài, không timeout).
* Cache JSON-LD schema trong `_aeo_schema_cache` post_meta — tái tạo khi bài cập nhật hoặc tóm tắt thay đổi, tránh json_encode mỗi page load.
* API key từ hằng số wp-config.php: `AEO_SB_GEMINI_KEY`, `AEO_SB_CLAUDE_KEY`, `AEO_SB_OPENAI_KEY`, `AEO_SB_CUSTOM_KEY` — an toàn hơn lưu trong DB.
* Token tracking: hiển thị số token input/output sau mỗi lần sinh AI trong metabox.
* WPML/Polylang compat: sinh tóm tắt theo ngôn ngữ của từng bài dịch.
* Metabox: thêm trường TL;DR, bộ đếm ký tự, nhãn nút sinh AI hiển thị đúng provider đang dùng.
* Admin.js: `collectSummary()` và `populateEditor()` bao gồm trường `tldr`.
* Uninstall: dọn thêm `_aeo_schema_cache`, `_aeo_summary_tokens`, `aeo_sb_bulk_queue`, cron job.

= 1.3.0 =
* Render server-side: bài KHÔNG dựng bằng Elementor (classic/Gutenberg) chèn hộp tóm tắt trực tiếp vào `the_content` — crawler/bot AI không chạy JS vẫn đọc được nội dung hộp.
* Nhận diện Elementor theo từng bài (`_elementor_edit_mode`) thay vì theo toàn site — bài Elementor dùng JS, bài thường dùng server-side.
* Sửa lỗi chèn hộp 2 lần khi site bật Elementor nhưng bài viết dùng editor thường.
* Thêm file `/llms.txt` (chuẩn llmstxt.org) — liệt kê bài viết + tóm tắt giúp AI crawler (ChatGPT, Perplexity, Claude, Gemini) khám phá nội dung site.
* `llms.txt` cache bằng transient, tự làm mới khi có bài/tóm tắt thay đổi.
* Thêm setting bật/tắt `llms.txt`.
* `frontend.js`: tách phần chèn JS khỏi phần thu gọn mobile — thu gọn hoạt động cả với hộp render server-side.

= 1.2.0 =
* Thêm trường `tldr` — câu tóm tắt ≤160 ký tự tối ưu cho AI Overview.
* Tự động inject `<meta name="description">` và `og:description` từ `tldr`.
* Speakable cssSelector thu hẹp về `.aeo-sb-tldr, .aeo-sb-title, .aeo-sb-content`.
* Mobile auto-collapse: hiển thị 3 bullets đầu, nút "Xem thêm" để expand.
* Compact density trên mobile (13px, padding 10px).
* Thêm setting `compact_mobile` toggle trong Settings.
* Prompt cập nhật: entity-first content rule, gợi ý nhãn 8 lĩnh vực.
* Print media query: toggle ẩn, tất cả bullets hiện khi in.

= 1.1.0 =
* Thêm Schema.org JSON-LD: `Article` + `FAQPage` inject vào `<head>`.
* Đăng ký shortcode `[aeo_summary]`.
* Settings: thêm Language selector, Post Types checkboxes, Prompt Template textarea.
* Fix NULL bug trong `Settings::__construct()` — `wp_parse_args` không ghi đè NULL.
* Fix double summary box khi Elementor active.
* Fix Vietnamese font encoding (`JSON_UNESCAPED_UNICODE`).
* Max-width 800px trên desktop, full-width mobile.

= 1.0.0 =
* Ra mắt lần đầu.
* AI Client hỗ trợ Gemini, Claude, OpenAI, Custom endpoint.
* Metabox chỉnh sửa trong Classic + Gutenberg.
* Renderer với JS auto-insert (sau TOC, sapo, H1).
* Elementor widget kéo-thả.
* REST API `/aeo-summary/v1/generate` và `/save`.

== Upgrade Notice ==

= 1.3.0 =
Cập nhật cải thiện khả năng được AI search trích dẫn: bài viết thường nay render hộp tóm tắt server-side, và site có thêm file /llms.txt. Không cần thao tác thủ công — chỉ cần cập nhật plugin.

= 1.2.0 =
Tái tạo tóm tắt bằng nút "Tạo tóm tắt bằng AI" để thêm trường `tldr` cho các bài viết cũ. Không ảnh hưởng bài đã có tóm tắt — chỉ thiếu `tldr` và meta description mới.
