<?php
/**
 * Plugin Name: Video SEO Pro (Simple)
 * Plugin URI: https://example.com/video-seo-pro
 * Description: Add video SEO features to your existing posts - no separate post type needed!
 * Version: 2.0.1
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: video-seo-pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class VideoSEOProSimple {
    private $version = '2.0.1';
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

        register_activation_hook(__FILE__, [$this, 'activate']);
