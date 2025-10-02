<?php
/**
 * 本ファイルは「the_content」フィルターで、カスタム投稿タイプ report の本文を
 * ACF の入力値（報告項目）で置き換えて出力します。
 *
 * 仕様:
 * - 発生日（acf_event_date; 返り値例: 'Ymd'）をサイトの date_format で表示
 * - テキスト項目（原因/対策/補足）を見出し+本文ブロックで出力
 * - 添付ファイル（acf_attachment_file; 返り値=配列）は a[download] でリンク出力
 *
 * セキュリティ:
 * - テキスト: esc_html()
 * - リッチテキスト: wp_kses_post( wpautop(...) )
 * - URL: esc_url()
 *
 * @package ReportList
 */

/**
 * 投稿本文を「ACF:報告項目」の出力に置き換える。
 *
 * フィルター: {@see 'the_content'}
 *
 * 条件:
 * - カスタム投稿タイプ 'report' のシングル表示時のみ置き換え
 * - それ以外は元の本文をそのまま返す
 *
 * 出力構造（概要）:
 * <div class="report-fields">
 *   <section class="report-block">...（発生日）...</section>
 *   <section class="report-block">...（テキスト項目）...</section>*
 *   <p class="report-attach"><a href="...">... をダウンロード</a></p>
 * </div>
 *
 * @param string $content 元の投稿本文 HTML。
 * @return string 置き換え後の本文 HTML。
 * @since 1.0.0
 */
add_filter( 'the_content', function ( $content ) {
	// CPT 'report' のシングルでなければ元の本文を返す。
	if ( ! is_singular( 'report' ) ) {
		return $content;
	}

	$out = '<div class="report-fields">';

	/**
	 * 発生日の出力
	 *
	 * @var string|int|false $d ACF フィールド acf_event_date の値（例: 'Ymd'）/未設定時 false。
	 */
	$d = get_field( 'acf_event_date' );
	if ( $d ) {
		$dt = DateTime::createFromFormat( 'Ymd', $d );
		if ( $dt ) {
			$out .= '<section class="report-block"><h2>発生日</h2><div>' .
				esc_html( date_i18n( get_option( 'date_format' ), $dt->getTimestamp() ) ) .
				'</div></section>';
		}
	}

	/**
	 * テキスト項目のマッピング
	 *
	 * @var array<string,string> $map ラベル => ACFキー の対応表。
	 */
	$map = [
		'発生原因'         => 'acf_cause_occurrence',
		'流出原因'         => 'acf_cause_outflow',
		'発生対策'         => 'acf_counter_occurrence',
		'流出対策'         => 'acf_counter_outflow',
		'重ポ・注意・補足' => 'acf_notes_important',
	];

	foreach ( $map as $label => $key ) {
		/**
		 * @var string|string[]|array|null $v ACF の get_field($key) の返り値。
		 * 基本は文字列想定だが、柔軟に wp_kses_post() で許可タグを通す。
		 */
		$v = get_field( $key );
		if ( $v ) {
			$out .= '<section class="report-block"><h2>' . esc_html( $label ) . '</h2><div>' .
				wp_kses_post( wpautop( $v ) ) .
				'</div></section>';
		}
	}

	/**
	 * 添付ファイルの出力
	 *
	 * @var array<string,mixed>|false $file ACF acf_attachment_file（返り値=配列）。未設定時は false。
	 * 想定キー:
	 * - url       : string ダウンロードURL
	 * - filename  : string（任意）表示用ファイル名
	 */
	$file = get_field( 'acf_attachment_file' );
	if ( ! empty( $file['url'] ) ) {
		/**
		 * 表示名の決定:
		 * - filename が空でなければそれを使用
		 * - なければ URL のパス部分から basename を抽出
		 *
		 * @var string $name 表示用のファイル名。
		 */
		$name = ( isset( $file['filename'] ) && $file['filename'] !== '' )
			? $file['filename']
			: basename( parse_url( $file['url'], PHP_URL_PATH ) );

		$out .= '<p class="report-attach"><a href="' . esc_url( $file['url'] ) . '" download>' .
			esc_html( $name ) . ' をダウンロード</a></p>';
	}

	$out .= '</div>';

	return $out;
} );
