<?php
// 全角/半角カナ非依存検索：posts_search フィルタ関数
if ( ! function_exists('n_kana_search_posts_search') ) :
function n_kana_search_posts_search( $search, $wp_query ) {
	if ( is_admin() ) return $search;

	$s = (string) $wp_query->get('s');
	if ( $s === '' ) return $search;

	global $wpdb;

	// スペース（半角/全角）区切り
	$terms = preg_split('/[\h\x{3000}]+/u', $s, -1, PREG_SPLIT_NO_EMPTY);
	if ( empty($terms) ) return $search;

	$groups = [];
	$params = [];

	foreach ( $terms as $t ) {
		$t = trim($t);
		if ( $t === '' ) continue;

		// バリアント（そのまま / 全角カタカナ / 半角カタカナ）
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

	// WPコアの他条件とは AND で結合される
	$where = ' AND ' . implode(' AND ', $groups);
	return $wpdb->prepare($where, $params);
}
endif;