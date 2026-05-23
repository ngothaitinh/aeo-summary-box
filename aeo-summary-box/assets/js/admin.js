/* AEO Summary Box – Admin metabox JS */
/* global aeoSb, jQuery */
(function ($) {
  'use strict';

  const postId   = aeoSb.postId;
  const restBase = aeoSb.restBase;
  const nonce    = aeoSb.nonce;
  const provider = aeoSb.provider || 'AI';

  // ── Helpers ──────────────────────────────────────────────────────────────

  function showStatus(msg, type) {
    $('#aeo-sb-status')
      .removeClass('success error loading')
      .addClass(type)
      .html(msg)
      .show();
  }

  function collectSummary() {
    const bullets = [];
    $('#aeo-sb-bullets .aeo-sb-bullet-row').each(function () {
      const label    = $(this).find('.aeo-sb-label').val().trim();
      const question = $(this).find('.aeo-sb-question').val().trim();
      const content  = $(this).find('.aeo-sb-content').val().trim();
      if (label || content) {
        bullets.push({ label, question, content });
      }
    });

    return {
      intent:  $('#aeo-sb-intent').val()  || 'hybrid',
      persona: $('#aeo-sb-persona').val() || '',
      title:   $('#aeo-sb-title').val().trim(),
      tldr:    $('#aeo-sb-tldr').val().trim(),
      bullets,
      note:    $('#aeo-sb-note').val().trim(),
      cta:     $('#aeo-sb-cta').val().trim(),
      contact: {
        org_name: $('#aeo-sb-contact-org-name').val().trim(),
        address:  $('#aeo-sb-contact-address').val().trim(),
        phone:    $('#aeo-sb-contact-phone').val().trim(),
        hours:    $('#aeo-sb-contact-hours').val().trim(),
      },
    };
  }

  function updateGoContactVisibility() {
    const intent = $('#aeo-sb-intent').val();
    $('#aeo-sb-go-contact').toggle(intent === 'go');
  }

  function populateEditor(summary) {
    $('#aeo-sb-intent').val(summary.intent || 'hybrid');
    // Persona: restore nếu có trong summary (saved), jangan reset jika AI response tidak ada.
    if (summary.persona !== undefined) {
      $('#aeo-sb-persona').val(summary.persona || '');
    }
    $('#aeo-sb-title').val(summary.title || '');
    $('#aeo-sb-tldr').val(summary.tldr || '');
    $('#aeo-sb-note').val(summary.note || '');
    $('#aeo-sb-cta').val(summary.cta || '');

    // Contact fields (GO intent per-post override)
    const contact = summary.contact || {};
    $('#aeo-sb-contact-org-name').val(contact.org_name || '');
    $('#aeo-sb-contact-address').val(contact.address  || '');
    $('#aeo-sb-contact-phone').val(contact.phone    || '');
    $('#aeo-sb-contact-hours').val(contact.hours    || '');

    const $bullets = $('#aeo-sb-bullets').empty();
    (summary.bullets || []).forEach(function (b) {
      $bullets.append(makeBulletRow(b.label, b.content, b.question));
    });

    $('#aeo-sb-editor').show();
    updateTldrCount();
    updateGoContactVisibility();
    updatePreview();
  }

  function makeBulletRow(label, content, question) {
    return $('<div class="aeo-sb-bullet-row">')
      .append(
        $('<input type="text" class="aeo-sb-label">').attr('placeholder', 'Nhãn (vd: Vị trí)').val(label || ''),
        $('<input type="text" class="aeo-sb-question large-text">').attr('placeholder', 'Câu hỏi người dùng (cho FAQ schema)').val(question || ''),
        $('<input type="text" class="aeo-sb-content large-text">').attr('placeholder', 'Nội dung ngắn').val(content || ''),
        $('<button type="button" class="aeo-sb-remove-bullet button-link" title="Xoá">').text('✕')
      );
  }

  function updatePreview() {
    const summary = collectSummary();
    if (!summary.bullets.length) return;

    const tldr = summary.tldr
      ? `<p class="aeo-sb-tldr">${escHtml(summary.tldr)}</p>`
      : '';
    const bullets = summary.bullets
      .map(b => `<li class="aeo-sb-bullet"><strong class="aeo-sb-label">${escHtml(b.label)}:</strong> <span class="aeo-sb-content">${escHtml(b.content)}</span></li>`)
      .join('');
    const note = summary.note
      ? `<p class="aeo-sb-note"><em>(*) ${escHtml(summary.note)}</em></p>`
      : '';
    const cta = summary.cta
      ? `<p class="aeo-sb-cta"><span class="aeo-sb-cta-arrow">👇</span> ${escHtml(summary.cta)}</p>`
      : '';

    $('#aeo-sb-preview').html(`
      <div class="aeo-sb-box">
        <div class="aeo-sb-header">
          <span class="aeo-sb-header-icon">🏠</span>
          <span class="aeo-sb-header-text">Sổ tay bất động sản</span>
        </div>
        <div class="aeo-sb-body">
          ${tldr}
          <p class="aeo-sb-title"><strong>${escHtml(summary.title)}</strong></p>
          <ul class="aeo-sb-bullets">${bullets}</ul>
          ${note}
          ${cta}
        </div>
      </div>
    `);
  }

  function updateTldrCount() {
    const val = $('#aeo-sb-tldr').val() || '';
    const len = val.length;
    const color = len > 120 ? '#d32f2f' : len > 100 ? '#f57c00' : '#2e7d32';
    $('#aeo-sb-tldr-count').css('color', color).text(len + '/120 ' + (aeoSb.i18n.chars || 'ký tự'));
  }

  function showTokenInfo(tokens) {
    if (!tokens || !tokens.total) return;
    const html = `🔢 <strong>${tokens.input}</strong> in + <strong>${tokens.output}</strong> out tokens (${escHtml(tokens.provider || provider)})`;
    $('.aeo-sb-tokens').html(html).show();
  }

  // Format unix timestamp → "dd/mm HH:MM"
  function formatTs(ts) {
    if (!ts) return '';
    const d = new Date(ts * 1000);
    const pad = n => String(n).padStart(2, '0');
    return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
  }

  // Inject nút Hoàn tác động vào DOM (khi chưa có sẵn — lần đầu lưu)
  function ensureRestoreBtn(prevTime) {
    if ($('#aeo-sb-restore').length) {
      $('#aeo-sb-restore').text('↩ Hoàn tác (bản ' + formatTs(prevTime) + ')').show().prop('disabled', false);
      return;
    }
    const $btn = $('<button type="button" id="aeo-sb-restore" class="button aeo-sb-restore-btn">')
      .text('↩ Hoàn tác (bản ' + formatTs(prevTime) + ')');
    $btn.insertAfter('#aeo-sb-save');
  }

  function escHtml(str) {
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // ── Events ───────────────────────────────────────────────────────────────

  // Tạo tóm tắt AI
  $('#aeo-sb-generate').on('click', function () {
    const $btn = $(this).prop('disabled', true).text('⏳ ' + aeoSb.i18n.generating);
    showStatus(aeoSb.i18n.generating, 'loading');

    $.ajax({
      url: restBase + '/generate/' + postId,
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({
        intent:  $('#aeo-sb-intent').val()  || 'hybrid',
        persona: $('#aeo-sb-persona').val() || '',
      }),
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', nonce);
      },
    })
      .done(function (res) {
        if (res.summary) {
          populateEditor(res.summary);
          showStatus('✅ Tóm tắt đã được tạo. Hãy kiểm tra và chỉnh sửa trước khi lưu.', 'success');
          showTokenInfo(res.tokens);
        }
      })
      .fail(function (xhr) {
        const msg = xhr.responseJSON?.error || 'Lỗi không xác định.';
        showStatus(aeoSb.i18n.error + msg, 'error');
      })
      .always(function () {
        $btn.prop('disabled', false).text('✨ Tạo tóm tắt bằng AI (' + provider + ')');
      });
  });

  // Lưu tóm tắt
  $('#aeo-sb-save').on('click', function () {
    const summary = collectSummary();
    if (!summary.bullets.length) {
      showStatus('Chưa có bullets để lưu.', 'error');
      return;
    }

    const $btn = $(this).prop('disabled', true).text('⏳ ' + aeoSb.i18n.saving);
    showStatus(aeoSb.i18n.saving, 'loading');

    $.ajax({
      url: restBase + '/save/' + postId,
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({ summary }),
      beforeSend: function (xhr) {
        xhr.setRequestHeader('X-WP-Nonce', nonce);
      },
    })
      .done(function (res) {
        showStatus('✅ ' + aeoSb.i18n.saved, 'success');
        $('.aeo-sb-indicator').removeClass('no-data has-data').addClass('has-data').text('✅ Đã có tóm tắt');
        // Sau khi lưu, inject / cập nhật nút Hoàn tác nếu có backup mới
        if (res && res.prev_time) {
          ensureRestoreBtn(res.prev_time);
        }
      })
      .fail(function (xhr) {
        const msg = xhr.responseJSON?.error || 'Lỗi không xác định.';
        showStatus(aeoSb.i18n.error + msg, 'error');
      })
      .always(function () {
        $btn.prop('disabled', false).text('💾 Lưu tóm tắt');
      });
  });

  // ── Diff helpers ──────────────────────────────────────────────────────────

  function buildDiffBody(summary, $el) {
    var html = '';
    // TL;DR
    html += '<div class="aeo-sb-diff-tldr">';
    html += '<strong>TL;DR:</strong> ' + escHtml(summary.tldr || '(trống)');
    html += '</div>';
    // Bullets
    (summary.bullets || []).forEach(function (b) {
      html += '<div class="aeo-sb-diff-bullet">• <strong>' + escHtml(b.label) + ':</strong> ' + escHtml(b.content) + '</div>';
    });
    // Note + CTA nhỏ
    if (summary.note) {
      html += '<div style="margin-top:6px;font-size:11px;color:#666;">📌 ' + escHtml(summary.note) + '</div>';
    }
    $el.html(html);
  }

  function markDiffChanges(currentSummary, backupSummary) {
    // Highlight tldr nếu khác
    var $curTldr = $('#aeo-sb-diff-current-body .aeo-sb-diff-tldr');
    var $bakTldr = $('#aeo-sb-diff-backup-body .aeo-sb-diff-tldr');
    if ((currentSummary.tldr || '') !== (backupSummary.tldr || '')) {
      $curTldr.addClass('changed');
      $bakTldr.addClass('changed');
    } else {
      $curTldr.addClass('same');
      $bakTldr.addClass('same');
    }
    // Highlight bullets thay đổi (so sánh theo label)
    var curLabels = (currentSummary.bullets || []).map(function(b){ return b.label; });
    var bakLabels = (backupSummary.bullets  || []).map(function(b){ return b.label; });
    // Bullets chỉ có trong current (sẽ mất sau restore)
    $('#aeo-sb-diff-current-body .aeo-sb-diff-bullet').each(function(i){
      var label = (currentSummary.bullets[i] || {}).label || '';
      if (!bakLabels.includes(label)) $(this).addClass('removed');
    });
    // Bullets chỉ có trong backup (sẽ xuất hiện sau restore)
    $('#aeo-sb-diff-backup-body .aeo-sb-diff-bullet').each(function(i){
      var label = (backupSummary.bullets[i] || {}).label || '';
      if (!curLabels.includes(label)) $(this).addClass('added');
    });
  }

  var pendingBackupData = null; // Backup data đang chờ confirm

  // Hoàn tác — bước 1: fetch backup rồi show diff
  $(document).on('click', '#aeo-sb-restore', function () {
    const $btn = $(this).prop('disabled', true);
    showStatus('🔍 Đang tải phiên bản backup...', 'loading');

    $.ajax({
      url: restBase + '/backup/' + postId,
      method: 'GET',
      beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', nonce); },
    })
      .done(function (res) {
        if (!res.summary) {
          showStatus((aeoSb.i18n.error || 'Lỗi: ') + 'Không có backup.', 'error');
          $btn.prop('disabled', false);
          return;
        }
        pendingBackupData = res;
        const currentSummary = collectSummary();
        // Điền 2 cột diff
        buildDiffBody(currentSummary,  $('#aeo-sb-diff-current-body'));
        buildDiffBody(res.summary,     $('#aeo-sb-diff-backup-body'));
        markDiffChanges(currentSummary, res.summary);
        // Cập nhật tiêu đề cột backup với thời gian
        if (res.saved_at) {
          $('#aeo-sb-diff-backup-head').text('🕐 Phiên bản backup (' + formatTs(res.saved_at) + ')');
        }
        $('#aeo-sb-diff').slideDown(200);
        $('#aeo-sb-diff-confirm, #aeo-sb-diff-cancel').show();
        $('#aeo-sb-status').hide();
        $btn.prop('disabled', false);
      })
      .fail(function (xhr) {
        const msg = xhr.responseJSON?.error || 'Lỗi không xác định.';
        showStatus((aeoSb.i18n.error || 'Lỗi: ') + msg, 'error');
        $btn.prop('disabled', false);
      });
  });

  // Hoàn tác — bước 2: user confirm
  $('#aeo-sb-diff-confirm').on('click', function () {
    const $btn = $(this).prop('disabled', true);
    showStatus(aeoSb.i18n.restoring || 'Đang hoàn tác...', 'loading');
    $('#aeo-sb-diff').slideUp(200);

    $.ajax({
      url: restBase + '/restore/' + postId,
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify({}),
      beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', nonce); },
    })
      .done(function (res) {
        if (res.summary) {
          populateEditor(res.summary);
          showStatus('✅ ' + (aeoSb.i18n.restored || 'Đã hoàn tác!'), 'success');
          pendingBackupData = null;
          if (res.has_prev) {
            ensureRestoreBtn(res.saved_at || Math.floor(Date.now() / 1000));
          } else {
            $('#aeo-sb-restore').hide();
          }
        }
        $btn.prop('disabled', false);
      })
      .fail(function (xhr) {
        const msg = xhr.responseJSON?.error || 'Lỗi không xác định.';
        showStatus((aeoSb.i18n.error || 'Lỗi: ') + msg, 'error');
        $btn.prop('disabled', false);
      });
  });

  // Huỷ diff
  $('#aeo-sb-diff-cancel').on('click', function () {
    pendingBackupData = null;
    $('#aeo-sb-diff').slideUp(200);
  });

  // Thêm bullet row
  $('#aeo-sb-add-bullet').on('click', function () {
    $('#aeo-sb-bullets').append(makeBulletRow('', ''));
  });

  // Xoá bullet row (delegated)
  $('#aeo-sb-bullets').on('click', '.aeo-sb-remove-bullet', function () {
    $(this).closest('.aeo-sb-bullet-row').remove();
    updatePreview();
  });

  // Live preview khi gõ
  $('#aeo-sb-metabox').on('input', 'input[type="text"]', function () {
    updatePreview();
  });

  // Toggle GO contact fields khi đổi intent
  $('#aeo-sb-intent').on('change', function () {
    updateGoContactVisibility();
    updatePreview();
  });

  // Character counter cho tldr
  $('#aeo-sb-tldr').on('input', updateTldrCount);

  // Khởi tạo: counter + GO contact visibility
  if ($('#aeo-sb-tldr').val()) {
    updateTldrCount();
  }
  updateGoContactVisibility();

}(jQuery));
