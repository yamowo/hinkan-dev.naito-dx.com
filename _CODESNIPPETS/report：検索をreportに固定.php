<?php
/**
 * 通常検索を report のみに限定（?post_type=report を補完）
 */
add_action('pre_get_posts', function( WP_Query $q ){
	if ( is_admin() || ! $q->is_main_query() ) return;
	if ( $q->is_search() && ! $q->get('post_type') ) {
		$q->set('post_type', 'report');
	}
});
