<?php
/**
 * GutenBlock Bridge (MU-Plugin)
 * Content-Replacement und Style-Preview für GutenBlock SaaS
 * Version: 2.0.1
 * 
 * INSTALLATION: Wird automatisch von GutenBlock Pro nach /wp-content/mu-plugins/ kopiert
 * MU-Plugins werden automatisch geladen, kein Aktivieren nötig.
 * 
 * WICHTIG: Diese Datei hat KEINEN Plugin-Header, damit WordPress sie nicht als separates Plugin erkennt.
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================================================
// KONFIGURATION
// ============================================================================

// SaaS API URL - kann per wp-config.php überschrieben werden
if (!defined('GUTENBLOCK_SAAS_API_URL')) {
    if (defined('GUTENBLOCK_DEV_MODE') && GUTENBLOCK_DEV_MODE) {
        define('GUTENBLOCK_SAAS_API_URL', 'http://localhost:3000');
    } else {
        define('GUTENBLOCK_SAAS_API_URL', 'https://app.gutenblock.com');
    }
}

// ============================================================================
// CONTENT REPLACEMENT (Live-Preview im SaaS)
// ============================================================================

add_action('wp_enqueue_scripts', 'gutenblock_bridge_content_replacement');

function gutenblock_bridge_content_replacement() {
    // Nur wenn Content-ID vorhanden
    if (!isset($_GET['gutenblock-content-id'])) {
        return;
    }
    
    $content_id = sanitize_text_field($_GET['gutenblock-content-id']);
    
    // API-URL ermitteln
    $api_url = gutenblock_bridge_get_api_url();
    
    // Inline-Script für Content-Replacement
    $script = gutenblock_bridge_get_replacement_script();
    
    wp_register_script('gutenblock-content-replacement', false);
    wp_enqueue_script('gutenblock-content-replacement');
    wp_add_inline_script('gutenblock-content-replacement', $script);
    
    wp_localize_script('gutenblock-content-replacement', 'gutenblockContent', array(
        'apiUrl' => $api_url,
        'contentId' => $content_id
    ));
}

function gutenblock_bridge_get_api_url() {
    // Localhost-Erkennung
    $current_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $is_localhost = (
        strpos($current_host, 'localhost') !== false ||
        strpos($current_host, '127.0.0.1') !== false ||
        strpos($current_host, '.local') !== false
    );
    
    if (defined('GUTENBLOCK_DEV_MODE') && GUTENBLOCK_DEV_MODE) {
        return 'http://localhost:3000/api/v1/content/';
    } elseif ($is_localhost) {
        return 'http://localhost:3000/api/v1/content/';
    }
    
    return GUTENBLOCK_SAAS_API_URL . '/api/v1/content/';
}

function gutenblock_bridge_get_replacement_script() {
    return <<<'JS'
(function() {
    'use strict';
    
    if (typeof gutenblockContent === 'undefined') {
        console.log('GutenBlock Bridge: Keine Content-Daten vorhanden');
        return;
    }
    
    const { apiUrl, contentId } = gutenblockContent;
    const normalizedApiUrl = apiUrl.endsWith('/') ? apiUrl : apiUrl + '/';
    const fullUrl = normalizedApiUrl + contentId;
    
    console.log('GutenBlock Bridge: Lade Content...', { apiUrl: normalizedApiUrl, contentId, fullUrl });
    
    fetch(fullUrl)
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error('API-Fehler: ' + response.status + ' - ' + text);
                });
            }
            return response.json();
        })
        .then(data => {
            if (!data.content || typeof data.content !== 'object') {
                console.warn('GutenBlock Bridge: Keine Content-Felder gefunden');
                return;
            }
            
            let replacedCount = 0;
            
            for (const [fieldId, text] of Object.entries(data.content)) {
                if (!text) continue;
                
                // Primär: data-content-field Attribut
                let elements = document.querySelectorAll(`[data-content-field="${fieldId}"]`);
                
                // Fallback: CSS-ID
                if (elements.length === 0) {
                    elements = document.querySelectorAll('#' + fieldId);
                }
                
                if (elements.length > 0) {
                    elements.forEach(element => {
                        element.textContent = text;
                        replacedCount++;
                    });
                    console.log('GutenBlock Bridge: Ersetzt', fieldId, '→', text.substring(0, 50) + '...');
                } else {
                    console.warn('GutenBlock Bridge: Element nicht gefunden:', fieldId);
                }
            }
            
            console.log(`GutenBlock Bridge: ${replacedCount} Felder ersetzt`);
        })
        .catch(error => {
            console.error('GutenBlock Bridge: Fehler beim Laden', error);
        });
})();
JS;
}

// ============================================================================
// LINK-DEAKTIVIERUNG (iFrame-Preview)
// ============================================================================

add_action('wp_enqueue_scripts', 'gutenblock_bridge_disable_links');

function gutenblock_bridge_disable_links() {
    if (!isset($_GET['gutenblock-iframe'])) {
        return;
    }
    
    $script = <<<'JS'
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('a').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
        });
    });
});
JS;
    
    wp_register_script('gutenblock-disable-links', false);
    wp_enqueue_script('gutenblock-disable-links');
    wp_add_inline_script('gutenblock-disable-links', $script);
}

// ============================================================================
// CORS & CSP HEADERS
// ============================================================================

add_action('send_headers', 'gutenblock_bridge_send_headers');

function gutenblock_bridge_send_headers() {
    header_remove('X-Frame-Options');
    header("Content-Security-Policy: frame-ancestors 'self' https://gutenblock.com https://app.gutenblock.com https://*.vercel.app http://localhost:3000;");
}

add_action('rest_api_init', 'gutenblock_bridge_cors_headers');

function gutenblock_bridge_cors_headers() {
    add_filter('rest_pre_serve_request', function ($value) {
        $origin = get_http_origin();
        $allowed = array(
            'https://gutenblock.com',
            'https://app.gutenblock.com',
            'http://localhost:3000'
        );
        
        // Auch Vercel Preview URLs erlauben
        if ($origin && (in_array($origin, $allowed, true) || strpos($origin, '.vercel.app') !== false)) {
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Credentials: true");
            header("Access-Control-Allow-Headers: Authorization, Content-Type");
        }
        return $value;
    });
}

// ============================================================================
// STYLE-VARIANTEN API
// ============================================================================

add_action('rest_api_init', 'gutenblock_bridge_register_api');

function gutenblock_bridge_register_api() {
    register_rest_route('gutenblock/v1', '/style-variants', array(
        'methods' => 'GET',
        'callback' => 'gutenblock_bridge_get_style_variants',
        'permission_callback' => '__return_true'
    ));
    
    register_rest_route('gutenblock/v1', '/pages', array(
        'methods' => 'GET',
        'callback' => 'gutenblock_bridge_get_pages',
        'permission_callback' => '__return_true'
    ));
    
    register_rest_route('gutenblock/v1', '/sections', array(
        'methods' => 'GET',
        'callback' => 'gutenblock_bridge_get_sections',
        'permission_callback' => '__return_true',
        'args' => array(
            'page' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            )
        )
    ));
    
    register_rest_route('gutenblock/v1', '/bridge-version', array(
        'methods' => 'GET',
        'callback' => function() {
            return array('version' => '2.0.0');
        },
        'permission_callback' => '__return_true'
    ));
}

function gutenblock_bridge_get_style_variants() {
    $theme = wp_get_theme();
    $stylesheet_dir = get_stylesheet_directory();
    $styles_dir = $stylesheet_dir . '/styles';
    $theme_json_file = $stylesheet_dir . '/theme.json';
    
    $variants = array();
    $default_variant = null;
    
    // Lade Standard-Theme
    if (class_exists('WP_Theme_JSON_Resolver')) {
        $theme_json = WP_Theme_JSON_Resolver::get_merged_data('custom');
        $theme_data = $theme_json->get_data();
        
        $palette = null;
        if (isset($theme_data['settings']['color']['palette'])) {
            $palette = $theme_data['settings']['color']['palette'];
        }
        
        if ($palette && is_array($palette)) {
            $default_variant = gutenblock_bridge_extract_variant_data(array(
                'settings' => array('color' => array('palette' => $palette)),
                'styles' => isset($theme_data['styles']) ? $theme_data['styles'] : array()
            ), '', 'Standard');
        }
    }
    
    // Lade Style-Variationen
    if (is_dir($styles_dir)) {
        $files = glob($styles_dir . '/*.json');
        
        foreach ($files as $file) {
            $json_content = file_get_contents($file);
            $style_data = json_decode($json_content, true);
            
            if ($style_data && isset($style_data['title'])) {
                $slug = basename($file, '.json');
                $variant_data = gutenblock_bridge_extract_variant_data($style_data, $slug, $style_data['title']);
                if ($variant_data) {
                    $variants[] = $variant_data;
                }
            }
        }
    }
    
    return array(
        'variants' => $variants,
        'theme' => $theme->get('Name'),
        'default' => $default_variant
    );
}

function gutenblock_bridge_extract_variant_data($style_data, $slug, $title) {
    $base_color = '#CCCCCC';
    $contrast_color = '#333333';
    
    $palette = null;
    if (isset($style_data['settings']['color']['palette']['theme'])) {
        $palette = $style_data['settings']['color']['palette']['theme'];
    } elseif (isset($style_data['settings']['color']['palette'])) {
        $palette = $style_data['settings']['color']['palette'];
    }
    
    if (!is_array($palette)) {
        $palette = array();
    }
    
    if (!empty($palette)) {
        foreach ($palette as $color_item) {
            if (isset($color_item['slug']) && $color_item['slug'] === 'base' && isset($color_item['color'])) {
                $base_color = $color_item['color'];
                break;
            }
        }
        if ($base_color === '#CCCCCC' && isset($palette[0]['color'])) {
            $base_color = $palette[0]['color'];
        }
        
        foreach ($palette as $color_item) {
            if (isset($color_item['slug']) && $color_item['slug'] === 'contrast' && isset($color_item['color'])) {
                $contrast_color = $color_item['color'];
                break;
            }
        }
        if ($contrast_color === '#333333' && isset($palette[1]['color'])) {
            $contrast_color = $palette[1]['color'];
        }
    }
    
    return array(
        'id' => $slug,
        'name' => $title,
        'color' => $base_color,
        'baseColor' => $base_color,
        'contrastColor' => $contrast_color,
        'palette' => $palette
    );
}

function gutenblock_bridge_get_pages() {
    $pages = get_pages(array(
        'post_status' => 'publish',
        'sort_column' => 'menu_order,post_title'
    ));
    
    $result = array();
    
    foreach ($pages as $page) {
        $result[] = array(
            'id' => $page->ID,
            'title' => $page->post_title,
            'slug' => $page->post_name,
            'url' => get_permalink($page->ID)
        );
    }
    
    return new WP_REST_Response($result, 200);
}

function gutenblock_bridge_get_sections($request) {
    $page_slug = $request->get_param('page');
    $page = get_page_by_path($page_slug);
    
    if (!$page) {
        return new WP_REST_Response(array('error' => 'Page not found'), 404);
    }
    
    $content = apply_filters('the_content', $page->post_content);
    
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $content);
    
    $sections = array();
    $section_groups = array();
    $xpath = new DOMXPath($dom);
    
    $section_nodes = $xpath->query("//section[contains(@class, 'gb-section-')]");
    
    foreach ($section_nodes as $node) {
        $classes = $node->getAttribute('class');
        
        if (preg_match('/gb-section-([a-z0-9-]+)/i', $classes, $matches)) {
            $section_id = 'gb-section-' . $matches[1];
            $section_slug = $matches[1];
            
            $base_slug = $section_slug;
            $variant_number = null;
            if (preg_match('/^(.+)-v(\d+)$/', $section_slug, $variant_matches)) {
                $base_slug = $variant_matches[1];
                $variant_number = intval($variant_matches[2]);
            }
            
            $base_id = 'gb-section-' . $base_slug;
            $section_name = ucfirst(str_replace('-', ' ', $base_slug));
            
            $is_hidden_by_default = preg_match('/\bgb-section-off\b/', $classes);
            
            if (!isset($section_groups[$base_id])) {
                $section_groups[$base_id] = array(
                    'id' => $base_id,
                    'name' => $section_name,
                    'variants' => array()
                );
            }
            
            $section_groups[$base_id]['variants'][] = array(
                'id' => $section_id,
                'name' => $variant_number ? $section_name . ' v' . $variant_number : $section_name,
                'isDefault' => $variant_number === null,
                'isHiddenByDefault' => $is_hidden_by_default
            );
        }
    }
    
    foreach ($section_groups as $group) {
        usort($group['variants'], function($a, $b) {
            if ($a['isDefault']) return -1;
            if ($b['isDefault']) return 1;
            return 0;
        });
        
        $default_variant = null;
        foreach ($group['variants'] as $variant) {
            if (!$variant['isHiddenByDefault']) {
                $default_variant = $variant['id'];
                break;
            }
        }
        if (!$default_variant) {
            $default_variant = $group['variants'][0]['id'];
        }
        
        $sections[] = array(
            'id' => $group['id'],
            'name' => $group['name'],
            'variants' => $group['variants'],
            'hasVariants' => count($group['variants']) > 1,
            'defaultVariant' => $default_variant,
            'isHiddenByDefault' => $group['variants'][0]['isHiddenByDefault']
        );
    }
    
    return new WP_REST_Response($sections, 200);
}

// ============================================================================
// CUSTOM STYLES
// ============================================================================

add_action('wp_enqueue_scripts', 'gutenblock_bridge_enqueue_custom_styles', 999);
add_action('enqueue_block_editor_assets', 'gutenblock_bridge_enqueue_custom_styles', 999);

function gutenblock_bridge_enqueue_custom_styles() {
    static $enqueued = false;
    
    if ($enqueued) {
        return;
    }
    
    $css_file = get_stylesheet_directory() . '/css/gutenblock-custom-styles.css';
    if (file_exists($css_file)) {
        wp_enqueue_style(
            'gutenblock-custom-styles',
            get_stylesheet_directory_uri() . '/css/gutenblock-custom-styles.css',
            array(),
            filemtime($css_file)
        );
        $enqueued = true;
    }
}

// ============================================================================
// CACHE-DEAKTIVIERUNG FÜR PREVIEW
// ============================================================================

if (!empty($_GET['gutenblock-preview']) || !empty($_GET['gutenblock-preview-content'])) {
    add_filter('wp_cache_themes_persistently', '__return_false');
    if (!defined('WP_CACHE')) {
        define('WP_CACHE', false);
    }
}

// ============================================================================
// ACTIVE STYLE FÜR EDITOR
// ============================================================================

add_filter('pre_option_wp_theme_preview', function($value) {
    if (!empty($_GET['gutenblock-preview'])) {
        return sanitize_text_field($_GET['gutenblock-preview']);
    }
    return $value;
});

// ============================================================================
// STYLE-PREVIEW (Inline CSS für Varianten)
// ============================================================================

add_filter('wp_theme_json_data_theme', 'gutenblock_bridge_apply_style_variant', 20);

function gutenblock_bridge_apply_style_variant($theme_json) {
    $style_slug = !empty($_GET['gutenblock-preview']) 
        ? sanitize_text_field($_GET['gutenblock-preview']) 
        : get_option('gutenblock_active_style', '');
    
    if (!$style_slug) {
        return $theme_json;
    }
    
    $style_file = get_stylesheet_directory() . '/styles/' . $style_slug . '.json';
    if (!file_exists($style_file)) {
        $style_file = get_template_directory() . '/styles/' . $style_slug . '.json';
    }
    
    if (!file_exists($style_file)) {
        return $theme_json;
    }
    
    $style_json = file_get_contents($style_file);
    $style_variation = json_decode($style_json, true);
    
    if (!$style_variation || !is_array($style_variation)) {
        return $theme_json;
    }
    
    $theme_data = $theme_json->get_data();
    $modified = false;
    
    if (isset($style_variation['settings'])) {
        if (!isset($theme_data['settings'])) {
            $theme_data['settings'] = array();
        }
        
        if (isset($style_variation['settings']['color']['palette'])) {
            if (!isset($theme_data['settings']['color'])) {
                $theme_data['settings']['color'] = array();
            }
            $theme_data['settings']['color']['palette'] = $style_variation['settings']['color']['palette'];
            $modified = true;
        }
        
        if (isset($style_variation['settings']['typography'])) {
            if (!isset($theme_data['settings']['typography'])) {
                $theme_data['settings']['typography'] = array();
            }
            $theme_data['settings']['typography'] = array_merge(
                $theme_data['settings']['typography'],
                $style_variation['settings']['typography']
            );
            $modified = true;
        }
        
        if (isset($style_variation['settings']['layout'])) {
            if (!isset($theme_data['settings']['layout'])) {
                $theme_data['settings']['layout'] = array();
            }
            $theme_data['settings']['layout'] = array_merge(
                $theme_data['settings']['layout'],
                $style_variation['settings']['layout']
            );
            $modified = true;
        }
    }
    
    if (isset($style_variation['styles'])) {
        if (!isset($theme_data['styles'])) {
            $theme_data['styles'] = array();
        }
        $theme_data['styles'] = array_merge_recursive($theme_data['styles'], $style_variation['styles']);
        $modified = true;
    }
    
    if ($modified) {
        return $theme_json->update_with($theme_data);
    }
    
    return $theme_json;
}

add_action('wp_head', 'gutenblock_bridge_inline_css', 999);

function gutenblock_bridge_inline_css() {
    $style_slug = !empty($_GET['gutenblock-preview']) 
        ? sanitize_text_field($_GET['gutenblock-preview']) 
        : get_option('gutenblock_active_style', '');
    
    if (!$style_slug) {
        return;
    }
    
    $style_file = get_stylesheet_directory() . '/styles/' . $style_slug . '.json';
    if (!file_exists($style_file)) {
        $style_file = get_template_directory() . '/styles/' . $style_slug . '.json';
    }
    
    if (!file_exists($style_file)) {
        return;
    }
    
    $style_json = file_get_contents($style_file);
    $style_data = json_decode($style_json, true);
    
    if (!$style_data) {
        return;
    }
    
    echo '<style id="gutenblock-preview-inline">';
    echo ':root {';
    
    if (isset($style_data['settings']['color']['palette'])) {
        foreach ($style_data['settings']['color']['palette'] as $color) {
            if (isset($color['slug']) && isset($color['color'])) {
                echo '--wp--preset--color--' . esc_attr($color['slug']) . ':' . esc_attr($color['color']) . ';';
            }
        }
    }
    
    if (isset($style_data['settings']['typography']['fontFamilies'])) {
        foreach ($style_data['settings']['typography']['fontFamilies'] as $font) {
            if (isset($font['slug']) && isset($font['fontFamily'])) {
                echo '--wp--preset--font-family--' . esc_attr($font['slug']) . ':' . esc_attr($font['fontFamily']) . ';';
            }
        }
    }
    
    echo '}';
    echo '</style>';
    
    $mode = !empty($_GET['gutenblock-preview']) ? 'Preview' : 'Persistent';
    echo '<!-- GutenBlock ' . $mode . ': ' . esc_attr($style_slug) . ' (Farben + Typografie) -->';
}

// ============================================================================
// BACKUP API (UpdraftPlus Integration)
// ============================================================================

add_action('rest_api_init', 'gutenblock_bridge_register_backup_api');

function gutenblock_bridge_register_backup_api() {
    register_rest_route('gutenblock-bridge/v1', '/backup-info', array(
        'methods' => 'GET',
        'callback' => 'gutenblock_bridge_get_backup_info',
        'permission_callback' => '__return_true'
    ));
}

function gutenblock_bridge_get_backup_info() {
    $backup_dir = WP_CONTENT_DIR . '/gutenblock-backups';
    $backup_file = $backup_dir . '/template-backup.zip';
    
    if (!file_exists($backup_file)) {
        return new WP_Error('no_backup', 'Template-Backup nicht gefunden', array('status' => 404));
    }
    
    return array(
        'url' => content_url('gutenblock-backups/' . basename($backup_file)),
        'filename' => basename($backup_file),
        'size' => filesize($backup_file),
        'size_mb' => round(filesize($backup_file) / 1024 / 1024, 2),
        'last_modified' => date('Y-m-d H:i:s', filemtime($backup_file)),
        'timestamp' => filemtime($backup_file)
    );
}

// ============================================================================
// UPDRAFTPLUS HOOK: Auto-Package nach Backup
// ============================================================================

add_filter('updraftplus_backup_complete', 'gutenblock_bridge_auto_package', 10, 1);

function gutenblock_bridge_auto_package($delete_jobdata) {
    if (!class_exists('UpdraftPlus_Options')) {
        return $delete_jobdata;
    }
    
    $backup_history = UpdraftPlus_Options::get_updraft_option('updraft_backup_history');
    if (empty($backup_history) || !is_array($backup_history)) {
        return $delete_jobdata;
    }
    
    $latest_timestamp = 0;
    $latest_backup = null;
    $latest_nonce = '';
    
    foreach ($backup_history as $timestamp_key => $backup_set) {
        $ts = is_numeric($timestamp_key) ? (int)$timestamp_key : 0;
        if ($ts > $latest_timestamp) {
            $latest_timestamp = $ts;
            $latest_backup = $backup_set;
            $latest_nonce = isset($backup_set['nonce']) ? $backup_set['nonce'] : '';
        }
    }
    
    if (!$latest_backup || $latest_timestamp === 0) {
        return $delete_jobdata;
    }
    
    gutenblock_bridge_create_template_zip($latest_timestamp, $latest_nonce, $latest_backup);
    
    return $delete_jobdata;
}

function gutenblock_bridge_create_template_zip($timestamp, $nonce, $backup_set) {
    $updraft_dir = WP_CONTENT_DIR . '/updraft';
    $backup_dir = WP_CONTENT_DIR . '/gutenblock-backups';
    
    if (!file_exists($backup_dir)) {
        wp_mkdir_p($backup_dir);
    }
    
    $zip_path = $backup_dir . '/template-backup.zip';
    
    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        error_log('[GutenBlock Bridge] Konnte ZIP nicht erstellen');
        return false;
    }
    
    $backup_files = array();
    $entities = array('plugins', 'themes', 'uploads', 'mu-plugins', 'others', 'db');
    
    foreach ($entities as $entity) {
        if (isset($backup_set[$entity])) {
            $files = is_array($backup_set[$entity]) ? $backup_set[$entity] : array($backup_set[$entity]);
            foreach ($files as $file) {
                $file_path = $updraft_dir . '/' . $file;
                if (file_exists($file_path)) {
                    $backup_files[] = $file;
                    $zip->addFile($file_path, $file);
                }
            }
        }
    }
    
    $metadata = array(
        'timestamp' => $timestamp,
        'nonce' => $nonce,
        'files' => $backup_files,
        'created_at' => date('Y-m-d H:i:s'),
        'site_url' => get_option('siteurl'),
        'home_url' => get_option('home')
    );
    
    $zip->addFromString('backup-metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));
    $zip->close();
    
    error_log('[GutenBlock Bridge] Template-ZIP erstellt: ' . count($backup_files) . ' Dateien');
    
    return true;
}
