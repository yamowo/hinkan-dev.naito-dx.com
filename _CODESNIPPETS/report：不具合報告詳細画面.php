<?php
/**
 * 単一 report の本文を、指定テンプレートの表に全面置換する
 * - ACF:
 *   - acf_event_date (返り値: Ymd) → サイト設定の date_format で表示（現行仕様を踏襲）
 *   - acf_attachment_file (返り値: 配列) → filename 優先、無ければ URL から抽出して新規タブ（現行仕様を踏襲）
 *   - acf_failure_detail / acf_cause_occurrence / acf_cause_outflow
 *     / acf_counter_occurrence / acf_counter_outflow / acf_quantity
 * - Taxonomies (想定スラッグ):
 *   - cpt_maker, cpt_unit, cpt_owner, cpt_phase, cpt_responsibility, cpt_manufacturer_site
 *   - cpt_failure_mode_lv1 / lv2 / lv3
 *   - cpt_category_lv1 / lv2
 * - 追加: 一覧→詳細の検索条件を維持する「検索結果へ戻る」リンク（sessionStorage / referrer を優先）
 */
add_filter('the_content', function ($content) {
	if ( ! is_singular('report') ) return $content;

	$post_id = get_the_ID();

	// タクソノミーの表示名（「、」区切り）
	$term_list = function($tax) use ($post_id) {
		$terms = get_the_terms($post_id, $tax);
		if ( is_wp_error($terms) || empty($terms) ) return '';
		$names = array();
		foreach ( $terms as $t ) {
			$names[] = esc_html($t->name);
		}
		return implode('、', $names);
	};

	// 段落化＋許可タグ
	$fmt_text = function($v) {
		$v = is_string($v) ? $v : '';
		return wp_kses_post( wpautop( $v ) );
	};

	// 値が空ならダッシュ
	$nz = function($s) {
		return ($s === '' ? '—' : $s);
	};

	// ── ACF: 発生日（Ymd前提） → 現行仕様（サイトの date_format）
	$event_disp = '—';
	if ( function_exists('get_field') ) {
		$d = get_field('acf_event_date', $post_id);
		if ( $d ) {
			$dt = DateTime::createFromFormat('Ymd', (string) $d);
			if ( $dt ) {
				$event_disp = esc_html( date_i18n( get_option('date_format'), $dt->getTimestamp() ) );
			}
		}
	}

	// ── ACF: 受理票（現行仕様：新規タブ、downloadは付けない）
	$file_html = '—';
	if ( function_exists('get_field') ) {
		$file = get_field('acf_attachment_file', $post_id); // 返り値: 配列
		if ( is_array($file) && ! empty($file['url']) ) {
			$name = (isset($file['filename']) && $file['filename'] !== '')
				? $file['filename']
				: basename( parse_url( $file['url'], PHP_URL_PATH ) );
			$file_html = '<a href="' . esc_url($file['url']) . '" target="_blank" rel="noopener noreferrer">'
				. esc_html($name) . '&nbsp;<span data-icon="LsDownload" data-id="26" style="--the-icon-svg: url(data:image/svg+xml;base64,PHN2ZyBoZWlnaHQ9IjFlbSIgd2lkdGg9IjFlbSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiBhcmlhLWhpZGRlbj0idHJ1ZSIgdmlld0JveD0iMCAwIDQ4IDQ4Ij48cGF0aCBkPSJNNDQgNDJINGMtMS4xIDAtMiAuOS0yIDJzLjkgMiAyIDJoNDBjMS4xIDAgMi0uOSAyLTJzLS45LTItMi0yek0zMSAyMFY0YzAtMS4xLS45LTItMi0ySDE5Yy0xLjEgMC0yIC45LTIgMnYxNkg5LjRjLS45IDAtMS4zIDEuMS0uNyAxLjdsMTQuNSAxNS40Yy40LjUgMS4xLjUgMS41IDBsMTQuNS0xNS40Yy42LS42LjItMS43LS43LTEuN0gzMXoiPjwvcGF0aD48L3N2Zz4=)" aria-hidden="true" class="swl-inline-icon">&emsp;</span></a>';
		}
	}

	// ── タクソノミー
	$cpt_maker   = $term_list('cpt_maker');
	$cpt_unit    = $term_list('cpt_unit');
	$cpt_owner   = $term_list('cpt_owner');
	$cpt_phase   = $term_list('cpt_phase');
	$cpt_resp    = $term_list('cpt_responsibility');
	$cpt_mfgsite = $term_list('cpt_manufacturer_site');

	$fm1 = $term_list('cpt_failure_mode_lv1');
	$fm2 = $term_list('cpt_failure_mode_lv2');
	$fm3 = $term_list('cpt_failure_mode_lv3');
	$failure_mode_parts = array();
	if ($fm1 !== '') $failure_mode_parts[] = $fm1;
	if ($fm2 !== '') $failure_mode_parts[] = $fm2;
	if ($fm3 !== '') $failure_mode_parts[] = $fm3;
	$failure_mode = implode(' &gt; ', $failure_mode_parts);

	$cat1 = $term_list('cpt_category_primary');
	$cat2 = $term_list('cpt_category_secondary');
	$category_parts = array();
	if ($cat1 !== '') $category_parts[] = $cat1;
	if ($cat2 !== '') $category_parts[] = $cat2;
	$category = implode(' &gt; ', $category_parts);

	// ── ACF: テキスト系
	$failure_detail     = function_exists('get_field') ? $fmt_text(get_field('acf_failure_detail',     $post_id)) : '';
	$cause_occurrence   = function_exists('get_field') ? $fmt_text(get_field('acf_cause_occurrence',   $post_id)) : '';
	$cause_outflow      = function_exists('get_field') ? $fmt_text(get_field('acf_cause_outflow',      $post_id)) : '';
	$counter_occurrence = function_exists('get_field') ? $fmt_text(get_field('acf_counter_occurrence', $post_id)) : '';
	$counter_outflow    = function_exists('get_field') ? $fmt_text(get_field('acf_counter_outflow',    $post_id)) : '';
	$quantity_raw       = function_exists('get_field') ? get_field('acf_quantity',                     $post_id)   : '';
	$quantity           = ($quantity_raw === '' || $quantity_raw === null) ? '' : esc_html( (string) $quantity_raw );

	// ── 「検索結果へ戻る」リンク（既定はアーカイブ。JSで sessionStorage/referrer を優先）
	$archive_url   = get_post_type_archive_link('report');
	$back_link_html = '<p class="report-back"><a id="js-report-back" class="report-back__link" href="' . esc_url($archive_url) . '"><span data-icon="FiArrowLeft" data-id="29" style="--the-icon-svg: url(data:image/svg+xml;base64,PHN2ZyBoZWlnaHQ9IjFlbSIgd2lkdGg9IjFlbSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiBhcmlhLWhpZGRlbj0idHJ1ZSIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9ImN1cnJlbnRDb2xvciIgc3Ryb2tlLXdpZHRoPSIyIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiPjxsaW5lIHgxPSIxOSIgeTE9IjEyIiB4Mj0iNSIgeTI9IjEyIj48L2xpbmU+PHBvbHlsaW5lIHBvaW50cz0iMTIgMTkgNSAxMiAxMiA1Ij48L3BvbHlsaW5lPjwvc3ZnPg==)" aria-hidden="true" class="swl-inline-icon">&emsp;</span> 検索結果へ戻る</a></p>';

	// ── 出力（テンプレート準拠）
	$out  = $back_link_html;
	$out .= '<figure data-table-scrollable="both" data-cell1-fixed="both" class="p-reportTable wp-block-table min_width10_">';

	// ★★ ここを改修：colgroup で列幅を固定（ユニット=3列目、責任=5列目）
	// 変えたい場合は --swl-col3-width / --swl-col5-width の値を調整してください。
	$out .= '<table class="has-fixed-layout">';
	$out .= '<colgroup>'
	      .  '<col style="width:var(--swl-cell1-width);">'  // 1列目：行見出し
	      .  '<col style="width:var(--swl-col2-width);">'   // 2列目：値
	      .  '<col style="width:var(--swl-col3-width);">'   // 3列目：ユニット（見出しセル）
	      .  '<col style="width:var(--swl-col4-width);">'   // 4列目：値
	      .  '<col style="width:var(--swl-col5-width);">'   // 5列目：責任（見出しセル）
	      .  '<col>'                                        // 6列目：値
	      .  '</colgroup><tbody>';

	// 1行目：機種名 / 発生日
	$out .= '<tr>';
	$out .= '<th class="has-text-align-right" data-align="right">機種名</th>';
	$out .= '<td colspan="3">' . esc_html( get_the_title($post_id) ) . '</td>';
	$out .= '<th class="has-text-align-right -ws" data-align="right">発生日</th>';
	$out .= '<td>' . $nz($event_disp) . '</td>';
	$out .= '</tr>';

	// 2行目：メーカー / ユニット / 担当
	$out .= '<tr>';
	$out .= '<th class="has-text-align-right" data-align="right">メーカー</th>';
	$out .= '<td>' . $nz($cpt_maker) . '</td>';
	$out .= '<th class="has-text-align-right -wm" data-align="right">ユニット</th>';
	$out .= '<td>' . $nz($cpt_unit) . '</td>';
	$out .= '<th class="has-text-align-right" data-align="right">担当</th>';
	$out .= '<td>' . $nz($cpt_owner) . '</td>';
	$out .= '</tr>';

	// 3行目：フェーズ / 責任 / 製造元
	$out .= '<tr>';
	$out .= '<th class="has-text-align-right" data-align="right">フェーズ</th>';
	$out .= '<td>' . $nz($cpt_phase) . '</td>';
	$out .= '<th class="has-text-align-right -wm" data-align="right">責任</th>';
	$out .= '<td>' . $nz($cpt_resp) . '</td>';
	$out .= '<th class="has-text-align-right" data-align="right">製造元</th>';
	$out .= '<td>' . $nz($cpt_mfgsite) . '</td>';
	$out .= '</tr>';

	// 4行目：不具合モード
	$out .= '<tr>';
	$out .= '<th class="has-text-align-right" data-align="right">不具合モード</th>';
	$out .= '<td colspan="5">' . $nz($failure_mode) . '</td>';
	$out .= '</tr>';

	// 5行目：不具合分類
	$out .= '<tr>';
	$out .= '<th class="has-text-align-right" data-align="right">不具合分類</th>';
	$out .= '<td colspan="5">' . $nz($category) . '</td>';
	$out .= '</tr>';

	// 6行目：不具合内容
	$out .= '<tr>';
	$out .= '<th class="has-text-align-right" data-align="right">不具合内容</th>';
	$out .= '<td colspan="5">' . $nz($failure_detail) . '</td>';
	$out .= '</tr>';

	// 7行目：発生原因
	$out .= '<tr>';
	$out .= '<th class="has-text-align-right" data-align="right">発生原因</th>';
	$out .= '<td colspan="5">' . $nz($cause_occurrence) . '</td>';
	$out .= '</tr>';

	// 8行目：流出原因
	$out .= '<tr>';
	$out .= '<th class="has-text-align-right" data-align="right">流出原因</th>';
	$out .= '<td colspan="5">' . $nz($cause_outflow) . '</td>';
	$out .= '</tr>';

	// 9行目：発生対策
	$out .= '<tr>';
	$out .= '<th class="has-text-align-right" data-align="right">発生対策</th>';
	$out .= '<td colspan="5">' . $nz($counter_occurrence) . '</td>';
	$out .= '</tr>';

	// 10行目：流出対策
	$out .= '<tr>';
	$out .= '<th class="has-text-align-right" data-align="right">流出対策</th>';
	$out .= '<td colspan="5">' . $nz($counter_outflow) . '</td>';
	$out .= '</tr>';

	// 11行目：件数
	$out .= '<tr>';
	$out .= '<th class="has-text-align-right" data-align="right">件数</th>';
	$out .= '<td colspan="5">' . ($quantity !== '' ? $quantity : '—') . '</td>';
	$out .= '</tr>';

	// 12行目：受理票
	$out .= '<tr>';
	$out .= '<th class="has-text-align-right" data-align="right">受理票</th>';
	$out .= '<td colspan="5">' . $file_html . '</td>';
	$out .= '</tr>';

	$out .= '</tbody></table></figure>';

	// ── JS: 戻るリンクの href を sessionStorage / referrer で上書き
	//   - 一覧側で sessionStorage.setItem('reportLastListURL', location.href) を仕込んでおくと精度が高いです
	$out .= '<script>(function(){try{var a=document.getElementById("js-report-back");if(!a)return;var st=window.sessionStorage||null;var url="";if(st){url=st.getItem("reportLastListURL")||st.getItem("report_last_list_url")||"";}if(url && url.indexOf(location.origin)===0){a.href=url;return;}var ref=document.referrer||"";var same=ref.indexOf(location.origin)===0;var looksList=same&&(ref.indexOf("/report")!==-1||ref.indexOf("post_type=report")!==-1||ref.indexOf("vkfs_")!==-1||ref.indexOf("?s=")!==-1);if(looksList){a.href=ref;}}catch(e){}})();</script>';

	return $out;
}, 10);
