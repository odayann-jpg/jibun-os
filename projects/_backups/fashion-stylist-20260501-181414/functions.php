<?php
/*----------------------------------------
	賢威7用 子テーマ
	
	第1版　　2016. 11. 28
	第2版　　2017. 11. 30

	株式会社 ウェブライダー
----------------------------------------*/

//---------------------------------------------------------------------------
//	賢威のベースを引き継ぐ基本設定
//---------------------------------------------------------------------------
add_action( 'wp_enqueue_scripts', 'theme_enqueue_styles' ); 
function theme_enqueue_styles() {
	wp_enqueue_style( 'keni_base', get_template_directory_uri() . '/base.css?20210709' );
	wp_enqueue_style( 'child_keni_base', get_stylesheet_directory_uri() . '/base.css', array('keni_base'));
	if (the_keni('mobile_layout') == 'y') {
		wp_enqueue_style( 'keni_rwd', get_template_directory_uri() . '/rwd.css' );
		wp_enqueue_style( 'child_keni_rwd', get_stylesheet_directory_uri() . '/rwd.css', array('keni_rwd'));
	}
};

add_action( 'admin_menu', 'theme_admin_styles' );
function theme_admin_styles() {
	wp_enqueue_style( 'keni_admin_css', get_template_directory_uri() . '/keni_admin.css' );
};


// サムネイルのサイズ　ここから //
add_filter( "keni_post_thumbnail_size", "set_keni_post_thumbnail_size", 10 );
function set_keni_post_thumbnail_size() {
    return array(  250, 200 );
}
// サムネイルのサイズ　ここまで //

// 大サイズの画像設定　ここから
add_filter( "keni_post_large_image_size", "set_keni_post_large_image_size", 10 );
function set_keni_post_large_image_size() {
    return array( 300, 300 );
}
// 大サイズの画像設定　ここまで　//

// 中サイズの画像設定　ここから
add_filter( "keni_post_middle_image_size", "set_keni_post_middle_image_size", 10 );
function set_keni_post_middle_image_size() {
    return array( 200, 200 );
}
// 中サイズの画像設定　ここまで　//

// 小サイズの画像設定 ここから //
add_filter( "keni_post_small_image_size", "set_keni_post_small_image_size", 10 );
function set_keni_post_small_image_size() {
    return array( 250, 200 );
}
// 小サイズの画像設定 ここまで //

// 極小サイズの画像設定 ここから　//
add_filter( "keni_post_ss_image_size", "set_keni_post_ss_image_size", 10 );
function set_keni_post_ss_image_size() {
    return array( 100, 100 );
}
// 極小サイズの画像設定 ここまで　//


function my_excerpt_more($more) {
  return '・・・';
}
add_filter('excerpt_more', 'my_excerpt_more');

/* 最新メディア掲載情報リスト */
function getMediaItems($atts) {
	extract(shortcode_atts(array(
		"num" => '' //最新メディア掲載情報リストの取得数
	), $atts));
	global $post;
	$oldpost = $post;
	$myposts = get_posts('numberposts='.$num.'&order=DESC&orderby=post_date&category=6');
	$metHtml='<div class="news"><a href="https://fashion-stylist.co.jp/category/media/">';
		foreach($myposts as $post) :
			setup_postdata($post);
			$thumbUrl = get_the_post_thumbnail_url($post->ID, 'medium');
			$contentTitle = get_the_title($post->ID);
			$metHtml.='<article class="news-item">';
			if( has_post_thumbnail() ){
				$metHtml.='<div class="news-thumb"><img src="'.$thumbUrl.'" alt="'.$contentTitle.'"></div>';
			}
			$metHtml.='<h3 class="news-title">'.$contentTitle.'</h3></article>';
		endforeach;
	$metHtml.='</a></div>';
	$post = $oldpost;
	wp_reset_postdata();
	return $metHtml;
}
add_shortcode("medialist", "getMediaItems");

/* SmartNewsを読み込む */
remove_filter('do_feed_rss2', 'do_feed_rss2', 10);
function custom_feed_rss2(){
	$rss2_file = '/feed-rss2.php';
	load_template(get_stylesheet_directory() . $rss2_file);
}
add_action('do_feed_rss2', 'custom_feed_rss2', 10);

/* Public Post Preview有効期限延長 */
add_filter( 'ppp_nonce_life', 'my_nonce_life' );
function my_nonce_life() {
	return 60 * 60 * 24 * 7;
}