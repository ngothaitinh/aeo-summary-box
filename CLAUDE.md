# Plugin WordPress: AEO/GEO Summary Box

Hướng dẫn này dành cho Claude Code khi làm việc trong dự án. Đọc kỹ trước khi code.

## 1. Mục tiêu sản phẩm

Xây plugin WordPress tên **`aeo-summary-box`** giúp:

1. Tự động sinh **hộp tóm tắt bài viết** (giống mẫu Vinpearl "Sổ tay du lịch") đặt đầu mỗi bài post.
2. Tối ưu **AEO** (Answer Engine Optimization) — để Google AI Overview, Bing Copilot, SGE trích dẫn.
3. Tối ưu **GEO** (Generative Engine Optimization) — để ChatGPT, Perplexity, Gemini, Claude lấy bài làm nguồn.
4. Cho phép **biên tập viên chỉnh sửa thủ công** tóm tắt do AI sinh ra trước khi publish.

## 2. Ngữ cảnh dự án

- **Domain nội dung:** Bất động sản (dự án căn hộ, đất nền, nhà phố...).
- **Bài mẫu để test:** https://tpiland.com/the-metropolis-sonkim-land/
- **Platform:** WordPress + **Elementor Pro** (KHÔNG dùng Gutenberg thuần).
- **AI provider mặc định:** Gemini Flash (`GOOGLE_API_KEY`).
- **Môi trường dev:** Docker `wp-env`.

## 3. Mẫu output (tham khảo Vinpearl)

Layout hộp tham khảo: https://vinpearl.com/vi/tron-bo-kinh-nghiem-du-lich-phu-quoc-tu-a-z

Cấu trúc hộp:

```
┌─ [icon] Sổ tay du lịch ──────────────────────┐
│                                                │
│  ❓ Tóm tắt nhanh cho [chủ đề bài viết]:      │
│                                                │
│  • Nhãn 1: nội dung ngắn gọn                  │
│  • Nhãn 2: nội dung ngắn gọn                  │
│  • Nhãn 3: nội dung ngắn gọn                  │
│  • Nhãn 4: nội dung ngắn gọn                  │
│  • Nhãn 5: nội dung ngắn gọn                  │
│                                                │
│  (*) Lưu ý: [thông tin quan trọng]             │
│                                                │
│  ❓ [Call-to-action cuộn xuống xem chi tiết]  │
└────────────────────────────────────────────────┘
```

