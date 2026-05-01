<?php
/*----------------------------------------
	賢威7.1

	投稿内容のループエリア
	
	第1版　2015. 9.29

	株式会社 ウェブライダー
----------------------------------------*/

if (have_posts()) {
	while (have_posts()) : the_post(); ?>

	<article id="postz-<?php the_ID(); ?>" class="section-wrap">
        <div class="section-in">
        <div class="article-body">
        <?php if (get_the_post_thumbnail()) echo '<div class="eye-catch"><a href="'.get_the_permalink().'" title="'. the_title_attribute('echo=0') .'">'.get_the_post_thumbnail()."</a></div>\n"; ?>
            <h2 class="section-title"><a href="<?php the_permalink() ?>" title="<?php the_title_attribute(); ?>"><?php echo esc_html(get_the_title()); ?></a></h2>
            <p class="post-date"><time datetime="<?php the_time('Y-m-d'); ?>"><?php the_time(get_option('date_format')); ?></time></p>
            <?php if (the_keni('pv_view') == "y" && preg_match("/^[0-9]+$/",getViewPV(get_the_ID()))) { ?><p class="post-pv"><?php viewPV(); ?>PV</p><?php } ?>
<?php {
            $site_url = site_url();
            if (!preg_match("/\/$/", $site_url)) $site_url .= "/";
                $category_data = get_category_keni();
                if (!empty($category_data)) echo "<div class=\"post-cat\">\n".$category_data."\n</div>\n";
            } ?>
<?php $post_type = get_query_var('post_type');
            if ((!empty($post_type) && the_keni('social_archive_view') == "y") || (is_front_page() && empty($post_type) && the_keni('social_top_archive_view') == "y") || (!is_front_page() && the_keni('social_archive_view') == "y")) get_template_part('social-button'); ?>
        <p><?php echo strip_tags(get_the_excerpt()); ?></p>
        <p class="link-next"><a href="<?php the_permalink() ?>"><?php _e('see more', 'keni'); ?></a></p>
        </div>
        </div>
    </article>

<?php endwhile;
	pager_keni();
}
?>