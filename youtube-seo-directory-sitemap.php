<?php
/**
 * YouTube SEO Directory Sitemap for WPML
 * 
 * Add this to your WordPress functions.php
 * 
 * This code generates directory-based sitemaps for WPML plugin
 * creating files like sitemap_general.xml + sitemap_{lang}.xml
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generate WPML language-specific sitemaps
 */
function youtube_seo_directory_sitemap_init() {
    add_action('init', 'youtube_seo_register_sitemap_routes');
}
add_action('plugins_loaded', 'youtube_seo_directory_sitemap_init');

/**
 * Register sitemap routes
 */
function youtube_seo_register_sitemap_routes() {
    add_rewrite_rule('^sitemap_general\.xml$', 'index.php?youtube_sitemap=general', 'top');
    
    // Register language-specific sitemap routes if WPML is active
    if (function_exists('icl_get_languages')) {
        $languages = icl_get_languages('skip_missing=0');
        if (!empty($languages)) {
            foreach ($languages as $lang_code => $lang) {
                add_rewrite_rule('^sitemap_' . $lang_code . '\.xml$', 'index.php?youtube_sitemap=' . $lang_code, 'top');
            }
        }
    }
}

/**
 * Add custom query vars
 */
function youtube_seo_sitemap_query_vars($vars) {
    $vars[] = 'youtube_sitemap';
    return $vars;
}
add_filter('query_vars', 'youtube_seo_sitemap_query_vars');

/**
 * Handle sitemap requests
 */
function youtube_seo_sitemap_template_redirect() {
    $sitemap_type = get_query_var('youtube_sitemap');
    
    if (!empty($sitemap_type)) {
        header('Content-Type: application/xml; charset=utf-8');
        echo youtube_seo_generate_sitemap($sitemap_type);
        exit;
    }
}
add_action('template_redirect', 'youtube_seo_sitemap_template_redirect');

/**
 * Generate sitemap XML content
 *
 * @param string $type Sitemap type (general or language code)
 * @return string XML content
 */
function youtube_seo_generate_sitemap($type) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    // Get posts based on sitemap type
    $args = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => -1,
    );
    
    $language_switched = false;
    $default_language = null;
    
    // If WPML is active and this is a language-specific sitemap
    if ($type !== 'general' && function_exists('icl_get_languages')) {
        $languages = icl_get_languages('skip_missing=0');
        $default_language = apply_filters('wpml_default_language', null);
        
        // Validate that the requested language exists
        if (isset($languages[$type])) {
            $args['suppress_filters'] = false;
            do_action('wpml_switch_language', $type);
            $language_switched = true;
        }
    }
    
    $posts = get_posts($args);
    
    foreach ($posts as $post) {
        $xml .= "\t<url>\n";
        $xml .= "\t\t<loc>" . esc_url(get_permalink($post->ID)) . "</loc>\n";
        $xml .= "\t\t<lastmod>" . get_the_modified_date('c', $post->ID) . "</lastmod>\n";
        $xml .= "\t\t<changefreq>weekly</changefreq>\n";
        $xml .= "\t\t<priority>0.8</priority>\n";
        $xml .= "\t</url>\n";
    }
    
    // Reset language if WPML was switched
    if ($language_switched && $default_language) {
        do_action('wpml_switch_language', $default_language);
    }
    
    $xml .= '</urlset>';
    
    return $xml;
}
