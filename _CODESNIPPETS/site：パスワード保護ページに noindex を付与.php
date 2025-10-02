<?php
// パスワード保護ページに noindex を付与（WPCode: Run Everywhere）
add_action('wp_head', function(){
  if (post_password_required()) {
      echo '<meta name="robots" content="noindex,nofollow" />' . "\n";
  }
});
