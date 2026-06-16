<?php
/**
 * Plugin Name: USA State All-in-One Calculators
 * Plugin URI: #
 * Description: All-in-One premium SEO-optimized calculator suite for all 50 US states. Includes Paycheck, Child Support, Alimony, Mortgage, Income Tax, Property Tax, and Sales Tax calculators. Auto-creates CPT pages with state-specific content and customizable HTML/CSS/JS editors.
 * Version: 2.3.0
 * Author: AI Assistant
 * Text Domain: usa-state-all-calculators
 */

if (!defined('ABSPATH')) exit;

// ============================================================
// PLUGIN 1 (USC) — Paycheck, Child Support, Alimony, Mortgage
// ============================================================

define('USC_PATH', plugin_dir_path(__FILE__));
define('USC_URL',  plugin_dir_url(__FILE__));
define('USC_VERSION', '2.3.0');
define('USC_CPT', 'usc_calculator');

// ============================================================
// PLUGIN 2 (UST) — Income Tax, Property Tax, Sales Tax
// ============================================================

define('UST_PATH', plugin_dir_path(__FILE__));
define('UST_URL',  plugin_dir_url(__FILE__));
define('UST_VERSION', '2.3.0');
define('UST_CPT', 'ust_calculator');

// ============================================================
// INCLUDE ALL COMPONENTS
// ============================================================

// USC includes
require_once USC_PATH . 'includes/class-usc-cpt.php';
require_once USC_PATH . 'includes/class-usc-metaboxes.php';
require_once USC_PATH . 'includes/class-usc-seo.php';
require_once USC_PATH . 'data/usc-default-content.php';
require_once USC_PATH . 'data/usc-default-templates.php';
require_once USC_PATH . 'data/alimony.php';
require_once USC_PATH . 'data/mortgage.php';

// UST includes
require_once UST_PATH . 'includes/class-ust-cpt.php';
require_once UST_PATH . 'includes/class-ust-metaboxes.php';
require_once UST_PATH . 'includes/class-ust-seo.php';
require_once UST_PATH . 'data/income-tax.php';
require_once UST_PATH . 'data/property-tax.php';
require_once UST_PATH . 'data/sales-tax.php';
require_once UST_PATH . 'data/other-tax.php';
require_once UST_PATH . 'data/cost-of-living.php';
require_once UST_PATH . 'data/ust-default-content.php';
require_once UST_PATH . 'data/ust-default-templates.php';

// ============================================================
// INITIALIZE CORE CLASSES
// ============================================================

add_action('plugins_loaded', 'usac_init_plugin');
function usac_init_plugin() {
    new USC_CPT();
    new USC_Metaboxes();
    new USC_SEO();
    new UST_CPT();
    new UST_Metaboxes();
    new UST_SEO();
}

// ============================================================
// AUTO-SYNC ON ADMIN LOAD — USC
// ============================================================

add_action('admin_init', 'usc_admin_sync_pages');
function usc_admin_sync_pages() {
    if (!current_user_can('manage_options')) return;
    if (get_transient('usc_pages_generated_v21')) return;
    usc_auto_generate_state_pages();
    flush_rewrite_rules();
    set_transient('usc_pages_generated_v21', true, DAY_IN_SECONDS);
}

// ============================================================
// AUTO-SYNC ON ADMIN LOAD — UST
// ============================================================

add_action('admin_init', 'ust_admin_sync_pages');
function ust_admin_sync_pages() {
    if (!current_user_can('manage_options')) return;
    if (get_transient('ust_pages_generated_v21')) return;
    set_transient('ust_pages_generated_v21', true, DAY_IN_SECONDS);
    ust_auto_generate_state_pages();
    flush_rewrite_rules();
}

// ============================================================
// TAXONOMY MIGRATION — USC
// ============================================================

add_action('admin_init', 'usc_admin_init_taxonomy_migration', 11);
function usc_admin_init_taxonomy_migration() {
    if (!current_user_can('manage_options')) return;

    $categories = array(
        'paycheck'      => 'Paycheck',
        'child-support' => 'Child Support',
        'alimony'       => 'Alimony',
        'mortgage'      => 'Mortgage',
        'tax'           => 'Tax',
        'auto-loan'     => 'Auto Loan',
        'insurance'     => 'Insurance'
    );

    foreach ($categories as $slug => $name) {
        if (!term_exists($slug, 'usc_category')) {
            wp_insert_term($name, 'usc_category', array('slug' => $slug));
        }
    }

    $posts = get_posts(array(
        'post_type'      => USC_CPT,
        'posts_per_page' => -1,
        'post_status'    => array('publish', 'draft', 'pending', 'private'),
        'tax_query'      => array(
            array(
                'taxonomy' => 'usc_category',
                'field'    => 'slug',
                'terms'    => array_keys($categories),
                'operator' => 'NOT IN'
            )
        )
    ));

    if (!empty($posts)) {
        foreach ($posts as $p) {
            $calc_type = get_post_meta($p->ID, '_usc_calc_type', true);
            if ($calc_type && isset($categories[$calc_type])) {
                wp_set_object_terms($p->ID, $calc_type, 'usc_category');
            }
        }
    }
}

// ============================================================
// TAXONOMY MIGRATION — UST
// ============================================================

add_action('admin_init', 'ust_admin_init_taxonomy_migration', 11);
function ust_admin_init_taxonomy_migration() {
    if (!current_user_can('manage_options')) return;

    $categories = array(
        'income-tax'   => 'Income Tax',
        'property-tax' => 'Property Tax',
        'sales-tax'    => 'Sales Tax',
        'other'        => 'Other'
    );

    foreach ($categories as $slug => $name) {
        if (!term_exists($slug, 'ust_category')) {
            wp_insert_term($name, 'ust_category', array('slug' => $slug));
        }
    }

    $posts = get_posts(array(
        'post_type'      => UST_CPT,
        'posts_per_page' => -1,
        'post_status'    => array('publish', 'draft', 'pending', 'private'),
        'tax_query'      => array(
            array(
                'taxonomy' => 'ust_category',
                'field'    => 'slug',
                'terms'    => array_keys($categories),
                'operator' => 'NOT IN'
            )
        )
    ));

    if (!empty($posts)) {
        foreach ($posts as $p) {
            $calc_type = get_post_meta($p->ID, '_ust_calc_type', true);
            if ($calc_type && isset($categories[$calc_type])) {
                wp_set_object_terms($p->ID, $calc_type, 'ust_category');
            }
        }
    }
}

// ============================================================
// ENQUEUE ASSETS — USC (Front-end)
// ============================================================

add_action('wp_enqueue_scripts', 'usc_enqueue_assets');
function usc_enqueue_assets() {
    if (is_singular(USC_CPT)) {
        wp_enqueue_style('usc-style', USC_URL . 'public/assets/css/usc-style.css', [], USC_VERSION);
        wp_enqueue_script('usc-nonce-init', USC_URL . 'public/assets/js/nonce.js', [], USC_VERSION, true);
        wp_localize_script('usc-nonce-init', 'uscAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('usc_frontend_nonce'),
        ]);
    }
}

// ============================================================
// ENQUEUE ASSETS — UST (Front-end)
// ============================================================

add_action('wp_enqueue_scripts', 'ust_enqueue_assets');
function ust_enqueue_assets() {
    if (is_singular(UST_CPT)) {
        wp_enqueue_style('ust-style', UST_URL . 'public/assets/css/ust-style.css', [], UST_VERSION);
        wp_enqueue_script('ust-nonce-init', UST_URL . 'public/assets/js/nonce.js', [], UST_VERSION, true);
        wp_localize_script('ust-nonce-init', 'ustAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ust_frontend_nonce'),
        ]);
    }
}

// ============================================================
// ENQUEUE ADMIN ASSETS — USC
// ============================================================

add_action('admin_enqueue_scripts', 'usac_enqueue_admin_assets');
function usac_enqueue_admin_assets($hook) {
    global $post;
    $post_type = $post ? $post->post_type : '';
    if (empty($post_type) && isset($_GET['post_type'])) {
        $post_type = sanitize_key($_GET['post_type']);
    }
    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

    $is_usc_page = ($post_type === USC_CPT || strpos($hook, 'usc_') !== false || strpos($page, 'usc_') !== false);
    $is_ust_page = ($post_type === UST_CPT || strpos($hook, 'ust_') !== false || strpos($page, 'ust_') !== false);
    $is_usac_page = (strpos($hook, 'usac_') !== false || strpos($page, 'usac_') !== false);

    // Load USC admin styles on USC pages and combined hub
    if ($is_usc_page || $is_usac_page) {
        $css_file = USC_PATH . 'public/assets/css/usc-admin-style.css';
        $ver = file_exists($css_file) ? filemtime($css_file) : USC_VERSION;
        wp_enqueue_style('usc-admin-style', USC_URL . 'public/assets/css/usc-admin-style.css', [], $ver);
    }

    // Load UST admin styles on UST pages and combined hub
    if ($is_ust_page || $is_usac_page) {
        $css_file = UST_PATH . 'public/assets/css/ust-admin-style.css';
        $ver = file_exists($css_file) ? filemtime($css_file) : UST_VERSION;
        wp_enqueue_style('ust-admin-style', UST_URL . 'public/assets/css/ust-admin-style.css', [], $ver);
    }
}



// ============================================================
// CSV EXPORT — USC
// ============================================================

add_action('admin_init', 'usc_export_leads_csv');
function usc_export_leads_csv() {
    if (isset($_GET['action']) && $_GET['action'] === 'usc_export_leads') {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user.');
        }
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'usc_export_leads_nonce')) {
            wp_die('Security check failed.');
        }
        global $wpdb;
        $leads = $wpdb->get_results("SELECT l.id, p.post_title, l.name, l.email, l.created_at FROM {$wpdb->prefix}usc_leads l LEFT JOIN {$wpdb->posts} p ON l.post_id = p.ID ORDER BY l.created_at DESC", ARRAY_A);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=captured_leads_' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Lead ID', 'State Calculator Name', 'User Name', 'User Email', 'Submitted On']);
        if (!empty($leads)) {
            foreach ($leads as $lead) {
                fputcsv($output, [$lead['id'], $lead['post_title'] ?: 'State Calculator', $lead['name'], $lead['email'], $lead['created_at']]);
            }
        }
        fclose($output);
        exit;
    }
}

// ============================================================
// CSV EXPORT — UST
// ============================================================

add_action('admin_init', 'ust_export_leads_csv');
function ust_export_leads_csv() {
    if (isset($_GET['action']) && $_GET['action'] === 'ust_export_leads') {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user.');
        }
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'ust_export_leads_nonce')) {
            wp_die('Security check failed.');
        }
        global $wpdb;
        $leads = $wpdb->get_results("SELECT l.id, p.post_title, l.name, l.email, l.created_at FROM {$wpdb->prefix}ust_leads l LEFT JOIN {$wpdb->posts} p ON l.post_id = p.ID ORDER BY l.created_at DESC", ARRAY_A);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=captured_tax_leads_' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Lead ID', 'State Tax Calculator Name', 'User Name', 'User Email', 'Submitted On']);
        if (!empty($leads)) {
            foreach ($leads as $lead) {
                fputcsv($output, [$lead['id'], $lead['post_title'] ?: 'State Calculator', $lead['name'], $lead['email'], $lead['created_at']]);
            }
        }
        fclose($output);
        exit;
    }
}

// ============================================================
// SYNC PAGES ACTION — USC
// ============================================================

add_action('admin_init', 'usc_handle_sync_pages_action');
function usc_handle_sync_pages_action() {
    if (isset($_GET['action']) && $_GET['action'] === 'usc_sync_pages') {
        if (!current_user_can('manage_options')) wp_die('Unauthorized user.');
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'usc_sync_pages_nonce')) wp_die('Security check failed.');
        // Delete ALL USC transients to force fresh generation
        foreach (['v1','v2','v3','v4','v5','v6','v7','v8','v9','v10','v11','v12','v13','v14','v15','v16','v17','v18','v19','v20','v21','v22','v23','v24'] as $v) {
            delete_transient('usc_pages_generated_' . $v);
        }
        // Force reset all template versions so they get regenerated
        global $wpdb;
        $wpdb->query("UPDATE {$wpdb->postmeta} SET meta_value = '0' WHERE meta_key = '_usc_template_version'");
        usc_auto_generate_state_pages();
        flush_rewrite_rules();
        wp_safe_redirect(add_query_arg('usc_message', 'sync_success', admin_url('admin.php?page=usac_calculators_hub')));
        exit;
    }
}


// ============================================================
// SYNC PAGES ACTION — UST
// ============================================================

add_action('admin_init', 'ust_handle_sync_pages_action');
function ust_handle_sync_pages_action() {
    if (isset($_GET['action']) && $_GET['action'] === 'ust_sync_pages') {
        if (!current_user_can('manage_options')) wp_die('Unauthorized user.');
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'ust_sync_pages_nonce')) wp_die('Security check failed.');
        foreach (['v1','v2','v4','v6','v7','v8','v9','v10','v11','v12','v13','v14','v15','v16','v17','v18','v19','v20','v21','v22','v23','v24'] as $v) {
            delete_transient('ust_pages_generated_' . $v);
        }
        // Force reset all UST template versions so they get regenerated
        global $wpdb;
        $wpdb->query("UPDATE {$wpdb->postmeta} SET meta_value = '0' WHERE meta_key = '_ust_template_version'");
        ust_auto_generate_state_pages();
        flush_rewrite_rules();
        wp_safe_redirect(add_query_arg('ust_message', 'sync_success', admin_url('admin.php?page=usac_calculators_hub')));
        exit;
    }
}

// ============================================================
// FORCE RESET ALL TEMPLATES ACTION
// ============================================================

