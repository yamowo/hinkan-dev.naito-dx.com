<?php
/**
 * 検索を「全角/半角カナ非依存」にする。
 * - 入力語を正規化（全角カタカナ/HV結合処理）と半角カタカナに変換し、すべて OR 条件で検索。
 * - 語が複数ある場合は AND（WP標準と同じ）で結合。
 *
 * 要：mbstring（mb_convert_kana）
 */
add_filter('posts_search', function ($search, $wp_query) {
    if ( is_admin() || ! $wp_query->is_search() ) return $search;
    if ( method_exists($wp_query, 'is_main_query') && ! $wp_query->is_main_query() ) return $search;

    global $wpdb;

    $s = (string) $wp_query->get('s');
    if ($s === '') return $search;

    // スペース（半角/全角）区切り
    $terms = preg_split('/[\h\x{3000}]+/u', $s, -1, PREG_SPLIT_NO_EMPTY);

    $groups = [];
    $params = [];

    foreach ($terms as $t) {
        $t = trim($t);
        if ($t === '') continue;

        // バリアントを用意：そのまま / 全角カタカナ正規化 / 半角カタカナ
        $variants = [$t];
        if (function_exists('mb_convert_kana')) {
            // 全角カタカナに正規化（半角→全角K、濁点結合V、ひらがな→カタカナC）
            $variants[] = mb_convert_kana($t, 'KVC', 'UTF-8');  // 例: ｶﾅ / かな → カナ
            // 半角カタカナに正規化（カタカナ→半角k、ひらがな→半角カタカナh）
            $variants[] = mb_convert_kana($t, 'kh',  'UTF-8');  // 例: カナ / かな → ｶﾅ
        }
        $variants = array_values(array_unique($variants));

        // 各バリアントについて、タイトル/本文/抜粋の3箇所をORで
        $or_parts = [];
        foreach ($variants as $v) {
            $like = '%' . $wpdb->esc_like($v) . '%';
            $or_parts[] = "({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s)";
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        if ($or_parts) {
            // 語ごとに ( …variant1… OR …variant2… ) を作り、語間は AND で結合
            $groups[] = '(' . implode(' OR ', $or_parts) . ')';
        }
    }

    if (!$groups) return $search;

    // 既存の $search を置き換え（WPの他条件とは AND で結合される）
    $where = ' AND ' . implode(' AND ', $groups);
    return $wpdb->prepare($where, $params);
}, 10, 2);
