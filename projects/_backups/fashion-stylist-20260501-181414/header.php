<?php
/**
 * header.php
 */
global $post;
$pid = "";
if ( isset( $post ) ) $pid = $post->ID;
?><!DOCTYPE html>
<html lang="ja"
      class="<?php echo getPageLayout( $pid ); ?>"<?php if ( the_keni( 'gp_view' ) == "y" ) { ?> itemscope itemtype="http://schema.org/<?php echo getMicroCodeType(); ?>"<?php } ?>>
<head prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb#">
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-5PCX38B');</script>
<!-- End Google Tag Manager -->
    <title><?php title_keni(); ?></title>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
	<?php if ( the_keni( 'mobile_layout' ) == "y" ) { ?>
        <meta name="viewport" content="width=device-width, initial-scale=1.0"><?php } ?>

	<?php if ( ! the_keni( 'view_meta' ) ) { ?>
		<?php if ( the_keni( 'view_meta_keyword' ) && the_keni( 'view_meta_keyword' ) == "y" ) { ?>
            <meta name="keywords" content="<?php keyword_keni(); ?>">
		<?php } ?>
		<?php if ( the_keni( 'view_meta_description' ) && the_keni( 'view_meta_description' ) == "y" ) { ?>
            <meta name="description" content="<?php description_keni(); ?>">
		<?php }
	} elseif ( the_keni( 'view_meta' ) == "y" ) { ?>
        <meta name="keywords" content="<?php keyword_keni(); ?>">
        <meta name="description" content="<?php description_keni(); ?>">
	<?php }
	wp_enqueue_script( 'jquery' );
	if ( get_option( 'blog_public' ) != false ) {
		echo getIndexFollow();
	}
	canonical_keni();
	pageRelNext();

	wp_head();

	facebook_keni();
	tw_cards_keni();
	microdata_keni();

	if ( function_exists( "get_site_icon_url" ) && get_site_icon_url() == "" ) { ?>
        <link rel="shortcut icon" type="image/x-icon" href="<?php echo get_template_directory_uri(); ?>/favicon.ico">
        <link rel="apple-touch-icon" href="<?php echo get_template_directory_uri(); ?>/images/apple-touch-icon.png">
        <link rel="apple-touch-icon-precomposed"
              href="<?php echo get_template_directory_uri(); ?>/images/apple-touch-icon.png">
        <link rel="icon" href="<?php echo get_template_directory_uri(); ?>/images/apple-touch-icon.png">
	<?php } ?>
    <!--[if lt IE 9]>
    <script src="<?php echo get_template_directory_uri(); ?>/js/html5.js"></script><![endif]-->
	<?php echo do_shortcode( the_keni( 'meta_text' ) ) . "\n";
	if ( is_single() || is_page() ) {
		echo get_post_meta( $pid, 'page_tags', true ) . "\n";
	}
	?>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.0.0/jquery.min.js"></script>
    <link rel="stylesheet" type="text/css" href="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css"/>
    <script type="text/javascript" src="//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
    
</head>
<?php
$gnav = ( ( get_globalmenu_keni( 'top_menu' ) == "" ) || ( ( is_front_page() || is_home() || is_singular() ) && get_post_meta( $pid, 'menu_view', true ) == "n" ) ) ? "no-gn" : "";    // メニューを表示しない場合は、classにno-gnを設定する