add_action('admin_init', 'usac_handle_force_reset_action');
function usac_handle_force_reset_action() {
    if (isset($_GET['action']) && $_GET['action'] === 'usac_force_reset_templates') {
        if (!current_user_can('manage_options')) wp_die('Unauthorized user.');
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'usac_force_reset_nonce')) wp_die('Security check failed.');

        global $wpdb;

        // Step 1: Reset ALL USC template versions to 0 -- forces regeneration
        $wpdb->query("UPDATE {$wpdb->postmeta} SET meta_value = '0' WHERE meta_key = '_usc_template_version'");

        // Step 2: Reset ALL UST template versions to 0
        $wpdb->query("UPDATE {$wpdb->postmeta} SET meta_value = '0' WHERE meta_key = '_ust_template_version'");

        // Step 3: Clear ALL sync transients (v1-v23)
        foreach (['v1','v2','v3','v4','v5','v6','v7','v8','v9','v10','v11','v12','v13','v14','v15','v16','v17','v18','v19','v20','v21','v22','v23'] as $v) {
            delete_transient('usc_pages_generated_' . $v);
            delete_transient('ust_pages_generated_' . $v);
        }

        // Step 4: Run fresh generation
        usc_auto_generate_state_pages();
        ust_auto_generate_state_pages();
        flush_rewrite_rules();
        usac_clear_all_caches();

        wp_safe_redirect(add_query_arg('usac_message', 'reset_success', admin_url('admin.php?page=usac_calculators_hub')));
        exit;
    }
}

function usac_clear_all_caches() {
    // LiteSpeed Cache
    if (class_exists('LiteSpeed\Purge')) {
        \LiteSpeed\Purge::purge_all();
    }
    // WP Rocket
    if (function_exists('rocket_clean_domain')) {
        rocket_clean_domain();
    }
    // SG Optimizer
    if (function_exists('sg_cachepress_purge_cache')) {
        sg_cachepress_purge_cache();
    }
    // W3 Total Cache
    if (function_exists('w3tc_pgcache_flush')) {
        w3tc_pgcache_flush();
    }
    // WP Super Cache
    if (function_exists('wp_cache_clear_cache')) {
        wp_cache_clear_cache();
    }
    // Autoptimize
    if (class_exists('autoptimizeCache') && method_exists('autoptimizeCache', 'clearall')) {
        \autoptimizeCache::clearall();
    }
}

// Automatically detect and deactivate old plugins if they are active to prevent duplicate templates/styles
add_action('admin_init', 'usac_deactivate_old_plugins');
function usac_deactivate_old_plugins() {
    if (!current_user_can('activate_plugins')) return;
    
    $old_plugins = [
        'usa-state-calculators/usa-state-calculators.php',
        'usa-state-tax-calculators/usa-state-tax-calculators.php'
    ];
    
    $deactivated = false;
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    foreach ($old_plugins as $plugin) {
        if (is_plugin_active($plugin)) {
            deactivate_plugins($plugin);
            $deactivated = true;
        }
    }
    
    if ($deactivated) {
        global $wpdb;
        $wpdb->query("UPDATE {$wpdb->postmeta} SET meta_value = '0' WHERE meta_key = '_usc_template_version'");
        $wpdb->query("UPDATE {$wpdb->postmeta} SET meta_value = '0' WHERE meta_key = '_ust_template_version'");
        
        foreach (['v1','v2','v3','v4','v5','v6','v7','v8','v9','v10','v11','v12','v13','v14','v15','v16','v17','v18','v19','v20','v21','v22','v23'] as $v) {
            delete_transient('usc_pages_generated_' . $v);
            delete_transient('ust_pages_generated_' . $v);
        }
        
        usc_auto_generate_state_pages();
        ust_auto_generate_state_pages();
        flush_rewrite_rules();
        usac_clear_all_caches();
        
        // Redirect back to hub with custom message
        wp_safe_redirect(add_query_arg('usac_message', 'old_deactivated', admin_url('admin.php?page=usac_calculators_hub')));
        exit;
    }
}

// ============================================================
// ADMIN NOTICES
// ============================================================

add_action('admin_notices', 'usac_admin_sync_notice');
function usac_admin_sync_notice() {
    if (isset($_GET['usc_message']) && $_GET['usc_message'] === 'sync_success') {
        echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Paycheck/Child Support/Alimony/Mortgage calculators synchronized successfully!</strong></p></div>';
    }
    if (isset($_GET['ust_message']) && $_GET['ust_message'] === 'sync_success') {
        echo '<div class="notice notice-success is-dismissible"><p><strong>✅ Tax calculators synchronized successfully!</strong></p></div>';
    }
    if (isset($_GET['usac_message']) && $_GET['usac_message'] === 'reset_success') {
        echo '<div class="notice notice-success is-dismissible"><p><strong>✅ All calculator templates have been force-reset and regenerated successfully!</strong></p></div>';
    }
}


// ============================================================
// ACTIVATION HOOK — Combined
// ============================================================

register_activation_hook(__FILE__, 'usac_activate_plugin');
function usac_activate_plugin() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // USC Leads table
    $t_usc_leads = $wpdb->prefix . 'usc_leads';
    dbDelta("CREATE TABLE IF NOT EXISTS $t_usc_leads (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        post_id     INT NOT NULL,
        name        VARCHAR(200) NOT NULL,
        email       VARCHAR(200) NOT NULL,
        created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
    ) $charset;");

    // USC Usage Stats table
    $t_usc_usage = $wpdb->prefix . 'usc_usage_stats';
    dbDelta("CREATE TABLE IF NOT EXISTS $t_usc_usage (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        post_id     INT NOT NULL UNIQUE,
        count       BIGINT DEFAULT 0,
        last_used   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) $charset;");

    // UST Leads table
    $t_ust_leads = $wpdb->prefix . 'ust_leads';
    dbDelta("CREATE TABLE $t_ust_leads (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        name varchar(200) NOT NULL,
        email varchar(200) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset;");

    // UST Usage Stats table
    $t_ust_usage = $wpdb->prefix . 'ust_usage_stats';
    dbDelta("CREATE TABLE $t_ust_usage (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        count bigint(20) DEFAULT 0 NOT NULL,
        last_used datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY post_id  (post_id)
    ) $charset;");

    // Register USC CPT and flush
    $cpt = new USC_CPT();
    $cpt->register_post_type();
    
    // Force reset all template versions to 0 on activation to guarantee clean overwrite
    $wpdb->query("UPDATE {$wpdb->postmeta} SET meta_value = '0' WHERE meta_key = '_usc_template_version'");
    $wpdb->query("UPDATE {$wpdb->postmeta} SET meta_value = '0' WHERE meta_key = '_ust_template_version'");

    // Delete transients
    foreach (['v1','v2','v3','v4','v5','v6','v7','v8','v9','v10','v11','v12','v13','v14','v15','v16','v17','v18','v19','v20','v21','v22','v23'] as $v) {
        delete_transient('usc_pages_generated_' . $v);
        delete_transient('ust_pages_generated_' . $v);
    }

    usc_auto_generate_state_pages();
    ust_auto_generate_state_pages();
    flush_rewrite_rules();
    usac_clear_all_caches();
}

// ============================================================
// DEACTIVATION HOOK
// ============================================================

register_deactivation_hook(__FILE__, 'usac_deactivate_plugin');
function usac_deactivate_plugin() {
    flush_rewrite_rules();
}

// ============================================================
// AJAX: SAVE LEAD — USC
// ============================================================

add_action('wp_ajax_usc_submit_lead', 'usc_ajax_submit_lead');
add_action('wp_ajax_nopriv_usc_submit_lead', 'usc_ajax_submit_lead');
function usc_ajax_submit_lead() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'usc_frontend_nonce')) {
        wp_send_json_error('Security check failed.', 403);
    }
    $ip_key = 'usc_lead_rate_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $attempts = (int) get_transient($ip_key);
    if ($attempts >= 3) {
        wp_send_json_error('Too many submissions. Please try again later.', 429);
    }
    set_transient($ip_key, $attempts + 1, HOUR_IN_SECONDS);
    $post_id = intval($_POST['post_id'] ?? 0);
    $name    = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $email   = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    if (!$post_id || empty($name) || empty($email)) {
        wp_send_json_error('Invalid input data.');
    }
    if (!is_email($email)) {
        wp_send_json_error('Invalid email address.');
    }
    $post = get_post($post_id);
    if (!$post || $post->post_type !== USC_CPT || $post->post_status !== 'publish') {
        wp_send_json_error('Invalid calculator reference.');
    }
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'usc_leads', ['post_id' => $post_id, 'name' => $name, 'email' => $email], ['%d', '%s', '%s']);
    wp_send_json_success('Lead saved successfully.');
}

// ============================================================
// AJAX: SAVE LEAD — UST
// ============================================================

add_action('wp_ajax_ust_submit_lead', 'ust_ajax_submit_lead');
add_action('wp_ajax_nopriv_ust_submit_lead', 'ust_ajax_submit_lead');
function ust_ajax_submit_lead() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ust_frontend_nonce')) {
        wp_send_json_error('Security check failed.', 403);
    }
    $ip_key = 'ust_lead_rate_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $attempts = (int) get_transient($ip_key);
    if ($attempts >= 3) {
        wp_send_json_error('Too many submissions. Please try again later.', 429);
    }
    set_transient($ip_key, $attempts + 1, HOUR_IN_SECONDS);
    $post_id = intval($_POST['post_id'] ?? 0);
    $name    = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $email   = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    if (!$post_id || empty($name) || empty($email)) {
        wp_send_json_error('Invalid input data.');
    }
    if (!is_email($email)) {
        wp_send_json_error('Invalid email address.');
    }
    $post = get_post($post_id);
    if (!$post || $post->post_type !== UST_CPT || $post->post_status !== 'publish') {
        wp_send_json_error('Invalid calculator reference.');
    }
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'ust_leads', ['post_id' => $post_id, 'name' => $name, 'email' => $email], ['%d', '%s', '%s']);
    wp_send_json_success('Lead saved successfully.');
}

// ============================================================
// AJAX: TRACK USAGE — USC
// ============================================================

add_action('wp_ajax_usc_track_usage', 'usc_ajax_track_usage');
add_action('wp_ajax_nopriv_usc_track_usage', 'usc_ajax_track_usage');
function usc_ajax_track_usage() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'usc_frontend_nonce')) {
        wp_send_json_error('Security check failed.', 403);
    }
    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$post_id) wp_send_json_error();
    $post = get_post($post_id);
    if (!$post || $post->post_type !== USC_CPT) wp_send_json_error();
    global $wpdb;
    $table = $wpdb->prefix . 'usc_usage_stats';
    $wpdb->query($wpdb->prepare(
        "INSERT INTO $table (post_id, count, last_used) VALUES (%d, 1, NOW()) ON DUPLICATE KEY UPDATE count = count + 1, last_used = NOW()",
        $post_id
    ));
    wp_send_json_success();
}

// ============================================================
// AJAX: TRACK USAGE — UST
// ============================================================

add_action('wp_ajax_ust_track_usage', 'ust_ajax_track_usage');
add_action('wp_ajax_nopriv_ust_track_usage', 'ust_ajax_track_usage');
function ust_ajax_track_usage() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'ust_frontend_nonce')) {
        wp_send_json_error('Security check failed.', 403);
    }
    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$post_id) wp_send_json_error();
    $post = get_post($post_id);
    if (!$post || $post->post_type !== UST_CPT) wp_send_json_error();
    global $wpdb;
    $table = $wpdb->prefix . 'ust_usage_stats';
    $wpdb->query($wpdb->prepare(
        "INSERT INTO $table (post_id, count, last_used) VALUES (%d, 1, NOW()) ON DUPLICATE KEY UPDATE count = count + 1, last_used = NOW()",
        $post_id
    ));
    wp_send_json_success();
}

// ============================================================
// HELPER: GET CALCULATOR BY META — USC
// ============================================================

function usc_get_calculator_by_meta($calc_type, $state_slug) {
    $posts = get_posts([
        'post_type'   => USC_CPT,
        'post_status' => ['publish', 'draft', 'pending', 'private', 'future', 'trash'],
        'meta_query'  => [
            'relation' => 'AND',
            ['key' => '_usc_calc_type', 'value' => $calc_type, 'compare' => '='],
            ['key' => '_usc_state_slug', 'value' => $state_slug, 'compare' => '=']
        ],
        'numberposts' => 1
    ]);
    if (!empty($posts)) return $posts[0];

    $slug_options = [];
    if ($calc_type === 'mortgage') {
        $slug_options[] = 'mortgage-calculator-' . $state_slug;
        $slug_options[] = $state_slug . '-mortgage-calculator';
    } else {
        $slug_options[] = $state_slug . '-' . $calc_type . '-calculator';
    }
    foreach ($slug_options as $slug) {
        $posts_by_slug = get_posts([
            'post_type'   => USC_CPT,
            'name'        => $slug,
            'post_status' => ['publish', 'draft', 'pending', 'private', 'future', 'trash'],
            'numberposts' => 1
        ]);
        if (!empty($posts_by_slug)) return $posts_by_slug[0];
    }
    return null;
}

// ============================================================
// HELPER: GET CALCULATOR BY META — UST
// ============================================================

function ust_get_calculator_by_meta($calc_type, $state_slug) {
    global $wpdb;
    $post_id = $wpdb->get_var($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_ust_calc_type' AND pm1.meta_value = %s
         INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_ust_state_slug' AND pm2.meta_value = %s
         WHERE p.post_type = %s AND p.post_status IN ('publish', 'draft', 'pending', 'private', 'future', 'trash')
         ORDER BY p.ID ASC LIMIT 1",
        $calc_type, $state_slug, UST_CPT
    ));
    if ($post_id) return get_post($post_id);

    $expected_slug = $state_slug . '-' . $calc_type . '-calculator';
    if ($calc_type === 'other') $expected_slug = $state_slug;
    $post_id = $wpdb->get_var($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = %s AND post_status IN ('publish', 'draft', 'pending', 'private', 'future', 'trash') ORDER BY ID ASC LIMIT 1",
        $expected_slug, UST_CPT
    ));
    if ($post_id) return get_post($post_id);
    return null;
}

// ============================================================
// CLEANUP DUPLICATE CALCULATORS — USC
// ============================================================

