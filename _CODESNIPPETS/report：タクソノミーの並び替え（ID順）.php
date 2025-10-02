<?php
/**
 * タクソノミーの用語一覧を「登録順（ID昇順）」に並べるスニペット
 *
 * 概要:
 * - get_terms() / wp_get_object_terms() などの取得結果を登録順に並べ替える
 * - Gutenberg エディタや REST API でのタクソノミー用語一覧も登録順で返す
 * - 管理画面（クラシックチェックリスト）、フロントエンドともに有効
 *
 * 対象:
 * - 以下のタクソノミー（必要に応じて $target_taxonomies に追加）
 *   - cpt_maker, cpt_unit, cpt_owner, cpt_phase
 *   - cpt_responsibility, cpt_manufacturer_site
 *   - cpt_failure_mode_lv1, cpt_failure_mode_lv2, cpt_failure_mode_lv3
 *   - cpt_category_primary, cpt_category_secondary
 *
 * 実装ポイント:
 * - `get_terms_args` フィルターで WP 内部の get_terms() 系に適用
 * - `rest_term_query` フィルターで REST API 経由の取得結果に適用
 * - orderby: 'term_id' または 'id'、order: 'ASC'
 *
 * 注意点:
 * - REST API 側では 'orderby' => 'id' と 'order' => 'asc' を使用
 * - タクソノミーのリストに含まれていない場合は処理をスキップ
 */

add_action('init', function () {

  // 対象タクソノミー（必要に応じて追加）
  $target_taxonomies = [
    'cpt_maker',
    'cpt_unit',
    'cpt_owner',
    'cpt_phase',
    'cpt_responsibility',
    'cpt_manufacturer_site',
    'cpt_failure_mode_lv1',
    'cpt_failure_mode_lv2',
    'cpt_failure_mode_lv3',
    'cpt_category_primary',
    'cpt_category_secondary',
  ];

  /** 1) get_terms() 系：管理/フロント問わず登録順に */
  add_filter('get_terms_args', function ($args, $taxonomies) use ($target_taxonomies) {
    // 対象タクソノミーが含まれている場合に適用
    if (array_intersect((array) $taxonomies, $target_taxonomies)) {
      $args['orderby'] = 'term_id'; // 'id' でも可
      $args['order']   = 'ASC';
    }
    return $args;
  }, 10, 2);

  /** 2) REST 経由（Gutenbergパネルなど） */
  add_filter('rest_term_query', function ($args, $request) use ($target_taxonomies) {
    $tax = (array) $request->get_param('taxonomy');
    if (array_intersect($tax, $target_taxonomies)) {
      $args['orderby'] = 'id';
      $args['order']   = 'asc';
    }
    return $args;
  }, 10, 2);

});