// ランディングページで画像をフルサイズで表示する
if ( is_singular( LP_DIR ) && get_post_meta( $pid, 'fullscreen_view', true ) == "y" ) {
$gnav .= ( $gnav != "" ) ? " lp" : "lp"; ?>
<body <?php body_class( $gnav ); ?>>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-5PCX38B"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
<?php echo do_shortcode( the_keni( 'body_text' ) ) . "\n"; ?>
<div class="container">
    <header id="top"
            class="site-header full-screen"<?php if ( get_post_meta( $pid, 'header_image', true ) != "" ) { ?> style="background-image: url(<?php echo get_post_meta( $pid, 'header_image', true ); ?>)"<?php } ?>>
        <div class="site-header-in">
            <div class="site-header-conts">
                <h1 class="site-title"><?php echo ( get_post_meta( $pid, 'page_h1', true ) ) ? esc_html( get_post_meta( $pid, 'page_h1', true ) ) : get_h1_keni(); ?></h1>
				<?php echo get_post_meta( $pid, 'catch_text', true ) ? "<p class=\"lp-catch\">" . esc_html( get_post_meta( $pid, 'catch_text', true ) ) . "</p>" : ""; ?>
                <p><a href="#main"><img
                                src="<?php echo get_template_directory_uri(); ?>/images/common/icon-arw-full-screen.png"
                                alt="メインへ" width="48" height="48"></a></p>
            </div>
        </div>
    </header>
	<?php
	if ( strpos( $gnav, "no-gn" ) === false ) { ?>
        <!--▼グローバルナビ-->
        <nav class="global-nav">
            <div class="global-nav-in">
                <div class="global-nav-panel"><span class="btn-global-nav icon-gn-menu">メニュー</span></div>
                <ul id="menu">
					<?php echo get_globalmenu_keni( 'top_menu' ); ?>
                </ul>
            </div>
        </nav>
        <!--▲グローバルナビ-->
	<?php }

	// それ以外の場合
	} else { ?>
    <body <?php body_class( $gnav ); ?>>
	<?php echo do_shortcode( the_keni( 'body_text' ) ) . "\n"; ?>
    <div class="container">
        <header id="top" class="site-header <?php if ( is_singular( LP_DIR ) ) {
			echo 'normal-screen';
		} ?>">
            <div class="site-header-in">
                <div class="site-header-conts">
					<?php if ( is_singular( LP_DIR ) ) {
						echo '<h1 class="site-title">';
						echo get_h1_keni();
						echo "</h1>\n";
						echo ( get_post_meta( $pid, 'catch_text', true ) ) ? "<p class=\"lp-catch\">" . esc_html( get_post_meta( $pid, 'catch_text', true ) ) . "</p>\n" : ""; ?>
					<?php } elseif ( is_front_page() ) { ?>
                        <h1 class="site-title"><a
                                    href="<?php echo esc_url( home_url() ); ?>"><?php echo ( the_keni( 'site_logo' ) != "" ) ? "<img src=\"" . the_keni( 'site_logo' ) . "\" alt=\"" . esc_html( get_bloginfo( 'name' ) ) . "\" />" : esc_html( get_bloginfo( 'name' ) ); ?></a>
                        </h1>
					<?php } else { ?>
                        <p class="site-title"><a
                                    href="<?php echo esc_url( home_url() ); ?>"><?php echo ( the_keni( 'site_logo' ) != "" ) ? "<img src=\"" . the_keni( 'site_logo' ) . "\" alt=\"" . esc_html( get_bloginfo( 'name' ) ) . "\" />" : esc_html( get_bloginfo( 'name' ) ); ?></a>
                        </p>
					<?php } ?>
                    
                    <?php

			if ( $gnav == "" ) { ?>
                <!--▼グローバルナビ-->
                <nav class="global-nav">
                    <div class="global-nav-in">
                        <div class="global-nav-panel"><span class="btn-global-nav icon-gn-menu">メニュー</span></div>
                        <ul id="menu">
							<!--
                            <li class="menu1"><a href="/beginner/">初めての方へ</a></li>
                            <li class="menu2"><a href="/coordinate/">コーディネート事例</a></li>
                            <li class="menu3"><a href="/service/">サービス紹介</a></li>
                            -->
                            <li class="menu4"><a href="/stylist/">スタイリスト紹介</a></li>
                            <li class="menu5"><a href="/beginner/#qa-top">よくあるご質問</a></li>
                            <li class="menu6"><a href="/shop/">店舗情報</a></li>
                            <li class="menu7"><a href="/blog/">ブログ</a></li>
                            <li class="menu8"><a href="/contact/">お問い合わせ</a></li>
                        </ul>
                    </div>
                </nav>
                <!--▲グローバルナビ-->
                    
                </div>
            </div>
			
			<?php }

			if (is_front_page() && (!isset($_GET['post_type']) || $_GET['post_type'] == "")) { ?>
		<?php if (wp_is_mobile()) : ?>
    <div class="flickity-syncer fade">
        <a href="/beginner/"><img src="http://fashion-stylist.co.jp/wp-content/themes/fsj_child/images/slide1_m.png"/></a>
        <a href="/news/stylishchange/"><img src="http://fashion-stylist.co.jp/wp-content/themes/fsj_child/images/slide-sc_m.jpg"/></a>
        <a href="/beginner/"><img src="http://fashion-stylist.co.jp/wp-content/themes/fsj_child/images/slide2_m.png"/></a>
        <a href="/beginner/"><img src="http://fashion-stylist.co.jp/wp-content/themes/fsj_child/images/slide3_m.png"/></a>
    </div>
<?php else: ?>
    <div class="flickity-syncer fade">
        <a href="/beginner/"><img src="http://fashion-stylist.co.jp/wp-content/themes/fsj_child/images/slide1.png"/></a>
        <a href="/news/stylishchange/"><img src="http://fashion-stylist.co.jp/wp-content/themes/fsj_child/images/slide-sc.jpg"/></a>
        <a href="/beginner/"><img src="http://fashion-stylist.co.jp/wp-content/themes/fsj_child/images/slide2.png"/></a>
        <a href="/beginner/"><img src="http://fashion-stylist.co.jp/wp-content/themes/fsj_child/images/slide3.png"/></a>
    </div>
<?php endif; ?>

<script>
$('.fade').slick({
  arrows: false,
  infinite: true,
  speed: 500,
  fade: true,
  cssEase: 'linear',
  autoplay: true,
  autoplaySpeed: 4000
});
</script>

<?php }else if (is_page('13')) { ?>
        <div class="main-image">
            <div class="main-image-in-text"><h1 class="img-title top-img"><img src="http://fashion-stylist.co.jp/wp-content/themes/fsj_child/images/coordinate/coordinate_header_pc.png" alt="コーディネート事例" class="image-switch"></h1></div>
		</div>
<?php }else if (is_page('17')) { ?>
        <div class="main-image">
            <div class="main-image-in-text"><h1 class="img-title top-img"><img src="http://fashion-stylist.co.jp/wp-content/themes/fsj_child/images/coordinate/coordinate_header_pc.png" alt="コーディネート事例" class="image-switch"></h1></div>
		</div>
<?php } else if (is_page('11')) { ?>
        <div class="main-image">
            <div class="main-image-in-text"><h1 class="img-title top-img"><img src="http://fashion-stylist.co.jp/wp-content/themes/fsj_child/images/beginner/beginner_header_pc.png" alt="初めての方へ" class="image-switch"></h1></div>
		</div>
<?php } else if (is_page('21')) { ?>
        <div class="main-image">
            <div class="main-image-in-text"><h1 class="img-title top-img"><img src="http://fashion-stylist.co.jp/wp-content/themes/fsj_child/images/service/service_header_pc.png" alt="サービス紹介" class="image-switch"></h1></div>
		</div>
<?php } else if (is_page('24')) { ?>
        <div class="main-image">
            <div class="main-image-in-text"><h1 class="img-title top-img"><img src="http://fashion-stylist.co.jp/wp-content/themes/fsj_child/images/stylist/stylist_header_pc.png" alt="スタイリスト紹介" class="image-switch"></h1></div>
		</div>
<?php } else if (is_page('28')) { ?>
        <div class="main-image">
            <div class="main-image-in-text"><h1 class="img-title top-img"><img src="http://fashion-stylist.co.jp/wp-content/themes/fsj_child/images/event/event_header_pc.png" alt="セミナー・イベント情報" class="image-switch"></h1></div>
		</div>
<?php } else if (is_page('32')) { ?>
        <div class="main-image">
            <div class="main-image-in-text"><h1 class="img-title top-img"><img src="http://fashion-stylist.co.jp/wp-content/themes/fsj_child/images/shop/shop_header_pc.png" alt="店舗情報" class="image-switch"></h1></div>
		</div>
<?php } else if (is_page('47')) { ?>
        <div class="main-image">
            <div class="main-image-in-text"><h1 class="img-title top-img"><img src="http://fashion-stylist.co.jp/wp-content/themes/fsj_child/images/contact/contact_header_pc.png" alt="お問い合わせ" class="image-switch"></h1></div>
		</div>
<?php } else if (is_home()) { ?>
        <div class="main-image">
            <div class="main-image-in-text"><h1 class="img-title top-img"><img src="http://fashion-stylist.co.jp/wp-content/themes/fsj_child/images/blog/blog_header_pc.png" alt="ブログ" class="image-switch"></h1></div>
		</div>
<?php } else { ?>
        <div class="main-image">
            <div class="main-image-in-text"></div>
		</div>
<?php } ?>
	</header>
		<?php
		}
		?>
        <!--▲サイトヘッダー-->