function usc_cleanup_duplicate_calculators() {
    $property_tax_posts = get_posts([
        'post_type'   => USC_CPT,
        'post_status' => ['publish', 'draft', 'pending', 'private', 'future', 'trash'],
        'numberposts' => -1,
        'meta_query'  => [['key' => '_usc_calc_type', 'value' => 'property-tax', 'compare' => '=']]
    ]);
    foreach ($property_tax_posts as $post) wp_delete_post($post->ID, true);

    $property_tax_slug_posts = get_posts([
        'post_type'   => USC_CPT,
        'post_status' => ['publish', 'draft', 'pending', 'private', 'future', 'trash'],
        'numberposts' => -1,
        's'           => 'property-tax'
    ]);
    foreach ($property_tax_slug_posts as $post) {
        if (strpos($post->post_name, 'property-tax') !== false) wp_delete_post($post->ID, true);
    }

    $all_posts = get_posts([
        'post_type'   => USC_CPT,
        'post_status' => ['publish', 'draft', 'pending', 'private', 'future', 'trash'],
        'numberposts' => -1,
        'orderby'     => 'ID',
        'order'       => 'ASC'
    ]);
    $seen = [];
    foreach ($all_posts as $post) {
        $calc_type  = get_post_meta($post->ID, '_usc_calc_type', true);
        $state_slug = get_post_meta($post->ID, '_usc_state_slug', true);
        if (empty($calc_type) || empty($state_slug)) {
            $post_slug     = $post->post_name;
            $detected_type = '';
            if (strpos($post_slug, 'paycheck') !== false)      $detected_type = 'paycheck';
            elseif (strpos($post_slug, 'child-support') !== false) $detected_type = 'child-support';
            elseif (strpos($post_slug, 'alimony') !== false)   $detected_type = 'alimony';
            elseif (strpos($post_slug, 'mortgage') !== false)  $detected_type = 'mortgage';
            if ($detected_type) {
                $calc_type  = $detected_type;
                $prefixes   = ['paycheck-calculator-', 'child-support-calculator-', 'alimony-calculator-', 'mortgage-calculator-'];
                $suffixes   = ['-paycheck-calculator', '-child-support-calculator', '-alimony-calculator', '-mortgage-calculator'];
                $state_slug = str_replace($prefixes, '', $post_slug);
                $state_slug = str_replace($suffixes, '', $state_slug);
                $state_slug = preg_replace('/-[0-9]+$/', '', $state_slug);
                update_post_meta($post->ID, '_usc_calc_type', $calc_type);
                update_post_meta($post->ID, '_usc_state_slug', $state_slug);
            }
        }
        if ($calc_type && $state_slug) {
            $key = $calc_type . '_' . $state_slug;
            if (isset($seen[$key])) {
                wp_delete_post($post->ID, true);
            } else {
                $seen[$key] = $post->ID;
            }
        }
    }
}

// ============================================================
// CLEANUP DUPLICATE CALCULATORS — UST
// ============================================================

function ust_cleanup_duplicate_calculators() {
    global $wpdb;
    $posts = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, post_name FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish', 'draft', 'pending', 'private', 'future', 'trash') ORDER BY ID ASC",
        UST_CPT
    ));
    $seen = [];
    foreach ($posts as $post) {
        $calc_type  = get_post_meta($post->ID, '_ust_calc_type', true);
        $state_slug = get_post_meta($post->ID, '_ust_state_slug', true);
        if (empty($calc_type) || empty($state_slug)) {
            $post_slug     = $post->post_name;
            $detected_type = '';
            $other_slugs   = ['federal-income-tax-calculator','state-income-tax-calculator','income-tax-refund-calculator','tax-withholding-calculator','tax-bracket-calculator','estimated-tax-calculator','capital-gains-tax-calculator','self-employment-tax-calculator','payroll-tax-calculator','sales-tax-calculator','property-tax-estimator','effective-property-tax-rate-calculator','state-tax-comparison-calculator','bonus-tax-calculator'];
            if (in_array($post_slug, $other_slugs)) {
                $detected_type = 'other';
                $state_slug    = $post_slug;
            } elseif (strpos($post_slug, 'property-tax') !== false) $detected_type = 'property-tax';
            elseif (strpos($post_slug, 'sales-tax') !== false)      $detected_type = 'sales-tax';
            elseif (strpos($post_slug, 'income-tax') !== false)     $detected_type = 'income-tax';
            if ($detected_type) {
                $calc_type = $detected_type;
                if ($calc_type !== 'other') {
                    $prefixes   = ['income-tax-calculator-', 'property-tax-calculator-', 'sales-tax-calculator-'];
                    $suffixes   = ['-income-tax-calculator', '-property-tax-calculator', '-sales-tax-calculator'];
                    $state_slug = str_replace($prefixes, '', $post_slug);
                    $state_slug = str_replace($suffixes, '', $state_slug);
                    $state_slug = preg_replace('/-[0-9]+$/', '', $state_slug);
                }
                update_post_meta($post->ID, '_ust_calc_type', $calc_type);
                update_post_meta($post->ID, '_ust_state_slug', $state_slug);
            }
        }
        if ($calc_type && $state_slug) {
            $key = $calc_type . '_' . $state_slug;
            if (isset($seen[$key])) {
                wp_delete_post($post->ID, true);
            } else {
                $seen[$key] = $post->ID;
            }
        }
    }
}

// ============================================================
// AUTO-GENERATE STATE PAGES — USC
// ============================================================

function usc_auto_generate_state_pages() {
    usc_cleanup_duplicate_calculators();
    $states = usc_get_states_data();
    foreach ($states as $slug => $state) {
        // Paycheck
        $paycheck_exists = usc_get_calculator_by_meta('paycheck', $slug);
        if (!$paycheck_exists) {
            $post_id = wp_insert_post(['post_title' => $state['name'] . ' Paycheck Calculator', 'post_name' => $slug . '-paycheck-calculator', 'post_status' => 'publish', 'post_type' => USC_CPT, 'post_content' => usc_get_default_paycheck_article_content($state)]);
            if (!is_wp_error($post_id)) {
                update_post_meta($post_id, '_usc_calc_type', 'paycheck');
                update_post_meta($post_id, '_usc_state_slug', $slug);
                update_post_meta($post_id, '_usc_seo_title', usc_get_default_seo_title('paycheck', $state['name']));
                update_post_meta($post_id, '_usc_seo_desc', usc_get_default_seo_desc('paycheck', $state));
                $defaults = usc_get_default_templates('paycheck', $slug);
                update_post_meta($post_id, '_usc_calc_html', $defaults['html']);
                update_post_meta($post_id, '_usc_calc_css', $defaults['css']);
                update_post_meta($post_id, '_usc_calc_js', $defaults['js']);
                update_post_meta($post_id, '_usc_faqs', usc_get_default_paycheck_faqs($state));
                update_post_meta($post_id, '_usc_template_version', '24');
                $thumb_id = usc_get_or_create_illustration_attachment('paycheck');
                if ($thumb_id) set_post_thumbnail($post_id, $thumb_id);
                wp_set_object_terms($post_id, 'paycheck', 'usc_category');
            }
        } else {
            $post_id = $paycheck_exists->ID;
            $post_status = get_post_status($post_id);
            if ($post_status === 'trash' || $post_status === 'draft') wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
            $post_content = $paycheck_exists->post_content;
            if (empty($post_content) || strpos($post_content, '<!-- usc-v5-article -->') === false || strpos($post_content, '<h2>13. Frequently Asked Questions') !== false) {
                wp_update_post(['ID' => $post_id, 'post_content' => usc_get_default_paycheck_article_content($state)]);
                update_post_meta($post_id, '_usc_faqs', usc_get_default_paycheck_faqs($state));
            }
            if (!has_post_thumbnail($post_id)) { $thumb_id = usc_get_or_create_illustration_attachment('paycheck'); if ($thumb_id) set_post_thumbnail($post_id, $thumb_id); }
            update_post_meta($post_id, '_usc_calc_type', 'paycheck');
            update_post_meta($post_id, '_usc_state_slug', $slug);
            wp_set_object_terms($post_id, 'paycheck', 'usc_category');
            $current_ver = get_post_meta($post_id, '_usc_template_version', true);
            if ($current_ver !== '24' || empty(get_post_meta($post_id, '_usc_calc_html', true)) || empty(get_post_meta($post_id, '_usc_calc_js', true))) {
                $defaults = usc_get_default_templates('paycheck', $slug);
                update_post_meta($post_id, '_usc_calc_html', $defaults['html']);
                update_post_meta($post_id, '_usc_calc_css', $defaults['css']);
                update_post_meta($post_id, '_usc_calc_js', $defaults['js']);
                update_post_meta($post_id, '_usc_template_version', '24');
            }
        }

        // Child Support
        $cs_exists = usc_get_calculator_by_meta('child-support', $slug);
        if (!$cs_exists) {
            $post_id = wp_insert_post(['post_title' => $state['name'] . ' Child Support Calculator', 'post_name' => $slug . '-child-support-calculator', 'post_status' => 'publish', 'post_type' => USC_CPT, 'post_content' => usc_get_default_child_support_article_content($state)]);
            if (!is_wp_error($post_id)) {
                update_post_meta($post_id, '_usc_calc_type', 'child-support');
                update_post_meta($post_id, '_usc_state_slug', $slug);
                update_post_meta($post_id, '_usc_seo_title', usc_get_default_seo_title('child-support', $state['name']));
                update_post_meta($post_id, '_usc_seo_desc', usc_get_default_seo_desc('child-support', $state));
                $defaults = usc_get_default_templates('child-support', $slug);
                update_post_meta($post_id, '_usc_calc_html', $defaults['html']);
                update_post_meta($post_id, '_usc_calc_css', $defaults['css']);
                update_post_meta($post_id, '_usc_calc_js', $defaults['js']);
                update_post_meta($post_id, '_usc_faqs', usc_get_default_child_support_faqs($state));
                update_post_meta($post_id, '_usc_template_version', '24');
                $thumb_id = usc_get_or_create_illustration_attachment('child-support');
                if ($thumb_id) set_post_thumbnail($post_id, $thumb_id);
                wp_set_object_terms($post_id, 'child-support', 'usc_category');
            }
        } else {
            $post_id = $cs_exists->ID;
            $post_status = get_post_status($post_id);
            if ($post_status === 'trash' || $post_status === 'draft') wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
            $post_content = $cs_exists->post_content;
            if (empty($post_content) || strpos($post_content, '<!-- usc-v5-article -->') === false || strpos($post_content, '<h2>13. Frequently Asked Questions') !== false) {
                wp_update_post(['ID' => $post_id, 'post_content' => usc_get_default_child_support_article_content($state)]);
                update_post_meta($post_id, '_usc_faqs', usc_get_default_child_support_faqs($state));
            }
            if (!has_post_thumbnail($post_id)) { $thumb_id = usc_get_or_create_illustration_attachment('child-support'); if ($thumb_id) set_post_thumbnail($post_id, $thumb_id); }
            update_post_meta($post_id, '_usc_calc_type', 'child-support');
            update_post_meta($post_id, '_usc_state_slug', $slug);
            wp_set_object_terms($post_id, 'child-support', 'usc_category');
            $current_ver = get_post_meta($post_id, '_usc_template_version', true);
            if ($current_ver !== '24' || empty(get_post_meta($post_id, '_usc_calc_html', true)) || empty(get_post_meta($post_id, '_usc_calc_js', true))) {
                $defaults = usc_get_default_templates('child-support', $slug);
                update_post_meta($post_id, '_usc_calc_html', $defaults['html']);
                update_post_meta($post_id, '_usc_calc_css', $defaults['css']);
                update_post_meta($post_id, '_usc_calc_js', $defaults['js']);
                update_post_meta($post_id, '_usc_template_version', '24');
            }
        }

        // Alimony
        $alimony_exists = usc_get_calculator_by_meta('alimony', $slug);
        if (!$alimony_exists) {
            $post_id = wp_insert_post(['post_title' => $state['name'] . ' Alimony Calculator', 'post_name' => $slug . '-alimony-calculator', 'post_status' => 'publish', 'post_type' => USC_CPT, 'post_content' => usc_get_default_alimony_article_content($state)]);
            if (!is_wp_error($post_id)) {
                update_post_meta($post_id, '_usc_calc_type', 'alimony');
                update_post_meta($post_id, '_usc_state_slug', $slug);
                update_post_meta($post_id, '_usc_seo_title', usc_get_default_alimony_seo_title($state['name']));
                update_post_meta($post_id, '_usc_seo_desc', usc_get_default_alimony_seo_desc($state));
                $defaults = usc_get_default_templates('alimony', $slug);
                update_post_meta($post_id, '_usc_calc_html', $defaults['html']);
                update_post_meta($post_id, '_usc_calc_css', $defaults['css']);
                update_post_meta($post_id, '_usc_calc_js', $defaults['js']);
                update_post_meta($post_id, '_usc_faqs', usc_get_default_alimony_faqs($state));
                update_post_meta($post_id, '_usc_template_version', '24');
                wp_set_object_terms($post_id, 'alimony', 'usc_category');
            }
        } else {
            $post_id = $alimony_exists->ID;
            $post_status = get_post_status($post_id);
            if ($post_status === 'trash' || $post_status === 'draft') wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
            $post_content = $alimony_exists->post_content;
            if (empty($post_content) || strpos($post_content, '<!-- usc-v5-article -->') === false) {
                wp_update_post(['ID' => $post_id, 'post_content' => usc_get_default_alimony_article_content($state)]);
                update_post_meta($post_id, '_usc_faqs', usc_get_default_alimony_faqs($state));
            }
            update_post_meta($post_id, '_usc_calc_type', 'alimony');
            update_post_meta($post_id, '_usc_state_slug', $slug);
            wp_set_object_terms($post_id, 'alimony', 'usc_category');
            $current_ver = get_post_meta($post_id, '_usc_template_version', true);
            if ($current_ver !== '24' || empty(get_post_meta($post_id, '_usc_calc_html', true)) || empty(get_post_meta($post_id, '_usc_calc_js', true))) {
                $defaults = usc_get_default_templates('alimony', $slug);
                update_post_meta($post_id, '_usc_calc_html', $defaults['html']);
                update_post_meta($post_id, '_usc_calc_css', $defaults['css']);
                update_post_meta($post_id, '_usc_calc_js', $defaults['js']);
                update_post_meta($post_id, '_usc_template_version', '24');
            }
        }

        // Mortgage
        $new_mortgage_slug = 'mortgage-calculator-' . $slug;
        $mortgage_exists   = usc_get_calculator_by_meta('mortgage', $slug);
        if (!$mortgage_exists) {
            $post_id = wp_insert_post(['post_title' => 'Mortgage Calculator ' . $state['name'], 'post_name' => $new_mortgage_slug, 'post_status' => 'publish', 'post_type' => USC_CPT, 'post_content' => usc_get_default_mortgage_article_content($state)]);
            if (!is_wp_error($post_id)) {
                update_post_meta($post_id, '_usc_calc_type', 'mortgage');
                update_post_meta($post_id, '_usc_state_slug', $slug);
                update_post_meta($post_id, '_usc_seo_title', 'Mortgage Calculator ' . $state['name'] . ' - Calfy');
                update_post_meta($post_id, '_usc_seo_desc', usc_get_default_mortgage_seo_desc($state));
                $defaults = usc_get_mortgage_templates($slug);
                update_post_meta($post_id, '_usc_calc_html', $defaults['html']);
                update_post_meta($post_id, '_usc_calc_css', $defaults['css']);
                update_post_meta($post_id, '_usc_calc_js', $defaults['js']);
                update_post_meta($post_id, '_usc_faqs', usc_get_default_mortgage_faqs($state));
                update_post_meta($post_id, '_usc_template_version', '24');
                wp_set_object_terms($post_id, 'mortgage', 'usc_category');
            }
        } else {
            $post_id     = $mortgage_exists->ID;
            $post_status = get_post_status($post_id);
            if ($post_status === 'trash' || $post_status === 'draft') wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
            $post_content = $mortgage_exists->post_content;
            $post_title   = $mortgage_exists->post_title;
            $post_name    = $mortgage_exists->post_name;
            $needs_update = false;
            $update_args  = ['ID' => $post_id];
            $expected_title = 'Mortgage Calculator ' . $state['name'];
            if ($post_title !== $expected_title) { $update_args['post_title'] = $expected_title; $needs_update = true; }
            if ($post_name !== $new_mortgage_slug) {
                $conflicting_posts = get_posts(['post_type' => USC_CPT, 'post_status' => 'trash', 'name' => $new_mortgage_slug, 'numberposts' => -1]);
                foreach ($conflicting_posts as $cp) wp_delete_post($cp->ID, true);
                $update_args['post_name'] = $new_mortgage_slug; $needs_update = true;
            }
            if (empty($post_content) || strpos($post_content, '<!-- usc-v5-article -->') === false) {
                $update_args['post_content'] = usc_get_default_mortgage_article_content($state); $needs_update = true;
                update_post_meta($post_id, '_usc_faqs', usc_get_default_mortgage_faqs($state));
            }
            if ($needs_update) wp_update_post($update_args);
            update_post_meta($post_id, '_usc_calc_type', 'mortgage');
            update_post_meta($post_id, '_usc_state_slug', $slug);
            wp_set_object_terms($post_id, 'mortgage', 'usc_category');
            $current_ver = get_post_meta($post_id, '_usc_template_version', true);
            if ($current_ver !== '24' || empty(get_post_meta($post_id, '_usc_calc_html', true)) || empty(get_post_meta($post_id, '_usc_calc_js', true))) {
                $defaults = usc_get_mortgage_templates($slug);
                update_post_meta($post_id, '_usc_calc_html', $defaults['html']);
                update_post_meta($post_id, '_usc_calc_css', $defaults['css']);
                update_post_meta($post_id, '_usc_calc_js', $defaults['js']);
                update_post_meta($post_id, '_usc_template_version', '24');
            }
        }
    }
}

