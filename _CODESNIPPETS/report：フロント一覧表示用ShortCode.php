<?php
/**
 * Shortcode: [report_list]
 * - 既定：発生日（降順）
 * - 見出し「発生日」をクリックで 昇順/降順 トグル（?rl_dir=asc|desc）
 * - アーカイブ/検索の条件（cpt_maker, s, post_type）を継承
 * - ページネーションは現URLのパスから再構築してクエリを一度だけ付与（&amp; 混入回避）
 * - 検索は「全角/半角カナ 非依存」に対応（このショートコード内WP_Queryにだけ適用）
 *
 * 依存（任意）:
 * - ACF: acf_event_date（発生日）
 * - タクソノミー: cpt_maker
 *
 * メタキー:
 * - n_event_ts : 発生日のUNIXタイムスタンプ（秒）
 */

/* ----------------------------------------
 * ACF値をUNIXタイムスタンプへ正規化
 * ---------------------------------------- */
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

/* ----------------------------------------
 * 保存時に n_event_ts を同期
 * ---------------------------------------- */
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

/* ----------------------------------------
 * 全角/半角カナ 非依存検索（posts_search フィルタ関数）
 *  ※このショートコード内の WP_Query 実行時だけ ON にする
 * ---------------------------------------- */
if ( ! function_exists('n_kana_search_posts_search') ) :
function n_kana_search_posts_search( $search, $wp_query ) {
	if ( is_admin() ) return $search;

	$s = (string) $wp_query->get('s');
	if ( $s === '' ) return $search;

	global $wpdb;

	// スペース（半角/全角）で分割
	$terms = preg_split('/[\h\x{3000}]+/u', $s, -1, PREG_SPLIT_NO_EMPTY);
	if ( empty($terms) ) return $search;

	$groups = [];
	$params = [];

	foreach ( $terms as $t ) {
		$t = trim($t);
		if ( $t === '' ) continue;

		// そのまま / 全角カナ(KVC) / 半角カナ(kh)
		$variants = [$t];
		if ( function_exists('mb_convert_kana') ) {
			$variants[] = mb_convert_kana($t, 'KVC', 'UTF-8'); // かな/半角→全角カナ
			$variants[] = mb_convert_kana($t, 'kh',  'UTF-8'); // カナ/かな→半角カナ
		}
		$variants = array_values(array_unique($variants));

		$or = [];
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

/* ----------------------------------------
 * 一覧ショートコード
 * ---------------------------------------- */
function n_report_list_shortcode( $atts ) {
	$atts = shortcode_atts(
		[
			'posts_per_page' => get_option( 'posts_per_page' ),
			'sort'           => 'event', // event|post
			'dir'            => 'desc',  // asc|desc
			'jp_separator'   => true,
		],
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
	$maker_qv     = get_query_var( 'cpt_maker' );
	$post_type_qv = get_query_var( 'post_type' );

	// WP_Query ベース
	$query_args = [
		'post_type'           => 'report',
		'post_status'         => 'publish',
		'paged'               => max( 1, $paged ),
		'posts_per_page'      => (int) $atts['posts_per_page'],
		'no_found_rows'       => false,
		'ignore_sticky_posts' => true,
		'suppress_filters'    => false, // フィルタを有効化可能に
	];

	// ① タクソノミー継承
	$tax_query = [];
	if ( $maker_qv ) {
		$terms = is_array( $maker_qv ) ? $maker_qv : array_map( 'trim', explode( ',', (string) $maker_qv ) );
		$tax_query[] = [
			'taxonomy' => 'cpt_maker',
			'field'    => 'slug',
			'terms'    => $terms,
		];
	} elseif ( is_tax( 'cpt_maker' ) ) {
		$term = get_queried_object();
		if ( $term && ! is_wp_error( $term ) ) {
			$tax_query[] = [
				'taxonomy' => 'cpt_maker',
				'field'    => 'term_id',
				'terms'    => [ (int) $term->term_id ],
			];
		}
	}
	if ( ! empty( $tax_query ) ) {
		$query_args['tax_query'] = $tax_query;
	}

	// ② 検索語
	if ( ! is_null( $s ) && $s !== '' ) {
		$query_args['s'] = $s;
	}

	// 並び替え
	if ( $sort === 'event' ) {
		$query_args['meta_query'] = [
			'relation' => 'OR',
			'n_event_ts_clause' => [
				'key'     => 'n_event_ts',
				'compare' => 'EXISTS',
				'type'    => 'NUMERIC',
			],
			[
				'key'     => 'n_event_ts',
				'compare' => 'NOT EXISTS',
			],
		];
		$query_args['orderby'] = [
			'n_event_ts_clause' => $dir_sql, // ASC/DESC
			'date'              => 'DESC',   // セカンダリは新しい順固定
		];
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

	echo '<div class="p-reportList -table">';

	/* 見出しクリックで昇降トグル（現URLのパスから再構成＋ホワイトリスト再付与） */
	$current_abs  = home_url( add_query_arg( [], $_SERVER['REQUEST_URI'] ) );
	$current_path = strtok( $current_abs, '?' ); // パスのみ
	$toggle_args  = [];
	if ( ! is_null( $s ) && $s !== '' )           $toggle_args['s'] = $s;
	if ( ! empty( $maker_qv ) )                   $toggle_args['cpt_maker'] = is_array($maker_qv) ? implode(',', $maker_qv) : (string) $maker_qv;
	if ( ! empty( $post_type_qv ) )               $toggle_args['post_type'] = is_array($post_type_qv) ? implode(',', $post_type_qv) : (string) $post_type_qv;
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
	echo '<th scope="col">' . esc_html__( '受理票', 'default' ) . '</th>';
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
			} else {
				$event_disp = '—';
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

	/* ページネーション：現URLのパス基準で再構築（クエリは一度だけ付与） */
	if ( $q->max_num_pages > 1 ) {
		$current_abs  = home_url( add_query_arg( [], $_SERVER['REQUEST_URI'] ) );
		$current_path = strtok( $current_abs, '?' ); // パスのみ

		// ホワイトリストで一度だけ付与
		$persist = [];
		if ( ! is_null( $s ) && $s !== '' )           $persist['s'] = $s;
		if ( ! empty( $maker_qv ) )                   $persist['cpt_maker'] = is_array($maker_qv) ? implode(',', $maker_qv) : (string) $maker_qv;
		if ( ! empty( $post_type_qv ) )               $persist['post_type'] = is_array($post_type_qv) ? implode(',', $post_type_qv) : (string) $post_type_qv;
		$persist['rl_sort'] = $sort;
		$persist['rl_dir']  = $dir;

		$big           = 999999999;
		$base_with_big = add_query_arg( array_merge( $persist, [ 'paged' => $big ] ), $current_path );
		$base          = str_replace( $big, '%#%', $base_with_big );

		echo '<nav class="c-pagination" aria-label="pagination">';
		echo paginate_links( [
			'base'      => $base,   // 常に ?paged=%#% 形式
			'format'    => '',      // baseに含めているので空
			'current'   => max( 1, $paged ),
			'total'     => (int) $q->max_num_pages,
			'mid_size'  => 1,
			'prev_text' => '«',
			'next_text' => '»',
			'type'      => 'a',     // SWELL互換
		] );
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
