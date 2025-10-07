<?php
/**
 * Plugin Name: Video SEO Pro
 * Plugin URI: https://example.com/video-seo-pro
 * Description: Comprehensive video SEO toolkit with auto-generated meta descriptions, schema markup, video sitemaps, companion blog posts, YouTube integration, and analytics
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * Text Domain: video-seo-pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class VideoSEOPro {
    private $version = '1.0.0';
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'video_analytics';
        
        add_action('init', [$this, 'register_video_post_type']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('add_meta_boxes', [$this, 'add_video_meta_boxes']);
        add_action('save_post_video', [$this, 'save_video_meta'], 10, 2);
        add_action('wp_head', [$this, 'add_video_schema']);
        add_action('wp_footer', [$this, 'add_analytics_tracking']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_filter('template_include', [$this, 'load_video_template']);
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
        add_action('wp_ajax_scan_existing_posts', [$this, 'ajax_scan_existing_posts']);
        add_action('wp_ajax_import_video_post', [$this, 'ajax_import_video_post']);
        add_action('wp_ajax_scan_duplicates', [$this, 'ajax_scan_duplicates']);
        add_action('wp_ajax_delete_duplicate', [$this, 'ajax_delete_duplicate']);
        
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    public function activate() {
        $this->register_video_post_type();
        $this->create_analytics_table();
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    private function create_analytics_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            video_id bigint(20) NOT NULL,
            user_id bigint(20) DEFAULT 0,
            session_id varchar(255) NOT NULL,
            view_date datetime DEFAULT CURRENT_TIMESTAMP,
            watch_time int(11) DEFAULT 0,
            completed tinyint(1) DEFAULT 0,
            user_agent text,
            ip_address varchar(45),
            PRIMARY KEY  (id),
            KEY video_id (video_id),
            KEY view_date (view_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function register_video_post_type() {
        $labels = [
            'name' => 'Videos',
            'singular_name' => 'Video',
            'add_new' => 'Add New Video',
            'add_new_item' => 'Add New Video',
            'edit_item' => 'Edit Video',
            'new_item' => 'New Video',
            'view_item' => 'View Video',
            'search_items' => 'Search Videos',
            'not_found' => 'No videos found',
            'not_found_in_trash' => 'No videos found in trash'
        ];
        
        $args = [
            'labels' => $labels,
            'public' => true,
            'has_archive' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'videos'],
            'capability_type' => 'post',
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'comments'],
            'menu_icon' => 'dashicons-video-alt3',
            'show_in_rest' => true
        ];
        
        register_post_type('video', $args);
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=video',
            'Analytics Dashboard',
            'Analytics',
            'manage_options',
            'video-analytics',
            [$this, 'analytics_page']
        );
        
        add_submenu_page(
            'edit.php?post_type=video',
            'Video SEO Settings',
            'Settings',
            'manage_options',
            'video-seo-settings',
            [$this, 'settings_page']
        );
        
        add_submenu_page(
            'edit.php?post_type=video',
            'Import Existing Videos',
            'Import Videos',
            'manage_options',
            'video-import',
            [$this, 'import_page']
        );
        
        add_submenu_page(
            'edit.php?post_type=video',
            'Clean Up Duplicates',
            'Clean Up',
            'manage_options',
            'video-cleanup',
            [$this, 'cleanup_page']
        );
    }
    
    public function add_video_meta_boxes() {
        add_meta_box(
            'video_details',
            'Video Details',
            [$this, 'video_details_meta_box'],
            'video',
            'normal',
            'high'
        );
        
        add_meta_box(
            'video_seo',
            'SEO Settings',
            [$this, 'video_seo_meta_box'],
            'video',
            'normal',
            'high'
        );
        
        add_meta_box(
            'video_analytics_summary',
            'Analytics Summary',
            [$this, 'video_analytics_meta_box'],
            'video',
            'side',
            'default'
        );
    }
    
    public function video_details_meta_box($post) {
        wp_nonce_field('video_meta_box', 'video_meta_box_nonce');
        
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
                    <input type="url" id="video_url" name="video_url" value="<?php echo esc_attr($video_url); ?>" class="regular-text">
                    <button type="button" class="button" id="fetch-youtube-data">Import from YouTube</button>
                    <p class="description">YouTube, Vimeo, or direct video URL</p>
                </td>
            </tr>
            <tr>
                <th><label for="youtube_id">YouTube Video ID</label></th>
                <td>
                    <input type="text" id="youtube_id" name="youtube_id" value="<?php echo esc_attr($youtube_id); ?>" class="regular-text">
                    <p class="description">Auto-extracted from URL or enter manually</p>
                </td>
            </tr>
            <tr>
                <th><label for="video_duration">Duration (seconds)</label></th>
                <td>
                    <input type="number" id="video_duration" name="video_duration" value="<?php echo esc_attr($video_duration); ?>" class="small-text">
                    <p class="description">Video length in seconds (e.g., 300 for 5 minutes)</p>
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
                    <input type="url" id="video_thumbnail" name="video_thumbnail" value="<?php echo esc_attr($video_thumbnail); ?>" class="regular-text">
                    <p class="description">Custom thumbnail (or leave empty to use featured image)</p>
                </td>
            </tr>
        </table>
        <div id="youtube-import-status" style="margin-top: 15px;"></div>
        <?php
    }
    
    public function video_seo_meta_box($post) {
        $seo_title = get_post_meta($post->ID, '_seo_title', true);
        $seo_description = get_post_meta($post->ID, '_seo_description', true);
        $auto_blog_post = get_post_meta($post->ID, '_auto_blog_post', true);
        
        ?>
        <table class="form-table">
            <tr>
                <th><label for="seo_title">SEO Title</label></th>
                <td>
                    <input type="text" id="seo_title" name="seo_title" value="<?php echo esc_attr($seo_title); ?>" class="large-text">
                    <button type="button" class="button" id="generate-seo-title">Auto-Generate</button>
                    <p class="description">Leave empty to auto-generate from video title</p>
                </td>
            </tr>
            <tr>
                <th><label for="seo_description">Meta Description</label></th>
                <td>
                    <textarea id="seo_description" name="seo_description" rows="3" class="large-text"><?php echo esc_textarea($seo_description); ?></textarea>
                    <button type="button" class="button" id="generate-seo-description">Auto-Generate</button>
                    <p class="description">Leave empty to auto-generate from video content</p>
                </td>
            </tr>
            <tr>
                <th><label for="auto_blog_post">Companion Blog Post</label></th>
                <td>
                    <label>
                        <input type="checkbox" id="auto_blog_post" name="auto_blog_post" value="1" <?php checked($auto_blog_post, '1'); ?>>
                        Create companion blog post
                    </label>
                    <button type="button" class="button" id="generate-blog-post" style="margin-left: 10px;">Generate Now</button>
                    <?php
                    $linked_post = get_post_meta($post->ID, '_linked_blog_post', true);
                    if ($linked_post) {
                        echo '<p class="description">Linked to: <a href="' . get_edit_post_link($linked_post) . '">View Blog Post</a></p>';
                    }
                    ?>
                </td>
            </tr>
        </table>
        <?php
    }
    
    public function video_analytics_meta_box($post) {
        global $wpdb;
        
        $total_views = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE video_id = %d",
            $post->ID
        ));
        
        $avg_watch_time = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(watch_time) FROM {$this->table_name} WHERE video_id = %d",
            $post->ID
        ));
        
        $completion_rate = $wpdb->get_var($wpdb->prepare(
            "SELECT (SUM(completed) / COUNT(*)) * 100 FROM {$this->table_name} WHERE video_id = %d",
            $post->ID
        ));
        
        ?>
        <div class="video-analytics-summary">
            <p><strong>Total Views:</strong> <?php echo number_format($total_views); ?></p>
            <p><strong>Avg Watch Time:</strong> <?php echo gmdate('i:s', (int)$avg_watch_time); ?></p>
            <p><strong>Completion Rate:</strong> <?php echo round($completion_rate, 1); ?>%</p>
            <p><a href="<?php echo admin_url('edit.php?post_type=video&page=video-analytics&video_id=' . $post->ID); ?>">View Detailed Analytics</a></p>
        </div>
        <style>
            .video-analytics-summary p { margin: 10px 0; }
        </style>
        <?php
    }
    
    public function ajax_fetch_youtube_data() {
        check_ajax_referer('video-seo-nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $video_url = sanitize_text_field($_POST['video_url']);
        $youtube_id = $this->extract_youtube_id($video_url);
        
        if (!$youtube_id) {
            wp_send_json_error('Invalid YouTube URL');
        }
        
        $api_key = get_option('video_seo_youtube_api_key');
        
        if (empty($api_key)) {
            wp_send_json_error('YouTube API key not configured. Please add it in Settings.');
        }
        
        $api_url = "https://www.googleapis.com/youtube/v3/videos?part=snippet,contentDetails,statistics&id={$youtube_id}&key={$api_key}";
        
        $response = wp_remote_get($api_url);
        
        if (is_wp_error($response)) {
            wp_send_json_error('Failed to fetch YouTube data: ' . $response->get_error_message());
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['items'])) {
            wp_send_json_error('Video not found on YouTube');
        }
        
        $video_data = $body['items'][0];
        
        // Parse duration (PT4M13S format)
        $duration = $video_data['contentDetails']['duration'];
        $seconds = $this->parse_youtube_duration($duration);
        
        wp_send_json_success([
            'title' => $video_data['snippet']['title'],
            'description' => $video_data['snippet']['description'],
            'thumbnail' => $video_data['snippet']['thumbnails']['maxres']['url'] ?? $video_data['snippet']['thumbnails']['high']['url'],
            'duration' => $seconds,
            'upload_date' => date('Y-m-d', strtotime($video_data['snippet']['publishedAt'])),
            'youtube_id' => $youtube_id
        ]);
    }
    
    private function extract_youtube_id($url) {
        $pattern = '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i';
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
        return false;
    }
    
    private function parse_youtube_duration($duration) {
        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $matches);
        $hours = isset($matches[1]) ? (int)$matches[1] : 0;
        $minutes = isset($matches[2]) ? (int)$matches[2] : 0;
        $seconds = isset($matches[3]) ? (int)$matches[3] : 0;
        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }
    
    public function ajax_track_video_view() {
        check_ajax_referer('video-seo-nonce', 'nonce');
        
        global $wpdb;
        
        $video_id = intval($_POST['video_id']);
        $watch_time = intval($_POST['watch_time']);
        $completed = isset($_POST['completed']) ? 1 : 0;
        $session_id = sanitize_text_field($_POST['session_id']);
        
        $user_id = get_current_user_id();
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        
        // Check if this session already has a view record
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_name} WHERE video_id = %d AND session_id = %s",
            $video_id,
            $session_id
        ));
        
        if ($existing) {
            // Update existing record
            $wpdb->update(
                $this->table_name,
                [
                    'watch_time' => $watch_time,
                    'completed' => $completed
                ],
                ['id' => $existing],
                ['%d', '%d'],
                ['%d']
            );
        } else {
            // Insert new record
            $wpdb->insert(
                $this->table_name,
                [
                    'video_id' => $video_id,
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
        
        // Save video details
        if (isset($_POST['video_url'])) {
            $video_url = sanitize_text_field($_POST['video_url']);
            update_post_meta($post_id, '_video_url', $video_url);
            
            // Auto-extract YouTube ID
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
        
        // Save SEO settings
        if (isset($_POST['seo_title'])) {
            update_post_meta($post_id, '_seo_title', sanitize_text_field($_POST['seo_title']));
        }
        
        if (isset($_POST['seo_description'])) {
            update_post_meta($post_id, '_seo_description', sanitize_textarea_field($_POST['seo_description']));
        }
        
        if (isset($_POST['auto_blog_post'])) {
            update_post_meta($post_id, '_auto_blog_post', '1');
            $this->create_blog_post($post_id);
        } else {
            update_post_meta($post_id, '_auto_blog_post', '0');
        }
        
        // Auto-generate SEO if empty
        if (empty($_POST['seo_title'])) {
            $this->generate_seo_title($post_id);
        }
        
        if (empty($_POST['seo_description'])) {
            $this->generate_seo_description($post_id);
        }
    }
    
    private function generate_seo_title($post_id) {
        $post = get_post($post_id);
        $title = $post->post_title;
        
        $seo_title = $title . ' - Watch Now | Video Tutorial';
        $seo_title = substr($seo_title, 0, 60);
        
        update_post_meta($post_id, '_seo_title', $seo_title);
        return $seo_title;
    }
    
    private function generate_seo_description($post_id) {
        $post = get_post($post_id);
        $content = wp_strip_all_tags($post->post_content);
        
        if (strlen($content) > 155) {
            $description = substr($content, 0, 152) . '...';
        } else {
            $description = $content;
        }
        
        if (empty($description)) {
            $description = 'Watch ' . $post->post_title . '. Learn more in this comprehensive video guide.';
        }
        
        update_post_meta($post_id, '_seo_description', $description);
        return $description;
    }
    
    private function create_blog_post($video_id) {
        $existing_post = get_post_meta($video_id, '_linked_blog_post', true);
        if ($existing_post && get_post_status($existing_post)) {
            return $existing_post;
        }
        
        $video = get_post($video_id);
        $blog_content = $this->generate_blog_content($video);
        
        $blog_post = [
            'post_title' => 'Complete Guide: ' . $video->post_title,
            'post_content' => $blog_content,
            'post_status' => 'draft',
            'post_type' => 'post',
            'post_author' => $video->post_author
        ];
        
        $blog_post_id = wp_insert_post($blog_post);
        
        if ($blog_post_id) {
            update_post_meta($video_id, '_linked_blog_post', $blog_post_id);
            update_post_meta($blog_post_id, '_linked_video', $video_id);
            
            $thumbnail_id = get_post_thumbnail_id($video_id);
            if ($thumbnail_id) {
                set_post_thumbnail($blog_post_id, $thumbnail_id);
            }
        }
        
        return $blog_post_id;
    }
    
    private function generate_blog_content($video) {
        $video_url = get_post_meta($video->ID, '_video_url', true);
        
        $content = '<p>In this comprehensive guide, we explore the topics covered in our video: <strong>' . esc_html($video->post_title) . '</strong>.</p>';
        
        $content .= "\n\n" . '[video_player id="' . $video->ID . '"]' . "\n\n";
        
        $content .= '<h2>Overview</h2>';
        $content .= '<p>' . wp_strip_all_tags($video->post_content) . '</p>';
        
        $content .= "\n\n<h2>Key Takeaways</h2>";
        $content .= '<ul>';
        $content .= '<li>Detailed insights from the video presentation</li>';
        $content .= '<li>Step-by-step guidance on implementing the concepts</li>';
        $content .= '<li>Additional resources and recommendations</li>';
        $content .= '</ul>';
        
        $content .= "\n\n<h2>Watch the Full Video</h2>";
        $content .= '<p>For the complete tutorial and visual demonstrations, <a href="' . get_permalink($video->ID) . '">watch the full video here</a>.</p>';
        
        $content .= "\n\n<p><em>Note: This blog post accompanies our video content. For the best learning experience, we recommend watching the video alongside reading this guide.</em></p>";
        
        return $content;
    }
    
    public function add_video_schema() {
        if (!is_singular('video')) {
            return;
        }
        
        global $post;
        
        $video_url = get_post_meta($post->ID, '_video_url', true);
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
        if (!is_singular('video')) {
            return;
        }
        
        global $post;
        
        $tracking_enabled = get_option('video_seo_enable_analytics', '1');
        if ($tracking_enabled !== '1') {
            return;
        }
        
        ?>
        <script>
        (function() {
            const videoId = <?php echo $post->ID; ?>;
            const sessionId = 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            let watchTime = 0;
            let trackingInterval = null;
            let lastUpdate = Date.now();
            
            function trackVideoView(completed = false) {
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'track_video_view',
                        nonce: '<?php echo wp_create_nonce('video-seo-nonce'); ?>',
                        video_id: videoId,
                        watch_time: Math.floor(watchTime),
                        completed: completed ? '1' : '',
                        session_id: sessionId
                    })
                });
            }
            
            // Track video player interaction
            document.addEventListener('DOMContentLoaded', function() {
                const videos = document.querySelectorAll('video, iframe[src*="youtube"], iframe[src*="vimeo"]');
                
                videos.forEach(video => {
                    if (video.tagName === 'VIDEO') {
                        // HTML5 video
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
                            if (trackingInterval) {
                                clearInterval(trackingInterval);
                            }
                            trackVideoView(true);
                        });
                    }
                });
                
                // Track page visibility (when user leaves)
                document.addEventListener('visibilitychange', function() {
                    if (document.hidden && trackingInterval) {
                        clearInterval(trackingInterval);
                        trackVideoView();
                    }
                });
                
                // Track before unload
                window.addEventListener('beforeunload', function() {
                    if (watchTime > 0) {
                        trackVideoView();
                    }
                });
            });
        })();
        </script>
        <?php
    }
    
    public function auto_embed_video($content) {
        if (!is_singular('video') || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        
        global $post;
        
        // Check if video player already exists in content
        if (strpos($content, 'video-seo-player') !== false || strpos($content, '[video_player]') !== false) {
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
        $atts = shortcode_atts([
            'id' => get_the_ID()
        ], $atts);
        
        return $this->render_video_player($atts['id']);
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
        
        $videos = get_posts([
            'post_type' => 'video',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);
        
        foreach ($videos as $video) {
            $video_url = get_post_meta($video->ID, '_video_url', true);
            $duration = get_post_meta($video->ID, '_video_duration', true);
            $thumbnail = get_post_meta($video->ID, '_video_thumbnail', true);
            $seo_description = get_post_meta($video->ID, '_seo_description', true);
            
            if (empty($thumbnail)) {
                $thumbnail = get_the_post_thumbnail_url($video->ID, 'full');
            }
            
            echo '  <url>' . "\n";
            echo '    <loc>' . get_permalink($video->ID) . '</loc>' . "\n";
            echo '    <video:video>' . "\n";
            echo '      <video:thumbnail_loc>' . esc_url($thumbnail) . '</video:thumbnail_loc>' . "\n";
            echo '      <video:title><![CDATA[' . get_the_title($video->ID) . ']]></video:title>' . "\n";
            echo '      <video:description><![CDATA[' . $seo_description . ']]></video:description>' . "\n";
            echo '      <video:content_loc>' . esc_url($video_url) . '</video:content_loc>' . "\n";
            
            if ($duration) {
                echo '      <video:duration>' . intval($duration) . '</video:duration>' . "\n";
            }
            
            echo '      <video:publication_date>' . get_the_date('c', $video->ID) . '</video:publication_date>' . "\n";
            echo '    </video:video>' . "\n";
            echo '  </url>' . "\n";
        }
        
        echo '</urlset>';
        exit;
    }
    
    public function enqueue_admin_scripts($hook) {
        // Load on post edit pages
        $load_scripts = false;
        
        if ('post.php' === $hook || 'post-new.php' === $hook) {
            global $post_type;
            if ('video' === $post_type) {
                $load_scripts = true;
            }
        }
        
        // Load on video admin pages
        if (strpos($hook, 'video-analytics') !== false || 
            strpos($hook, 'video-seo-settings') !== false || 
            strpos($hook, 'video-import') !== false ||
            strpos($hook, 'video-cleanup') !== false) {
            $load_scripts = true;
        }
        
        if (!$load_scripts) {
            return;
        }
        
        // Enqueue Chart.js for analytics
        if (strpos($hook, 'video-analytics') !== false) {
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', [], '3.9.1', true);
        }
        
        // Enqueue admin scripts
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
    
    public function ajax_scan_existing_posts() {
        check_ajax_referer('video-seo-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $debug_mode = isset($_POST['debug']) && $_POST['debug'] === 'true';
        $scan_meta = isset($_POST['scan_meta']) && $_POST['scan_meta'] === 'true';
        $debug_info = [];
        
        // Get all published posts
        $args = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ];
        
        $posts = get_posts($args);
        $posts_with_videos = [];
        
        foreach ($posts as $post_id) {
            $post = get_post($post_id);
            $content = $post->post_content;
            $video_urls = [];
            
            // Debug: Log content preview for this post
            $post_debug = [
                'post_id' => $post_id,
                'post_title' => get_the_title($post_id),
                'content_preview' => substr(strip_tags($content), 0, 200) . '...',
                'content_length' => strlen($content),
                'has_iframe' => strpos($content, '<iframe') !== false,
                'has_youtube_text' => strpos($content, 'youtube') !== false,
                'has_vimeo_text' => strpos($content, 'vimeo') !== false,
                'has_embed_shortcode' => strpos($content, '[embed]') !== false,
                'has_video_tag' => strpos($content, '<video') !== false,
            ];
            
            // Check if already imported
            $already_imported = get_post_meta($post_id, '_video_seo_imported', true);
            if ($already_imported) {
                if ($debug_mode) {
                    $post_debug['already_imported'] = true;
                }
                continue;
            }
            
            // Look for video URLs in content
            $video_urls = $this->extract_video_urls_from_content($content);
            
            // If scanning meta fields and no videos found in content, check post meta
            if ($scan_meta && empty($video_urls)) {
                $meta_videos = $this->extract_video_urls_from_meta($post_id);
                if (!empty($meta_videos)) {
                    $video_urls = $meta_videos;
                    $post_debug['found_in_meta'] = true;
                    $post_debug['meta_fields'] = array_column($meta_videos, 'meta_key');
                }
            }
            
            // Also check for attached videos
            $attached_videos = $this->get_attached_video_files($post_id);
            if (!empty($attached_videos)) {
                $video_urls = array_merge($video_urls, $attached_videos);
                $post_debug['found_attachments'] = true;
            }
            
            if ($debug_mode) {
                $post_debug['videos_found'] = count($video_urls);
                $debug_info[] = $post_debug;
            }
            
            if (!empty($video_urls)) {
                $posts_with_videos[] = [
                    'id' => $post_id,
                    'title' => get_the_title($post_id),
                    'url' => get_permalink($post_id),
                    'date' => get_the_date('Y-m-d', $post_id),
                    'videos' => $video_urls
                ];
            }
        }
        
        $response = [
            'total_posts' => count($posts),
            'posts_with_videos' => $posts_with_videos,
            'count' => count($posts_with_videos)
        ];
        
        if ($debug_mode) {
            $response['debug'] = $debug_info;
        }
        
        wp_send_json_success($response);
    }
    
    private function extract_video_urls_from_meta($post_id) {
        $video_urls = [];
        $all_meta = get_post_meta($post_id);
        
        // Common meta field names for video URLs
        $common_video_fields = [
            'video_url', 'video', 'youtube_url', 'youtube_id', 'vimeo_url', 
            'video_embed', 'video_link', 'featured_video', 'post_video',
            '_video_url', '_youtube_url', '_vimeo_url', 'wistia_url',
            'video_id', 'youtube_video_id', 'video_code'
        ];
        
        foreach ($all_meta as $meta_key => $meta_values) {
            // Skip internal WordPress fields
            if (strpos($meta_key, '_wp_') === 0 || strpos($meta_key, '_edit_') === 0) {
                continue;
            }
            
            foreach ($meta_values as $meta_value) {
                // Check if meta value contains a video URL
                if (is_string($meta_value)) {
                    // Check for YouTube
                    if (preg_match('/(?:youtube\.com|youtu\.be)/i', $meta_value)) {
                        $video_urls[] = [
                            'url' => $meta_value,
                            'type' => 'youtube',
                            'meta_key' => $meta_key
                        ];
                    }
                    // Check for Vimeo
                    elseif (preg_match('/vimeo\.com/i', $meta_value)) {
                        $video_urls[] = [
                            'url' => $meta_value,
                            'type' => 'vimeo',
                            'meta_key' => $meta_key
                        ];
                    }
                    // Check for direct video files
                    elseif (preg_match('/\.(mp4|webm|ogg|mov|avi|m4v)$/i', $meta_value)) {
                        $video_urls[] = [
                            'url' => $meta_value,
                            'type' => 'direct',
                            'meta_key' => $meta_key
                        ];
                    }
                    // Check if it's just a YouTube ID (11 characters)
                    elseif (in_array($meta_key, $common_video_fields) && 
                            preg_match('/^[a-zA-Z0-9_-]{11}$/', $meta_value)) {
                        $video_urls[] = [
                            'url' => 'https://www.youtube.com/watch?v=' . $meta_value,
                            'type' => 'youtube',
                            'meta_key' => $meta_key
                        ];
                    }
                }
            }
        }
        
        return $video_urls;
    }
    
    public function ajax_scan_duplicates() {
        check_ajax_referer('video-seo-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $duplicates = [];
        
        // Get all video posts
        $videos = get_posts([
            'post_type' => 'video',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1
        ]);
        
        // Group by title to find duplicates
        $by_title = [];
        foreach ($videos as $video) {
            $title = trim(strtolower($video->post_title));
            if (!isset($by_title[$title])) {
                $by_title[$title] = [];
            }
            $by_title[$title][] = $video;
        }
        
        // Find groups with more than one post
        foreach ($by_title as $title => $posts) {
            if (count($posts) > 1) {
                // Sort by date, newest first
                usort($posts, function($a, $b) {
                    return strtotime($b->post_date) - strtotime($a->post_date);
                });
                
                $keep = $posts[0]; // Keep the newest one (or published if available)
                $delete_posts = array_slice($posts, 1);
                
                // Prefer to keep published over drafts
                foreach ($posts as $post) {
                    if ($post->post_status === 'publish') {
                        $keep = $post;
                        $delete_posts = array_filter($posts, function($p) use ($keep) {
                            return $p->ID !== $keep->ID;
                        });
                        break;
                    }
                }
                
                $duplicates[] = [
                    'title' => $posts[0]->post_title,
                    'keep' => [
                        'id' => $keep->ID,
                        'status' => $keep->post_status,
                        'date' => get_the_date('Y-m-d H:i', $keep->ID),
                        'url' => get_edit_post_link($keep->ID, 'raw')
                    ],
                    'duplicates' => array_map(function($post) {
                        return [
                            'id' => $post->ID,
                            'status' => $post->post_status,
                            'date' => get_the_date('Y-m-d H:i', $post->ID),
                            'url' => get_edit_post_link($post->ID, 'raw')
                        ];
                    }, array_values($delete_posts))
                ];
            }
        }
        
        wp_send_json_success([
            'total_videos' => count($videos),
            'duplicate_groups' => count($duplicates),
            'duplicates' => $duplicates
        ]);
    }
    
    public function ajax_delete_duplicate() {
        check_ajax_referer('video-seo-nonce', 'nonce');
        
        if (!current_user_can('delete_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $post_id = intval($_POST['post_id']);
        $force_delete = isset($_POST['force_delete']) && $_POST['force_delete'] === 'true';
        
        $result = wp_delete_post($post_id, $force_delete);
        
        if ($result) {
            wp_send_json_success('Post deleted successfully');
        } else {
            wp_send_json_error('Failed to delete post');
        }
    }
    
    public function cleanup_page() {
        ?>
        <div class="wrap">
            <h1>Clean Up Duplicate Videos</h1>
            <p>Find and remove duplicate video posts to keep your library clean.</p>
            
            <div class="card">
                <h2>How It Works</h2>
                <p>This tool will:</p>
                <ol>
                    <li>Scan all video posts for duplicates (based on matching titles)</li>
                    <li>Show you which videos are duplicates</li>
                    <li>Recommend which one to keep (published posts preferred, then newest)</li>
                    <li>Let you delete the duplicates with one click</li>
                </ol>
                <p><strong>Note:</strong> Deleted posts go to trash by default (can be restored). Hold Shift while clicking to permanently delete.</p>
            </div>
            
            <div style="margin: 20px 0;">
                <button type="button" id="scan-duplicates-btn" class="button button-primary button-large">
                    Scan for Duplicates
                </button>
                <span id="scan-dup-status" style="margin-left: 15px;"></span>
            </div>
            
            <div id="duplicates-results" style="display: none; margin-top: 30px;">
                <h2>Found <span id="duplicate-groups-count">0</span> Groups of Duplicates</h2>
                
                <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                    <strong>⚠️ Before Deleting:</strong>
                    <ul style="margin: 10px 0;">
                        <li>Review each group to ensure you're keeping the right one</li>
                        <li>Deleted posts go to trash (can be restored from Videos → Trash)</li>
                        <li>Hold <kbd>Shift</kbd> while clicking delete to permanently remove</li>
                    </ul>
                </div>
                
                <div id="duplicates-list">
                </div>
                
                <div style="margin-top: 20px;">
                    <button type="button" id="delete-all-duplicates-btn" class="button button-primary button-large">
                        Delete All Duplicate Copies (Keep Recommended)
                    </button>
                </div>
            </div>
        </div>
        
        <style>
        .duplicate-group {
            background: white;
            border: 1px solid #ddd;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .duplicate-group h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #2271b1;
        }
        .keep-post {
            background: #d4edda;
            border: 2px solid #28a745;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .duplicate-post {
            background: #f8d7da;
            border: 1px solid #dc3545;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
            position: relative;
        }
        .post-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            margin-left: 10px;
        }
        .status-publish { background: #28a745; color: white; }
        .status-draft { background: #6c757d; color: white; }
        .status-pending { background: #ffc107; color: black; }
        .status-private { background: #17a2b8; color: white; }
        .deleted-post {
            opacity: 0.5;
            background: #e9ecef !important;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            let duplicateGroups = [];
            
            $('#scan-duplicates-btn').on('click', function() {
                const btn = $(this);
                const status = $('#scan-dup-status');
                
                btn.prop('disabled', true).text('Scanning...');
                status.html('<span class="spinner is-active" style="float: none;"></span> Scanning for duplicates...');
                
                $.ajax({
                    url: videoSEO.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'scan_duplicates',
                        nonce: videoSEO.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            duplicateGroups = response.data.duplicates;
                            $('#duplicate-groups-count').text(response.data.duplicate_groups);
                            
                            if (response.data.duplicate_groups > 0) {
                                displayDuplicates(duplicateGroups);
                                $('#duplicates-results').show();
                                status.html('<span style="color: green;">✓</span> Found ' + response.data.duplicate_groups + ' groups of duplicates (Total videos: ' + response.data.total_videos + ')');
                            } else {
                                status.html('<span style="color: green;">✓</span> No duplicates found! Your video library is clean. (Total videos: ' + response.data.total_videos + ')');
                                $('#duplicates-results').hide();
                            }
                        } else {
                            status.html('<span style="color: red;">✗</span> Error: ' + response.data);
                        }
                    },
                    error: function() {
                        status.html('<span style="color: red;">✗</span> Failed to scan for duplicates');
                    },
                    complete: function() {
                        btn.prop('disabled', false).text('Scan for Duplicates');
                    }
                });
            });
            
            function displayDuplicates(groups) {
                const container = $('#duplicates-list');
                container.empty();
                
                groups.forEach(function(group, groupIndex) {
                    const groupHtml = `
                        <div class="duplicate-group" data-group-index="${groupIndex}">
                            <h3>${group.title}</h3>
                            
                            <div class="keep-post">
                                <strong>✓ KEEP THIS ONE:</strong>
                                <span class="post-status status-${group.keep.status}">${group.keep.status}</span>
                                <br>
                                Created: ${group.keep.date}
                                <br>
                                <a href="${group.keep.url}" target="_blank">Edit This Video</a>
                            </div>
                            
                            <div style="margin-top: 15px;">
                                <strong>❌ Delete these duplicates:</strong>
                            </div>
                            
                            ${group.duplicates.map((dup, dupIndex) => `
                                <div class="duplicate-post" data-post-id="${dup.id}" data-group-index="${groupIndex}" data-dup-index="${dupIndex}">
                                    <strong>Duplicate ${dupIndex + 1}</strong>
                                    <span class="post-status status-${dup.status}">${dup.status}</span>
                                    <br>
                                    Created: ${dup.date}
                                    <br>
                                    <a href="${dup.url}" target="_blank">View/Edit</a>
                                    <button type="button" class="button button-small delete-single-btn" data-post-id="${dup.id}" style="margin-left: 10px;">
                                        Delete This
                                    </button>
                                </div>
                            `).join('')}
                        </div>
                    `;
                    container.append(groupHtml);
                });
            }
            
            $(document).on('click', '.delete-single-btn', function(e) {
                const postId = $(this).data('post-id');
                const forceDelete = e.shiftKey;
                const postDiv = $(this).closest('.duplicate-post');
                
                if (!confirm(forceDelete ? 
                    'PERMANENTLY delete this video? This cannot be undone!' : 
                    'Move this video to trash? You can restore it later from Videos → Trash.')) {
                    return;
                }
                
                $(this).prop('disabled', true).text('Deleting...');
                
                $.ajax({
                    url: videoSEO.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'delete_duplicate',
                        nonce: videoSEO.nonce,
                        post_id: postId,
                        force_delete: forceDelete ? 'true' : 'false'
                    },
                    success: function(response) {
                        if (response.success) {
                            postDiv.addClass('deleted-post').fadeOut(500, function() {
                                $(this).remove();
                                checkIfGroupEmpty();
                            });
                        } else {
                            alert('Error: ' + response.data);
                            postDiv.find('.delete-single-btn').prop('disabled', false).text('Delete This');
                        }
                    },
                    error: function() {
                        alert('Failed to delete post');
                        postDiv.find('.delete-single-btn').prop('disabled', false).text('Delete This');
                    }
                });
            });
            
            $('#delete-all-duplicates-btn').on('click', function(e) {
                const forceDelete = e.shiftKey;
                const totalDuplicates = $('.duplicate-post').length;
                
                if (!confirm(forceDelete ? 
                    `PERMANENTLY delete all ${totalDuplicates} duplicate videos? This cannot be undone!` : 
                    `Move all ${totalDuplicates} duplicate videos to trash? You can restore them later.`)) {
                    return;
                }
                
                const btn = $(this);
                btn.prop('disabled', true).text('Deleting all duplicates...');
                
                const toDelete = [];
                $('.duplicate-post').each(function() {
                    toDelete.push($(this).data('post-id'));
                });
                
                deleteNext(0);
                
                function deleteNext(index) {
                    if (index >= toDelete.length) {
                        btn.text('All duplicates deleted!');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                        return;
                    }
                    
                    const postId = toDelete[index];
                    const postDiv = $('.duplicate-post[data-post-id="' + postId + '"]');
                    
                    $.ajax({
                        url: videoSEO.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'delete_duplicate',
                            nonce: videoSEO.nonce,
                            post_id: postId,
                            force_delete: forceDelete ? 'true' : 'false'
                        },
                        success: function() {
                            postDiv.addClass('deleted-post');
                            btn.text(`Deleting... (${index + 1}/${toDelete.length})`);
                            deleteNext(index + 1);
                        },
                        error: function() {
                            console.error('Failed to delete post ID:', postId);
                            deleteNext(index + 1);
                        }
                    });
                }
            });
            
            function checkIfGroupEmpty() {
                $('.duplicate-group').each(function() {
                    if ($(this).find('.duplicate-post:visible').length === 0) {
                        $(this).fadeOut(500, function() {
                            $(this).remove();
                            const remaining = $('.duplicate-group').length;
                            $('#duplicate-groups-count').text(remaining);
                            if (remaining === 0) {
                                $('#duplicates-results').html('<div class="notice notice-success"><p>All duplicates cleaned up! 🎉</p></div>');
                            }
                        });
                    }
                });
            }
        });
        </script>
        <?php
    }
    
    private function get_attached_video_files($post_id) {
        $video_urls = [];
        
        $attachments = get_posts([
            'post_type' => 'attachment',
            'post_parent' => $post_id,
            'post_mime_type' => 'video',
            'posts_per_page' => -1
        ]);
        
        foreach ($attachments as $attachment) {
            $url = wp_get_attachment_url($attachment->ID);
            if ($url) {
                $video_urls[] = [
                    'url' => $url,
                    'type' => 'attachment',
                    'meta_key' => 'attachment'
                ];
            }
        }
        
        return $video_urls;
    }
    
    private function extract_video_urls_from_content($content) {
        $video_urls = [];
        
        // YouTube patterns - multiple formats
        $youtube_patterns = [
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
            '/(?:https?:\/\/)?(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]{11})/',
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]{11})/',
        ];
        
        foreach ($youtube_patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[0] as $url) {
                    $video_urls[] = [
                        'url' => $url,
                        'type' => 'youtube'
                    ];
                }
            }
        }
        
        // Check for YouTube in iframes
        if (preg_match_all('/<iframe[^>]+src=["\']([^"\']*youtube[^"\']*)["\'][^>]*>/i', $content, $matches)) {
            foreach ($matches[1] as $url) {
                $video_urls[] = [
                    'url' => $url,
                    'type' => 'youtube'
                ];
            }
        }
        
        // Vimeo patterns
        $vimeo_patterns = [
            '/(?:https?:\/\/)?(?:www\.)?vimeo\.com\/(\d+)/',
            '/player\.vimeo\.com\/video\/(\d+)/',
        ];
        
        foreach ($vimeo_patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[0] as $url) {
                    $video_urls[] = [
                        'url' => $url,
                        'type' => 'vimeo'
                    ];
                }
            }
        }
        
        // Check for Vimeo in iframes
        if (preg_match_all('/<iframe[^>]+src=["\']([^"\']*vimeo[^"\']*)["\'][^>]*>/i', $content, $matches)) {
            foreach ($matches[1] as $url) {
                $video_urls[] = [
                    'url' => $url,
                    'type' => 'vimeo'
                ];
            }
        }
        
        // WordPress [embed] shortcode
        if (preg_match_all('/\[embed\](.*?)\[\/embed\]/i', $content, $matches)) {
            foreach ($matches[1] as $url) {
                $url = trim($url);
                if (strpos($url, 'youtube') !== false || strpos($url, 'youtu.be') !== false) {
                    $video_urls[] = [
                        'url' => $url,
                        'type' => 'youtube'
                    ];
                } elseif (strpos($url, 'vimeo') !== false) {
                    $video_urls[] = [
                        'url' => $url,
                        'type' => 'vimeo'
                    ];
                }
            }
        }
        
        // WordPress [video] shortcode
        if (preg_match_all('/\[video[^\]]*src=["\']([^"\']+)["\'][^\]]*\]/i', $content, $matches)) {
            foreach ($matches[1] as $url) {
                $video_urls[] = [
                    'url' => $url,
                    'type' => 'direct'
                ];
            }
        }
        
        // HTML5 video tags
        if (preg_match_all('/<video[^>]*>.*?<source[^>]+src=["\']([^"\']+)["\'][^>]*>.*?<\/video>/is', $content, $matches)) {
            foreach ($matches[1] as $url) {
                $video_urls[] = [
                    'url' => $url,
                    'type' => 'direct'
                ];
            }
        }
        
        // Direct video files (.mp4, .webm, .ogg, .mov, .avi)
        if (preg_match_all('/https?:\/\/[^\s<>"]+\.(mp4|webm|ogg|mov|avi|m4v|flv)/i', $content, $matches)) {
            foreach ($matches[0] as $url) {
                $video_urls[] = [
                    'url' => $url,
                    'type' => 'direct'
                ];
            }
        }
        
        // Remove duplicates
        $unique_urls = [];
        $seen = [];
        foreach ($video_urls as $video) {
            $key = $video['url'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique_urls[] = $video;
            }
        }
        
        return $unique_urls;
    }
    
    public function ajax_import_video_post() {
        check_ajax_referer('video-seo-nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $post_id = intval($_POST['post_id']);
        $video_url = sanitize_text_field($_POST['video_url']);
        $import_type = sanitize_text_field($_POST['import_type']); // 'convert' or 'duplicate'
        $prevent_duplicates = isset($_POST['prevent_duplicates']) && $_POST['prevent_duplicates'] === 'true';
        
        $original_post = get_post($post_id);
        
        if (!$original_post) {
            wp_send_json_error('Post not found');
        }
        
        // Check if already imported
        $existing_video_id = get_post_meta($post_id, '_video_seo_imported_to', true);
        if ($existing_video_id && get_post_status($existing_video_id)) {
            wp_send_json_error('This post has already been imported to video ID: ' . $existing_video_id);
        }
        
        // Check for duplicate titles if prevention is enabled
        if ($prevent_duplicates && $import_type === 'duplicate') {
            $existing = get_posts([
                'post_type' => 'video',
                'title' => $original_post->post_title,
                'post_status' => ['publish', 'draft', 'pending', 'private'],
                'posts_per_page' => 1,
                'fields' => 'ids'
            ]);
            
            if (!empty($existing)) {
                wp_send_json_error('A video with this title already exists (ID: ' . $existing[0] . '). Enable duplicates or use a different title.');
            }
        }
        
        if ($import_type === 'convert') {
            // Convert the post to video post type
            $result = wp_update_post([
                'ID' => $post_id,
                'post_type' => 'video'
            ]);
            
            if (is_wp_error($result)) {
                wp_send_json_error('Failed to convert post: ' . $result->get_error_message());
            }
            
            $video_id = $post_id;
            
        } else {
            // Duplicate as new video post
            $new_post = [
                'post_title' => $original_post->post_title,
                'post_content' => $original_post->post_content,
                'post_status' => 'draft', // Create as draft for review
                'post_type' => 'video',
                'post_author' => $original_post->post_author,
                'post_excerpt' => $original_post->post_excerpt
            ];
            
            $video_id = wp_insert_post($new_post);
            
            if (is_wp_error($video_id)) {
                wp_send_json_error('Failed to create video: ' . $video_id->get_error_message());
            }
            
            // Copy featured image
            $thumbnail_id = get_post_thumbnail_id($post_id);
            if ($thumbnail_id) {
                set_post_thumbnail($video_id, $thumbnail_id);
            }
            
            // Copy categories and tags
            $categories = wp_get_post_categories($post_id);
            if ($categories) {
                wp_set_post_categories($video_id, $categories);
            }
            
            $tags = wp_get_post_tags($post_id, ['fields' => 'ids']);
            if ($tags) {
                wp_set_post_tags($video_id, $tags);
            }
            
            // Mark original post as imported
            update_post_meta($post_id, '_video_seo_imported_to', $video_id);
        }
        
        // Add video metadata
        update_post_meta($video_id, '_video_url', $video_url);
        
        // Extract YouTube ID if applicable
        $youtube_id = $this->extract_youtube_id($video_url);
        if ($youtube_id) {
            update_post_meta($video_id, '_youtube_id', $youtube_id);
            
            // Try to fetch YouTube data
            $api_key = get_option('video_seo_youtube_api_key');
            if (!empty($api_key)) {
                $youtube_data = $this->fetch_youtube_data_internal($youtube_id, $api_key);
                if ($youtube_data) {
                    update_post_meta($video_id, '_video_duration', $youtube_data['duration']);
                    update_post_meta($video_id, '_video_thumbnail', $youtube_data['thumbnail']);
                    update_post_meta($video_id, '_video_upload_date', $youtube_data['upload_date']);
                }
            }
        }
        
        // Generate SEO metadata
        $this->generate_seo_title($video_id);
        $this->generate_seo_description($video_id);
        
        // Mark as imported
        update_post_meta($video_id, '_video_seo_imported', '1');
        update_post_meta($video_id, '_video_seo_original_post', $post_id);
        
        wp_send_json_success([
            'video_id' => $video_id,
            'edit_url' => get_edit_post_link($video_id, 'raw'),
            'view_url' => get_permalink($video_id)
        ]);
    }
    
    private function fetch_youtube_data_internal($youtube_id, $api_key) {
        $api_url = "https://www.googleapis.com/youtube/v3/videos?part=snippet,contentDetails,statistics&id={$youtube_id}&key={$api_key}";
        
        $response = wp_remote_get($api_url);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (empty($body['items'])) {
            return false;
        }
        
        $video_data = $body['items'][0];
        
        $duration = $video_data['contentDetails']['duration'];
        $seconds = $this->parse_youtube_duration($duration);
        
        return [
            'title' => $video_data['snippet']['title'],
            'description' => $video_data['snippet']['description'],
            'thumbnail' => $video_data['snippet']['thumbnails']['maxres']['url'] ?? $video_data['snippet']['thumbnails']['high']['url'],
            'duration' => $seconds,
            'upload_date' => date('Y-m-d', strtotime($video_data['snippet']['publishedAt']))
        ];
    }
    
    public function import_page() {
        ?>
        <div class="wrap">
            <h1>Import Existing Videos</h1>
            <p>Scan your existing posts for videos and import them into the Video SEO Pro system.</p>
            
            <div class="card">
                <h2>How It Works</h2>
                <p>This tool will:</p>
                <ol>
                    <li>Scan all your published posts for YouTube, Vimeo, and direct video URLs</li>
                    <li>Let you choose which posts to import</li>
                    <li>Either <strong>convert</strong> the post to a video post type OR <strong>duplicate</strong> it as a new video</li>
                    <li>Automatically extract video metadata (if YouTube API is configured)</li>
                    <li>Generate SEO titles and descriptions</li>
                    <li>Mark imported posts to prevent duplicates</li>
                </ol>
            </div>
            
            <div style="margin: 20px 0;">
                <button type="button" id="scan-posts-btn" class="button button-primary button-large">
                    Scan for Videos
                </button>
                <button type="button" id="debug-scan-btn" class="button button-large" style="margin-left: 10px;">
                    Debug Scan
                </button>
                <br><br>
                <label style="font-weight: bold;">
                    <input type="checkbox" id="scan-meta-checkbox" checked>
                    Scan Custom Fields & Post Meta (Recommended if videos not found in content)
                </label>
                <p class="description" style="margin-left: 24px;">
                    Check this if your videos are stored in custom fields like "video_url", "youtube_url", etc.
                </p>
                <br>
                <label style="font-weight: bold;">
                    <input type="checkbox" id="prevent-duplicates-checkbox" checked>
                    Prevent Duplicate Videos (Check for existing videos with same title before importing)
                </label>
                <p class="description" style="margin-left: 24px;">
                    Recommended to avoid creating duplicate drafts. Uncheck if you intentionally want duplicates.
                </p>
                <span id="scan-status" style="margin-left: 15px; display: block; margin-top: 10px;"></span>
            </div>
            
            <div id="debug-results" style="display: none; margin-top: 20px; background: #f0f0f1; padding: 15px; border-radius: 4px;">
                <h3>Debug Information</h3>
                <p>This shows what the scanner found in each post:</p>
                <div id="debug-content" style="max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 12px; background: white; padding: 10px; border-radius: 3px;">
                </div>
            </div>
            
            <div id="scan-results" style="display: none; margin-top: 30px;">
                <h2>Found <span id="video-count">0</span> Posts with Videos</h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="select-all">
                            </th>
                            <th>Post Title</th>
                            <th>Date</th>
                            <th>Videos Found</th>
                            <th>Import Type</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="video-posts-list">
                    </tbody>
                </table>
                
                <div style="margin-top: 20px;">
                    <button type="button" id="import-selected-btn" class="button button-primary button-large">
                        Import Selected Posts
                    </button>
                </div>
            </div>
            
            <div id="import-progress" style="display: none; margin-top: 30px;">
                <h2>Import Progress</h2>
                <div style="background: #f0f0f1; padding: 20px; border-radius: 4px;">
                    <div style="margin-bottom: 10px;">
                        <strong>Status:</strong> <span id="import-status-text">Starting...</span>
                    </div>
                    <div style="background: #fff; height: 30px; border-radius: 4px; overflow: hidden; position: relative;">
                        <div id="import-progress-bar" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-weight: bold;">
                            <span id="import-progress-text">0%</span>
                        </div>
                    </div>
                    <div id="import-log" style="margin-top: 15px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px;">
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .import-type-select {
            padding: 4px 8px;
            border-radius: 3px;
        }
        .video-url-list {
            font-size: 12px;
            color: #666;
        }
        .video-url-item {
            display: inline-block;
            margin-right: 10px;
            padding: 2px 6px;
            background: #f0f0f1;
            border-radius: 3px;
        }
        #import-log div {
            padding: 4px;
            border-bottom: 1px solid #ddd;
        }
        .log-success { color: #007017; }
        .log-error { color: #d63638; }
        .log-info { color: #2271b1; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Debug logging
            console.log('Import page loaded');
            console.log('videoSEO object:', typeof videoSEO !== 'undefined' ? videoSEO : 'NOT DEFINED');
            console.log('ajaxurl:', typeof ajaxurl !== 'undefined' ? ajaxurl : 'NOT DEFINED');
            
            // Ensure we have the required variables
            if (typeof videoSEO === 'undefined') {
                console.error('videoSEO object not found! Scripts may not be loading correctly.');
                $('#scan-status').html('<span style="color: red;">Error: Required scripts not loaded. Please refresh the page.</span>');
                return;
            }
            
            let foundPosts = [];
            
            function performScan(debugMode = false) {
                const btn = debugMode ? $('#debug-scan-btn') : $('#scan-posts-btn');
                const status = $('#scan-status');
                const scanMeta = $('#scan-meta-checkbox').is(':checked');
                
                console.log('Scan button clicked, debug mode:', debugMode, 'scan meta:', scanMeta);
                
                btn.prop('disabled', true);
                if (debugMode) {
                    btn.text('Debugging...');
                } else {
                    btn.text('Scanning...');
                }
                status.html('<span class="spinner is-active" style="float: none;"></span> Scanning posts' + (scanMeta ? ' and custom fields' : '') + '...');
                
                console.log('Sending AJAX request to:', videoSEO.ajaxurl);
                console.log('Nonce:', videoSEO.nonce);
                
                $.ajax({
                    url: videoSEO.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'scan_existing_posts',
                        nonce: videoSEO.nonce,
                        debug: debugMode ? 'true' : 'false',
                        scan_meta: scanMeta ? 'true' : 'false'
                    },
                    success: function(response) {
                        console.log('Scan response:', response);
                        
                        if (response.success) {
                            foundPosts = response.data.posts_with_videos;
                            $('#video-count').text(response.data.count);
                            
                            if (debugMode && response.data.debug) {
                                displayDebugInfo(response.data.debug);
                            }
                            
                            if (response.data.count > 0) {
                                displayResults(foundPosts);
                                $('#scan-results').show();
                                status.html('<span style="color: green;">✓</span> Found ' + response.data.count + ' posts with videos');
                            } else {
                                status.html('<span style="color: orange;">⚠</span> No posts with videos found (Scanned ' + response.data.total_posts + ' posts)');
                                $('#scan-results').hide();
                                
                                if (debugMode) {
                                    status.append(' <strong>Check debug info below to see what was found in your posts.</strong>');
                                } else if (!scanMeta) {
                                    status.append('<br><strong>💡 Tip: Try checking "Scan Custom Fields & Post Meta" and scanning again!</strong>');
                                }
                            }
                        } else {
                            console.error('Scan failed:', response.data);
                            status.html('<span style="color: red;">✗</span> Error: ' + response.data);
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error('AJAX error:', textStatus, errorThrown);
                        console.error('Response:', jqXHR.responseText);
                        status.html('<span style="color: red;">✗</span> Failed to scan posts. Check browser console for details.');
                    },
                    complete: function() {
                        btn.prop('disabled', false);
                        if (debugMode) {
                            btn.text('Debug Scan');
                        } else {
                            btn.text('Scan for Videos');
                        }
                    }
                });
            }
            
            $('#scan-posts-btn').on('click', function() {
                performScan(false);
            });
            
            $('#debug-scan-btn').on('click', function() {
                performScan(true);
            });
            
            function displayDebugInfo(debugData) {
                const debugContent = $('#debug-content');
                debugContent.empty();
                $('#debug-results').show();
                
                debugData.forEach(function(post) {
                    let metaInfo = '';
                    if (post.found_in_meta) {
                        metaInfo = '<br><strong style="color: green;">✓ Found in meta fields:</strong> ' + post.meta_fields.join(', ');
                    }
                    if (post.found_attachments) {
                        metaInfo += '<br><strong style="color: green;">✓ Found attached video files</strong>';
                    }
                    if (post.already_imported) {
                        metaInfo += '<br><strong style="color: blue;">ℹ Already imported</strong>';
                    }
                    
                    const postDebug = `
                        <div style="border-bottom: 1px solid #ddd; padding: 10px; margin-bottom: 10px;">
                            <strong>Post: ${post.post_title} (ID: ${post.post_id})</strong>
                            ${post.videos_found ? '<span style="color: green; margin-left: 10px;">✓ ' + post.videos_found + ' video(s) found</span>' : ''}
                            <br>
                            Content Length: ${post.content_length} characters<br>
                            Has iframe: ${post.has_iframe ? 'YES' : 'NO'}<br>
                            Has "youtube" text: ${post.has_youtube_text ? 'YES' : 'NO'}<br>
                            Has "vimeo" text: ${post.has_vimeo_text ? 'YES' : 'NO'}<br>
                            Has [embed] shortcode: ${post.has_embed_shortcode ? 'YES' : 'NO'}<br>
                            Has &lt;video&gt; tag: ${post.has_video_tag ? 'YES' : 'NO'}
                            ${metaInfo}
                            <br>
                            <em>Content preview:</em><br>
                            <div style="background: #fafafa; padding: 5px; margin-top: 5px; font-size: 11px; color: #666;">
                                ${post.content_preview}
                            </div>
                        </div>
                    `;
                    debugContent.append(postDebug);
                });
            }
            
            function displayResults(posts) {
                console.log('Displaying results for', posts.length, 'posts');
                const tbody = $('#video-posts-list');
                tbody.empty();
                
                posts.forEach(function(post, index) {
                    const videosList = post.videos.map(v => {
                        let label = v.type;
                        if (v.meta_key && v.meta_key !== 'attachment') {
                            label += ' (' + v.meta_key + ')';
                        } else if (v.type === 'attachment') {
                            label = 'attached video';
                        }
                        return '<span class="video-url-item">' + label + '</span>';
                    }).join('');
                    
                    const row = `
                        <tr data-post-id="${post.id}" data-index="${index}">
                            <td><input type="checkbox" class="post-checkbox" checked></td>
                            <td>
                                <strong>${post.title}</strong><br>
                                <a href="${post.url}" target="_blank">View Post</a>
                            </td>
                            <td>${post.date}</td>
                            <td class="video-url-list">${videosList}</td>
                            <td>
                                <select class="import-type-select">
                                    <option value="duplicate">Duplicate (keep original)</option>
                                    <option value="convert">Convert to Video</option>
                                </select>
                            </td>
                            <td>
                                <button type="button" class="button import-single-btn" data-index="${index}">
                                    Import This
                                </button>
                            </td>
                        </tr>
                    `;
                    tbody.append(row);
                });
            }
            
            $('#select-all').on('change', function() {
                $('.post-checkbox').prop('checked', $(this).prop('checked'));
            });
            
            $(document).on('click', '.import-single-btn', function() {
                const index = $(this).data('index');
                const row = $('tr[data-index="' + index + '"]');
                const post = foundPosts[index];
                const importType = row.find('.import-type-select').val();
                
                importPost(post, importType, row);
            });
            
            $('#import-selected-btn').on('click', function() {
                const selected = [];
                
                $('.post-checkbox:checked').each(function() {
                    const row = $(this).closest('tr');
                    const index = row.data('index');
                    const importType = row.find('.import-type-select').val();
                    
                    selected.push({
                        post: foundPosts[index],
                        importType: importType,
                        row: row
                    });
                });
                
                if (selected.length === 0) {
                    alert('Please select at least one post to import');
                    return;
                }
                
                importMultiplePosts(selected);
            });
            
            function importPost(post, importType, row) {
                const videoUrl = post.videos[0].url; // Use first video found
                const preventDuplicates = $('#prevent-duplicates-checkbox').is(':checked');
                
                console.log('Importing post:', post.id, 'Type:', importType);
                
                row.find('.import-single-btn').prop('disabled', true).text('Importing...');
                
                $.ajax({
                    url: videoSEO.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'import_video_post',
                        nonce: videoSEO.nonce,
                        post_id: post.id,
                        video_url: videoUrl,
                        import_type: importType,
                        prevent_duplicates: preventDuplicates ? 'true' : 'false'
                    },
                    success: function(response) {
                        console.log('Import response:', response);
                        
                        if (response.success) {
                            row.css('background-color', '#d4edda');
                            row.find('td:last').html(
                                '<a href="' + response.data.edit_url + '" class="button">Edit Video</a>'
                            );
                        } else {
                            alert('Error importing: ' + response.data);
                            row.find('.import-single-btn').prop('disabled', false).text('Import This');
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error('Import AJAX error:', textStatus, errorThrown);
                        alert('Failed to import post. Check console for details.');
                        row.find('.import-single-btn').prop('disabled', false).text('Import This');
                    }
                });
            }
            
            function importMultiplePosts(selected) {
                $('#import-progress').show();
                $('#import-selected-btn').prop('disabled', true);
                
                const preventDuplicates = $('#prevent-duplicates-checkbox').is(':checked');
                const total = selected.length;
                let completed = 0;
                let successful = 0;
                let failed = 0;
                
                function updateProgress() {
                    const percent = Math.round((completed / total) * 100);
                    $('#import-progress-bar').css('width', percent + '%');
                    $('#import-progress-text').text(percent + '%');
                    $('#import-status-text').text(`Imported ${completed} of ${total} posts (${successful} successful, ${failed} failed)`);
                }
                
                function logMessage(message, type = 'info') {
                    const logClass = 'log-' + type;
                    $('#import-log').prepend('<div class="' + logClass + '">' + message + '</div>');
                }
                
                function importNext(index) {
                    if (index >= selected.length) {
                        $('#import-status-text').text('Import complete!');
                        $('#import-selected-btn').prop('disabled', false);
                        logMessage('Import complete: ' + successful + ' successful, ' + failed + ' failed', 'info');
                        return;
                    }
                    
                    const item = selected[index];
                    const videoUrl = item.post.videos[0].url;
                    
                    logMessage('Importing: ' + item.post.title, 'info');
                    
                    $.ajax({
                        url: videoSEO.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'import_video_post',
                            nonce: videoSEO.nonce,
                            post_id: item.post.id,
                            video_url: videoUrl,
                            import_type: item.importType,
                            prevent_duplicates: preventDuplicates ? 'true' : 'false'
                        },
                        success: function(response) {
                            completed++;
                            
                            if (response.success) {
                                successful++;
                                logMessage('✓ Successfully imported: ' + item.post.title, 'success');
                                item.row.css('background-color', '#d4edda');
                                item.row.find('td:last').html(
                                    '<a href="' + response.data.edit_url + '" class="button">Edit Video</a>'
                                );
                            } else {
                                failed++;
                                logMessage('✗ Failed: ' + item.post.title + ' - ' + response.data, 'error');
                            }
                            
                            updateProgress();
                            importNext(index + 1);
                        },
                        error: function() {
                            completed++;
                            failed++;
                            logMessage('✗ Error importing: ' + item.post.title, 'error');
                            updateProgress();
                            importNext(index + 1);
                        }
                    });
                }
                
                logMessage('Starting import of ' + total + ' posts...', 'info');
                importNext(0);
            }
        });
        </script>
        <?php
    }
    
    public function analytics_page() {
        global $wpdb;
        
        $video_id = isset($_GET['video_id']) ? intval($_GET['video_id']) : 0;
        
        ?>
        <div class="wrap">
            <h1>Video Analytics Dashboard</h1>
            
            <?php if ($video_id): 
                $video = get_post($video_id);
                ?>
                <h2>Analytics for: <?php echo esc_html($video->post_title); ?></h2>
                <p><a href="<?php echo admin_url('edit.php?post_type=video&page=video-analytics'); ?>">← Back to Overview</a></p>
                
                <?php
                $analytics = $wpdb->get_results($wpdb->prepare(
                    "SELECT DATE(view_date) as date, COUNT(*) as views, AVG(watch_time) as avg_time, SUM(completed) as completions
                    FROM {$this->table_name}
                    WHERE video_id = %d
                    GROUP BY DATE(view_date)
                    ORDER BY date DESC
                    LIMIT 30",
                    $video_id
                ));
                
                $dates = array_reverse(array_column($analytics, 'date'));
                $views = array_reverse(array_column($analytics, 'views'));
                ?>
                
                <div style="max-width: 800px; margin: 20px 0;">
                    <canvas id="viewsChart"></canvas>
                </div>
                
                <script>
                const ctx = document.getElementById('viewsChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($dates); ?>,
                        datasets: [{
                            label: 'Daily Views',
                            data: <?php echo json_encode($views); ?>,
                            borderColor: 'rgb(75, 192, 192)',
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true
                    }
                });
                </script>
                
            <?php else: 
                // Overview of all videos
                $videos = get_posts(['post_type' => 'video', 'posts_per_page' => -1]);
                ?>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Video</th>
                            <th>Total Views</th>
                            <th>Avg Watch Time</th>
                            <th>Completion Rate</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($videos as $video): 
                            $stats = $wpdb->get_row($wpdb->prepare(
                                "SELECT COUNT(*) as views, AVG(watch_time) as avg_time, (SUM(completed) / COUNT(*)) * 100 as completion_rate
                                FROM {$this->table_name}
                                WHERE video_id = %d",
                                $video->ID
                            ));
                        ?>
                        <tr>
                            <td><strong><?php echo esc_html($video->post_title); ?></strong></td>
                            <td><?php echo number_format($stats->views); ?></td>
                            <td><?php echo gmdate('i:s', (int)$stats->avg_time); ?></td>
                            <td><?php echo round($stats->completion_rate, 1); ?>%</td>
                            <td>
                                <a href="<?php echo admin_url('edit.php?post_type=video&page=video-analytics&video_id=' . $video->ID); ?>">View Details</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function settings_page() {
        $youtube_api_key = get_option('video_seo_youtube_api_key', '');
        $enable_analytics = get_option('video_seo_enable_analytics', '1');
        $auto_embed = get_option('video_seo_auto_embed', '1');
        ?>
        <div class="wrap">
            <h1>Video SEO Pro Settings</h1>
            
            <form id="video-seo-settings-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="youtube_api_key">YouTube API Key</label>
                        </th>
                        <td>
                            <input type="text" id="youtube_api_key" name="youtube_api_key" value="<?php echo esc_attr($youtube_api_key); ?>" class="regular-text">
                            <p class="description">
                                Get your API key from <a href="https://console.developers.google.com/" target="_blank">Google Developer Console</a>.
                                Enable the YouTube Data API v3.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="enable_analytics">Enable Analytics Tracking</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="enable_analytics" name="enable_analytics" value="1" <?php checked($enable_analytics, '1'); ?>>
                                Track video views and engagement
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="auto_embed">Auto-Embed Videos</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" id="auto_embed" name="auto_embed" value="1" <?php checked($auto_embed, '1'); ?>>
                                Automatically display video player at the top of video posts
                            </label>
                            <p class="description">
                                If disabled, you can manually add the video using the [video_player] shortcode
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">Save Settings</button>
                </p>
            </form>
            
            <div id="settings-message" style="margin-top: 20px;"></div>
            
            <hr>
            
            <div class="card">
                <h2>Video Sitemap</h2>
                <p>Your video sitemap is available at:</p>
                <p><code><?php echo home_url('/video-sitemap.xml'); ?></code></p>
                <p>Submit this URL to Google Search Console to help Google discover your videos.</p>
            </div>
            
            <div class="card">
                <h2>Shortcode Usage</h2>
                <p>Use the <code>[video_player]</code> shortcode to display the video player anywhere in your content:</p>
                <ul>
                    <li><code>[video_player]</code> - Displays the current video</li>
                    <li><code>[video_player id="123"]</code> - Displays a specific video by ID</li>
                </ul>
            </div>
            
            <div class="card">
                <h2>Features</h2>
                <ul>
                    <li>✓ YouTube API integration for automatic data import</li>
                    <li>✓ Auto-generated SEO titles and descriptions</li>
                    <li>✓ Schema.org VideoObject markup for rich snippets</li>
                    <li>✓ XML video sitemap for search engines</li>
                    <li>✓ Analytics tracking (views, watch time, completion rate)</li>
                    <li>✓ Companion blog post generation</li>
                    <li>✓ Automatic video embedding (YouTube, Vimeo, direct files)</li>
                </ul>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#video-seo-settings-form').on('submit', function(e) {
                e.preventDefault();
                
                $.post(ajaxurl, {
                    action: 'save_video_settings',
                    nonce: videoSEO.nonce,
                    youtube_api_key: $('#youtube_api_key').val(),
                    enable_analytics: $('#enable_analytics').is(':checked') ? '1' : '0',
                    auto_embed: $('#auto_embed').is(':checked') ? '1' : '0'
                }, function(response) {
                    if (response.success) {
                        $('#settings-message').html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                    } else {
                        $('#settings-message').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    public function load_video_template($template) {
        if (is_singular('video')) {
            $plugin_template = plugin_dir_path(__FILE__) . 'templates/single-video.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }
    
    public function render_video_player($video_id) {
        $video_url = get_post_meta($video_id, '_video_url', true);
        $youtube_id = get_post_meta($video_id, '_youtube_id', true);
        $video_thumbnail = get_post_meta($video_id, '_video_thumbnail', true);
        
        if (empty($video_url)) {
            return '';
        }
        
        $output = '<div class="video-seo-player" style="max-width: 100%; margin: 20px 0;">';
        
        // YouTube embed
        if ($youtube_id) {
            $output .= '<div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden;">';
            $output .= '<iframe src="https://www.youtube.com/embed/' . esc_attr($youtube_id) . '?rel=0" ';
            $output .= 'style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" ';
            $output .= 'frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" ';
            $output .= 'allowfullscreen></iframe>';
            $output .= '</div>';
        }
        // Vimeo embed
        elseif (strpos($video_url, 'vimeo.com') !== false) {
            preg_match('/vimeo\.com\/(\d+)/', $video_url, $matches);
            if (!empty($matches[1])) {
                $vimeo_id = $matches[1];
                $output .= '<div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden;">';
                $output .= '<iframe src="https://player.vimeo.com/video/' . esc_attr($vimeo_id) . '" ';
                $output .= 'style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" ';
                $output .= 'frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>';
                $output .= '</div>';
            }
        }
        // Direct video file
        else {
            $output .= '<video controls style="width: 100%; height: auto;"';
            if ($video_thumbnail) {
                $output .= ' poster="' . esc_url($video_thumbnail) . '"';
            }
            $output .= '>';
            $output .= '<source src="' . esc_url($video_url) . '" type="video/mp4">';
            $output .= 'Your browser does not support the video tag.';
            $output .= '</video>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
}

// Initialize the plugin
new VideoSEOPro();