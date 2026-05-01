<!--▼サイトフッター-->
<footer class="site-footer">
	<div class="site-footer-in">
	<div class="site-footer-conts">
<?php	$footer = get_globalmenu_keni('footer_menu');
if ( $footer != "") { ?>
		<ul class="site-footer-nav"><?php	echo $footer; ?></ul>
<?php }
$comment = the_keni('footer_comment');
if ($comment != "") { ?>
<div class="site-footer-conts-area"><?php echo do_shortcode( richtext_formats($comment)); ?></div>
<?php } ?>
	</div>
	</div>
	<div class="copyright">
		<p><small>&copy; <?php echo date('Y'); ?> 株式会社リフレイム</small></p>
	</div>
</footer>
<!--▲サイトフッター-->


<!--▼ページトップ-->
<p class="page-top"><a href="#top"><img class="over" src="<?php echo esc_url(get_template_directory_uri()); ?>/images/common/page-top_off.png" width="80" height="80" alt="<?php _e('To the top', 'keni'); ?>"></a></p>
<!--▲ページトップ-->

</div><!--container-->

<?php wp_footer(); ?>
	
<?php echo do_shortcode( the_keni('body_bottom_text'))."\n"; ?>


<script type="text/javascript">
// スライドコンテンツを後ほど操作するための配列 (グローバル変数)
var flickitySyncer = [];

// ページ内の[.flickity-syncer]のエレメントを取得する
var elms = document.getElementsByClassName( "flickity-syncer" ) ;

// [elms]全てに、ループ処理でFlickityを適用する
for( var i=0,l=elms.length; l>i; i++ )
{
	flickitySyncer[i] = new Flickity( elms[i] , {
        imagesLoaded: true,
      wrapAround: true,
      contain: true,
      draggable: false,
      autoPlay: 6000,
      prevNextButtons: false,
            pageDots: false,} ) ;
}
</script>

<script type="text/javascript">
(function($) {
  // 置換の対象とするclass属性。
  var $elem = $('.image-switch');
  // 置換の対象とするsrc属性の末尾の文字列。
  var sp = '_sp.';
  var pc = '_pc.';
  // 画像を切り替えるウィンドウサイズ。
  var replaceWidth = 737;
  function imageSwitch() {
    // ウィンドウサイズを取得する。
    var windowWidth = parseInt($(window).width());
    // ページ内にあるすべての`.image-switch`に適応される。
    $elem.each(function() {
      var $this = $(this);
      // ウィンドウサイズが737px以上であれば_spを_pcに置換する。
      if(windowWidth >= replaceWidth) {
        $this.attr('src', $this.attr('src').replace(sp, pc));
      // ウィンドウサイズが737px未満であれば_pcを_spに置換する。
      } else {
        $this.attr('src', $this.attr('src').replace(pc, sp));
      }
    });
  }
  imageSwitch();
  // 動的なリサイズは操作後0.2秒経ってから処理を実行する。
  var resizeTimer;
  $(window).on('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function() {
      imageSwitch();
    }, 200);
  });
})( jQuery );
</script>


</body>
</html>