Đặc tả thị giác:
- Viền bo tròn, màu cam nhạt (#FFF6E5) nền, viền cam (#F4A300).
- Nhãn header màu cam đậm trên nền vàng, có icon ngôi nhà (vì là BĐS).
- Header text: **"Sổ tay bất động sản"** (thay vì "Sổ tay du lịch").
- Nhãn bullet **in đậm**, theo sau là dấu `:` rồi nội dung.
- Font: kế thừa theme, size ~16px.
- Responsive: full width trên mobile, max-width 800px trên desktop.

**Bullets điển hình cho bài BĐS:**
- Vị trí, Chủ đầu tư, Quy mô, Loại hình, Giá tham khảo, Tiện ích, Pháp lý, Tiến độ.
- AI tự chọn 5–7 trường phù hợp với từng bài.

## 3. Tính năng bắt buộc (MVP)

### 3.1. Sinh tóm tắt bằng AI
- Tích hợp **Claude API** (mặc định, dùng `claude-haiku-4-5-20251001` cho rẻ + nhanh).
- Hỗ trợ thêm OpenAI và Gemini làm provider phụ (cấu hình trong settings).
- Prompt sinh ra **JSON cấu trúc** có các trường:
  ```json
  {
    "title": "Tóm tắt nhanh cho [chủ đề]",
    "bullets": [
      {"label": "Di chuyển", "content": "..."},
      {"label": "Thời điểm đẹp nhất", "content": "..."}
    ],
    "note": "Lưu ý: ...",
    "cta": "Cuộn xuống để..."
  }
  ```
- Lưu vào `post_meta` key `_aeo_summary_json`.

### 3.2. Editor UI
- Thêm **metabox** trong màn hình edit post (Classic + Gutenberg block).
- Nút **"Tạo tóm tắt bằng AI"** → gọi REST API → trả về JSON → hiển thị form chỉnh sửa.
- Cho phép thêm/xoá/sửa bullets, label, note, cta.
- Nút **"Lưu"** ghi đè `_aeo_summary_json`.

### 3.3. Render frontend (QUAN TRỌNG — Elementor Pro)

Vì site dùng **Elementor Pro**, không chỉ dựa vào hook `the_content`. Dùng 3 cơ chế song song:

1. **Elementor widget riêng** `AEO Summary Box`
   - Đăng ký qua `elementor/widgets/register`.
   - Kéo-thả vào template/post bất kỳ, tự đọc `_aeo_summary_json` của post hiện tại.
   - **Đây là cách khuyến nghị** cho user — chính xác nhất về vị trí.

2. **Shortcode** `[aeo_summary]`
   - Để chèn trong Text Editor widget của Elementor hoặc Classic editor.

3. **Auto-insert mặc định (JS-based)**
   - Enqueue 1 script frontend nhỏ, chạy sau `DOMContentLoaded`.
   - Logic chèn:
     1. Tìm Elementor TOC widget: `.elementor-widget-table-of-contents` → chèn hộp **ngay sau** widget này.
     2. Nếu không có TOC, tìm sapo: đoạn `<p>` hoặc `.elementor-widget-text-editor` đầu tiên có `<strong>` hoặc nằm trước H2 đầu → chèn **sau** sapo.
     3. Nếu không phát hiện được, fallback: chèn sau heading H1 của bài.
   - Có thể tắt auto-insert trong settings.
   - HTML hộp được nhúng sẵn vào trang qua wp_footer dưới dạng `<template id="aeo-summary-template">`, JS clone và chèn → tránh FOUC.

**Lý do dùng JS thay vì PHP filter:** Elementor render qua template builder, `the_content` không chứa TOC widget. JS-based insertion là cách ổn định nhất trên mọi Elementor template.

### 3.4. Schema.org cho AEO/GEO
Inject JSON-LD vào `<head>`:
- `Article` schema với `description` = TL;DR của tóm tắt.
- `FAQPage` schema: mỗi bullet → 1 cặp Question/Answer (label làm question, content làm answer).
- `speakable` specification để Google Assistant đọc.

### 3.5. Settings page
- Path: Settings → AEO Summary.
- Fields: API provider, API key, model name, max tokens, language (mặc định `vi`), số bullets (4–7), auto-insert vị trí, post types áp dụng.
- Prompt template có thể chỉnh (advanced).

## 4. Kiến trúc thư mục

```
aeo-summary-box/
├── aeo-summary-box.php          # Plugin header, bootstrap
├── includes/
│   ├── class-plugin.php         # Singleton, init hooks
│   ├── class-ai-client.php      # Wrapper cho Claude/OpenAI/Gemini
│   ├── class-metabox.php        # Editor metabox
│   ├── class-renderer.php       # Frontend output + the_content filter
│   ├── class-schema.php         # JSON-LD injection
│   ├── class-rest-api.php       # /aeo-summary/v1/generate endpoint
│   └── class-settings.php       # Settings page
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css         # Style hộp tóm tắt
│   ├── js/
│   │   ├── admin.js             # Metabox JS, gọi REST
│   │   └── block.js             # Gutenberg block (optional)
│   └── icons/
├── templates/
│   └── summary-box.php          # Markup hộp tóm tắt
├── languages/
├── readme.txt                    # Format WordPress.org
└── uninstall.php
```

## 5. Prompt mẫu cho AI (BĐS) — lưu trong `class-ai-client.php`

```
Bạn là biên tập viên SEO chuyên ngành bất động sản. Đọc bài viết về dự án/sản phẩm BĐS sau và tạo tóm tắt tối ưu cho AI search (Google SGE, ChatGPT, Perplexity, Gemini).

Yêu cầu:
- Trả về JSON đúng schema, KHÔNG kèm markdown fence, KHÔNG giải thích.
- 5–7 bullets, ưu tiên các trường: Vị trí, Chủ đầu tư, Quy mô, Loại hình, Giá tham khảo, Tiện ích nổi bật, Pháp lý, Tiến độ bàn giao. Bỏ qua trường không có thông tin.
- Mỗi bullet: label ngắn (2–4 từ), content 1 câu súc tích (≤25 từ), KHÔNG cường điệu, KHÔNG dùng từ "đẳng cấp/hoàn hảo/tuyệt vời".
- Note: 1 câu lưu ý quan trọng (vd: cần xác minh pháp lý, giá có thể thay đổi…).
- CTA: 1 câu mời cuộn xuống xem thông tin chi tiết.
- Title: "Tóm tắt nhanh về [tên dự án]".
- Ngôn ngữ: {language} (mặc định vi).

Schema:
{"title": "...", "bullets": [{"label": "...", "content": "..."}], "note": "...", "cta": "..."}

Tiêu đề bài: {post_title}
Nội dung bài:
{post_content}
```

## 6. Quy tắc code

- **PHP 8.0+**, tuân thủ WordPress Coding Standards (WPCS).
- Mọi input phải `sanitize_*`, output phải `esc_*`.
- Nonce + capability check (`edit_post`) cho mọi REST endpoint và form admin.
- API key lưu trong `wp_options` (KHÔNG hard-code), encrypt nếu có thể.
- Không gọi AI ở frontend — chỉ ở admin hoặc cron.
- I18n: dùng `__()`, `_e()` với text domain `aeo-summary-box`.
- Không thêm dependency Composer cho MVP — dùng `wp_remote_post` thuần.
- Không tạo file Markdown phụ ngoài `readme.txt` và `CLAUDE.md` này.

## 7. Test plan

- Test với 3 loại bài: hướng dẫn du lịch, review sản phẩm, tin tức ngắn.
- Kiểm tra schema bằng Google Rich Results Test.
- Kiểm tra hộp render đúng trên mobile (≤375px) và desktop.
- Test fallback khi AI fail (hiển thị thông báo trong metabox, không crash frontend).

## 8. Giao tiếp với người dùng

- Người dùng nói tiếng Việt. Trả lời tiếng Việt, ngắn gọn.
- Trước khi code mỗi phase, xác nhận với người dùng nếu có quyết định kiến trúc lớn.
- Không tự ý cài WordPress local — hỏi người dùng đã có môi trường chưa.
