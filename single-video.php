<?php
/**
 * Template for displaying single video posts
 * 
 * Copy this file to your theme's directory to customize the video display
 * Location: /wp-content/plugins/video-seo-pro/templates/single-video.php
 * 
 * This is optional - the plugin will automatically embed videos without this template
 */

get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

        <?php
        while (have_posts()) :
            the_post();
            
            $video_url = get_post_meta(get_the_ID(), '_video_url', true);
            $youtube_id = get_post_meta(get_the_ID(), '_youtube_id', true);
            $video_duration = get_post_meta(get_the_ID(), '_video_duration', true);
            $video_thumbnail = get_post_meta(get_the_ID(), '_video_thumbnail', true);
            ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                
                <header class="entry-header">
                    <?php the_title('<h1 class="entry-title">', '</h1>'); ?>
                    
                    <div class="entry-meta">
                        <span class="posted-on">
                            <?php echo get_the_date(); ?>
                        </span>
                        
                        <?php if ($video_duration): ?>
                            <span class="video-duration">
                                | Duration: <?php echo gmdate('i:s', $video_duration); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </header>

                <div class="entry-content">
                    
                    <?php
                    // Display video player using shortcode
                    // This automatically handles YouTube, Vimeo, and direct video files
                    echo do_shortcode('[video_player]');
                    ?>
                    
                    <?php the_content(); ?>
                    
                    <?php
                    wp_link_pages(array(
                        'before' => '<div class="page-links">' . esc_html__('Pages:', 'textdomain'),
                        'after'  => '</div>',
                    ));
                    ?>
                    
                </div>

                <footer class="entry-footer">
                    <?php
                    // Display categories and tags
                    $categories_list = get_the_category_list(', ');
                    if ($categories_list) {
                        echo '<span class="cat-links">Categories: ' . $categories_list . '</span>';
                    }
                    
                    $tags_list = get_the_tag_list('', ', ');
                    if ($tags_list) {
                        echo '<span class="tags-links">Tags: ' . $tags_list . '</span>';
                    }
                    ?>
                </footer>

            </article>

            <?php
            // Display comments if enabled
            if (comments_open() || get_comments_number()) :
                comments_template();
            endif;

        endwhile;
        ?>

    </main>
</div>

<?php
get_sidebar();
get_footer();