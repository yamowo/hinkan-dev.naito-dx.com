<?php
/**
 * Shortcode: [report_list]
 * - 既定：発生日（降順）
 * - 見出し「発生日」クリックで 昇順/降順 トグル（?rl_dir=asc|desc）
 * - アーカイブ/検索の条件（s, post_type, あらゆる report 連携タクソノミー）を継承
 * - VK FilterSearch のパラメータ（ID/slug, カンマ区切り/配列）も自動対応
 * - ページネーションは現URLのパスから再構築してクエリを一度だけ付与（&amp; 混入回避）
 * - 検索は「全角/半角カナ 非依存」（このショートコード内の WP_Query にだけ適用）
 * - 総件数を data-total に出力し、H1のタイトルに（○○件）を追記（トップページH1にも対応）
 *
 * 依存（任意）:
 * - ACF: acf_event_date（発生日）, acf_attachment_file（受理票）
 * - タクソノミー: cpt_maker ほか、report に紐づく公開タクソノミー
 *
 * メタキー:
 * - n_event_ts : 発生日のUNIXタイムスタンプ（秒）
 */

/* ──────────────────────────────────
 * ACF値をUNIXタイムスタンプへ正規化
 * ────────────────────────────────── */
if ( ! function_exists( 'n_normalize_date_to_ts' ) ) :
  function n_normalize_date_to_ts( $raw ) {
    if ( empty( $raw ) ) return null;
  
    if ( is_array( $raw ) ) {
      if ( isset( $raw['timestamp'] ) && is_numeric( $raw['timestamp'] ) ) {
        $raw = (string) $raw['timestamp'];
      } elseif ( isset( $raw['date'] ) ) {
        $raw = (string) $raw['date'];
      } else {
        $raw = reset( $raw );
      }
    }
  
    $s = trim( (string) $raw );
  
    if ( ctype_digit( $s ) && strlen( $s ) === 13 ) {
      return (int) floor( (int) $s / 1000 );
    }
    if ( ctype_digit( $s ) && strlen( $s ) === 10 ) {
      return (int) $s;
    }
    if ( ctype_digit( $s ) && strlen( $s ) === 8 ) {
      $dt = DateTime::createFromFormat( 'Ymd', $s );
      if ( $dt ) return $dt->getTimestamp();
    }
    if ( preg_match( '/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}$/', $s ) ) {
      $ts = strtotime( str_replace( '/', '-', $s ) );
      if ( $ts ) return $ts;
    }
    if ( preg_match( '/^\d{4}年\d{1,2}月\d{1,2}日$/u', $s ) ) {
      $s2 = strtr( $s, [ '年' => '-', '月' => '-', '日' => '' ] );
      $ts = strtotime( $s2 );
      if ( $ts ) return $ts;
    }
  
    $ts = strtotime( $s );
    return $ts ?: null;
  }
  endif;
  
  /* ──────────────────────────────────
   * 保存時に n_event_ts を同期
   * ────────────────────────────────── */
  add_action( 'save_post_report', function ( $post_id ) {
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;
  
    $ts = null;
    if ( function_exists( 'get_field' ) ) {
      $event_raw = get_field( 'acf_event_date', $post_id );
      $ts        = n_normalize_date_to_ts( $event_raw );
    }
    if ( $ts ) {
      update_post_meta( $post_id, 'n_event_ts', (int) $ts );
    } else {
      delete_post_meta( $post_id, 'n_event_ts' );
    }
  }, 10, 1 );
  
  /* ──────────────────────────────────
   * 全角/半角カナ 非依存検索（posts_search フィルタ関数）
   *  ※このショートコード内の WP_Query 実行時だけ ON
   * ────────────────────────────────── */
  if ( ! function_exists('n_kana_search_posts_search') ) :
  function n_kana_search_posts_search( $search, $wp_query ) {
    if ( is_admin() ) return $search;
  
    $s = (string) $wp_query->get('s');
    if ( $s === '' ) return $search;
  
    global $wpdb;
  
    // スペース（半角/全角）で分割
    $terms = preg_split('/[\h\x{3000}]+/u', $s, -1, PREG_SPLIT_NO_EMPTY);
    if ( empty($terms) ) return $search;
  
    $groups = array();
    $params = array();
  
    foreach ( $terms as $t ) {
      $t = trim($t);
      if ( $t === '' ) continue;
  
      // そのまま / 全角カナ(KVC) / 半角カナ(kh)
      $variants = array($t);
      if ( function_exists('mb_convert_kana') ) {
        $variants[] = mb_convert_kana($t, 'KVC', 'UTF-8'); // かな/半角→全角カナ
        $variants[] = mb_convert_kana($t, 'kh',  'UTF-8'); // カナ/かな→半角カナ
      }
      $variants = array_values(array_unique($variants));
  
      $or = array();
      foreach ( $variants as $v ) {
        $like = '%' . $wpdb->esc_like($v) . '%';
        $or[] = "({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s)";
        $params[] = $like; $params[] = $like; $params[] = $like;
      }
      if ( $or ) $groups[] = '(' . implode(' OR ', $or) . ')';
    }
  
    if ( empty($groups) ) return $search;
  
    $where = ' AND ' . implode(' AND ', $groups);
    return $wpdb->prepare($where, $params);
  }
  endif;
  
  /* ──────────────────────────────────
   * 一覧→詳細へ現在の絞り込みクエリを引き継ぐヘルパー
   * ────────────────────────────────── */
  if ( ! function_exists('n_report_collect_persist_query_args') ) :
  function n_report_collect_persist_query_args( $persist_taxqs, $s, $post_type_qv ) {
    $persist = array();
  
    // 基本
    if ( ! is_null($s) && $s !== '' ) {
      $persist['s'] = wp_unslash( $s );
    }
    if ( ! empty( $post_type_qv ) ) {
      $persist['post_type'] = is_array($post_type_qv)
        ? implode(',', array_map('sanitize_key', $post_type_qv))
        : sanitize_key($post_type_qv);
    }
  
    // すべてのタクソノミー条件（既存ロジックで整形済み）
    foreach ( (array) $persist_taxqs as $k => $v ) {
      $persist[ $k ] = $v;
    }
  
    // VK Filter Search のパラメータはそのまま保持（配列含む）
    foreach ( $_GET as $k => $v ) {
      $k = (string) $k;
      if ( $k === 'paged' ) continue; // ページ番号は不要
      if ( strpos($k, 'vkfs_') === 0 ) {
        $persist[$k] = wp_unslash( $v );
      }
    }
  
    // 並び替え（あれば）
    if ( isset($_GET['rl_sort']) ) $persist['rl_sort'] = sanitize_key($_GET['rl_sort']);
    if ( isset($_GET['rl_dir'])  ) {
      $dir = strtolower( (string) $_GET['rl_dir'] );
      $persist['rl_dir'] = ($dir === 'asc') ? 'asc' : 'desc';
    }
  
    return $persist;
  }
  endif;
  
  /* ──────────────────────────────────
   * 一覧ショートコード
   * ────────────────────────────────── */
  function n_report_list_shortcode( $atts ) {
    $atts = shortcode_atts(
      array(
        'posts_per_page' => get_option( 'posts_per_page' ),
        'sort'           => 'event', // event|post
        'dir'            => 'desc',  // asc|desc
        'jp_separator'   => true,
      ),
      $atts,
      'report_list'
    );
  
    // ソート基準
    $sort = isset( $_GET['rl_sort'] ) ? sanitize_key( $_GET['rl_sort'] ) : $atts['sort'];
    if ( $sort !== 'post' ) $sort = 'event';
  
    // 昇順/降順
    $dir = isset( $_GET['rl_dir'] ) ? strtolower( sanitize_text_field( $_GET['rl_dir'] ) ) : strtolower( $atts['dir'] );
    $dir = ( $dir === 'asc' ) ? 'asc' : 'desc';
    $dir_sql = strtoupper( $dir ); // ASC|DESC
  
    // 現在ページ
    $paged = (int) ( get_query_var( 'paged' ) ?: get_query_var( 'page' ) ?: 1 );
  
    // 継承条件
    $s            = get_query_var( 's' );
    $post_type_qv = get_query_var( 'post_type' );
  
    // WP_Query ベース
    $query_args = array(
      'post_type'           => 'report',
      'post_status'         => 'publish',
      'paged'               => max( 1, $paged ),
      'posts_per_page'      => (int) $atts['posts_per_page'],
      'no_found_rows'       => false,
      'ignore_sticky_posts' => true,
      'suppress_filters'    => false, // フィルタを有効化可能に
    );
  
    /* ① タクソノミー継承（report に紐づく公開タクソノミーを総ざらい） */
    $tax_query     = array('relation' => 'AND');
    $persist_taxqs = array(); // URL引き継ぎ用
    $tax_objects   = get_object_taxonomies( 'report', 'objects' ); // 例: cpt_maker, category, post_tag など
  
    foreach ( $tax_objects as $tax_name => $tax_obj ) {
      if ( ! $tax_obj->public ) continue; // 公開のみ対象
  
      $qv = $tax_obj->query_var ? $tax_obj->query_var : $tax_name; // 通常はスラッグと同じ
  
      // GET/クエリ変数から拾う（配列 or カンマ区切り）
      $val = get_query_var( $qv );
      if ( empty( $val ) && isset( $_GET[ $tax_name ] ) ) {
        $val = sanitize_text_field( wp_unslash( $_GET[ $tax_name ] ) ); // 念のため tax名キーでも拾う
      }
      if ( empty( $val ) ) continue;
  
      $terms = is_array( $val ) ? $val : array_map( 'trim', explode( ',', (string) $val ) );
      $terms = array_filter( $terms, function( $v ){ return $v !== ''; } );
      if ( ! $terms ) continue;
  
      // すべて数値なら term_id 指定、混在/文字を含むなら slug 指定
      $all_numeric = count( array_filter( $terms, function( $v ){ return ctype_digit( (string) $v ); } ) ) === count( $terms );
      $field      = $all_numeric ? 'term_id' : 'slug';
      $terms_cast = $all_numeric ? array_map( 'intval', $terms ) : array_map( 'sanitize_title', $terms );
  
      $tax_query[] = array(
        'taxonomy' => $tax_name,
        'field'    => $field,
        'terms'    => $terms_cast,
        'operator' => 'IN', // VKFS の基本挙動（OR）に合わせる。ANDにしたい場合は 'AND'
      );
  
      // URL引き継ぎ（元のクエリ変数名で保持）
      $persist_taxqs[ $qv ] = implode( ',', $terms_cast );
    }
  
    if ( count( $tax_query ) > 1 ) { // relation + 1以上あれば条件あり
      $query_args['tax_query'] = $tax_query;
    }
  
    /* ② 検索語 */
    if ( ! is_null( $s ) && $s !== '' ) {
      $query_args['s'] = $s;
    }
  
    /* 並び替え */
    if ( $sort === 'event' ) {
      $query_args['meta_query'] = array(
        'relation' => 'OR',
        'n_event_ts_clause' => array(
          'key'     => 'n_event_ts',
          'compare' => 'EXISTS',
          'type'    => 'NUMERIC',
        ),
        array(
          'key'     => 'n_event_ts',
          'compare' => 'NOT EXISTS',
        ),
      );
      $query_args['orderby'] = array(
        'n_event_ts_clause' => $dir_sql, // ASC/DESC
        'date'              => 'DESC',   // セカンダリは新しい順固定
      );
    } else {
      $query_args['orderby'] = 'date';
      $query_args['order']   = $dir_sql;
    }
  
    /* ★ このクエリの直前だけ「全角/半角カナ非依存検索」をON */
    add_filter('posts_search', 'n_kana_search_posts_search', 10, 2);
    $q = new WP_Query( $query_args );
    remove_filter('posts_search', 'n_kana_search_posts_search', 10);
  
    // フォールバック（0件時は投稿日降順）
    if ( ! $q->have_posts() && $sort === 'event' ) {
      $fallback_args = $query_args;
      unset( $fallback_args['meta_key'], $fallback_args['meta_type'], $fallback_args['meta_query'], $fallback_args['orderby'] );
      $fallback_args['orderby'] = 'date';
      $fallback_args['order']   = 'DESC';
  
      add_filter('posts_search', 'n_kana_search_posts_search', 10, 2);
      $q = new WP_Query( $fallback_args );
      remove_filter('posts_search', 'n_kana_search_posts_search', 10);
    }
  
    ob_start();
  
    // 総件数を data-total に埋め込む
    echo '<div class="p-reportList -table" data-total="' . (int) $q->found_posts . '">';
  
    /* 見出しクリックで昇降トグル（パスから再構成＋ホワイトリスト再付与） */
    $current_abs  = home_url( add_query_arg( array(), $_SERVER['REQUEST_URI'] ) );
    $current_path = strtok( $current_abs, '?' ); // パスのみ
    $toggle_args  = array();
    if ( ! is_null( $s ) && $s !== '' )            $toggle_args['s'] = $s;
    if ( ! empty( $post_type_qv ) )                $toggle_args['post_type'] = is_array($post_type_qv) ? implode(',', $post_type_qv) : (string) $post_type_qv;
    foreach ( $persist_taxqs as $k => $v )         $toggle_args[ $k ] = $v; // ★ すべてのタクソノミー条件を引き継ぐ
    $toggle_args['rl_sort'] = 'event';
    $toggle_args['rl_dir']  = ( $dir === 'asc' ) ? 'desc' : 'asc';
    $toggle_url = add_query_arg( $toggle_args, $current_path );
  
    $aria_sort = ( $sort === 'event' ) ? ( $dir === 'asc' ? 'ascending' : 'descending' ) : 'none';
    $indicator = ( $sort === 'event' ) ? ( $dir === 'asc' ? '▲' : '▼' ) : '';
  
    echo '<table class="wp-block-table is-style-stripes" style="width:100%;">';
    echo '<thead><tr>';
  
    // 発生日（クリックで昇降トグル）
    echo '<th scope="col" aria-sort="' . esc_attr( $aria_sort ) . '">';
    echo '<a class="report-sort" href="' . esc_url( $toggle_url ) . '" aria-label="発生日を' . ( $dir === 'asc' ? '降順' : '昇順' ) . 'に並べ替え">';
    echo esc_html__( '発生日', 'default' ) . ( $indicator ? ' ' . esc_html( $indicator ) : '' );
    echo '</a>';
    echo '</th>';
  
    echo '<th scope="col">' . esc_html__( 'メーカー', 'default' ) . '</th>';
    echo '<th scope="col">' . esc_html__( '機種名', 'default' ) . '</th>';
    echo '<th scope="col" style="text-align:center;">' . esc_html__( '受理票', 'default' ) . '</th>';
    echo '</tr></thead><tbody>';
  
    if ( $q->have_posts() ) :
      while ( $q->have_posts() ) :
        $q->the_post();
  
        $post_id = get_the_ID();
  
        // 発生日の表示 + 不足時は n_event_ts を補完
        $event_disp = '';
        $event_ts   = get_post_meta( $post_id, 'n_event_ts', true );
        $event_ts   = is_numeric( $event_ts ) ? (int) $event_ts : 0;
  
        if ( ! $event_ts && function_exists( 'get_field' ) ) {
          $event_raw = get_field( 'acf_event_date', $post_id );
          $ts_calc   = n_normalize_date_to_ts( $event_raw );
          if ( $ts_calc ) {
            $event_ts = (int) $ts_calc;
            update_post_meta( $post_id, 'n_event_ts', $event_ts );
          }
        }
        if ( $event_ts ) {
          $event_disp = wp_date( 'Y年m月d日', $event_ts ); // 0000年00月00日 形式
        } else {
          $event_disp = '—';
        }
  
        // メーカー（cpt_maker を表示）
        $maker_disp = '';
        $makers     = get_the_terms( $post_id, 'cpt_maker' );
        if ( ! is_wp_error( $makers ) && ! empty( $makers ) ) {
          $names = array();
          foreach ( $makers as $t ) { $names[] = esc_html( $t->name ); }
          $sep   = filter_var( $atts['jp_separator'], FILTER_VALIDATE_BOOLEAN ) ? '、' : ', ';
          $maker_disp = implode( $sep, $names );
        }
  
        // 受理票
        $file_url = '';
        if ( function_exists( 'get_field' ) ) {
          $file_val = get_field( 'acf_attachment_file' );
          if ( is_array( $file_val ) && ! empty( $file_val['url'] ) ) {
            $file_url = esc_url( $file_val['url'] );
          } elseif ( is_string( $file_val ) ) {
            $file_url = esc_url( $file_val );
          }
        }
  
        // 現在の絞り込みクエリをリンクへ引き継ぐ
        $persist_for_links = n_report_collect_persist_query_args( $persist_taxqs, $s, $post_type_qv );
        $detail_url = add_query_arg( $persist_for_links, get_permalink( $post_id ) );
  
        echo '<tr>';
        echo '<td>' . esc_html( $event_disp ) . '</td>';
        echo '<td>' . $maker_disp . '</td>';
        echo '<td><a href="' . esc_url( $detail_url ) . '">' . esc_html( get_the_title() ) . '</a></td>';
        echo '<td style="text-align:center;">';
        if ( $file_url ) {
          echo '<a href="' . $file_url . '" class="report-clip" aria-label="受理票をダウンロード" target="_blank">';
          echo '<span class="dashicons dashicons-paperclip" aria-hidden="true"></span>';
          echo '</a>';
        } else {
          echo '—';
        }
        echo '</td>';
        echo '</tr>';
  
      endwhile;
    else :
      echo '<tr><td colspan="4">' . esc_html__( '表示する不具合報告はありません。', 'default' ) . '</td></tr>';
    endif;
  
    echo '</tbody></table>';
    echo '</div>';
  
    /* ページネーション：現URLのパス基準で再構築（クエリは一度だけ付与） */
    if ( $q->max_num_pages > 1 ) {
      $current_abs  = home_url( add_query_arg( array(), $_SERVER['REQUEST_URI'] ) );
      $current_path = strtok( $current_abs, '?' ); // パスのみ
  
      $persist = array();
      if ( ! is_null( $s ) && $s !== '' )            $persist['s'] = $s;
      if ( ! empty( $post_type_qv ) )                $persist['post_type'] = is_array($post_type_qv) ? implode(',', $post_type_qv) : (string) $post_type_qv;
      foreach ( $persist_taxqs as $k => $v )         $persist[ $k ] = $v; // ★ すべてのタクソノミー条件を引き継ぐ
      $persist['rl_sort'] = $sort;
      $persist['rl_dir']  = $dir;
  
      $big           = 999999999;
      $base_with_big = add_query_arg( array_merge( $persist, array( 'paged' => $big ) ), $current_path );
      $base          = str_replace( $big, '%#%', $base_with_big );
  
      echo '<nav class="c-pagination" aria-label="pagination">';
      echo paginate_links( array(
        'base'      => $base,   // 常に ?paged=%#% 形式
        'format'    => '',      // baseに含めているので空
        'current'   => max( 1, $paged ),
        'total'     => (int) $q->max_num_pages,
        'mid_size'  => 1,
        'prev_text' => '«',
        'next_text' => '»',
        'type'      => 'a',     // SWELL互換
      ) );
      echo '</nav>';
    }
  
    wp_reset_postdata();
  
    return ob_get_clean();
  }
  add_shortcode( 'report_list', 'n_report_list_shortcode' );
  
  /* Dashicons（フロント） */
  add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'dashicons' );
  });
  
  /* ──────────────────────────────────
   * フッターで件数をタイトルへ追記（ラッパの data-total を利用）
   *  - トップページの h1.wp-block-heading にも対応
   * ────────────────────────────────── */
  add_action('wp_footer', function(){ ?>
  <script>
  (()=>{'use strict';
    function run(){
      const wrap = document.querySelector('.p-reportList.-table[data-total]');
      if(!wrap) return; // ショートコードが無いページは何もしない
      const total = parseInt(wrap.getAttribute('data-total'),10);
      if(!Number.isFinite(total)) return;
  
      // 優先順で見出し要素を探す
      const titleEl =
        document.querySelector('h1.c-pageTitle .c-pageTitle__inner') ||
        document.querySelector('.c-pageTitle .c-pageTitle__inner')   ||
        document.querySelector('.c-pageTitle__inner')                ||
        document.querySelector('h1.wp-block-heading')                ||
        document.querySelector('h1.c-pageTitle');
      if(!titleEl) return;
  
      // 既存の（○○件）を消してから付け直す
      const base = titleEl.textContent.replace(/\s*（\d{1,3}(?:,\d{3})*件）\s*$/,'').trim();
      titleEl.textContent = base + '（' + total.toLocaleString('ja-JP') + '件）';
    }
    if(document.readyState==='loading'){
      document.addEventListener('DOMContentLoaded', run, { once:true });
    }else{
      run();
    }
  })();
  </script>
  <?php }, 99);
  // ──────────────────────────────────
  // 一覧ページURLを sessionStorage に記録（詳細画面の「検索結果へ戻る」用）
  // ※ .p-reportList が存在するページ（ショートコード/置換レンダラーの一覧）だけ記録します
  // ──────────────────────────────────
  if ( ! defined( 'N_REPORT_SAVE_LAST_LIST_URL' ) ) {
    define( 'N_REPORT_SAVE_LAST_LIST_URL', true );
    add_action( 'wp_footer', function () { ?>
  <script>
  (function(){try{
    // 一覧テーブルの存在で判定（ショートコード/置換レンダラー共通のクラス）
    if (document.querySelector('.p-reportList')) {
      sessionStorage.setItem('reportLastListURL', location.href);
    }
  }catch(e){}})();
  </script>
  <?php }, 99 );
  }