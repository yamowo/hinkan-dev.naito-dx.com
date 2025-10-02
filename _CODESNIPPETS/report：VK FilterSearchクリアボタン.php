<?php
/**
 * VK FilterSearch：クリアボタン（.-vk-clear a）で入力値・選択値を全リセット
 * - クリック対象のボタンに最も近いウィジェット/ブロック内の VKFS フォームのみを対象
 * - hidden や operator 系 hidden は維持
 */
add_action('wp_footer', function () { ?>
<script>
(() => {
  'use strict';

  // クリア対象の入力タイプ（hidden, submit, button などは除外）
  const CLEARABLE_INPUT_TYPES = new Set([
    'text', 'search', 'email', 'url', 'tel', 'number', 'password',
    'date', 'month', 'week', 'time', 'datetime-local', 'color'
  ]);

  function clearForm(form) {
    if (!form) return;

    // すべてのフォーム要素を走査
    const elements = form.querySelectorAll('input, select, textarea');

    elements.forEach(el => {
      if (el.disabled) return; // 無効は対象外

      const tag = el.tagName.toLowerCase();

      if (tag === 'input') {
        const type = (el.getAttribute('type') || 'text').toLowerCase();

        // hidden は設定用途のため残す（vkfs_post_type[], vkfs_form_id, *_operator など）
        if (type === 'hidden') return;

        if (type === 'checkbox' || type === 'radio') {
          if (el.checked) {
            el.checked = false;
            el.dispatchEvent(new Event('change', { bubbles: true }));
          }
          return;
        }

        if (CLEARABLE_INPUT_TYPES.has(type)) {
          if (el.value !== '') {
            el.value = '';
            el.dispatchEvent(new Event('input',  { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
          }
          return;
        }

        // その他の input は様子見（range 等は value空でOK）
        try {
          el.value = '';
          el.dispatchEvent(new Event('change', { bubbles: true }));
        } catch(_) {}
        return;
      }

      if (tag === 'textarea') {
        if (el.value !== '') {
          el.value = '';
          el.dispatchEvent(new Event('input',  { bubbles: true }));
          el.dispatchEvent(new Event('change', { bubbles: true }));
        }
        return;
      }

      if (tag === 'select') {
        if (el.multiple) {
          let changed = false;
          [...el.options].forEach(opt => { if (opt.selected) { opt.selected = false; changed = true; } });
          if (changed) el.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
          // 先頭に空optionがある前提で index=0、無い場合は未選択化（-1）
          const newIndex = (el.options.length && el.options[0].value === '') ? 0 : -1;
          if (el.selectedIndex !== newIndex) {
            el.selectedIndex = newIndex;
            el.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }
        return;
      }
    });
  }

  // 「同じボックス内の VKFS フォーム」を見つける
  function findTargetForm(fromButton) {
    if (!fromButton) return null;

    // 近い範囲で探す：まず .vkfs__call-filter-search（VKFSのラッパ）内
    const container =
      fromButton.closest('.vkfs__call-filter-search') ||
      fromButton.closest('.widget, .block-editor-block-list__block, .p-blogParts') ||
      document;

    // VK FilterSearch のフォームセレクタ（念のため幅広く）
    return container.querySelector('form.vk-filter-search, form.vkfs, form.wp-block-vk-filter-search-pro-filter-search-pro');
  }

  function onClearClick(ev) {
    const a = ev.currentTarget;
    ev.preventDefault();

    const form = findTargetForm(a);
    if (!form) return;

    clearForm(form);

    // ★自動送信したい場合はコメントアウト解除
    // form.submit();
  }

  function bind() {
    // クリアボタンが複数あってもOK
    document.querySelectorAll('.-vk-clear a').forEach(a => {
      a.addEventListener('click', onClearClick, { passive: false });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind, { once: true });
  } else {
    bind();
  }
})();
</script>
<?php }, 99);
