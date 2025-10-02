<?php
/**
 * Report Quick Edit Fields
 *
 * 概要:
 * - 管理一覧の「クイック編集」に「発生日(ACF)」「メーカー(タクソノミー)」を追加
 * - 一覧行の既存値をクイック編集フォームに自動セット
 * - 保存時に ACF フィールド更新／タクソノミー反映
 *
 * 前提:
 * - 投稿タイプ: report
 * - 日付フィールド(ACF): $acf_date_field（返り値 Ymd or Y-m-d 想定）
 * - メーカー taxonomy: cpt_maker（単一選択想定。複数にしたい場合は select を multiple に）
 *
 * 既知の制約:
 * - 「メーカー」は最初のタームを既定値としてセットします（複数付与時は先頭のみ）
 */

$report_cpt     = 'report';
$acf_date_field = 'acf_event_date';
$tax_maker      = 'cpt_maker';

/**
 * クイック編集フォームにフィールドを描画
 */
add_action('quick_edit_custom_box', function ($column_name, $post_type) use ($report_cpt, $tax_maker) {
	if ($post_type !== $report_cpt) return;

	if ($column_name === 'report_date' || $column_name === 'maker') {
		// 用語一覧（メーカー）
		$maker_terms = get_terms([
			'taxonomy'   => $tax_maker,
			'hide_empty' => false,
			'orderby'    => 'id',
			'order'      => 'ASC',
		]);
		?>
		<fieldset class="inline-edit-col-left">
			<div class="inline-edit-col">
				<?php if ($column_name === 'report_date'): ?>
					<div class="inline-edit-group">
						<label>
							<span class="title">発生日</span>
							<input type="date" name="report_quick[date]" value="" />
						</label>
					</div>
				<?php endif; ?>

				<?php if ($column_name === 'maker'): ?>
					<div class="inline-edit-group">
						<label>
							<span class="title">メーカー</span>
							<select name="report_quick[maker]" style="min-width:160px;">
								<option value="">— 選択 —</option>
								<?php if (!is_wp_error($maker_terms)) : foreach ($maker_terms as $t) : ?>
									<option value="<?php echo esc_attr($t->term_id); ?>">
										<?php echo esc_html($t->name); ?>
									</option>
								<?php endforeach; endif; ?>
							</select>
						</label>
					</div>
				<?php endif; ?>
			</div>
		</fieldset>
		<?php
	}
}, 10, 2);

/**
 * 一覧行に現在値を「隠しデータ」として埋め込み（JSが拾ってフォームにセット）
 * 既存のカラム出力に追記する形（列レンダリング後に付与）
 */
add_action("manage_{$report_cpt}_posts_custom_column", function ($column, $post_id) use ($acf_date_field, $tax_maker) {
	if ($column === 'report_date' || $column === 'maker') {
		// 発生日（Y-m-dに正規化）
		$date_val = function_exists('get_field') ? get_field($acf_date_field, $post_id) : get_post_meta($post_id, $acf_date_field, true);
		$ymd_dash = '';
		if ($date_val instanceof DateTime) {
			$ymd_dash = $date_val->format('Y-m-d');
		} elseif (is_string($date_val) && $date_val !== '') {
			if (preg_match('/^\d{8}$/', $date_val)) {
				$ymd_dash = substr($date_val,0,4) . '-' . substr($date_val,4,2) . '-' . substr($date_val,6,2);
			} else {
				$ts = strtotime($date_val);
				if ($ts) $ymd_dash = date('Y-m-d', $ts);
			}
		}
		// メーカー（最初のタームID）
		$terms = get_the_terms($post_id, $tax_maker);
		$maker_id = (is_array($terms) && $terms && !is_wp_error($terms)) ? (int)$terms[0]->term_id : 0;

		// JS が見つけるデータ属性（行ID: #post-{$post_id} の下に仕込む）
		echo '<span class="report-quickseed" data-post="' . esc_attr($post_id) . '" data-date="' . esc_attr($ymd_dash) . '" data-maker="' . esc_attr($maker_id) . '" style="display:none;"></span>';
	}
}, 20, 2);

/**
 * クイック編集オープン時に現在値をフォームへ投入
 */
add_action('admin_footer-edit.php', function () use ($report_cpt) {
	$screen = get_current_screen();
	if (!$screen || $screen->post_type !== $report_cpt) return;
	?>
	<script>
		(function($){
			const $wp_inline_edit = inlineEditPost.edit;
			inlineEditPost.edit = function( id ) {
				$wp_inline_edit.apply( this, arguments );
				let postId = 0;
				if ( typeof(id) === 'object' ) postId = parseInt( this.getId(id) , 10);
				if (!postId) return;

				const $row   = $( '#post-' + postId );
				const $edit  = $( '#edit-' + postId ); // クイック編集フォーム行
				const $seed  = $row.find('.report-quickseed');

				if ($seed.length) {
					const date  = $seed.data('date') || '';
					const maker = $seed.data('maker') || '';

					$edit.find('input[name="report_quick[date]"]').val( date );
					$edit.find('select[name="report_quick[maker]"]').val( maker );
				}
			};
		})(jQuery);
	</script>
	<?php
});

/**
 * 保存処理（クイック編集のPOSTを受けて更新）
 */
add_action('save_post_' . $report_cpt, function ($post_id) use ($acf_date_field, $tax_maker) {
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
	if ( ! current_user_can('edit_post', $post_id) ) return;
	if ( empty($_POST['report_quick']) || !is_array($_POST['report_quick']) ) return;

	$data = wp_unslash( $_POST['report_quick'] );

	// 発生日（input type="date" → Ymd へ正規化して保存）
	if ( isset($data['date']) ) {
		$date_dash = trim( (string)$data['date'] ); // 例: 2025-08-01
		$save_val  = '';
		if ($date_dash !== '') {
			// ACFの返り値/保存形式をYmdに寄せる（ACF側がY-m-d保存でも動作OK）
			if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_dash)) {
				$save_val = str_replace('-', '', $date_dash); // 20250801
			} else {
				$ts = strtotime($date_dash);
				if ($ts) $save_val = date('Ymd', $ts);
			}
		}
		if ( function_exists('update_field') ) {
			update_field($acf_date_field, $save_val, $post_id);
		} else {
			update_post_meta($post_id, $acf_date_field, $save_val);
		}
	}

	// メーカー（単一選択を想定。未選択はクリア）
	if ( array_key_exists('maker', $data) ) {
		$term_id = (int)$data['maker'];
		if ($term_id > 0) {
			wp_set_object_terms($post_id, [$term_id], $tax_maker, false);
		} else {
			// クリア
			wp_set_object_terms($post_id, [], $tax_maker, false);
		}
	}
});
