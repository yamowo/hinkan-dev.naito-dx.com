<?php
/**
 * [latest_reports] : 発生日(n_event_ts)が新しい順で「最新の報告」を出すミニリスト
 *
 * 使い方（ウィジェットやサイドバーの「ショートコード」ブロックに貼る）:
 *   [latest_reports]                      // デフォルト5件、発生日降順
 *   [latest_reports count="8"]            // 件数指定
 *   [latest_reports maker="sony,heiwa"]   // メーカー絞り込み（slugカンマ区切り）
 *   [latest_reports show_date="1"]        // 発生日を表示（デフォは 0=非表示）
 *   [latest_reports date_format="Y.m.d"]  // 日付フォーマット
 *   [latest_reports include_no_event="0"] // 発生日未設定を含めるか（0=含めない, 1=含める）
 *   [latest_reports class="-mini"]        // 追加クラス（SWELLの見た目に寄せる等）
 *
 * 前提:
 *  - 保存時に n_event_ts を同期する既存スニペットが有効（= 発生日のUNIX秒が入っている）
 *  - CPT: report / タクソノミー: cpt_maker
 */
add_shortcode( 'latest_reports', function( $atts ){
	$atts = shortcode_atts( [
		'count'            => 5,
		'maker'            => '',       // slugカンマ区切り
		'show_date'        => '0',      // '1' で表示
		'date_format'      => 'Y.m.d',
		'include_no_event' => '0',      // '1' で n_event_ts 無しも含める（後段で投稿日降順にフォールバック）
		'class'            => '',       // 追加クラス
	], $atts, 'latest_reports' );

	$count            = max( 1, (int) $atts['count'] );
	$show_date        = filter_var( $atts['show_date'], FILTER_VALIDATE_BOOLEAN );
	$include_no_event = filter_var( $atts['include_no_event'], FILTER_VALIDATE_BOOLEAN );
	$date_format      = (string) $atts['date_format'];
	$extra_class      = sanitize_html_class( $atts['class'] );

	// クエリ組み立て
	$args = [
		'post_type'           => 'report',
		'post_status'         => 'publish',
		'posts_per_page'      => $count,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
		'suppress_filters'    => false,
	];

	// メーカー絞り込み（slugカンマ区切り）
	$tax_query = [];
	if ( $atts['maker'] !== '' ) {
		$maker_slugs = array_filter( array_map( 'sanitize_title', array_map( 'trim',
			explode( ',', (string) $atts['maker'] )
		) ) );
		if ( $maker_slugs ) {
			$tax_query[] = [
				'taxonomy' => 'cpt_maker',
				'field'    => 'slug',
				'terms'    => $maker_slugs,
				'operator' => 'IN',
			];
		}
	}
	if ( $tax_query ) {
		$args['tax_query'] = $tax_query;
	}

	// 並び順：発生日（n_event_ts）降順。未設定を含めるかで分岐
	if ( $include_no_event ) {
		$args['meta_query'] = [
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
		$args['orderby'] = [
			'n_event_ts_clause' => 'DESC',
			'date'              => 'DESC', // 未設定は投稿日でフォールバック
		];
	} else {
		$args['meta_key'] = 'n_event_ts';
		$args['orderby']  = 'meta_value_num';
		$args['order']    = 'DESC';
	}

	$q = new WP_Query( $args );

	ob_start();

	// SWELL風クラスを付けておく（必要に応じて調整）
	$wrap_class = 'p-postList ' . $extra_class;
	echo '<div class="' . esc_attr( trim( $wrap_class ) ) . '"><ul class="p-postList__items">';

	if ( $q->have_posts() ) {
		while ( $q->have_posts() ) {
			$q->the_post();
			$post_id = get_the_ID();

			// 発生日の表示
			$event_disp = '';
			$event_ts   = get_post_meta( $post_id, 'n_event_ts', true );
			$event_ts   = is_numeric( $event_ts ) ? (int) $event_ts : 0;
			if ( $show_date ) {
				if ( $event_ts ) {
					$event_disp = wp_date( $date_format, $event_ts );
				} elseif ( $include_no_event ) {
					$event_disp = esc_html__( '—', 'default' );
				}
			}

			echo '<li class="p-postList__item">';
			echo '  <a class="p-postList__link" href="' . esc_url( get_permalink() ) . '">';
			echo '    <span class="p-postList__title">' . esc_html( get_the_title() ) . '</span>';
			if ( $event_disp !== '' ) {
				echo '    <time class="p-postList__meta">' . esc_html( $event_disp ) . '</time>';
			}
			echo '  </a>';
			echo '</li>';
		}
	} else {
		echo '<li class="p-postList__item">', esc_html__( '表示する不具合報告はありません。', 'default' ), '</li>';
	}

	echo '</ul></div>';

	wp_reset_postdata();

	return ob_get_clean();
});
