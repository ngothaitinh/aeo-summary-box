/* AEO Summary Box – Bulk progress bar */
/* global aeoBulk, jQuery */
(function ($) {
  'use strict';

  var $bar      = null;
  var pollTimer = null;
  var isDone    = false;

  // ── Tạo floating bar ─────────────────────────────────────────────────────

  function createBar() {
    $bar = $([
      '<div id="aeo-sb-bulk-bar" style="',
        'position:fixed;bottom:24px;right:24px;z-index:99999;',
        'background:#fff;border:1px solid #c3c4c7;border-radius:8px;',
        'padding:14px 16px 12px;min-width:280px;max-width:340px;',
        'box-shadow:0 4px 12px rgba(0,0,0,.12);font-size:13px;',
        'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;',
      '">',
        '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">',
          '<span style="font-weight:600;color:#1d2327;">⚙️ AEO Bulk đang chạy</span>',
          '<a href="#" id="aeo-sb-bulk-hide" style="color:#a7aaad;font-size:18px;text-decoration:none;line-height:1;" title="Ẩn">×</a>',
        '</div>',
        '<div style="background:#f0f0f1;border-radius:4px;height:6px;overflow:hidden;margin-bottom:8px;">',
          '<div id="aeo-sb-bulk-fill" style="height:100%;background:#2271b1;width:0%;transition:width .5s ease;"></div>',
        '</div>',
        '<div id="aeo-sb-bulk-info" style="color:#646970;font-size:12px;line-height:1.5;"></div>',
      '</div>'
    ].join('')).appendTo('body');

    $('#aeo-sb-bulk-hide').on('click', function (e) {
      e.preventDefault();
      $bar.hide();
    });
  }

  // ── Cập nhật bar ─────────────────────────────────────────────────────────

  function updateBar(data) {
    if (!$bar || !$bar.length) createBar();
    $bar.show();

    var s      = data.status || {};
    var total  = s.total  || 1;
    var done   = s.done   || 0;
    var remain = data.remaining || 0;
    var pct    = total > 0 ? Math.round((done / total) * 100) : 0;

    $('#aeo-sb-bulk-fill').css('width', pct + '%');

    var info = '';

    if (done === 0 && !s.current) {
      // Cron đã được schedule nhưng chưa kích hoạt lần đầu
      info += '⏳ Đang khởi động — chờ xử lý bài đầu tiên...';
      info += '<br><span style="color:#aaa;font-size:11px;">0 / ' + total + ' bài</span>';
    } else {
      info += 'Đã xử lý: <strong>' + done + ' / ' + total + '</strong>';
      if (pct > 0) info += ' (' + pct + '%)';
      if (s.current) {
        info += '<br>📄 ' + escHtml(s.current);
      }
      if (remain > 0) {
        info += '<br><span style="color:#aaa;font-size:11px;">Còn lại: ' + remain + ' bài trong hàng đợi</span>';
      }
    }

    $('#aeo-sb-bulk-info').html(info);
  }

  function showDone(data) {
    if (!$bar || !$bar.length) createBar();
    $bar.show();

    var s     = data.status || {};
    var total = s.total || 0;

    $('#aeo-sb-bulk-fill').css('width', '100%').css('background', '#00a32a');
    $('#aeo-sb-bulk-info').html('✅ Hoàn tất <strong>' + total + '</strong> bài. <a href="" style="color:#2271b1;">Làm mới trang</a> để xem kết quả.');
    $('a', $('#aeo-sb-bulk-info')).on('click', function (e) {
      e.preventDefault();
      window.location.reload();
    });
    $bar.find('span:first').text('⚙️ AEO Bulk xong!');

    setTimeout(function () { $bar.fadeOut(800); }, 6000);
  }

  // ── Polling ───────────────────────────────────────────────────────────────

  function poll() {
    $.ajax({
      url: aeoBulk.restBase + '/bulk-status',
      method: 'GET',
      beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', aeoBulk.nonce); },
    }).done(function (data) {
      if (!data || !data.status) return;

      var s = data.status;

      if (s.running || data.remaining > 0) {
        updateBar(data);
        // Poll nhanh hơn khi chưa có bài nào được xử lý (chờ cron khởi động).
        var interval = (s.done === 0 && !s.current) ? 2000 : 3000;
        pollTimer = setTimeout(poll, interval);
      } else if (s.done > 0 && !isDone) {
        isDone = true;
        showDone(data);
      }
    }).fail(function () {
      // Lỗi mạng — thử lại sau 5s
      pollTimer = setTimeout(poll, 5000);
    });
  }

  function escHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  // ── Khởi động ─────────────────────────────────────────────────────────────

  $(function () {
    // Poll ngay khi trang load — kiểm tra job đang chạy không.
    poll();

    // Cũng kích hoạt poll khi user submit bulk action.
    $(document).on('click', '[name="doaction"], [name="doaction2"]', function () {
      var action = $('[name="action"]').val() || $('[name="action2"]').val();
      if (action === 'aeo_sb_generate') {
        isDone = false;
        clearTimeout(pollTimer);
        pollTimer = setTimeout(poll, 4000); // chờ redirect xong mới poll
      }
    });
  });

}(jQuery));
