<?php
/**
 * カスタム投稿タイプ「report」の管理画面一覧にカラムを追加・整理し、ソート対応
 *
 * 概要:
 * - カラム: 発生日（ACF）/ メーカー（taxonomy）/ タイトル / 受理票（ACFファイル）
 * - 並び順と幅・寄せ:
 *   - 発生日: 80px 左寄せ（ソート可）
 *   - メーカー: 120px 左寄せ（ソート可：ターム名で）
 *   - タイトル: 自動伸縮 左寄せ
 *   - 受理票: 40px 中央寄せ（クリップアイコン）
 *
 * 注意:
 * - 発生日: ACFの返り値（Ymd / Y-m-d / DateTime）に耐性あり
 * - メーカー: taxonomy 'cpt_maker' の最初のターム名でソート
 */

$report_cpt     = 'report';
$tax_maker      = 'cpt_maker';
$acf_date_field = 'acf_event_date';        // ACF: 発生日（返り値は Ymd 推奨）
$acf_file_field = 'acf_attachment_file';   // ACF: 受理票ファイル

/** カラム定義（表示順） */
add_filter("manage_edit-{$report_cpt}_columns", function ($cols) {
	return [
		'cb'          => '<input type="checkbox" />',
		'report_date' => '発生日',
		'maker'       => 'メーカー',
		'title'       => 'タイトル',
		'acf_file'    => '受理票',
	];
});

/** カラムの中身 */
add_action("manage_{$report_cpt}_posts_custom_column", function ($col, $post_id) use ($acf_date_field, $acf_file_field, $tax_maker) {

	// 発生日（ACF）
	if ($col === 'report_date') {
		$val = function_exists('get_field') ? get_field($acf_date_field, $post_id) : get_post_meta($post_id, $acf_date_field, true);
		$ts  = null;
		if ($val instanceof DateTime) {
			$ts = $val->getTimestamp();
		} elseif (is_string($val) && $val !== '') {
			if (preg_match('/^\d{8}$/', $val)) { // Ymd
				$ts = strtotime(substr($val,0,4) . '-' . substr($val,4,2) . '-' . substr($val,6,2));
			} else {
				$ts = strtotime($val);
			}
		}
		echo $ts ? esc_html(date_i18n(get_option('date_format'), $ts)) : '<span style="color:#999">—</span>';
	}

	// メーカー（taxonomy）
	if ($col === 'maker') {
		$terms = get_the_terms($post_id, $tax_maker);
		if (is_array($terms) && !is_wp_error($terms) && $terms) {
			echo esc_html(implode(', ', wp_list_pluck($terms, 'name')));
		} else {
			echo '<span style="color:#999">—</span>';
		}
	}

	// 受理票（ACF ファイル → アイコンリンク）
	if ($col === 'acf_file') {
		$file = function_exists('get_field') ? get_field($acf_file_field, $post_id) : get_post_meta($post_id, $acf_file_field, true);
		$url  = '';
		if (is_array($file) && !empty($file['url'])) {
			$url = $file['url'];                      // ACF: ファイル配列
		} elseif (is_numeric($file)) {
			$url = wp_get_attachment_url($file);      // ACF: ID
		} elseif (is_string($file) && filter_var($file, FILTER_VALIDATE_URL)) {
			$url = $file;                             // ACF: URL
		}
		if ($url) {
			echo '<a href="' . esc_url($url) . '" target="_blank" aria-label="添付ファイル" title="添付ファイル">'
			   . '<span class="dashicons dashicons-paperclip"></span>'
			   . '</a>';
		} else {
			echo '<span style="color:#999">—</span>';
		}
	}
}, 10, 2);

/** ソート可能カラムの登録（発生日・メーカー） */
add_filter("manage_edit-{$report_cpt}_sortable_columns", function ($cols) {
	$cols['report_date'] = 'report_date';
	$cols['maker']       = 'maker';
	return $cols;
});

/** 一覧クエリの並び替え制御 */
add_action('pre_get_posts', function (WP_Query $q) use ($report_cpt, $acf_date_field, $tax_maker) {
	if (!is_admin() || !$q->is_main_query() || $q->get('post_type') !== $report_cpt) return;

	// 発生日（ACF メタ）でソート
	if ($q->get('orderby') === 'report_date') {
		$q->set('meta_key', $acf_date_field);
		$q->set('orderby', 'meta_value'); // ACF日付（Ymd / Y-m-d）想定
		$q->set('order', $q->get('order') ?: 'desc');
	}

	// メーカー（taxonomy ターム名）でソート
	if ($q->get('orderby') === 'maker') {
		global $wpdb;

		// タクソノミJOIN
		add_filter('posts_join', function ($join, $query) use ($q, $wpdb, $tax_maker) {
			if ($query !== $q) return $join;
			$join .= " LEFT JOIN {$wpdb->term_relationships} tr_maker ON tr_maker.object_id = {$wpdb->posts}.ID";
			$join .= " LEFT JOIN {$wpdb->term_taxonomy}    tt_maker ON tt_maker.term_taxonomy_id = tr_maker.term_taxonomy_id AND tt_maker.taxonomy = '" . esc_sql($tax_maker) . "'";
			$join .= " LEFT JOIN {$wpdb->terms}            t_maker  ON t_maker.term_id = tt_maker.term_id";
			return $join;
		}, 10, 2);

		// ORDER BY ターム名
		add_filter('posts_orderby', function ($orderby, $query) use ($q) {
			if ($query !== $q) return $orderby;
			$dir = strtoupper($q->get('order')) === 'ASC' ? 'ASC' : 'DESC';
			return "t_maker.name {$dir}";
		}, 10, 2);

		// 複数ターム付与時の重複排除
		add_filter('posts_groupby', function ($groupby, $query) use ($q, $wpdb) {
			if ($query !== $q) return $groupby;
			return "{$wpdb->posts}.ID";
		}, 10, 2);
	}
});

/** カラム幅・寄せ（CSS） */
add_action('admin_head', function () use ($report_cpt) {
	echo '<style>
		/* 発生日: 120px 左寄せ */
		.post-type-' . esc_attr($report_cpt) . ' table.wp-list-table th#report_date,
		.post-type-' . esc_attr($report_cpt) . ' table.wp-list-table td.column-report_date { width:120px; text-align:left; }

		/* メーカー: 150px 左寄せ */
		.post-type-' . esc_attr($report_cpt) . ' table.wp-list-table th#maker,
		.post-type-' . esc_attr($report_cpt) . ' table.wp-list-table td.column-maker { width:150px; text-align:left; }

		/* タイトル: 自動伸縮（幅指定しない） 左寄せ */
		.post-type-' . esc_attr($report_cpt) . ' table.wp-list-table th#title,
		.post-type-' . esc_attr($report_cpt) . ' table.wp-list-table td.column-title { text-align:left; }

		/* 受理票: 80px 中央寄せ */
		.post-type-' . esc_attr($report_cpt) . ' table.wp-list-table th#acf_file,
		.post-type-' . esc_attr($report_cpt) . ' table.wp-list-table td.column-acf_file { width:80px; text-align:center; }
	</style>';
});