// ============================================================
// AUTO-GENERATE STATE PAGES — UST
// ============================================================

function ust_auto_generate_state_pages() {
    ust_cleanup_duplicate_calculators();
    $states = ust_get_states_data();
    foreach ($states as $slug => $state) {
        // Income Tax
        $income_exists = ust_get_calculator_by_meta('income-tax', $slug);
        if (!$income_exists) {
            $post_id = wp_insert_post(['post_title' => $state['name'] . ' Income Tax Calculator', 'post_name' => $slug . '-income-tax-calculator', 'post_status' => 'publish', 'post_type' => UST_CPT, 'post_content' => ust_get_income_tax_default_content($state)]);
            if (!is_wp_error($post_id)) {
                update_post_meta($post_id, '_ust_calc_type', 'income-tax');
                update_post_meta($post_id, '_ust_state_slug', $slug);
                update_post_meta($post_id, '_ust_seo_title', sprintf(__('%s Income Tax Calculator - Calfy', 'usa-state-all-calculators'), $state['name']));
                update_post_meta($post_id, '_ust_seo_desc', sprintf(__('Estimate your annual take-home salary, federal income taxes, FICA withholdings, and %s state tax brackets using our free income tax calculator.', 'usa-state-all-calculators'), $state['name']));
                $defaults = ust_get_default_templates('income-tax', $slug);
                update_post_meta($post_id, '_ust_calc_html', $defaults['html']);
                update_post_meta($post_id, '_ust_calc_css', $defaults['css']);
                update_post_meta($post_id, '_ust_calc_js', $defaults['js']);
                update_post_meta($post_id, '_ust_faqs', ust_get_income_tax_faqs($state));
                update_post_meta($post_id, '_ust_template_version', '25');
                update_post_meta($post_id, '_ust_enable_lead_capture', '0');
                $thumb_id = ust_get_or_create_illustration_attachment('income-tax');
                if ($thumb_id) set_post_thumbnail($post_id, $thumb_id);
                wp_set_object_terms($post_id, 'income-tax', 'ust_category');
            }
        } else {
            $post_id = $income_exists->ID;
            $post_status = get_post_status($post_id);
            if ($post_status === 'trash' || $post_status === 'draft') wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
            $post_content = $income_exists->post_content;
            if (empty($post_content) || strpos($post_content, '<!-- ust-v12-article -->') === false) {
                wp_update_post(['ID' => $post_id, 'post_content' => ust_get_income_tax_default_content($state) . '<!-- ust-v12-article -->']);
                update_post_meta($post_id, '_ust_faqs', ust_get_income_tax_faqs($state));
            }
            if (!has_post_thumbnail($post_id)) { $thumb_id = ust_get_or_create_illustration_attachment('income-tax'); if ($thumb_id) set_post_thumbnail($post_id, $thumb_id); }
            update_post_meta($post_id, '_ust_calc_type', 'income-tax');
            update_post_meta($post_id, '_ust_state_slug', $slug);
            wp_set_object_terms($post_id, 'income-tax', 'ust_category');
            $current_ver = get_post_meta($post_id, '_ust_template_version', true);
            if ($current_ver !== '25' || empty(get_post_meta($post_id, '_ust_calc_html', true))) {
                $defaults = ust_get_default_templates('income-tax', $slug);
                update_post_meta($post_id, '_ust_calc_html', $defaults['html']);
                update_post_meta($post_id, '_ust_calc_css', $defaults['css']);
                update_post_meta($post_id, '_ust_calc_js', $defaults['js']);
                update_post_meta($post_id, '_ust_template_version', '25');
            }
        }

        // Property Tax
        $property_exists = ust_get_calculator_by_meta('property-tax', $slug);
        if (!$property_exists) {
            $post_id = wp_insert_post(['post_title' => $state['name'] . ' Property Tax Calculator', 'post_name' => $slug . '-property-tax-calculator', 'post_status' => 'publish', 'post_type' => UST_CPT, 'post_content' => ust_get_property_tax_default_content($state)]);
            if (!is_wp_error($post_id)) {
                update_post_meta($post_id, '_ust_calc_type', 'property-tax');
                update_post_meta($post_id, '_ust_state_slug', $slug);
                update_post_meta($post_id, '_ust_seo_title', sprintf(__('%s Property Tax Calculator - Calfy', 'usa-state-all-calculators'), $state['name']));
                update_post_meta($post_id, '_ust_seo_desc', sprintf(__('Calculate your monthly and annual property taxes in %s. Select your county, apply senior/homestead exemptions, and view 5-year projections.', 'usa-state-all-calculators'), $state['name']));
                $defaults = ust_get_default_templates('property-tax', $slug);
                update_post_meta($post_id, '_ust_calc_html', $defaults['html']);
                update_post_meta($post_id, '_ust_calc_css', $defaults['css']);
                update_post_meta($post_id, '_ust_calc_js', $defaults['js']);
                update_post_meta($post_id, '_ust_faqs', ust_get_property_tax_faqs($state));
                update_post_meta($post_id, '_ust_template_version', '25');
                update_post_meta($post_id, '_ust_enable_lead_capture', '0');
                $thumb_id = ust_get_or_create_illustration_attachment('property-tax');
                if ($thumb_id) set_post_thumbnail($post_id, $thumb_id);
                wp_set_object_terms($post_id, 'property-tax', 'ust_category');
            }
        } else {
            $post_id = $property_exists->ID;
            $post_status = get_post_status($post_id);
            if ($post_status === 'trash' || $post_status === 'draft') wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
            $post_content = $property_exists->post_content;
            if (empty($post_content) || strpos($post_content, '<!-- ust-v12-article -->') === false) {
                wp_update_post(['ID' => $post_id, 'post_content' => ust_get_property_tax_default_content($state) . '<!-- ust-v12-article -->']);
                update_post_meta($post_id, '_ust_faqs', ust_get_property_tax_faqs($state));
            }
            if (!has_post_thumbnail($post_id)) { $thumb_id = ust_get_or_create_illustration_attachment('property-tax'); if ($thumb_id) set_post_thumbnail($post_id, $thumb_id); }
            update_post_meta($post_id, '_ust_calc_type', 'property-tax');
            update_post_meta($post_id, '_ust_state_slug', $slug);
            wp_set_object_terms($post_id, 'property-tax', 'ust_category');
            $current_ver = get_post_meta($post_id, '_ust_template_version', true);
            if ($current_ver !== '25' || empty(get_post_meta($post_id, '_ust_calc_html', true))) {
                $defaults = ust_get_default_templates('property-tax', $slug);
                update_post_meta($post_id, '_ust_calc_html', $defaults['html']);
                update_post_meta($post_id, '_ust_calc_css', $defaults['css']);
                update_post_meta($post_id, '_ust_calc_js', $defaults['js']);
                update_post_meta($post_id, '_ust_template_version', '25');
            }
        }

        // Sales Tax
        $sales_exists = ust_get_calculator_by_meta('sales-tax', $slug);
        if (!$sales_exists) {
            $post_id = wp_insert_post(['post_title' => $state['name'] . ' Sales Tax Calculator', 'post_name' => $slug . '-sales-tax-calculator', 'post_status' => 'publish', 'post_type' => UST_CPT, 'post_content' => ust_get_sales_tax_default_content($state)]);
            if (!is_wp_error($post_id)) {
                update_post_meta($post_id, '_ust_calc_type', 'sales-tax');
                update_post_meta($post_id, '_ust_state_slug', $slug);
                update_post_meta($post_id, '_ust_seo_title', sprintf(__('%s Sales Tax Calculator - Calfy', 'usa-state-all-calculators'), $state['name']));
                update_post_meta($post_id, '_ust_seo_desc', sprintf(__('Calculate the combined state and local sales tax for purchases in %s. Apply tax-exempt status for groceries or medicine and view benchmarking costs.', 'usa-state-all-calculators'), $state['name']));
                $defaults = ust_get_default_templates('sales-tax', $slug);
                update_post_meta($post_id, '_ust_calc_html', $defaults['html']);
                update_post_meta($post_id, '_ust_calc_css', $defaults['css']);
                update_post_meta($post_id, '_ust_calc_js', $defaults['js']);
                update_post_meta($post_id, '_ust_faqs', ust_get_sales_tax_faqs($state));
                update_post_meta($post_id, '_ust_template_version', '25');
                update_post_meta($post_id, '_ust_enable_lead_capture', '0');
                $thumb_id = ust_get_or_create_illustration_attachment('sales-tax');
                if ($thumb_id) set_post_thumbnail($post_id, $thumb_id);
                wp_set_object_terms($post_id, 'sales-tax', 'ust_category');
            }
        } else {
            $post_id = $sales_exists->ID;
            $post_status = get_post_status($post_id);
            if ($post_status === 'trash' || $post_status === 'draft') wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
            $post_content = $sales_exists->post_content;
            if (empty($post_content) || strpos($post_content, '<!-- ust-v12-article -->') === false) {
                wp_update_post(['ID' => $post_id, 'post_content' => ust_get_sales_tax_default_content($state) . '<!-- ust-v12-article -->']);
                update_post_meta($post_id, '_ust_faqs', ust_get_sales_tax_faqs($state));
            }
            if (!has_post_thumbnail($post_id)) { $thumb_id = ust_get_or_create_illustration_attachment('sales-tax'); if ($thumb_id) set_post_thumbnail($post_id, $thumb_id); }
            update_post_meta($post_id, '_ust_calc_type', 'sales-tax');
            update_post_meta($post_id, '_ust_state_slug', $slug);
            wp_set_object_terms($post_id, 'sales-tax', 'ust_category');
            $current_ver = get_post_meta($post_id, '_ust_template_version', true);
            if ($current_ver !== '25' || empty(get_post_meta($post_id, '_ust_calc_html', true))) {
                $defaults = ust_get_default_templates('sales-tax', $slug);
                update_post_meta($post_id, '_ust_calc_html', $defaults['html']);
                update_post_meta($post_id, '_ust_calc_css', $defaults['css']);
                update_post_meta($post_id, '_ust_calc_js', $defaults['js']);
                update_post_meta($post_id, '_ust_template_version', '25');
            }
        }
    }

    // Other Tax Calculators
    $other_registry = ust_get_other_calculators_registry();
    foreach ($other_registry as $other_slug => $other_data) {
        $other_exists = ust_get_calculator_by_meta('other', $other_slug);
        if (!$other_exists) {
            $post_id = wp_insert_post(['post_title' => $other_data['name'], 'post_name' => $other_slug, 'post_status' => 'publish', 'post_type' => UST_CPT, 'post_content' => ust_get_other_tax_default_content($other_slug)]);
            if (!is_wp_error($post_id)) {
                update_post_meta($post_id, '_ust_calc_type', 'other');
                update_post_meta($post_id, '_ust_state_slug', $other_slug);
                update_post_meta($post_id, '_ust_seo_title', $other_data['title_seo']);
                update_post_meta($post_id, '_ust_seo_desc', $other_data['desc_seo']);
                $defaults = ust_get_default_templates('other', $other_slug);
                update_post_meta($post_id, '_ust_calc_html', $defaults['html']);
                update_post_meta($post_id, '_ust_calc_css', $defaults['css']);
                update_post_meta($post_id, '_ust_calc_js', $defaults['js']);
                update_post_meta($post_id, '_ust_faqs', ust_get_other_tax_faqs($other_slug));
                update_post_meta($post_id, '_ust_template_version', '25');
                update_post_meta($post_id, '_ust_enable_lead_capture', '0');
                $thumb_id = ust_get_or_create_illustration_attachment('income-tax');
                if ($thumb_id) set_post_thumbnail($post_id, $thumb_id);
                wp_set_object_terms($post_id, 'other', 'ust_category');
            }
        } else {
            $post_id = $other_exists->ID;
            $post_status = get_post_status($post_id);
            if ($post_status === 'trash' || $post_status === 'draft') wp_update_post(['ID' => $post_id, 'post_status' => 'publish']);
            $post_content = $other_exists->post_content;
            if (empty($post_content) || strpos($post_content, '<!-- ust-v12-article -->') === false) {
                wp_update_post(['ID' => $post_id, 'post_content' => ust_get_other_tax_default_content($other_slug) . '<!-- ust-v12-article -->']);
                update_post_meta($post_id, '_ust_faqs', ust_get_other_tax_faqs($other_slug));
            }
            if (!has_post_thumbnail($post_id)) { $thumb_id = ust_get_or_create_illustration_attachment('income-tax'); if ($thumb_id) set_post_thumbnail($post_id, $thumb_id); }
            update_post_meta($post_id, '_ust_calc_type', 'other');
            update_post_meta($post_id, '_ust_state_slug', $other_slug);
            wp_set_object_terms($post_id, 'other', 'ust_category');
            $current_ver = get_post_meta($post_id, '_ust_template_version', true);
            if ($current_ver !== '25' || empty(get_post_meta($post_id, '_ust_calc_html', true))) {
                $defaults = ust_get_default_templates('other', $other_slug);
                update_post_meta($post_id, '_ust_calc_html', $defaults['html']);
                update_post_meta($post_id, '_ust_calc_css', $defaults['css']);
                update_post_meta($post_id, '_ust_calc_js', $defaults['js']);
                update_post_meta($post_id, '_ust_template_version', '25');
            }
        }
    }
}

