<?php
/**
 * VK FilterSearch アコーディオン初期状態＆自動再判定
 * - 上から2つは常に OPEN
 * - それ以降は、子フィールドに値/選択が1つでもあれば OPEN、なければ CLOSE
 * - 入力変更時や「.-vk-clear a」クリック後にも再計算
 */
add_action('wp_footer', function () { ?>
<script>
(() => {
  'use strict';

  /* ===== 設定 ===== */
  const OPEN_FIRST_N = 1; // 常に開いておきたい数

  const SELECTOR_WRAPPER = '.vkfs__label-accordion-outer-wrap';
  const SELECTOR_TRIGGER = '.vkfs__label-accordion-trigger';
  const SELECTOR_CONTENT = '.vkfs__label-accordion-content';

  function setState(wrap, isOpen) {
    if (!wrap) return;
    const trig = wrap.querySelector(SELECTOR_TRIGGER);
    const cont = wrap.querySelector(SELECTOR_CONTENT);

    if (trig) {
      trig.classList.remove('vkfs__label-accordion-trigger--open','vkfs__label-accordion-trigger--close');
      trig.classList.add(isOpen ? 'vkfs__label-accordion-trigger--open' : 'vkfs__label-accordion-trigger--close');
      trig.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }
    if (cont) {
      cont.classList.remove('vkfs__label-accordion-content--open','vkfs__label-accordion-content--close');
      cont.classList.add(isOpen ? 'vkfs__label-accordion-content--open' : 'vkfs__label-accordion-content--close');
      cont.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
    }
  }

  // ラッパ内に「選択/入力済み」のフィールドがあるか？
  function hasActiveValue(wrap) {
    if (!wrap) return false;
    const els = wrap.querySelectorAll('input, select, textarea');

    for (const el of els) {
      if (!el || el.disabled) continue;

      const tag  = el.tagName.toLowerCase();
      const type = (el.getAttribute('type') || '').toLowerCase();

      // 設定用hiddenは無視（vkfs_post_type[], vkfs_form_id, *_operator 等）
      if (tag === 'input' && (type === 'hidden' || type === 'submit' || type === 'button' || type === 'image' || type === 'reset')) {
        continue;
      }

      if (tag === 'input') {
        if (type === 'checkbox' || type === 'radio') {
          if (el.checked) return true;
        } else {
          if (String(el.value ?? '').trim() !== '') return true;
        }
        continue;
      }

      if (tag === 'textarea') {
        if (String(el.value ?? '').trim() !== '') return true;
        continue;
      }

      if (tag === 'select') {
        if (el.multiple) {
          for (const opt of el.options) {
            if (opt.selected && (opt.value !== '' || opt.text.trim() !== '')) return true;
          }
        } else {
          const val = el.value;
          if (val !== '' && val != null) return true;
        }
        continue;
      }
    }
    return false;
  }

  // すべてのラッパで開閉を再計算
  function recalcAll() {
    const wraps = document.querySelectorAll(SELECTOR_WRAPPER);
    if (!wraps.length) return;
    wraps.forEach((wrap, idx) => {
      const open = (idx < OPEN_FIRST_N) || hasActiveValue(wrap);
      setState(wrap, open);
    });
  }

  // “すべてクリア”の実装（.-vk-clear a をクリック→近傍のVKFSフォームをクリア）
  function bindClearButtons() {
    document.querySelectorAll('.-vk-clear a').forEach(a => {
      a.addEventListener('click', (ev) => {
        ev.preventDefault();

        const container =
          a.closest('.vkfs__call-filter-search') ||
          a.closest('.widget, .p-blogParts, .block-editor-block-list__block') ||
          document;

        const form = container.querySelector('form.vk-filter-search, form.vkfs, form.wp-block-vk-filter-search-pro-filter-search-pro');
        if (form) {
          const nodes = form.querySelectorAll('input, select, textarea');
          nodes.forEach(el => {
            if (!el || el.disabled) return;

            const tag  = el.tagName.toLowerCase();
            const type = (el.getAttribute('type') || '').toLowerCase();

            // hidden/submit系はそのまま
            if (tag === 'input' && (type === 'hidden' || type === 'submit' || type === 'button' || type === 'image' || type === 'reset')) {
              return;
            }

            if (tag === 'input') {
              if (type === 'checkbox' || type === 'radio') {
                if (el.checked) {
                  el.checked = false;
                  el.dispatchEvent(new Event('change', { bubbles: true }));
                }
              } else if (el.value !== '') {
                el.value = '';
                el.dispatchEvent(new Event('input',  { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
              }
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
                for (const opt of el.options) {
                  if (opt.selected) { opt.selected = false; changed = true; }
                }
                if (changed) el.dispatchEvent(new Event('change', { bubbles: true }));
              } else {
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

        // クリア後に開閉を再判定
        recalcAll();

        // 必要であれば自動送信も可能（コメント解除）
        // if (form) form.submit();
      }, { passive: false });
    });
  }

  // 入力の変更でも随時再判定（負荷を抑えるためrAFでスロットル）
  let raf = null;
  function scheduleRecalc() {
    if (raf) return;
    raf = requestAnimationFrame(() => { raf = null; recalcAll(); });
  }
  function bindLiveRecalc() {
    document.addEventListener('input',  (e) => { if (e.target.closest(SELECTOR_WRAPPER)) scheduleRecalc(); });
    document.addEventListener('change', (e) => { if (e.target.closest(SELECTOR_WRAPPER)) scheduleRecalc(); });
  }

  function init() {
    recalcAll();
    bindClearButtons();
    bindLiveRecalc();
  }

  if (document.readyState === 'complete') init();
  else window.addEventListener('load', init, { once: true });
})();
</script>
<?php }, 99);
