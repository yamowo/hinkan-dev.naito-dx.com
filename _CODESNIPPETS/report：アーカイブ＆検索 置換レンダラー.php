<?php
/**
 * report系のアーカイブ／タクソノミー／検索を
 * テーマのテンプレートを使わず、その場で [report_list] を描画して置き換える。
 *
 * 置換対象:
 * - /report/ などの CPT アーカイブ（is_post_type_archive('report')）
 * - メーカー別タクソノミー（is_tax('cpt_maker')）
 * - 検索結果（post_type=report の検索）
 *
 * 並び替え:
 * - ?rl_sort=event|post を引き継いでショートコードに渡す
 */
add_action('template_redirect', function () {
	if ( is_admin() ) return;

	// 置換対象ページか？
	$is_report_archive = is_post_type_archive('report');
	$is_report_tax     = is_tax('cpt_maker');
	$is_report_search  = is_search() && (
		get_query_var('post_type') === 'report'
		|| ( isset($_GET['post_type']) && $_GET['post_type'] === 'report' )
	);

	if ( ! ( $is_report_archive || $is_report_tax || $is_report_search ) ) return;

	// 並び替えパラメータを引き継ぎ（省略時 event）
	$sort = ( isset($_GET['rl_sort']) && $_GET['rl_sort'] === 'post' ) ? 'post' : 'event';

	// ▼ ここから「テーマのヘッダー／フッター」を呼びつつ、中身はショートコードで置換
	get_header();

	echo '<main id="main" class="l-main">';

	// ページタイトル（SWELL系クラス）
	echo '<h1 class="c-pageTitle"><span class="c-pageTitle__inner">';
	if ( $is_report_archive ) {
		post_type_archive_title();
	} elseif ( $is_report_tax ) {
		single_term_title();
	} else {
		echo esc_html__('不具合報告：検索結果', 'default');
	}
	echo '</span></h1>';

	// 並び替えUIの簡易リンク（任意／不要なら削除OK）
	// $current_url = remove_query_arg( 'paged' );
	// $link_event  = esc_url( add_query_arg( 'rl_sort', 'event', $current_url ) );
	// $link_post   = esc_url( add_query_arg( 'rl_sort', 'post',  $current_url ) );
	// echo '<p class="u-mb-15"><a href="'.$link_event.'">発生日順</a> | <a href="'.$link_post.'">投稿日時順</a></p>';

	// ショートコードをそのまま呼び出し（あなたの [report_list] を再利用）
	echo do_shortcode( '[report_list sort="'.$sort.'"]' );

	echo '</main>';

	get_footer();

	// テーマの既定テンプレートを読み込ませない
	exit;
});
