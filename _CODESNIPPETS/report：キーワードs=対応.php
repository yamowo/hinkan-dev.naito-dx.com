<?php
/**
 * VK FilterSearch のキーワード（s）を sessionStorage に保存し、
 * 詳細ページなど URL に s が無い時もサイドバーの入力欄へ復元する
 */
add_action('wp_footer', function () {
  // 管理画面やAMPでは出さない（必要に応じて調整）
  // if (is_admin()) return;

  // 必要なら読み込み条件を厳しめにする例：
  if (!(is_singular('report') || is_search() || is_post_type_archive('report'))) return;

  // ここからJSを出力
  ?>
  <script>
  (function () {
    // ---- 設定 ----
    const FORM_ID = '595';                        // VK FilterSearch のフォームID
    const STORAGE_KEY = `vkfs:s:${FORM_ID}`;      // 保存キー
    const INPUT_SELECTOR = 'form [name="s"]';     // キーワード<input>セレクタ（VKFS標準）

    // ---- ユーティリティ ----
    const getParam = (name, url) => {
      const u = url || window.location.href;
      name = name.replace(/[\\[\\]]/g, '\\$&');
      const regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)');
      const results = regex.exec(u);
      if (!results) return null;
      if (!results[2]) return '';
      return decodeURIComponent(results[2].replace(/\\+/g, ' '));
    };
    const hasParam = (name) => getParam(name) !== null;
    const isSameFormId = () => getParam('vkfs_form_id') === FORM_ID;

    // 検索結果で s を保存
    const saveIfOnResultsPage = () => {
      if (hasParam('s') && isSameFormId()) {
        const s = getParam('s') || '';
        if (s) {
          sessionStorage.setItem(STORAGE_KEY, s);
        } else {
          sessionStorage.removeItem(STORAGE_KEY);
        }
      }
    };

    // 詳細など URL に s が無い時だけ復元
    const prefillOnDetailOrOther = () => {
      if (!hasParam('s')) {
        const saved = sessionStorage.getItem(STORAGE_KEY);
        if (saved) {
          const $input = document.querySelector(INPUT_SELECTOR);
          if ($input && ($input.value || '').trim() === '') {
            $input.value = saved;
            $input.dispatchEvent(new Event('input', { bubbles: true }));
            $input.dispatchEvent(new Event('change', { bubbles: true }));
          }
        }
      }
    };

    // フォーム送信時：値が空なら保存を削除／値があれば更新
    const attachSubmitCleaner = () => {
      const $form = document.querySelector(`form[id*="${FORM_ID}"], form[action*="vkfs"]`);
      if (!$form) return;
      $form.addEventListener('submit', function () {
        const $input = $form.querySelector(INPUT_SELECTOR);
        if ($input) {
          const v = ($input.value || '').trim();
          if (v) {
            sessionStorage.setItem(STORAGE_KEY, v);
          } else {
            sessionStorage.removeItem(STORAGE_KEY);
          }
        }
      });
    };

    document.addEventListener('DOMContentLoaded', function () {
      saveIfOnResultsPage();
      prefillOnDetailOrOther();
      attachSubmitCleaner();
    });
  })();
  </script>
  <?php
}, 100);
