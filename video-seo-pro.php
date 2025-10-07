<?php
/**
 * Plugin Name: Video SEO Pro
 * Plugin URI: https://example.com/video-seo-pro
 * Description: Add video SEO features to your existing posts - no separate post type needed!
 * Version: 2.0.3
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: video-seo-pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class VideoSEOPro {
    private $version = '2.0.3';
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'video_analytics';

        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('add_meta_boxes', [$this, 'add_video_meta_boxes']);
        add_action('save_post', [$this, 'save_video_meta'], 10, 2);
        add_action('wp_head', [$this, 'add_video_schema']);
        add_action('wp_footer', [$this, 'add_analytics_tracking']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_filter('the_content', [$this, 'auto_embed_video']);
        add_shortcode('video_player', [$this, 'video_player_shortcode']);

        // Sitemap handling
        add_action('init', [$this, 'add_sitemap_rewrite']);
        add_action('template_redirect', [$this, 'serve_video_sitemap']);

        // AJAX handlers
        add_action('wp_ajax_fetch_youtube_data', [$this, 'ajax_fetch_youtube_data']);
        add_action('wp_ajax_track_video_view', [$this, 'ajax_track_video_view']);
        add_action('wp_ajax_nopriv_track_video_view', [$this, 'ajax_track_video_view']);
        add_action('wp_ajax_save_video_settings', [$this, 'ajax_save_settings']);
        add_action('wp_ajax_scan_video_posts', [$this, 'ajax_scan_video_posts']);
        add_action('wp_ajax_enable_video_features', [$this, 'ajax_enable_video_features']);

        register_activation_hook(__FILE__, [$this, 'activate']);
    }

    public function activate() {
        $this->create_analytics_table();
        $this->fix_analytics_table();
        flush_rewrite_rules();
    }

    private function create_analytics_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            user_id bigint(20) DEFAULT 0,
            session_id varchar(255) NOT NULL,
            view_date datetime DEFAULT CURRENT_TIMESTAMP,
            watch_time int(11) DEFAULT 0,
            completed tinyint(1) DEFAULT 0,
            user_agent text,
            ip_address varchar(45),
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY view_date (view_date)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function fix_analytics_table() {
        global $wpdb;
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_name} LIKE 'video_id'");

        if (!empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$this->table_name} CHANGE video_id post_id bigint(20) NOT NULL");
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            'Video SEO',
            'Video SEO',
            'manage_options',
            'video-seo-dashboard',
            [$this, 'dashboard_page'],
            'dashicons-video-alt3',
            30
        );

        add_submenu_page(
            'video-seo-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'video-seo-dashboard',
            [$this, 'dashboard_page']
        );

        add_submenu_page(
            'video-seo-dashboard',
            'Analytics',
            'Analytics',
            'manage_options',
            'video-seo-analytics',
            [$this, 'analytics_page']
        );

        add_submenu_page(
            'video-seo-dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'video-seo-settings',
            [$this, 'settings_page']
        );
    }

    public function add_video_meta_boxes() {
        add_meta_box(
            'video_seo_features',
            'Video SEO Features',
            [$this, 'video_features_meta_box'],
            'post',
            'side',
            'high'
        );

        add_meta_box(
            'video_details',
            'Video Details',
            [$this, 'video_details_meta_box'],
            'post',
            'normal',
            'high'
        );

        add_meta_box(
            'video_seo',
            'Video SEO',
            [$this, 'video_seo_meta_box'],
            'post',
            'normal',
            'high'
        );

        // Add analytics summary for posts with video enabled
        $screen = get_current_screen();
        if ($screen && $screen->id === 'post') {
            global $post;
            if ($post && get_post_meta($post->ID, '_is_video_post', true) === '1') {
                add_meta_box(
                    'video_analytics_summary',
                    'Video Analytics',
                    [$this, 'video_analytics_meta_box'],
                    'post',
                    'side',
                    'default'
                );
            }
        }
    }

    public function video_features_meta_box($post) {
        wp_nonce_field('video_meta_box', 'video_meta_box_nonce');

        $is_video_post = get_post_meta($post->ID, '_is_video_post', true);
        $video_url = get_post_meta($post->ID, '_video_url', true);

        ?>
        <div style="padding: 10px 0;">
            <label style="display: block; margin-bottom: 15px;">
                <input type="checkbox" id="is_video_post" name="is_video_post" value="1" <?php checked($is_video_post, '1'); ?>>
                <strong>This post contains a video</strong>
            </label>

            <div id="video-features-info" style="<?php echo $is_video_post === '1' ? '' : 'display:none;'; ?>">
                <p style="background: #d4edda; padding: 10px; border-radius: 4px; font-size: 12px;">
                    ✓ Video SEO features enabled<br>
                    ✓ Schema markup active<br>
                    ✓ Analytics tracking on<br>
                    <?php if ($video_url): ?>
                    ✓ Video will auto-embed
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#is_video_post').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#video-features-info').slideDown();
                    $('#video_details, #video_seo').closest('.postbox').slideDown();
                } else {
                    $('#video-features-info').slideUp();
                    $('#video_details, #video_seo').closest('.postbox').slideUp();
                }
            });

            // Hide video meta boxes if not a video post
            if (!$('#is_video_post').is(':checked')) {
                $('#video_details, #video_seo').closest('.postbox').hide();
            }
        });
        </script>
        <?php
    }

    public function video_details_meta_box($post) {
        $video_url = get_post_meta($post->ID, '_video_url', true);
        $video_duration = get_post_meta($post->ID, '_video_duration', true);
        $video_upload_date = get_post_meta($post->ID, '_video_upload_date', true);
        $video_thumbnail = get_post_meta($post->ID, '_video_thumbnail', true);
        $youtube_id = get_post_meta($post->ID, '_youtube_id', true);

        ?>
        <table class="form-table">
            <tr>
                <th><label for="video_url">Video URL</label></th>
                <td>
                    <input type="url" id="video_url" name="video_url" value="<?php echo esc_attr($video_url); ?>" class="large-text">
                    <button type="button" class="button" id="fetch-youtube-data">Import from YouTube</button>
                    <p class="description">YouTube, Vimeo, or direct video URL</p>
                </td>
            </tr>
            <tr>
                <th><label for="youtube_id">YouTube Video ID</label></th>
                <td>
                    <input type="text" id="youtube_id" name="youtube_id" value="<?php echo esc_attr($youtube_id); ?>" class="regular-text">
                    <p class="description">Auto-extracted from URL</p>
                </td>
            </tr>
            <tr>
                <th><label for="video_duration">Duration (seconds)</label></th>
                <td>
                    <input type="number" id="video_duration" name="video_duration" value="<?php echo esc_attr($video_duration); ?>" class="small-text">
                </td>
            </tr>
            <tr>
                <th><label for="video_upload_date">Upload Date</label></th>
                <td>
                    <input type="date" id="video_upload_date" name="video_upload_date" value="<?php echo esc_attr($video_upload_date); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="video_thumbnail">Thumbnail URL</label></th>
                <td>
                    <input type="url" id="video_thumbnail" name="video_thumbnail" value="<?php echo esc_attr($video_thumbnail); ?>" class="large-text">
                </td>
            </tr>
        </table>
        <div id="youtube-import-status" style="margin-top: 15px;"></div>
        <?php
    }

    public function video_seo_meta_box($post) {
        $seo_title = get_post_meta($post->ID, '_seo_title', true);
        $seo_description = get_post_meta($post->ID, '_seo_description', true);

        ?>
        <table class="form-table">
            <tr>
                <th><label for="seo_title">SEO Title</label></th>
                <td>
                    <input type="text" id="seo_title" name="seo_title" value="<?php echo esc_attr($seo_title); ?>" class="large-text">
                    <button type="button" class="button" id="generate-seo-title">Auto-Generate</button>
                </td>
            </tr>
            <tr>
                <th><label for="seo_description">Meta Description</label></th>
                <td>
                    <textarea id="seo_description" name="seo_description" rows="3" class="large-text"><?php echo esc_textarea($seo_description); ?></textarea>
                    <button type="button" class="button" id="generate-seo-description">Auto-Generate</button>
                </td>
            </tr>
        </table>
        <?php
    }

    public function video_analytics_meta_box($post) {
        global $wpdb;

        $total_views = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE post_id = %d",
            $post->ID
        ));

        $avg_watch_time = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(watch_time) FROM {$this->table_name} WHERE post_id = %d",
            $post->ID
        ));

        $completion_rate = $wpdb->get_var($wpdb->prepare(
            "SELECT (SUM(completed) / COUNT(*)) * 100 FROM {$this->table_name} WHERE post_id = %d",
            $post->ID
        ));

        ?>
        <div class="video-analytics-summary">
            <p><strong>Total Views:</strong> <?php echo number_format($total_views); ?></p>
            <p><strong>Avg Watch Time:</strong> <?php echo gmdate('i:s', (int)$avg_watch_time); ?></p>
            <p><strong>Completion Rate:</strong> <?php echo round($completion_rate, 1); ?>%</p>
        </div>
        <?php
    }

    public function save_video_meta($post_id, $post) {
        if (!isset($_POST['video_meta_box_nonce']) || !wp_verify_nonce($_POST['video_meta_box_nonce'], 'video_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if ($post->post_type !== 'post') {
            return;
        }

        $is_video_post = isset($_POST['is_video_post']) ? '1' : '0';
        update_post_meta($post_id, '_is_video_post', $is_video_post);

        if ($is_video_post === '0') {
            return;
        }

        if (isset($_POST['video_url'])) {
            $video_url = sanitize_text_field($_POST['video_url']);
            update_post_meta($post_id, '_video_url', $video_url);

            $youtube_id = $this->extract_youtube_id($video_url);
            if ($youtube_id) {
                update_post_meta($post_id, '_youtube_id', $youtube_id);
            }
        }

        if (isset($_POST['youtube_id'])) {
            update_post_meta($post_id, '_youtube_id', sanitize_text_field($_POST['youtube_id']));
        }

        if (isset($_POST['video_duration'])) {
            update_post_meta($post_id, '_video_duration', intval($_POST['video_duration']));
        }

        if (isset($_POST['video_upload_date'])) {
            update_post_meta($post_id, '_video_upload_date', sanitize_text_field($_POST['video_upload_date']));
        }

        if (isset($_POST['video_thumbnail'])) {
            update_post_meta($post_id, '_video_thumbnail', sanitize_text_field($_POST['video_thumbnail']));
        }

        if (isset($_POST['seo_title'])) {
            update_post_meta($post_id, '_seo_title', sanitize_text_field($_POST['seo_title']));
        }

        if (isset($_POST['seo_description'])) {
            update_post_meta($post_id, '_seo_description', sanitize_textarea_field($_POST['seo_description']));
        }

        if (empty($_POST['seo_title'])) {
            $this->generate_seo_title($post_id);
        }

        if (empty($_POST['seo_description'])) {
            $this->generate_seo_description($post_id);
        }
    }

    private function generate_seo_title($post_id) {
        $post = get_post($post_id);
        $title = $post->post_title . ' - Watch Now | Video';
        $title = substr($title, 0, 60);
        update_post_meta($post_id, '_seo_title', $title);
        return $title;
    }

    private function generate_seo_description($post_id) {
        $post = get_post($post_id);
        $content = wp_strip_all_tags($post->post_content);

        if (strlen($content) > 155) {
            $description = substr($content, 0, 152) . '...';
        } else {
            $description = $content ?: 'Watch ' . $post->post_title;
        }

        update_post_meta($post_id, '_seo_description', $description);
        return $description;
    }

    private function extract_youtube_id($url) {
        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return false;
    }

    public function ajax_fetch_youtube_data() {
        check_ajax_referer('video-seo-nonce', 'nonce');

        $video_url = sanitize_text_field($_POST['video_url']);
        $youtube_id = $this->extract_youtube_id($video_url);

        if (!$youtube_id) {
            wp_send_json_error('Invalid YouTube URL');
        }

        $api_key = get_option('video_seo_youtube_api_key');

        if (empty($api_key)) {
            wp_send_json_error('YouTube API key not configured');
        }

        $api_url = "https://www.googleapis.com/youtube/v3/videos?part=snippet,contentDetails&id={$youtube_id}&key={$api_key}";
        $response = wp_remote_get($api_url);

        if (is_wp_error($response)) {
            wp_send_json_error('Failed to fetch YouTube data');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['items'])) {
            wp_send_json_error('Video not found');
        }

        $video_data = $body['items'][0];
        $duration = $video_data['contentDetails']['duration'];

        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $matches);
        $hours = isset($matches[1]) ? (int)$matches[1] : 0;
        $minutes = isset($matches[2]) ? (int)$matches[2] : 0;
        $seconds = isset($matches[3]) ? (int)$matches[3] : 0;
        $total_seconds = ($hours * 3600) + ($minutes * 60) + $seconds;

        wp_send_json_success([
            'title' => $video_data['snippet']['title'],
            'description' => $video_data['snippet']['description'],
            'thumbnail' => $video_data['snippet']['thumbnails']['maxres']['url'] ?? $video_data['snippet']['thumbnails']['high']['url'],
            'duration' => $total_seconds,
            'upload_date' => date('Y-m-d', strtotime($video_data['snippet']['publishedAt'])),
            'youtube_id' => $youtube_id
        ]);
    }

    public function ajax_track_video_view() {
        check_ajax_referer('video-seo-nonce', 'nonce');

        global $wpdb;

        $post_id = intval($_POST['post_id']);
        $watch_time = intval($_POST['watch_time']);
        $completed = isset($_POST['completed']) ? 1 : 0;
        $session_id = sanitize_text_field($_POST['session_id']);

        $user_id = get_current_user_id();
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE post_id = %d AND session_id = %s",
            $post_id,
            $session_id
        ));

        if ($existing) {
            $wpdb->update(
                $this->table_name,
                ['watch_time' => $watch_time, 'completed' => $completed],
                ['id' => $existing],
                ['%d', '%d'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $this->table_name,
                [
                    'post_id' => $post_id,
                    'user_id' => $user_id,
                    'session_id' => $session_id,
                    'watch_time' => $watch_time,
                    'completed' => $completed,
                    'user_agent' => $user_agent,
                    'ip_address' => $ip_address
                ],
                ['%d', '%d', '%s', '%d', '%d', '%s', '%s']
            );
        }

        wp_send_json_success();
    }

    public function add_video_schema() {
        if (!is_single()) {
            return;
        }

        global $post;

        $is_video_post = get_post_meta($post->ID, '_is_video_post', true);
        if ($is_video_post !== '1') {
            return;
        }

        $video_url = get_post_meta($post->ID, '_video_url', true);
        if (empty($video_url)) {
            return;
        }

        $duration = get_post_meta($post->ID, '_video_duration', true);
        $upload_date = get_post_meta($post->ID, '_video_upload_date', true);
        $thumbnail = get_post_meta($post->ID, '_video_thumbnail', true);
        $seo_description = get_post_meta($post->ID, '_seo_description', true);

        if (empty($thumbnail)) {
            $thumbnail = get_the_post_thumbnail_url($post->ID, 'full');
        }

        if (empty($upload_date)) {
            $upload_date = get_the_date('Y-m-d', $post->ID);
        }

        if (empty($seo_description)) {
            $seo_description = get_the_excerpt($post);
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'VideoObject',
            'name' => get_the_title($post->ID),
            'description' => $seo_description,
            'thumbnailUrl' => $thumbnail,
            'uploadDate' => $upload_date,
            'contentUrl' => $video_url,
            'embedUrl' => $video_url
        ];

        if ($duration) {
            $schema['duration'] = 'PT' . $duration . 'S';
        }

        echo '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>' . "\n";
    }

    public function add_analytics_tracking() {
        if (!is_single()) {
            return;
        }

        global $post;

        $is_video_post = get_post_meta($post->ID, '_is_video_post', true);
        if ($is_video_post !== '1') {
            return;
        }

        $tracking_enabled = get_option('video_seo_enable_analytics', '1');
        if ($tracking_enabled !== '1') {
            return;
        }

        ?>
        <script>
        (function() {
            const postId = <?php echo $post->ID; ?>;
            const sessionId = 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            let watchTime = 0;
            let trackingInterval = null;
            let lastUpdate = Date.now();

            function trackVideoView(completed = false) {
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'track_video_view',
                        nonce: '<?php echo wp_create_nonce('video-seo-nonce'); ?>',
                        post_id: postId,
                        watch_time: Math.floor(watchTime),
                        completed: completed ? '1' : '',
                        session_id: sessionId
                    })
                });
            }

            document.addEventListener('DOMContentLoaded', function() {
                const videos = document.querySelectorAll('video, iframe[src*="youtube"], iframe[src*="vimeo"]');

                videos.forEach(video => {
                    if (video.tagName === 'VIDEO') {
                        video.addEventListener('play', function() {
                            lastUpdate = Date.now();
                            trackingInterval = setInterval(function() {
                                const now = Date.now();
                                watchTime += (now - lastUpdate) / 1000;
                                lastUpdate = now;
                            }, 1000);
                        });

                        video.addEventListener('pause', function() {
                            if (trackingInterval) {
                                clearInterval(trackingInterval);
                                trackVideoView();
                            }
                        });

                        video.addEventListener('ended', function() {
                            if (trackingInterval) clearInterval(trackingInterval);
                            trackVideoView(true);
                        });
                    }
                });
            });
        })();
        </script>
        <?php
    }

    public function auto_embed_video($content) {
        if (!is_singular('post') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        global $post;

        $is_video_post = get_post_meta($post->ID, '_is_video_post', true);
        if ($is_video_post !== '1') {
            return $content;
        }

        $auto_embed = get_option('video_seo_auto_embed', '1');
        if ($auto_embed !== '1') {
            return $content;
        }

        $video_player = $this->render_video_player($post->ID);
        return $video_player . $content;
    }

    public function video_player_shortcode($atts) {
        $atts = shortcode_atts(['id' => get_the_ID()], $atts);
        return $this->render_video_player($atts['id']);
    }

    private function render_video_player($post_id) {
        $video_url = get_post_meta($post_id, '_video_url', true);
        $youtube_id = get_post_meta($post_id, '_youtube_id', true);
        $video_thumbnail = get_post_meta($post_id, '_video_thumbnail', true);

        if (empty($video_url)) {
            return '';
        }

        $output = '<div class="video-seo-player" style="max-width: 100%; margin: 20px 0;">';

        if ($youtube_id) {
            $output .= '<div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden;">';
            $output .= '<iframe src="https://www.youtube.com/embed/' . esc_attr($youtube_id) . '?rel=0" ';
            $output .= 'style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" ';
            $output .= 'frameborder="0" allowfullscreen></iframe>';
            $output .= '</div>';
        } elseif (strpos($video_url, 'vimeo.com') !== false) {
            preg_match('/vimeo\.com\/(\d+)/', $video_url, $matches);
            if (!empty($matches[1])) {
                $vimeo_id = $matches[1];
                $output .= '<div style="position: relative; padding-bottom: 56.25%; height: 0;">';
                $output .= '<iframe src="https://player.vimeo.com/video/' . esc_attr($vimeo_id) . '" ';
                $output .= 'style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" ';
                $output .= 'frameborder="0" allowfullscreen></iframe>';
                $output .= '</div>';
            }
        } else {
            $output .= '<video controls style="width: 100%;"';
            if ($video_thumbnail) {
                $output .= ' poster="' . esc_url($video_thumbnail) . '"';
            }
            $output .= '><source src="' . esc_url($video_url) . '" type="video/mp4">Your browser does not support the video tag.</video>';
        }

        $output .= '</div>';
        return $output;
    }

    public function add_sitemap_rewrite() {
        add_rewrite_rule('^video-sitemap\.xml$', 'index.php?video_sitemap=1', 'top');
        add_rewrite_tag('%video_sitemap%', '([^&]+)');
    }

    public function serve_video_sitemap() {
        if (!get_query_var('video_sitemap')) {
            return;
        }

        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

        $video_posts = get_posts([
            'post_type' => 'post',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                ['key' => '_is_video_post', 'value' => '1']
            ]
        ]);

        foreach ($video_posts as $post) {
            $video_url = get_post_meta($post->ID, '_video_url', true);
            $duration = get_post_meta($post->ID, '_video_duration', true);
            $thumbnail = get_post_meta($post->ID, '_video_thumbnail', true);
            $seo_description = get_post_meta($post->ID, '_seo_description', true);

            if (empty($thumbnail)) {
                $thumbnail = get_the_post_thumbnail_url($post->ID, 'full');
            }

            echo '  <url>' . "\n";
            echo '    <loc>' . get_permalink($post->ID) . '</loc>' . "\n";
            echo '    <video:video>' . "\n";
            echo '      <video:thumbnail_loc>' . esc_url($thumbnail) . '</video:thumbnail_loc>' . "\n";
            echo '      <video:title><![CDATA[' . get_the_title($post->ID) . ']]></video:title>' . "\n";
            echo '      <video:description><![CDATA[' . $seo_description . ']]></video:description>' . "\n";
            echo '      <video:content_loc>' . esc_url($video_url) . '</video:content_loc>' . "\n";

            if ($duration) {
                echo '      <video:duration>' . intval($duration) . '</video:duration>' . "\n";
            }

            echo '      <video:publication_date>' . get_the_date('c', $post->ID) . '</video:publication_date>' . "\n";
            echo '    </video:video>' . "\n";
            echo '  </url>' . "\n";
        }

        echo '</urlset>';
        exit;
    }

    public function enqueue_admin_scripts($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            if (strpos($hook, 'video-seo') === false) {
                return;
            }
        }

        wp_enqueue_script(
            'video-seo-admin',
            plugin_dir_url(__FILE__) . 'js/admin.js',
            ['jquery'],
            $this->version,
            true
        );

        wp_localize_script('video-seo-admin', 'videoSEO', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('video-seo-nonce')
        ]);
    }

    public function ajax_save_settings() {
        check_ajax_referer('video-seo-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $youtube_api_key = sanitize_text_field($_POST['youtube_api_key']);
        $enable_analytics = isset($_POST['enable_analytics']) ? '1' : '0';
        $auto_embed = isset($_POST['auto_embed']) ? '1' : '0';

        update_option('video_seo_youtube_api_key', $youtube_api_key);
        update_option('video_seo_enable_analytics', $enable_analytics);
        update_option('video_seo_auto_embed', $auto_embed);

        wp_send_json_success('Settings saved successfully!');
    }

    public function ajax_scan_video_posts() {
        check_ajax_referer('video-seo-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $all_posts = get_posts([
            'post_type' => 'post',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);

        $video_posts = [];
        $potential_videos = [];

        foreach ($all_posts as $post) {
            $is_video = get_post_meta($post->ID, '_is_video_post', true);
            $has_video_url = get_post_meta($post->ID, '_video_url', true);

            if ($is_video === '1') {
                $video_posts[] = [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'url' => get_permalink($post->ID),
                    'edit_url' => get_edit_post_link($post->ID, 'raw'),
                    'has_video_url' => !empty($has_video_url)
                ];
            } else {
                $all_meta = get_post_meta($post->ID);
                $has_potential_video = false;

                foreach ($all_meta as $key => $values) {
                    foreach ($values as $value) {
                        if (is_string($value) && (strpos($value, 'youtube') !== false || strpos($value, 'vimeo') !== false)) {
                            $has_potential_video = true;
                            break 2;
                        }
                    }
                }

                if ($has_potential_video) {
                    $potential_videos[] = [
                        'id' => $post->ID,
                        'title' => $post->post_title,
                        'edit_url' => get_edit_post_link($post->ID, 'raw')
                    ];
                }
            }
        }

        wp_send_json_success([
            'total_posts' => count($all_posts),
            'video_posts' => $video_posts,
            'potential_videos' => $potential_videos
        ]);
    }

    public function ajax_enable_video_features() {
        check_ajax_referer('video-seo-nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }

        $post_id = intval($_POST['post_id']);
        update_post_meta($post_id, '_is_video_post', '1');

        wp_send_json_success('Video features enabled');
    }

    public function dashboard_page() {
        ?>
        <div class="wrap">
            <h1>Video SEO Dashboard</h1>

            <div class="card" style="max-width: 800px;">
                <h2>Welcome to Video SEO Pro</h2>
                <p>This plugin adds video SEO features directly to your existing posts - no separate post type needed!</p>

                <h3>How It Works:</h3>
                <ol>
                    <li>Edit any post and check <strong>"This post contains a video"</strong> in the sidebar</li>
                    <li>Add your video URL and details</li>
                    <li>Video SEO features automatically activate for that post</li>
                    <li>Your post stays a regular post - just with video superpowers!</li>
                </ol>
            </div>

            <div style="margin-top: 20px;">
                <button type="button" id="scan-posts-btn" class="button button-primary button-large">
                    Scan My Posts for Videos
                </button>
                <span id="scan-status" style="margin-left: 15px;"></span>
            </div>

            <div id="scan-results" style="display: none; margin-top: 30px;">
                <div class="card">
                    <h2>Posts with Video Features Enabled: <span id="video-count">0</span></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Post Title</th>
                                <th>Has Video URL</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="video-posts-list"></tbody>
                    </table>
                </div>

                <div class="card" style="margin-top: 20px;" id="potential-videos-card" style="display: none;">
                    <h2>Posts That Might Have Videos: <span id="potential-count">0</span></h2>
                    <p>These posts have video URLs in custom fields but video features aren't enabled yet.</p>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Post Title</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="potential-videos-list"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#scan-posts-btn').on('click', function() {
                const btn = $(this);
                const status = $('#scan-status');

                btn.prop('disabled', true).text('Scanning...');
                status.html('<span class="spinner is-active"></span>');

                $.ajax({
                    url: videoSEO.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'scan_video_posts',
                        nonce: videoSEO.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            displayResults(response.data);
                            $('#scan-results').show();
                            status.html('<span style="color: green;">✓ Scan complete</span>');
                        }
                    },
                    complete: function() {
                        btn.prop('disabled', false).text('Scan My Posts for Videos');
                    }
                });
            });

            function displayResults(data) {
                $('#video-count').text(data.video_posts.length);

                const videosTable = $('#video-posts-list');
                videosTable.empty();

                if (data.video_posts.length === 0) {
                    videosTable.append('<tr><td colspan="3">No posts with video features enabled yet.</td></tr>');
                } else {
                    data.video_posts.forEach(function(post) {
                        videosTable.append(`
                            <tr>
                                <td><strong>${post.title}</strong></td>
                                <td>${post.has_video_url ? '✓ Yes' : '✗ No video URL'}</td>
                                <td>
                                    <a href="${post.edit_url}" class="button">Edit</a>
                                    <a href="${post.url}" target="_blank" class="button">View</a>
                                </td>
                            </tr>
                        `);
                    });
                }

                if (data.potential_videos.length > 0) {
                    $('#potential-count').text(data.potential_videos.length);
                    $('#potential-videos-card').show();

                    const potentialTable = $('#potential-videos-list');
                    potentialTable.empty();

                    data.potential_videos.forEach(function(post) {
                        potentialTable.append(`
                            <tr>
                                <td><strong>${post.title}</strong></td>
                                <td>
                                    <button class="button enable-video-btn" data-post-id="${post.id}">Enable Video Features</button>
                                    <a href="${post.edit_url}" class="button">Edit Post</a>
                                </td>
                            </tr>
                        `);
                    });
                }
            }

            $(document).on('click', '.enable-video-btn', function() {
                const btn = $(this);
                const postId = btn.data('post-id');

                btn.prop('disabled', true).text('Enabling...');

                $.ajax({
                    url: videoSEO.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'enable_video_features',
                        nonce: videoSEO.nonce,
                        post_id: postId
                    },
                    success: function(response) {
                        if (response.success) {
                            btn.closest('tr').fadeOut(500, function() {
                                $(this).remove();
                                $('#scan-posts-btn').click();
                            });
                        }
                    }
                });
            });
        });
        </script>
        <?php
    }

    public function analytics_page() {
        global $wpdb;

        $video_posts = get_posts([
            'post_type' => 'post',
            'posts_per_page' => -1,
            'meta_query' => [
                ['key' => '_is_video_post', 'value' => '1']
            ]
        ]);

        ?>
        <div class="wrap">
            <h1>Video Analytics</h1>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Post</th>
                        <th>Total Views</th>
                        <th>Avg Watch Time</th>
                        <th>Completion Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($video_posts as $post):
                        $stats = $wpdb->get_row($wpdb->prepare(
                            "SELECT COUNT(*) as views, AVG(watch_time) as avg_time, (SUM(completed) / COUNT(*)) * 100 as completion_rate
                            FROM {$this->table_name}
                            WHERE post_id = %d",
                            $post->ID
                        ));
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($post->post_title); ?></strong><br>
                            <a href="<?php echo get_edit_post_link($post->ID); ?>">Edit</a> |
                            <a href="<?php echo get_permalink($post->ID); ?>" target="_blank">View</a>
                        </td>
                        <td><?php echo number_format($stats->views); ?></td>
                        <td><?php echo gmdate('i:s', (int)$stats->avg_time); ?></td>
                        <td><?php echo round($stats->completion_rate, 1); ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function settings_page() {
        $youtube_api_key = get_option('video_seo_youtube_api_key', '');
        $enable_analytics = get_option('video_seo_enable_analytics', '1');
        $auto_embed = get_option('video_seo_auto_embed', '1');
        ?>
        <div class="wrap">
            <h1>Video SEO Settings</h1>

            <form id="video-seo-settings-form">
                <table class="form-table">
                    <tr>
                        <th><label for="youtube_api_key">YouTube API Key</label></th>
                        <td>
                            <input type="text" id="youtube_api_key" name="youtube_api_key" value="<?php echo esc_attr($youtube_api_key); ?>" class="regular-text">
                            <p class="description">Get your API key from <a href="https://console.developers.google.com/" target="_blank">Google Developer Console</a></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="enable_analytics">Analytics Tracking</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="enable_analytics" name="enable_analytics" value="1" <?php checked($enable_analytics, '1'); ?>>
                                Enable video analytics tracking
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="auto_embed">Auto-Embed Videos</label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="auto_embed" name="auto_embed" value="1" <?php checked($auto_embed, '1'); ?>>
                                Automatically display videos at the top of posts
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">Save Settings</button>
                </p>
            </form>

            <div id="settings-message" style="margin-top: 20px;"></div>

            <div class="card" style="max-width: 800px; margin-top: 30px;">
                <h2>Video Sitemap</h2>
                <p>Your video sitemap: <code><?php echo home_url('/video-sitemap.xml'); ?></code></p>
                <p>Submit this to Google Search Console!</p>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#video-seo-settings-form').on('submit', function(e) {
                e.preventDefault();

                $.post(videoSEO.ajaxurl, {
                    action: 'save_video_settings',
                    nonce: videoSEO.nonce,
                    youtube_api_key: $('#youtube_api_key').val(),
                    enable_analytics: $('#enable_analytics').is(':checked') ? '1' : '0',
                    auto_embed: $('#auto_embed').is(':checked') ? '1' : '0'
                }, function(response) {
                    if (response.success) {
                        $('#settings-message').html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                    }
                });
            });
        });
        </script>
        <?php
    }
}

new VideoSEOPro();
