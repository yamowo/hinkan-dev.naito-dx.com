<?php
/**
 * Shortcode: [report_list]
 * 並び替え：デフォルトは「発生日（降順）」。UI切替に備え、sort指定に対応。
 *
 * 使い方:
 * - [report_list]                     → 発生日降順
 * - [report_list sort="post"]         → 投稿日時降順
 * - URLで上書き: ?rl_sort=event|post → ショートコード引数より優先
 *
 * 依存（任意）:
 * - ACF: acf_event_date（多様な返り値に対応）
 * - タクソノミー: cpt_maker
 *
 * メタキー:
 * - n_event_ts : 発生日のUNIXタイムスタンプ（秒）
 */

 if ( ! function_exists( 'n_normalize_date_to_ts' ) ) :
  /**
   * ACFの返り値を安全にUNIXタイムスタンプへ正規化。
   */
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
  
  /**
   * 発生日（ACF）を n_event_ts に同期（保存時）。
   */
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
  
  /**
   * 不具合報告一覧のショートコード本体。
   */
  function n_report_list_shortcode( $atts ) {
    $atts = shortcode_atts(
      [
        'posts_per_page' => get_option( 'posts_per_page' ),
        'sort'           => 'event', // event|post（URLの ?rl_sort= があれば上書き）
        'jp_separator'   => true,
      ],
      $atts,
      'report_list'
    );
  
    $sort = isset( $_GET['rl_sort'] ) ? sanitize_key( $_GET['rl_sort'] ) : $atts['sort'];
    if ( $sort !== 'post' ) $sort = 'event';
  
    $paged = 1;
    if ( get_query_var( 'paged' ) ) {
      $paged = (int) get_query_var( 'paged' );
    } elseif ( get_query_var( 'page' ) ) {
      $paged = (int) get_query_var( 'page' );
    }
  
    // ベース
    $query_args = [
      'post_type'           => 'report',
      'post_status'         => 'publish',
      'paged'               => max( 1, $paged ),
      'posts_per_page'      => (int) $atts['posts_per_page'],
      'no_found_rows'       => false,
      'ignore_sticky_posts' => true,
      'suppress_filters'    => false, // テーマ/プラグインのフィルタ併用を許容
    ];
  
    if ( $sort === 'event' ) {
      // 1) まず「n_event_ts（数値）」降順、同値は投稿日で降順
      //    ※ 一部環境で meta_key 指定が 0件 になるのを避けるため、meta_query を明示し、型も指定
      $query_args['meta_key']   = 'n_event_ts';
      $query_args['meta_type']  = 'NUMERIC';
      $query_args['meta_query'] = [
        // EXISTS/NOT EXISTS を明示して“持ってても持ってなくても含める”
        'relation' => 'OR',
        [
          'key'     => 'n_event_ts',
          'compare' => 'EXISTS',
        ],
        [
          'key'     => 'n_event_ts',
          'compare' => 'NOT EXISTS',
        ],
      ];
  
      // 複合ソート（WP 4.0+）
      $query_args['orderby'] = [
        'meta_value_num' => 'DESC',
        'date'           => 'DESC',
      ];
  
    } else {
      // 投稿日時 降順
      $query_args['orderby'] = 'date';
      $query_args['order']   = 'DESC';
    }
  
    $q = new WP_Query( $query_args );
  
    // --- フォールバック ---
    // もし何らかの相性で0件になった場合は、投稿日時降順で再クエリして“真っ白”を回避
    if ( ! $q->have_posts() && $sort === 'event' ) {
      $fallback_args = $query_args;
      unset( $fallback_args['meta_key'], $fallback_args['meta_type'], $fallback_args['meta_query'], $fallback_args['orderby'] );
      $fallback_args['orderby'] = 'date';
      $fallback_args['order']   = 'DESC';
      $q = new WP_Query( $fallback_args );
    }
  
    ob_start();
  
    echo '<div class="p-reportList -table">';
    echo '<table class="wp-block-table is-style-stripes" style="width:100%;">';
    echo '<thead><tr>';
    echo '<th scope="col" style="width:120px;">' . esc_html__( '発生日', 'default' ) . '</th>';
    echo '<th scope="col" style="width:160px;">' . esc_html__( 'メーカー', 'default' ) . '</th>';
    echo '<th scope="col">' . esc_html__( 'タイトル', 'default' ) . '</th>';
    echo '<th scope="col" style="width:80px; text-align:center;">' . esc_html__( '受理票', 'default' ) . '</th>';
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
          $event_disp = wp_date( 'Y年m月d日', $event_ts );
        }
  
        // メーカー
        $maker_disp = '';
        $makers     = get_the_terms( $post_id, 'cpt_maker' );
        if ( ! is_wp_error( $makers ) && ! empty( $makers ) ) {
          $names = array_map(
            static function ( $t ) { return esc_html( $t->name ); },
            $makers
          );
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
  
        echo '<tr>';
        echo '<td>' . esc_html( $event_disp ) . '</td>';
        echo '<td>' . $maker_disp . '</td>';
        echo '<td><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></td>';
        echo '<td style="text-align:center;">';
        if ( $file_url ) {
          echo '<a href="' . $file_url . '" class="report-clip" aria-label="受理票をダウンロード">';
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
  
    // ページネーション（SWELL互換: type => 'a'）
    if ( $q->max_num_pages > 1 ) {
      $big = 999999999;
      echo '<nav class="c-pagination" aria-label="pagination">';
      echo wp_kses_post( paginate_links( [
        'base'      => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
        'format'    => '?paged=%#%',
        'current'   => max( 1, $paged ),
        'total'     => (int) $q->max_num_pages,
        'mid_size'  => 1,
        'prev_text' => '«',
        'next_text' => '»',
        'type'      => 'a',
      ] ) );
      echo '</nav>';
    }
  
    wp_reset_postdata();
  
    return ob_get_clean();
  }
  
  add_shortcode( 'report_list', 'n_report_list_shortcode' );
  
  /**
   * Dashicons をフロントでも利用可能にする
   */
  add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'dashicons' );
  });
  