// ============================================================
// SHORTCODES
// ============================================================

add_shortcode('usc_directory', 'usc_render_directory_shortcode');
function usc_render_directory_shortcode($atts) {
    $atts   = shortcode_atts(['type' => 'paycheck'], $atts, 'usc_directory');
    $states = usc_get_states_data();
    $type   = sanitize_key($atts['type']);
    $titles = ['paycheck' => 'Paycheck Calculators', 'child-support' => 'Child Support Calculators', 'alimony' => 'Alimony Calculators', 'mortgage' => 'Mortgage Calculators'];
    $descs  = ['paycheck' => 'Select your state below to estimate taxes and take-home pay accurately.', 'child-support' => 'Select your state below to estimate child support obligations accurately.', 'alimony' => 'Select your state below to estimate alimony and maintenance payments.', 'mortgage' => 'Select your state below to calculate mortgage payments, amortization, and extra payments.'];
    $title  = $titles[$type] ?? 'Calculators';
    $desc   = $descs[$type]  ?? 'Select your state below to run calculations accurately.';
    ob_start();
    ?>
    <div class="usc-dir-container">
        <div class="usc-dir-header">
            <h3>Explore <?php echo esc_html($title); ?> by State</h3>
            <p><?php echo esc_html($desc); ?></p>
        </div>
        <div class="usc-dir-grid">
            <?php foreach ($states as $slug => $state) :
                $post_slug = ($type === 'mortgage') ? 'mortgage-calculator-' . $slug : $slug . '-' . $type . '-calculator';
                $post      = get_page_by_path($post_slug, OBJECT, USC_CPT);
                $url       = $post ? get_permalink($post->ID) : '#'; ?>
                <a href="<?php echo esc_url($url); ?>" class="usc-dir-card">
                    <span class="usc-dir-flag">🇺🇸</span>
                    <span class="usc-dir-name"><?php echo esc_html($state['name']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('ust_directory', 'ust_render_directory_shortcode');
function ust_render_directory_shortcode($atts) {
    $atts   = shortcode_atts(['type' => 'income-tax'], $atts, 'ust_directory');
    $states = ust_get_states_data();
    $type   = sanitize_key($atts['type']);
    $titles = ['income-tax' => 'Income Tax Calculators', 'property-tax' => 'Property Tax Calculators', 'sales-tax' => 'Sales Tax Calculators'];
    $descs  = ['income-tax' => 'Select your state below to estimate federal/state income taxes, FICA withholdings, and take-home pay.', 'property-tax' => 'Select your state below to estimate annual and monthly property taxes, assessments, and exemptions.', 'sales-tax' => 'Select your state below to calculate combined state and local sales taxes on retail purchases.'];
    $title  = $titles[$type] ?? 'Tax Calculators';
    $desc   = $descs[$type]  ?? 'Select your state below to run calculations accurately.';
    ob_start();
    ?>
    <div class="usc-dir-container">
        <div class="usc-dir-header">
            <h3>Explore <?php echo esc_html($title); ?> by State</h3>
            <p><?php echo esc_html($desc); ?></p>
        </div>
        <div class="usc-dir-grid">
            <?php foreach ($states as $slug => $state) :
                $post_slug = $slug . '-' . $type . '-calculator';
                $post      = get_page_by_path($post_slug, OBJECT, UST_CPT);
                $url       = $post ? get_permalink($post->ID) : '#'; ?>
                <a href="<?php echo esc_url($url); ?>" class="usc-dir-card">
                    <span class="usc-dir-flag">🇺🇸</span>
                    <span class="usc-dir-name"><?php echo esc_html($state['name']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ============================================================
// COMBINED ADMIN MENU — Single Hub for Both USC + UST
// ============================================================

add_action('admin_menu', 'usac_register_admin_menus');
function usac_register_admin_menus() {
    remove_menu_page('edit.php?post_type=' . USC_CPT);
    remove_menu_page('edit.php?post_type=' . UST_CPT);

    // MAIN COMBINED HUB
    add_menu_page('All Calculators Hub', 'All Calculators Hub', 'manage_options', 'usac_calculators_hub', 'usac_render_hub_dashboard', 'dashicons-calculator', 30);

    // Dashboard
    add_submenu_page('usac_calculators_hub', 'Dashboard', 'Dashboard', 'manage_options', 'usac_calculators_hub', 'usac_render_hub_dashboard');

    // USC submenus
    add_submenu_page('usac_calculators_hub', 'Paycheck Calculators', 'Paycheck', 'manage_options', 'edit.php?post_type=' . USC_CPT . '&calc_type=paycheck');
    add_submenu_page('usac_calculators_hub', 'Child Support Calculators', 'Child Support', 'manage_options', 'edit.php?post_type=' . USC_CPT . '&calc_type=child-support');
    add_submenu_page('usac_calculators_hub', 'Alimony Calculators', 'Alimony', 'manage_options', 'edit.php?post_type=' . USC_CPT . '&calc_type=alimony');
    add_submenu_page('usac_calculators_hub', 'Mortgage Calculators', 'Mortgage', 'manage_options', 'edit.php?post_type=' . USC_CPT . '&calc_type=mortgage');

    // UST submenus
    add_submenu_page('usac_calculators_hub', 'Income Tax Calculators', 'Income Tax', 'manage_options', 'edit.php?post_type=' . UST_CPT . '&calc_type=income-tax');
    add_submenu_page('usac_calculators_hub', 'Property Tax Calculators', 'Property Tax', 'manage_options', 'edit.php?post_type=' . UST_CPT . '&calc_type=property-tax');
    add_submenu_page('usac_calculators_hub', 'Sales Tax Calculators', 'Sales Tax', 'manage_options', 'edit.php?post_type=' . UST_CPT . '&calc_type=sales-tax');
    add_submenu_page('usac_calculators_hub', 'Other Tax Calculators', 'Other Tax', 'manage_options', 'edit.php?post_type=' . UST_CPT . '&calc_type=other');

    // Shared submenus
    add_submenu_page('usac_calculators_hub', 'Add New (Paycheck)', 'Add New Paycheck', 'manage_options', 'post-new.php?post_type=' . USC_CPT);
    add_submenu_page('usac_calculators_hub', 'Add New (Tax)', 'Add New Tax', 'manage_options', 'post-new.php?post_type=' . UST_CPT);
    add_submenu_page('usac_calculators_hub', 'Paycheck Leads', 'Paycheck Leads', 'manage_options', 'usc_captured_leads', 'usc_render_leads_page');
    add_submenu_page('usac_calculators_hub', 'Tax Leads', 'Tax Leads', 'manage_options', 'ust_captured_leads', 'ust_render_leads_page');
    add_submenu_page('usac_calculators_hub', 'Paycheck Analytics', 'Paycheck Analytics', 'manage_options', 'usc_usage_analytics', 'usc_render_usage_page');
    add_submenu_page('usac_calculators_hub', 'Tax Analytics', 'Tax Analytics', 'manage_options', 'ust_usage_analytics', 'ust_render_usage_page');
    add_submenu_page('usac_calculators_hub', 'Ads Settings', 'Ads Settings', 'manage_options', 'usac_ads_settings', 'usac_render_ads_settings_page');
    add_submenu_page('usac_calculators_hub', 'Data Sources & Freshness', '📅 Data Freshness', 'manage_options', 'usac_data_sources', 'usac_render_data_sources_page');
}

// Parent menu highlight for both CPTs
add_filter('parent_file', 'usac_admin_parent_menu_highlight');
function usac_admin_parent_menu_highlight($parent_file) {
    global $pagenow;
    if (is_admin()) {
        $post_type = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : '';
        if (in_array($pagenow, ['edit.php', 'post-new.php']) && in_array($post_type, [USC_CPT, UST_CPT])) {
            return 'usac_calculators_hub';
        }
        if ($pagenow === 'post.php' && isset($_GET['post'])) {
            $post_id = intval($_GET['post']);
            if (in_array(get_post_type($post_id), [USC_CPT, UST_CPT])) {
                return 'usac_calculators_hub';
            }
        }
    }
    return $parent_file;
}

// ============================================================
// ADS SETTINGS PAGE — Combined
// ============================================================

function usac_render_ads_settings_page() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized user.');

    if (isset($_POST['usac_save_ads_settings'])) {
        if (!isset($_POST['usac_ads_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['usac_ads_nonce'])), 'usac_save_ads')) {
            wp_die('Security check failed.');
        }
        $enabled = isset($_POST['usac_global_ads_enabled']) ? '1' : '0';
        update_option('usc_global_ads_enabled', $enabled);
        update_option('ust_global_ads_enabled', $enabled);
        if (current_user_can('unfiltered_html')) {
            $code = isset($_POST['usac_global_ads_code']) ? wp_unslash($_POST['usac_global_ads_code']) : '';
            update_option('usc_global_ads_code', $code);
            update_option('ust_global_ads_code', $code);
        }
        echo '<div class="notice notice-success is-dismissible"><p><strong>Ads settings saved for all calculators.</strong></p></div>';
    }

    $global_enabled = get_option('usc_global_ads_enabled', '1');
    $global_code    = get_option('usc_global_ads_code', '');
    ?>
    <div class="usc-admin-wrap">
        <div class="usc-panel">
            <div class="usc-panel-header"><h2>Native Ads Configuration — All Calculators</h2></div>
            <div class="usc-panel-content">
                <form method="post" action="">
                    <?php wp_nonce_field('usac_save_ads', 'usac_ads_nonce'); ?>
                    <div class="usc-meta-row" style="margin-bottom: 24px;">
                        <label for="usac_global_ads_enabled" style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px;">
                            <input type="checkbox" name="usac_global_ads_enabled" id="usac_global_ads_enabled" value="1" <?php checked($global_enabled, '1'); ?> style="margin: 0; width: 16px; height: 16px;" />
                            <strong>Enable Native Ads Globally (All Calculators)</strong>
                        </label>
                        <p class="description" style="margin-top: 6px; margin-left: 24px;">Toggle this to turn ads on/off across all Paycheck, Child Support, Alimony, Mortgage, Income Tax, Property Tax, and Sales Tax calculator pages.</p>
                    </div>
                    <div class="usc-meta-row" style="margin-bottom: 24px;">
                        <label for="usac_global_ads_code" style="font-size: 14px; font-weight: 700; margin-bottom: 8px; display: block;">Global Native Ads Script Code</label>
                        <textarea name="usac_global_ads_code" id="usac_global_ads_code" style="width: 100%; height: 180px; font-family: monospace; font-size: 13px; padding: 12px; border-radius: 8px;" placeholder="Paste your ad script code here..."><?php echo esc_textarea($global_code); ?></textarea>
                    </div>
                    <div class="usc-meta-row">
                        <button type="submit" name="usac_save_ads_settings" class="usc-btn usc-btn-primary" style="padding: 12px 24px; font-size: 14px;">
                            <span class="dashicons dashicons-saved" style="margin-top: 2px;"></span> Save Configurations
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
}

// ============================================================
// DATA SOURCES & FRESHNESS DASHBOARD
// Tracks which tax/financial datasets each calculator relies on,
// what year they represent, when they were last reviewed, and where
// they live in the code. Does NOT touch any calculator output.
// ============================================================

function usac_get_data_sources_registry() {
    // Default metadata describing every dataset the calculators depend on.
    return [
        'federal_income_tax' => [
            'label'    => 'Federal Income Tax Brackets',
            'covers'   => 'Paycheck, Alimony, Income Tax, Bonus & Other Tax calculators',
            'year'     => '2026',
            'authority'=> 'IRS',
            'source'   => 'https://www.irs.gov/filing/federal-income-tax-rates-and-brackets',
            'cadence'  => 'Annually — new brackets released ~Oct/Nov for the next year',
            'code'     => 'data/income-tax.php, data/usc-default-templates.php, data/alimony.php, data/other-tax.php',
        ],
        'standard_deduction' => [
            'label'    => 'Federal Standard Deduction',
            'covers'   => 'Paycheck, Alimony, Income Tax calculators',
            'year'     => '2026',
            'authority'=> 'IRS',
            'source'   => 'https://www.irs.gov/newsroom',
            'cadence'  => 'Annually (inflation-adjusted)',
            'code'     => 'data/usc-default-templates.php, data/alimony.php, data/income-tax.php',
        ],
        'fica_ss_wage_base' => [
            'label'    => 'Social Security Wage Base (FICA cap)',
            'covers'   => 'Paycheck, Alimony, Income Tax calculators',
            'year'     => '2026',
            'authority'=> 'SSA',
            'source'   => 'https://www.ssa.gov/oact/cola/cbb.html',
            'cadence'  => 'Annually — announced each October',
            'code'     => 'data/alimony.php (168600), data/usc-default-templates.php',
        ],
        'retirement_limits' => [
            'label'    => '401(k) / HSA / FSA Contribution Limits',
            'covers'   => 'Paycheck calculator (pre-tax deductions)',
            'year'     => '2026',
            'authority'=> 'IRS',
            'source'   => 'https://www.irs.gov/retirement-plans',
            'cadence'  => 'Annually',
            'code'     => 'data/usc-default-templates.php (tooltips & limits)',
        ],
        'state_income_tax' => [
            'label'    => 'State Income Tax Brackets & Deductions',
            'covers'   => 'Income Tax, Paycheck, State Income Tax calculators (50 states)',
            'year'     => '2024',
            'authority'=> 'State revenue departments',
            'source'   => 'https://taxfoundation.org/data/all/state/state-income-tax-rates/',
            'cadence'  => 'Annually',
            'code'     => 'data/income-tax.php (states array)',
        ],
        'sales_tax' => [
            'label'    => 'State & Local Sales Tax Rates',
            'covers'   => 'Sales Tax calculator',
            'year'     => '2025',
            'authority'=> 'State revenue departments',
            'source'   => 'https://taxfoundation.org/data/all/state/state-and-local-sales-tax-rates-2025/',
            'cadence'  => 'Annually / when states change rates',
            'code'     => 'data/sales-tax.php',
        ],
        'property_tax' => [
            'label'    => 'Property Tax Rates (effective %)',
            'covers'   => 'Property Tax calculator',
            'year'     => '2024',
            'authority'=> 'County assessors / Tax Foundation',
            'source'   => 'https://taxfoundation.org/data/all/state/property-taxes-by-state-county/',
            'cadence'  => 'Annually',
            'code'     => 'data/property-tax.php',
        ],
        'mortgage' => [
            'label'    => 'Mortgage Rates & Closing Costs',
            'covers'   => 'Mortgage calculator',
            'year'     => '2025',
            'authority'=> 'Freddie Mac / state averages',
            'source'   => 'https://www.freddiemac.com/pmms',
            'cadence'  => 'Rates change weekly; closing-cost averages yearly',
            'code'     => 'data/mortgage.php (mortgageStateDictionary)',
        ],
        'cost_of_living' => [
            'label'    => 'Cost of Living Index',
            'covers'   => 'Income Tax (COL adjuster), comparisons',
            'year'     => '2024',
            'authority'=> 'BEA / MERIC',
            'source'   => 'https://meric.mo.gov/data/cost-living-data-series',
            'cadence'  => 'Quarterly / annually',
            'code'     => 'data/cost-of-living.php',
        ],
    ];
}

function usac_render_data_sources_page() {
    if (!current_user_can('manage_options')) wp_die('Unauthorized user.');

    $registry = usac_get_data_sources_registry();
    $meta = get_option('usac_data_freshness', []);
    if (!is_array($meta)) $meta = [];
    $target_year = get_option('usac_data_target_year', '2026');

    // Handle saves
    if (isset($_POST['usac_data_action'])) {
        if (!isset($_POST['usac_data_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['usac_data_nonce'])), 'usac_data_freshness')) {
            wp_die('Security check failed.');
        }
        $action = sanitize_text_field(wp_unslash($_POST['usac_data_action']));

        if ($action === 'save_target_year') {
            $ty = preg_replace('/[^0-9]/', '', (string) ($_POST['usac_target_year'] ?? ''));
            if (strlen($ty) === 4) {
                update_option('usac_data_target_year', $ty);
                $target_year = $ty;
            }
            echo '<div class="notice notice-success is-dismissible"><p><strong>Target tax year saved.</strong></p></div>';
        }

        if ($action === 'update_source') {
            $key = sanitize_key($_POST['usac_source_key'] ?? '');
            if (isset($registry[$key])) {
                $year = preg_replace('/[^0-9]/', '', (string) ($_POST['usac_source_year'] ?? ''));
                $note = sanitize_text_field(wp_unslash($_POST['usac_source_note'] ?? ''));
                $meta[$key] = [
                    'year'         => (strlen($year) === 4) ? $year : ($meta[$key]['year'] ?? $registry[$key]['year']),
                    'last_reviewed'=> current_time('Y-m-d'),
                    'reviewer'     => wp_get_current_user()->user_login,
                    'note'         => $note,
                ];
                update_option('usac_data_freshness', $meta);
                echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html($registry[$key]['label']) . '</strong> marked as reviewed today.</p></div>';
            }
        }

        if ($action === 'save_reminder') {
            update_option('usac_data_reminder_enabled', isset($_POST['usac_reminder_enabled']) ? '1' : '0');
            $email = sanitize_email(wp_unslash($_POST['usac_reminder_email'] ?? ''));
            update_option('usac_data_reminder_email', is_email($email) ? $email : '');
            $days = (int) ($_POST['usac_reminder_days'] ?? 365);
            if ($days < 30) $days = 30;
            update_option('usac_data_reminder_days', $days);
            echo '<div class="notice notice-success is-dismissible"><p><strong>Reminder settings saved.</strong></p></div>';
        }
    }

    $reminder_enabled = get_option('usac_data_reminder_enabled', '1');
    $reminder_email   = get_option('usac_data_reminder_email', '');
    if (!$reminder_email) $reminder_email = get_option('admin_email');
    $reminder_days    = (int) get_option('usac_data_reminder_days', 365);

    // Build rows + summary counts
    $current = 0; $outdated = 0; $review_due = 0;
    $rows = '';
    foreach ($registry as $key => $src) {
        $eff_year = isset($meta[$key]['year']) ? $meta[$key]['year'] : $src['year'];
        $last_reviewed = isset($meta[$key]['last_reviewed']) ? $meta[$key]['last_reviewed'] : '—';
        $reviewer = isset($meta[$key]['reviewer']) ? $meta[$key]['reviewer'] : '';
        $note = isset($meta[$key]['note']) ? $meta[$key]['note'] : '';

        // Status
        if ((int)$eff_year >= (int)$target_year) {
            $status_label = 'Current'; $status_color = '#16a34a'; $status_bg = '#f0fdf4'; $current++;
        } elseif ((int)$eff_year === (int)$target_year - 1) {
            $status_label = 'Review due'; $status_color = '#b45309'; $status_bg = '#fffbeb'; $review_due++;
        } else {
            $status_label = 'Outdated'; $status_color = '#b91c1c'; $status_bg = '#fef2f2'; $outdated++;
        }

        // Reviewed-age flag
        $age_html = '';
        if ($last_reviewed !== '—') {
            $days = (int) floor((current_time('timestamp') - strtotime($last_reviewed)) / DAY_IN_SECONDS);
            $age_html = '<div style="font-size:11px;color:#6b7280;">' . esc_html($last_reviewed) . ($reviewer ? ' · ' . esc_html($reviewer) : '') . ' (' . $days . 'd ago)</div>';
        } else {
            $age_html = '<div style="font-size:11px;color:#b91c1c;">Never reviewed</div>';
        }

        $rows .= '<tr style="border-bottom:1px solid #e5e7eb;">'
            . '<td style="padding:12px 10px;vertical-align:top;">'
                . '<strong style="font-size:13px;">' . esc_html($src['label']) . '</strong>'
                . '<div style="font-size:11.5px;color:#6b7280;margin-top:3px;">' . esc_html($src['covers']) . '</div>'
                . ($note ? '<div style="font-size:11px;color:#374151;margin-top:4px;font-style:italic;">📝 ' . esc_html($note) . '</div>' : '')
            . '</td>'
            . '<td style="padding:12px 10px;vertical-align:top;text-align:center;font-weight:700;">' . esc_html($eff_year) . '</td>'
            . '<td style="padding:12px 10px;vertical-align:top;text-align:center;"><span style="background:' . $status_bg . ';color:' . $status_color . ';font-weight:700;font-size:11.5px;padding:4px 10px;border-radius:20px;white-space:nowrap;">' . esc_html($status_label) . '</span>' . $age_html . '</td>'
            . '<td style="padding:12px 10px;vertical-align:top;font-size:11.5px;color:#374151;">' . esc_html($src['cadence']) . '<div style="margin-top:4px;"><a href="' . esc_url($src['source']) . '" target="_blank" rel="noopener">Official source ↗</a></div></td>'
            . '<td style="padding:12px 10px;vertical-align:top;"><code style="font-size:10.5px;background:#f3f4f6;padding:2px 5px;border-radius:4px;display:inline-block;">' . esc_html($src['code']) . '</code></td>'
            . '<td style="padding:12px 10px;vertical-align:top;">'
                . '<form method="post" style="display:flex;flex-direction:column;gap:6px;min-width:150px;">'
                . wp_nonce_field('usac_data_freshness', 'usac_data_nonce', true, false)
                . '<input type="hidden" name="usac_data_action" value="update_source">'
                . '<input type="hidden" name="usac_source_key" value="' . esc_attr($key) . '">'
                . '<input type="text" name="usac_source_year" value="' . esc_attr($eff_year) . '" maxlength="4" placeholder="Year" style="width:70px;padding:4px 6px;" />'
                . '<input type="text" name="usac_source_note" value="' . esc_attr($note) . '" placeholder="Note (optional)" style="padding:4px 6px;" />'
                . '<button type="submit" class="button button-primary" style="font-size:11.5px;">✔ Mark reviewed today</button>'
                . '</form>'
            . '</td>'
            . '</tr>';
    }
    ?>
    <div class="wrap usc-admin-wrap">
        <h1 style="display:flex;align-items:center;gap:8px;">📅 Data Sources &amp; Freshness</h1>
        <p style="font-size:13px;color:#374151;max-width:820px;">Track every tax/financial dataset your calculators rely on: which year it represents, when it was last reviewed, how often it changes, and exactly where to update it in the code. Set your <strong>target tax year</strong> below and any dataset behind it is flagged.</p>

        <div style="display:flex;gap:14px;flex-wrap:wrap;margin:18px 0;">
            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:14px 20px;min-width:120px;"><div style="font-size:24px;font-weight:800;color:#16a34a;"><?php echo (int)$current; ?></div><div style="font-size:12px;color:#15803d;font-weight:600;">Current</div></div>
            <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:14px 20px;min-width:120px;"><div style="font-size:24px;font-weight:800;color:#b45309;"><?php echo (int)$review_due; ?></div><div style="font-size:12px;color:#b45309;font-weight:600;">Review due</div></div>
            <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:10px;padding:14px 20px;min-width:120px;"><div style="font-size:24px;font-weight:800;color:#b91c1c;"><?php echo (int)$outdated; ?></div><div style="font-size:12px;color:#b91c1c;font-weight:600;">Outdated</div></div>
            <form method="post" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:14px 20px;display:flex;align-items:center;gap:8px;">
                <?php wp_nonce_field('usac_data_freshness', 'usac_data_nonce'); ?>
                <input type="hidden" name="usac_data_action" value="save_target_year">
                <label style="font-size:12px;font-weight:700;color:#1e40af;">Target tax year:</label>
                <input type="text" name="usac_target_year" value="<?php echo esc_attr($target_year); ?>" maxlength="4" style="width:70px;padding:4px 6px;" />
                <button type="submit" class="button">Save</button>
            </form>
        </div>

        <table class="widefat" style="background:#fff;border-radius:10px;overflow:hidden;">
            <thead>
                <tr style="background:#f9fafb;">
                    <th style="padding:10px;text-align:left;">Dataset</th>
                    <th style="padding:10px;text-align:center;">Data year</th>
                    <th style="padding:10px;text-align:center;">Status</th>
                    <th style="padding:10px;text-align:left;">Update cadence</th>
                    <th style="padding:10px;text-align:left;">Where in code</th>
                    <th style="padding:10px;text-align:left;">Action</th>
                </tr>
            </thead>
            <tbody><?php echo $rows; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></tbody>
        </table>

        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:18px 20px;margin-top:18px;max-width:680px;">
            <h2 style="margin-top:0;font-size:15px;display:flex;align-items:center;gap:6px;">📧 Email reminders</h2>
            <p style="font-size:12.5px;color:#374151;">Get an automatic email when any dataset falls behind the target year or hasn't been reviewed in a while. Checked daily; sent at most once a week.</p>
            <form method="post">
                <?php wp_nonce_field('usac_data_freshness', 'usac_data_nonce'); ?>
                <input type="hidden" name="usac_data_action" value="save_reminder">
                <p>
                    <label style="display:inline-flex;align-items:center;gap:8px;font-size:13px;">
                        <input type="checkbox" name="usac_reminder_enabled" value="1" <?php checked($reminder_enabled, '1'); ?>>
                        <strong>Enable email reminders</strong>
                    </label>
                </p>
                <p style="font-size:13px;">
                    Send to: <input type="email" name="usac_reminder_email" value="<?php echo esc_attr($reminder_email); ?>" style="width:280px;padding:5px 8px;" placeholder="admin@example.com">
                </p>
                <p style="font-size:13px;">
                    Also remind if a dataset hasn't been reviewed in
                    <input type="number" name="usac_reminder_days" value="<?php echo esc_attr($reminder_days); ?>" min="30" style="width:80px;padding:5px 8px;"> days
                </p>
                <button type="submit" class="button button-primary">Save reminder settings</button>
            </form>
        </div>

        <p style="font-size:12px;color:#6b7280;margin-top:14px;">💡 Tip: after you update a dataset in the code files shown above, come back here and click <strong>"Mark reviewed today"</strong> so the date and your username are recorded.</p>
    </div>
    <?php
}

// ============================================================
// DATA FRESHNESS — EMAIL REMINDER AUTOMATION (daily cron)
// ============================================================

// Ensure the daily check is scheduled.
add_action('init', 'usac_schedule_data_freshness_check');
function usac_schedule_data_freshness_check() {
    if (!wp_next_scheduled('usac_data_freshness_cron')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'usac_data_freshness_cron');
    }
}

add_action('usac_data_freshness_cron', 'usac_run_data_freshness_check');
function usac_run_data_freshness_check() {
    // Respect the admin toggle (on by default).
    if (get_option('usac_data_reminder_enabled', '1') !== '1') {
        return;
    }

    $registry    = usac_get_data_sources_registry();
    $meta        = get_option('usac_data_freshness', []);
    if (!is_array($meta)) $meta = [];
    $target_year = (int) get_option('usac_data_target_year', '2026');
    $max_age     = (int) get_option('usac_data_reminder_days', 365); // review-age threshold in days

    $stale = [];
    foreach ($registry as $key => $src) {
        $eff_year = isset($meta[$key]['year']) ? (int) $meta[$key]['year'] : (int) $src['year'];
        $reason   = '';
        if ($eff_year < $target_year) {
            $reason = 'Behind target year (' . $eff_year . ' < ' . $target_year . ')';
        } else {
            $last = isset($meta[$key]['last_reviewed']) ? $meta[$key]['last_reviewed'] : '';
            if ($last) {
                $age_days = (int) floor((current_time('timestamp') - strtotime($last)) / DAY_IN_SECONDS);
                if ($age_days > $max_age) {
                    $reason = 'Not reviewed for ' . $age_days . ' days';
                }
            } else {
                $reason = 'Never reviewed';
            }
        }
        if ($reason) {
            $stale[] = ['label' => $src['label'], 'reason' => $reason, 'source' => $src['source']];
        }
    }

    if (empty($stale)) {
        return; // nothing to remind about
    }

    // Only send once every 7 days to avoid spamming.
    $last_sent = (int) get_option('usac_data_reminder_last_sent', 0);
    if ($last_sent && (time() - $last_sent) < 7 * DAY_IN_SECONDS) {
        return;
    }

    $to = get_option('usac_data_reminder_email', '');
    if (!is_email($to)) {
        $to = get_option('admin_email');
    }
    $site  = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
    $admin_url = admin_url('admin.php?page=usac_data_sources');

    $subject = '[' . $site . '] ' . count($stale) . ' calculator dataset(s) need review';
    $lines = [];
    $lines[] = 'The following tax/financial datasets used by your calculators may be out of date:';
    $lines[] = '';
    foreach ($stale as $s) {
        $lines[] = '• ' . $s['label'] . ' — ' . $s['reason'];
        $lines[] = '   Official source: ' . $s['source'];
    }
    $lines[] = '';
    $lines[] = 'Review & update here: ' . $admin_url;
    $lines[] = '';
    $lines[] = 'You are receiving this because data-freshness reminders are enabled in the All Calculators Hub.';

    wp_mail($to, $subject, implode("\n", $lines));
    update_option('usac_data_reminder_last_sent', time());
}

// ============================================================
// COMBINED DASHBOARD
// ============================================================

function usac_render_hub_dashboard() {
    global $wpdb;

    $usc_leads = intval($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}usc_leads"));
    $ust_leads = intval($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ust_leads"));
    $total_leads = $usc_leads + $ust_leads;

    $usc_usage = intval($wpdb->get_var("SELECT SUM(count) FROM {$wpdb->prefix}usc_usage_stats"));
    $ust_usage = intval($wpdb->get_var("SELECT SUM(count) FROM {$wpdb->prefix}ust_usage_stats"));
    $total_usage = $usc_usage + $ust_usage;

    $usc_count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s AND post_status='publish'", USC_CPT)));
    $ust_count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=%s AND post_status='publish'", UST_CPT)));
    $total_calcs = $usc_count + $ust_count;
    ?>
    <div class="usc-admin-wrap">

        <!-- Combined Banner -->
        <div class="usc-dashboard-banner-new">
            <div class="usc-banner-left">
                <div class="usc-banner-title-row">
                    <span class="usc-banner-icon">&#127482;&#127480;</span>
                    <h1>USA State All-in-One Calculators Hub</h1>
                </div>
                <div class="usc-banner-status-checks">
                    <span>Paycheck <span class="usc-chk-icon">&#10003;</span></span>
                    <span class="usc-divider">|</span>
                    <span>Tax <span class="usc-chk-icon">&#10003;</span></span>
                    <span class="usc-divider">|</span>
                    <span>Mortgage <span class="usc-chk-icon">&#10003;</span></span>
                    <span class="usc-divider">|</span>
                    <span>SEO Schema <span class="usc-chk-icon">&#10003;</span></span>
                </div>
            </div>
            <div class="usc-banner-right">
                <span class="usc-banner-tool-badge"><?php echo $total_calcs; ?> Calculators</span>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?action=usc_sync_pages'), 'usc_sync_pages_nonce')); ?>" class="usc-banner-btn-white" onclick="return confirm('Sync all Paycheck/Alimony/Mortgage pages?');">
                    <span class="dashicons dashicons-update"></span> Sync Paycheck
                </a>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?action=ust_sync_pages'), 'ust_sync_pages_nonce')); ?>" class="usc-banner-btn-purple" onclick="return confirm('Sync all Tax pages?');">
                    <span class="dashicons dashicons-update"></span> Sync Tax
                </a>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?action=usac_force_reset_templates'), 'usac_force_reset_nonce')); ?>" style="background:#dc2626;color:#fff;border-radius:8px;padding:8px 14px;font-size:12px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:6px;" onclick="return confirm('WARNING: This will reset ALL calculator templates. Proceed?');">
                    <span class="dashicons dashicons-trash"></span> Force Reset All
                </a>
            </div>
        </div>

        <style>
        .usac-hub-wrap{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
        .usac-stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin:24px 0}
        .usac-stat-card{background:#fff;border-radius:16px;padding:24px 20px;box-shadow:0 2px 12px rgba(0,0,0,.07);border:1px solid #e5e7eb;display:flex;flex-direction:column;gap:8px;transition:transform .2s,box-shadow .2s}
        .usac-stat-card:hover{transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.12)}
        .usac-stat-icon{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:800;margin-bottom:4px;border:none}
        .usac-stat-icon.blue{background:#eff6ff;color:#2563eb}
        .usac-stat-icon.green{background:#f0fdf4;color:#16a34a}
        .usac-stat-icon.amber{background:#fffbeb;color:#d97706}
        .usac-stat-icon.purple{background:#f5f3ff;color:#7c3aed}
        .usac-stat-val{font-size:32px;font-weight:800;color:#111827;line-height:1}
        .usac-stat-lbl{font-size:11px;font-weight:700;color:#6b7280;letter-spacing:1px;text-transform:uppercase}
        .usac-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin:20px 0}
        .usac-card{background:#fff;border-radius:14px;border:1px solid #e5e7eb;box-shadow:0 1px 6px rgba(0,0,0,.06);overflow:hidden}
        .usac-card-head{padding:16px 20px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;gap:10px}
        .usac-card-head h3{margin:0;font-size:15px;font-weight:700;color:#111827}
        .usac-card-body{padding:18px 20px}
        .usac-badge-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:#22c55e}
        .usac-calc-table{width:100%;border-collapse:collapse}
        .usac-calc-table th{padding:10px 12px;text-align:left;font-size:11px;font-weight:700;color:#6b7280;letter-spacing:.7px;text-transform:uppercase;background:#f9fafb;border-bottom:1px solid #e5e7eb}
        .usac-calc-table td{padding:12px;font-size:13px;color:#374151;border-bottom:1px solid #f3f4f6}
        .usac-calc-table tr:last-child td{border-bottom:none}
        .usac-calc-table tr:hover td{background:#f9fafb}
        .usac-pill{display:inline-block;padding:3px 10px;border-radius:99px;font-size:10.5px;font-weight:700;letter-spacing:.5px}
        .usac-pill.paycheck{background:#dbeafe;color:#1d4ed8}
        .usac-pill.cs{background:#d1fae5;color:#065f46}
        .usac-pill.alimony{background:#ede9fe;color:#5b21b6}
        .usac-pill.mortgage{background:#fef3c7;color:#92400e}
        .usac-pill.income-tax{background:#fce7f3;color:#9d174d}
        .usac-pill.property{background:#e0f2fe;color:#0c4a6e}
        .usac-pill.sales{background:#fff7ed;color:#9a3412}
        .usac-link-group{display:flex;flex-wrap:wrap;gap:8px;margin-top:4px}
        .usac-link-chip{padding:7px 14px;border-radius:8px;font-size:12.5px;font-weight:600;background:#f3f4f6;color:#374151;text-decoration:none;border:1px solid #e5e7eb;transition:background .15s,color .15s}
        .usac-link-chip:hover{background:#2563eb;color:#fff;border-color:#2563eb}
        .usac-link-chip.primary{background:#2563eb;color:#fff;border-color:#2563eb}
        .usac-link-chip.primary:hover{background:#1d4ed8}
        </style>

        <div class="usac-hub-wrap">

        <div class="usac-stat-grid">
            <div class="usac-stat-card">
                <div class="usac-stat-icon blue">US</div>
                <div class="usac-stat-val">50</div>
                <div class="usac-stat-lbl">Active States</div>
            </div>
            <div class="usac-stat-card">
                <div class="usac-stat-icon green">#</div>
                <div class="usac-stat-val"><?php echo $total_calcs; ?></div>
                <div class="usac-stat-lbl">Total Calculators</div>
            </div>
            <div class="usac-stat-card">
                <div class="usac-stat-icon amber">@</div>
                <div class="usac-stat-val"><?php echo number_format($total_leads); ?></div>
                <div class="usac-stat-lbl">Captured Leads</div>
            </div>
            <div class="usac-stat-card">
                <div class="usac-stat-icon purple">&#9654;</div>
                <div class="usac-stat-val"><?php echo number_format($total_usage); ?></div>
                <div class="usac-stat-lbl">Total Calc Runs</div>
            </div>
        </div>

        <div class="usac-grid-2">
            <div class="usac-card">
                <div class="usac-card-head">
                    <span class="usac-badge-dot"></span>
                    <h3>Calculator Type Summary</h3>
                </div>
                <div class="usac-card-body" style="padding:0;">
                    <table class="usac-calc-table">
                        <thead><tr><th>Type</th><th>Category</th><th>Pages</th><th>Leads</th><th>Runs</th></tr></thead>
                        <tbody>
                            <tr><td><strong>Paycheck</strong></td><td><span class="usac-pill paycheck">PAYCHECK</span></td><td><?php echo $usc_count > 0 ? '50' : '0'; ?>/50</td><td><?php echo $usc_leads; ?></td><td><?php echo number_format($usc_usage); ?></td></tr>
                            <tr><td><strong>Child Support</strong></td><td><span class="usac-pill cs">CHILD SUPPORT</span></td><td>50/50</td><td>&mdash;</td><td>&mdash;</td></tr>
                            <tr><td><strong>Alimony</strong></td><td><span class="usac-pill alimony">ALIMONY</span></td><td>50/50</td><td>&mdash;</td><td>&mdash;</td></tr>
                            <tr><td><strong>Mortgage</strong></td><td><span class="usac-pill mortgage">MORTGAGE</span></td><td>50/50</td><td>&mdash;</td><td>&mdash;</td></tr>
                            <tr><td><strong>Income Tax</strong></td><td><span class="usac-pill income-tax">INCOME TAX</span></td><td><?php echo $ust_count > 0 ? '50' : '0'; ?>/50</td><td><?php echo $ust_leads; ?></td><td><?php echo number_format($ust_usage); ?></td></tr>
                            <tr><td><strong>Property Tax</strong></td><td><span class="usac-pill property">PROPERTY TAX</span></td><td>50/50</td><td>&mdash;</td><td>&mdash;</td></tr>
                            <tr><td><strong>Sales Tax</strong></td><td><span class="usac-pill sales">SALES TAX</span></td><td>50/50</td><td>&mdash;</td><td>&mdash;</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:16px;">
                <div class="usac-card">
                    <div class="usac-card-head"><h3>Paycheck &amp; Legal Calculators</h3></div>
                    <div class="usac-card-body">
                        <p style="font-size:13px;color:#6b7280;margin:0 0 14px;">Manage paycheck, child support, alimony, and mortgage calculators for all 50 states.</p>
                        <div class="usac-link-group">
                            <a href="<?php echo admin_url('edit.php?post_type=' . USC_CPT . '&calc_type=paycheck'); ?>" class="usac-link-chip primary">Paycheck</a>
                            <a href="<?php echo admin_url('edit.php?post_type=' . USC_CPT . '&calc_type=child-support'); ?>" class="usac-link-chip">Child Support</a>
                            <a href="<?php echo admin_url('edit.php?post_type=' . USC_CPT . '&calc_type=alimony'); ?>" class="usac-link-chip">Alimony</a>
                            <a href="<?php echo admin_url('edit.php?post_type=' . USC_CPT . '&calc_type=mortgage'); ?>" class="usac-link-chip">Mortgage</a>
                        </div>
                    </div>
                </div>
                <div class="usac-card">
                    <div class="usac-card-head"><h3>Tax Calculators</h3></div>
                    <div class="usac-card-body">
                        <p style="font-size:13px;color:#6b7280;margin:0 0 14px;">Manage income tax, property tax, sales tax, and other tax calculators for all 50 states.</p>
                        <div class="usac-link-group">
                            <a href="<?php echo admin_url('edit.php?post_type=' . UST_CPT . '&calc_type=income-tax'); ?>" class="usac-link-chip primary">Income Tax</a>
                            <a href="<?php echo admin_url('edit.php?post_type=' . UST_CPT . '&calc_type=property-tax'); ?>" class="usac-link-chip">Property Tax</a>
                            <a href="<?php echo admin_url('edit.php?post_type=' . UST_CPT . '&calc_type=sales-tax'); ?>" class="usac-link-chip">Sales Tax</a>
                            <a href="<?php echo admin_url('edit.php?post_type=' . UST_CPT . '&calc_type=other'); ?>" class="usac-link-chip">Other Tax</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        </div>
    </div>
    <?php
}

// ============================================================
// LEADS PAGE — USC
// ============================================================

function usc_render_leads_page() {
    global $wpdb;
    $leads = $wpdb->get_results("SELECT l.*, p.post_title FROM {$wpdb->prefix}usc_leads l LEFT JOIN {$wpdb->posts} p ON l.post_id = p.ID ORDER BY l.created_at DESC", ARRAY_A);
    ?>
    <div class="usc-admin-wrap">
        <div class="usc-panel">
            <div class="usc-panel-header" style="border-bottom: none; padding-bottom: 0;">
                <div>
                    <h2 style="font-size:22px; margin-bottom: 5px;">Paycheck/Legal — Captured Leads Registry</h2>
                    <p style="margin: 0; color: var(--usc-text-muted); font-size:13px;">Users who requested estimate reports before printing paycheck/child support/alimony details.</p>
                </div>
                <div>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?action=usc_export_leads'), 'usc_export_leads_nonce')); ?>" class="usc-btn usc-btn-primary">
                        <span class="dashicons dashicons-download"></span> Export to CSV
                    </a>
                </div>
            </div>
            <div class="usc-panel-content" style="padding: 24px 0 0 0;">
                <table class="usc-custom-table" style="border-top: 1px solid var(--usc-border);">
                    <thead><tr><th style="width: 70px; text-align: center;">ID</th><th>Calculator State</th><th>User Name</th><th>User Email</th><th>Submitted On</th></tr></thead>
                    <tbody>
                        <?php if (empty($leads)) : ?>
                            <tr><td colspan="5" style="text-align: center; padding: 30px; color: var(--usc-text-muted);">No leads captured yet.</td></tr>
                        <?php else : ?>
                            <?php foreach ($leads as $lead) : ?>
                                <tr>
                                    <td style="text-align: center; color: var(--usc-text-muted); font-weight: 600;"><?php echo esc_html($lead['id']); ?></td>
                                    <td><strong><?php echo esc_html($lead['post_title'] ?: 'State Calculator'); ?></strong></td>
                                    <td><?php echo esc_html($lead['name']); ?></td>
                                    <td><a href="mailto:<?php echo esc_attr($lead['email']); ?>"><?php echo esc_html($lead['email']); ?></a></td>
                                    <td><?php echo esc_html($lead['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}

// ============================================================
// LEADS PAGE — UST
// ============================================================

function ust_render_leads_page() {
    global $wpdb;
    $leads = $wpdb->get_results("SELECT l.*, p.post_title FROM {$wpdb->prefix}ust_leads l LEFT JOIN {$wpdb->posts} p ON l.post_id = p.ID ORDER BY l.created_at DESC", ARRAY_A);
    ?>
    <div class="ust-admin-wrap">
        <div class="ust-panel">
            <div class="ust-panel-header" style="border-bottom: none; padding-bottom: 0;">
                <div>
                    <h2 style="font-size:22px; margin-bottom: 5px;">Tax Calculators — Captured Leads Registry</h2>
                    <p style="margin: 0; color: var(--ust-text-muted); font-size:13px;">Users who requested estimate reports before printing or downloading tax calculation details.</p>
                </div>
                <div>
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?action=ust_export_leads'), 'ust_export_leads_nonce')); ?>" class="ust-btn ust-btn-primary">
                        <span class="dashicons dashicons-download"></span> Export to CSV
                    </a>
                </div>
            </div>
            <div class="ust-panel-content" style="padding: 24px 0 0 0;">
                <table class="ust-custom-table" style="border-top: 1px solid var(--ust-border);">
                    <thead><tr><th style="width: 70px; text-align: center;">ID</th><th>Calculator State</th><th>User Name</th><th>User Email</th><th>Submitted On</th></tr></thead>
                    <tbody>
                        <?php if (empty($leads)) : ?>
                            <tr><td colspan="5" style="text-align: center; padding: 30px; color: var(--ust-text-muted);">No leads captured yet.</td></tr>
                        <?php else : ?>
                            <?php foreach ($leads as $lead) : ?>
                                <tr>
                                    <td style="text-align: center; color: var(--ust-text-muted); font-weight: 600;"><?php echo esc_html($lead['id']); ?></td>
                                    <td><strong><?php echo esc_html($lead['post_title'] ?: 'State Calculator'); ?></strong></td>
                                    <td><?php echo esc_html($lead['name']); ?></td>
                                    <td><a href="mailto:<?php echo esc_attr($lead['email']); ?>"><?php echo esc_html($lead['email']); ?></a></td>
                                    <td><?php echo esc_html($lead['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}

// ============================================================
// USAGE ANALYTICS — USC
// ============================================================

function usc_render_usage_page() {
    global $wpdb;
    $usage = $wpdb->get_results("SELECT u.*, p.post_title FROM {$wpdb->prefix}usc_usage_stats u LEFT JOIN {$wpdb->posts} p ON u.post_id = p.ID ORDER BY u.count DESC", ARRAY_A);
    ?>
    <div class="usc-admin-wrap">
        <div class="usc-panel">
            <div class="usc-panel-header" style="border-bottom: none; padding-bottom: 0;">
                <div>
                    <h2 style="font-size:22px; margin-bottom: 5px;">Paycheck/Legal — Usage Analytics</h2>
                    <p style="margin: 0; color: var(--usc-text-muted); font-size:13px;">Total calculations triggered per paycheck/child support/alimony/mortgage calculator.</p>
                </div>
            </div>
            <div class="usc-panel-content" style="padding: 24px 0 0 0;">
                <table class="usc-custom-table" style="border-top: 1px solid var(--usc-border);">
                    <thead><tr><th>State Calculator</th><th>Total Run Count</th><th>Last Run Date</th><th style="width: 100px; text-align: center;">Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($usage)) : ?>
                            <tr><td colspan="4" style="text-align: center; padding: 30px; color: var(--usc-text-muted);">No logs recorded yet.</td></tr>
                        <?php else : ?>
                            <?php foreach ($usage as $use) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html($use['post_title'] ?: 'State Calculator'); ?></strong></td>
                                    <td><span class="usc-badge-run"><?php echo esc_html($use['count']); ?> calculations</span></td>
                                    <td style="color: var(--usc-text-muted);"><?php echo esc_html($use['last_used']); ?></td>
                                    <td style="text-align: center;"><a href="<?php echo esc_url(get_edit_post_link($use['post_id'])); ?>" class="usc-btn usc-btn-white" style="padding: 4px 8px; font-size:12px;">Edit Settings</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}

// ============================================================
// USAGE ANALYTICS — UST
// ============================================================

function ust_render_usage_page() {
    global $wpdb;
    $usage = $wpdb->get_results("SELECT u.*, p.post_title FROM {$wpdb->prefix}ust_usage_stats u LEFT JOIN {$wpdb->posts} p ON u.post_id = p.ID ORDER BY u.count DESC", ARRAY_A);
    ?>
    <div class="ust-admin-wrap">
        <div class="ust-panel">
            <div class="ust-panel-header" style="border-bottom: none; padding-bottom: 0;">
                <div>
                    <h2 style="font-size:22px; margin-bottom: 5px;">Tax Calculators — Usage Analytics</h2>
                    <p style="margin: 0; color: var(--ust-text-muted); font-size:13px;">Total calculations triggered per state tax calculator page.</p>
                </div>
            </div>
            <div class="ust-panel-content" style="padding: 24px 0 0 0;">
                <table class="ust-custom-table" style="border-top: 1px solid var(--ust-border);">
                    <thead><tr><th>State Calculator</th><th>Total Run Count</th><th>Last Run Date</th><th style="width: 100px; text-align: center;">Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($usage)) : ?>
                            <tr><td colspan="4" style="text-align: center; padding: 30px; color: var(--ust-text-muted);">No logs recorded yet.</td></tr>
                        <?php else : ?>
                            <?php foreach ($usage as $use) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html($use['post_title'] ?: 'State Calculator'); ?></strong></td>
                                    <td><span class="ust-badge-run"><?php echo esc_html($use['count']); ?> calculations</span></td>
                                    <td style="color: var(--ust-text-muted);"><?php echo esc_html($use['last_used']); ?></td>
                                    <td style="text-align: center;"><a href="<?php echo esc_url(get_edit_post_link($use['post_id'])); ?>" class="ust-btn ust-btn-white" style="padding: 4px 8px; font-size:12px;">Edit Settings</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}

// ============================================================
// LIVE SEARCH OVERRIDE — Both CPTs
// ============================================================

add_action('init', 'usac_override_theme_live_search', 20);
function usac_override_theme_live_search() {
    remove_action('wp_ajax_fxtool_live_search', 'fxtool_ajax_live_search');
    remove_action('wp_ajax_nopriv_fxtool_live_search', 'fxtool_ajax_live_search');
    add_action('wp_ajax_fxtool_live_search', 'usac_custom_ajax_live_search');
    add_action('wp_ajax_nopriv_fxtool_live_search', 'usac_custom_ajax_live_search');
}

function usac_custom_ajax_live_search() {
    check_ajax_referer('fxtool_search', 'nonce');
    $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
    if (strlen($q) < 2) { wp_send_json_success(['items' => []]); }
    $out = [];

    // Search USC CPT
    $calc_query = new WP_Query(['post_type' => USC_CPT, 'posts_per_page' => 6, 'post_status' => 'publish', 's' => $q]);
    if ($calc_query->have_posts()) {
        while ($calc_query->have_posts()) {
            $calc_query->the_post();
            $pid       = get_the_ID();
            $calc_type = get_post_meta($pid, '_usc_calc_type', true);
            $icon      = $calc_type === 'paycheck' ? '💵' : ($calc_type === 'child-support' ? '👪' : ($calc_type === 'alimony' ? '⚖️' : '🏠'));
            $out[]     = ['name' => get_the_title(), 'url' => get_permalink(), 'desc' => wp_trim_words(get_the_excerpt(), 10, '…'), 'cat' => $calc_type, 'icon' => $icon];
        }
        wp_reset_postdata();
    }

    // Search UST CPT
    if (count($out) < 10) {
        $tax_query = new WP_Query(['post_type' => UST_CPT, 'posts_per_page' => 10 - count($out), 'post_status' => 'publish', 's' => $q]);
        if ($tax_query->have_posts()) {
            while ($tax_query->have_posts()) {
                $tax_query->the_post();
                $pid       = get_the_ID();
                $calc_type = get_post_meta($pid, '_ust_calc_type', true);
                $icon      = $calc_type === 'income-tax' ? '💵' : ($calc_type === 'property-tax' ? '🏠' : ($calc_type === 'sales-tax' ? '🛒' : '⚙️'));
                $out[]     = ['name' => get_the_title(), 'url' => get_permalink(), 'desc' => wp_trim_words(get_the_excerpt(), 10, '…'), 'cat' => $calc_type, 'icon' => $icon];
            }
            wp_reset_postdata();
        }
    }

    // Theme directory fallback
    if (count($out) < 10 && function_exists('fxtool_get_directory_items')) {
        $q_lower = strtolower($q);
        foreach (fxtool_get_directory_items() as $item) {
            if (false !== strpos(strtolower($item['name']), $q_lower)) {
                $exists = false;
                foreach ($out as $o) { if ($o['url'] === $item['url']) { $exists = true; break; } }
                if (!$exists) $out[] = ['name' => $item['name'], 'url' => $item['url'], 'desc' => $item['desc'] ? wp_trim_words($item['desc'], 10, '…') : '', 'cat' => $item['category'], 'icon' => $item['icon'] ?? '🔢'];
            }
            if (count($out) >= 10) break;
        }
    }
    wp_send_json_success(['items' => $out]);
}

// ============================================================
// ILLUSTRATION ATTACHMENT HELPERS
// ============================================================

function usc_get_or_create_illustration_attachment($type) {
    $option_name   = 'usc_attachment_id_' . $type;
    $attachment_id = get_option($option_name);
    if ($attachment_id && wp_get_attachment_url($attachment_id)) return (int) $attachment_id;
    $filename  = ($type === 'paycheck') ? 'paycheck-illustration.png' : 'child-support-illustration.png';
    $file_path = USC_PATH . 'public/assets/images/' . $filename;
    if (!file_exists($file_path)) return 0;
    $wp_upload_dir = wp_upload_dir();
    if (!empty($wp_upload_dir['error'])) return 0;
    $target_path = $wp_upload_dir['path'] . '/' . $filename;
    if (!copy($file_path, $target_path)) return 0;
    $filetype   = wp_check_filetype($filename, null);
    $attachment = ['guid' => $wp_upload_dir['url'] . '/' . basename($filename), 'post_mime_type' => $filetype['type'], 'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)), 'post_content' => '', 'post_status' => 'inherit'];
    $attach_id  = wp_insert_attachment($attachment, $target_path);
    if (is_wp_error($attach_id)) return 0;
    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $target_path));
    update_option($option_name, $attach_id);
    return (int) $attach_id;
}

function ust_get_or_create_illustration_attachment($type) {
    $option_name   = 'ust_attachment_id_' . $type;
    $attachment_id = get_option($option_name);
    if ($attachment_id && wp_get_attachment_url($attachment_id)) return (int) $attachment_id;
    $filename  = ($type === 'income-tax' || $type === 'sales-tax') ? 'income-tax-illustration.png' : 'property-tax-illustration.png';
    $file_path = UST_PATH . 'public/assets/images/' . $filename;
    if (!file_exists($file_path)) return 0;
    $wp_upload_dir = wp_upload_dir();
    if (!empty($wp_upload_dir['error'])) return 0;
    $target_path = $wp_upload_dir['path'] . '/' . $filename;
    if (!copy($file_path, $target_path)) return 0;
    $filetype   = wp_check_filetype($filename, null);
    $attachment = ['guid' => $wp_upload_dir['url'] . '/' . basename($filename), 'post_mime_type' => $filetype['type'], 'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)), 'post_content' => '', 'post_status' => 'inherit'];
    $attach_id  = wp_insert_attachment($attachment, $target_path);
    if (is_wp_error($attach_id)) return 0;
    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $target_path));
    update_option($option_name, $attach_id);
    return (int) $attach_id;
}


