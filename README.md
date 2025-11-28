# youtube-seo-directory-sitemap
Directory based Sitemap for WPML plugin to create somthing like sitemap_general.xml + sitemap_{lang}.xml

Add the snippet below to your functions.php or better consult with yout developer, he knows what do to.

Tested on 2 projects with WPML websites

Flush permalinks if needed

<?php

add_action('init', function () {
    add_rewrite_rule('^sitemap_general\.xml$', 'index.php?custom_sitemap=general', 'top');
    add_rewrite_rule('^sitemap_([a-zA-Z_-]+)\.xml$', 'index.php?custom_sitemap=$matches[1]', 'top');
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'custom_sitemap';
    return $vars;
});

add_action('parse_request', function ($wp) {

    if (!array_key_exists('custom_sitemap', $wp->query_vars)) {
        return;
    }

    $type = $wp->query_vars['custom_sitemap'];

    // Clear all output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Disable WPML filters
    global $sitepress;
    if (isset($sitepress)) {
        remove_filter('home_url', [$sitepress, 'home_url_filter'], 10);
        remove_filter('page_link', [$sitepress, 'page_link_filter'], 10);
        remove_filter('post_link', [$sitepress, 'post_link_filter'], 10);
        remove_filter('post_type_link', [$sitepress, 'post_type_link_filter'], 10);
    }

    // Send XML header
    header('Content-Type: application/xml; charset=utf-8');

    // Output sitemap
    if ($type === 'general') {
    echo generate_wpml_sitemap_index();
} else {
    echo generate_wpml_language_sitemap($type);
}

    exit; // stop WordPress entirely
}, 0);


function generate_wpml_sitemap_index() {
    // Get all active WPML languages
    if (!function_exists('icl_get_languages')) return '';
    $languages = icl_get_languages('skip_missing=0');

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

    if (!empty($languages)) {
        foreach ($languages as $lang) {
            $lang_code = $lang['language_code'];
            $url = get_site_url(null, "/sitemap_{$lang_code}.xml");
            $xml .= "<sitemap><loc>{$url}</loc></sitemap>";
        }
    }

    $xml .= '</sitemapindex>';
    return $xml;
}


function generate_wpml_language_sitemap($lang_code) {
    global $sitepress;

    // switch WPML language
    if (isset($sitepress)) {
        $sitepress->switch_lang($lang_code, true);
    }

    $post_types = ['post', 'page'];

    $posts = get_posts([
        'post_type'       => $post_types,
        'post_status'     => 'publish',
        'numberposts'     => -1,
        'suppress_filters'=> false, // important to respect WPML language filter
    ]);

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>';
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

    foreach ($posts as $post) {
        $translated_id = apply_filters('wpml_object_id', $post->ID, $post->post_type, false, $lang_code);
        if (!$translated_id) continue;

        $permalink = get_permalink($translated_id);
        $lastmod   = get_post_modified_time('c', true, $translated_id);

        $xml .= "<url>";
        $xml .= "<loc>{$permalink}</loc>";
        $xml .= "<lastmod>{$lastmod}</lastmod>";
        $xml .= "</url>";
    }

    // restore original language
    if (isset($sitepress)) {
        $sitepress->switch_lang(ICL_LANGUAGE_CODE, true);
    }

    $xml .= '</urlset>';
    return $xml;
}