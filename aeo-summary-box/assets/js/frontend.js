/* AEO Summary Box – Frontend auto-insert + mobile collapse + GA4 tracking */
/* global aeoSbFront, gtag, dataLayer */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var cfg  = window.aeoSbFront || {};
    var tmpl = document.getElementById('aeo-summary-template');

    // Chèn từ <template> — chỉ có trên bài Elementor. Bài thường đã được
    // render server-side qua the_content nên không in <template> này.
    if (tmpl) {
      insertFromTemplate(tmpl, cfg);
    }

    // Thu gọn mobile chạy cho MỌI hộp tóm tắt trên trang, bất kể hộp được
    // chèn bằng JS hay đã render sẵn server-side.
    if (cfg.compactMobile !== false) {
      initCollapse(cfg);
    }

    // GA4 tracking khởi tạo sau cùng (sau khi hộp đã được chèn vào DOM).
    initTracking(cfg);
  });

  // ── Auto-insert từ <template> ───────────────────────────────────────────────

  function insertFromTemplate(tmpl, cfg) {
    var position = cfg.position || 'after_toc';
    // Trên mobile, Elementor thường đảo thứ tự cột khi responsive nên TOC
    // có thể bị đẩy xuống cuối trang. Bỏ qua bước tìm TOC trên mobile để
    // tránh chèn summary box SAU TOC nhưng TRƯỚC nội dung chính (che TOC).
    var isMobile = window.innerWidth <= 600;
    var inserted = false;

    // 1. Sau Elementor TOC widget (chỉ áp dụng trên desktop)
    if (!inserted && position === 'after_toc' && !isMobile) {
      var toc = document.querySelector(
        '.elementor-widget-table-of-contents, [data-widget_type="table-of-contents.default"]'
      );
      if (toc) {
        var tocWidget = toc.closest('.elementor-widget') || toc;
        tocWidget.insertAdjacentElement('afterend', cloneBox(tmpl));
        inserted = true;
      }
    }

    // 2. Sau sapo — đoạn text-editor đầu tiên có nội dung, nằm trước H2
    if (!inserted && (position === 'after_toc' || position === 'after_sapo')) {
      var h2 = document.querySelector('.entry-content h2, .elementor-widget-theme-post-content h2');
      var textWidgets = document.querySelectorAll(
        '.elementor-widget-text-editor, .entry-content > p, .elementor-widget-theme-post-content > .elementor-widget-container > p'
      );

      for (var i = 0; i < textWidgets.length; i++) {
        var el = textWidgets[i];
        if (el.textContent.trim().length < 30) continue;
        if (!h2 || el.compareDocumentPosition(h2) & Node.DOCUMENT_POSITION_FOLLOWING) {
          var sapoWidget = el.closest('.elementor-widget') || el;
          sapoWidget.insertAdjacentElement('afterend', cloneBox(tmpl));
          inserted = true;
          break;
        }
      }
    }

    // 3. Fallback: sau H1
    if (!inserted) {
      var h1 = document.querySelector('h1.entry-title, h1.elementor-heading-title, .elementor-widget-theme-post-title h1');
      if (h1) {
        var h1Widget = h1.closest('.elementor-widget') || h1;
        h1Widget.insertAdjacentElement('afterend', cloneBox(tmpl));
        inserted = true;
      }
    }

    // 4. Fallback cuối: đầu .entry-content
    if (!inserted) {
      var content = document.querySelector('.entry-content, .elementor-widget-theme-post-content');
      if (content) {
        content.insertAdjacentElement('afterbegin', cloneBox(tmpl));
      }
    }
  }

  // ── Clone helper ───────────────────────────────────────────────────────────

  function cloneBox(tmpl) {
    var wrapper = document.createElement('div');
    wrapper.appendChild(tmpl.content.cloneNode(true));
    return wrapper;
  }

  // ── Mobile Collapse ────────────────────────────────────────────────────────

  function initCollapse(cfg) {
    // Chỉ áp dụng trên mobile (≤600px)
    if (window.innerWidth > 600) return;

    var boxes = document.querySelectorAll('.aeo-sb-box');
    boxes.forEach(function (box) {
      var bulletCount = parseInt(box.dataset.bullets || '0', 10);
      if (bulletCount <= 3) return; // Không cần collapse nếu ≤3 bullets

      // Đánh dấu hộp là compact
      box.setAttribute('data-compact', '');

      var toggle = box.querySelector('.aeo-sb-toggle');
      if (!toggle) return;

      // Hiện nút toggle (mặc định hidden trong HTML)
      toggle.removeAttribute('hidden');
      toggle.style.display = 'block';

      // Khôi phục trạng thái từ sessionStorage
      var storageKey = 'aeo-sb-expanded-' + ((cfg && cfg.postId) || '0');
      if (sessionStorage.getItem(storageKey) === '1') {
        expandBox(box, toggle);
      }

      // Xử lý click toggle
      toggle.addEventListener('click', function () {
        var isExpanded = box.classList.contains('aeo-sb-expanded');
        if (isExpanded) {
          collapseBox(box, toggle, cfg);
          sessionStorage.removeItem(storageKey);
        } else {
          expandBox(box, toggle, cfg);
          sessionStorage.setItem(storageKey, '1');
        }
      });
    });
  }

  function expandBox(box, toggle, cfg) {
    box.classList.add('aeo-sb-expanded');
    toggle.setAttribute('aria-expanded', 'true');
    trackEvent('aeo_summary_expand', { post_id: cfg && cfg.postId });
  }

  function collapseBox(box, toggle, cfg) {
    box.classList.remove('aeo-sb-expanded');
    toggle.setAttribute('aria-expanded', 'false');
    // Scroll nhẹ về đầu hộp khi thu gọn
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    trackEvent('aeo_summary_collapse', { post_id: cfg && cfg.postId });
  }

  // ── GA4 Tracking ───────────────────────────────────────────────────────────

  /**
   * Gửi event lên GA4 qua gtag() hoặc GTM dataLayer.
   * Không làm gì nếu cả hai đều chưa được cài.
   *
   * @param {string} eventName  Tên event GA4 (snake_case).
   * @param {Object} params     Các tham số bổ sung gửi kèm.
   */
  function trackEvent(eventName, params) {
    var payload = Object.assign(
      { event_category: 'AEO Summary Box' },
      params || {}
    );

    // Ưu tiên gtag (GA4 trực tiếp hoặc qua GTM tag GA4)
    if (typeof window.gtag === 'function') {
      window.gtag('event', eventName, payload);
      return;
    }

    // Fallback: GTM dataLayer
    if (Array.isArray(window.dataLayer)) {
      window.dataLayer.push(Object.assign({ event: eventName }, payload));
    }
  }

  /**
   * Khởi tạo toàn bộ tracking cho các hộp tóm tắt trên trang:
   *  - aeo_summary_view   : hộp xuất hiện ≥50% trong viewport (1 lần/trang).
   *  - aeo_summary_cta_click : nhấn CTA ở cuối hộp.
   */
  function initTracking(cfg) {
    var boxes = document.querySelectorAll('.aeo-sb-box');
    if (!boxes.length) return;

    var postId    = (cfg && cfg.postId) || 0;
    var pageTitle = document.title || '';

    // ── 1. Impression: dùng IntersectionObserver ──────────────────────────
    if ('IntersectionObserver' in window) {
      var viewObserver = new IntersectionObserver(
        function (entries, observer) {
          entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            trackEvent('aeo_summary_view', {
              post_id    : postId,
              page_title : pageTitle,
            });
            // Chỉ track 1 lần mỗi hộp
            observer.unobserve(entry.target);
          });
        },
        { threshold: 0.5 } // ≥50% hộp hiển thị mới tính là "viewed"
      );

      boxes.forEach(function (box) {
        viewObserver.observe(box);
      });
    }

    // ── 2. CTA click ──────────────────────────────────────────────────────
    boxes.forEach(function (box) {
      var cta = box.querySelector('.aeo-sb-cta');
      if (!cta) return;

      cta.addEventListener('click', function () {
        trackEvent('aeo_summary_cta_click', {
          post_id    : postId,
          page_title : pageTitle,
          cta_text   : (cta.textContent || '').trim().slice(0, 100),
        });
      });
    });

    // Lưu ý: expand/collapse được track bên trong expandBox() / collapseBox()
    // khi initCollapse() chạy — không cần bind lại ở đây.
  }

}());
