<?php
/**
 * Plugin Name: Chahriyti Live Demo Page
 * Description: Serves the Chahriyti app demo and the order form at /demo. Orders are created by the "Chahriyti Book Landing Orders" plugin, which must stay active.
 * Version: 1.0
 * Author: Chahriyti
 */

defined('ABSPATH') || exit;

// ─── /demo route ───
// Matched on the request path, so it needs no WordPress page and no
// permalink flush. demo.html is generated from the landing page by
// build-demo.mjs.
add_action('parse_request', function ($wp) {
    if (is_admin() || trim($wp->request, '/') !== 'demo') {
        return;
    }
    chahriyti_demo_render();
    exit;
});

function chahriyti_demo_render() {
    $html = file_get_contents(plugin_dir_path(__FILE__) . 'demo.html');
    if ($html === false) {
        wp_die('demo.html غير موجود في مجلد البلاقن');
    }

    $url = plugin_dir_url(__FILE__);
    $html = strtr($html, [
        '"../CHAHRIYTI png.png"'                => '"' . $url . 'CHAHRIYTI png.png"',
        "'./algeria_cities.csv'"                => "'" . $url . "algeria_cities.csv'",
        "'./Tarifs_Wilayas_Domicile_Bureau.json'" => "'" . $url . "Tarifs_Wilayas_Domicile_Bureau.json'",
        'href="favicon.png"'                    => 'href="' . $url . 'favicon.png"',
        'href="apple-touch-icon.png"'           => 'href="' . $url . 'apple-touch-icon.png"',
    ]);

    // Same AJAX config the landing page uses: the order goes to chahriyti_create_order
    $head = sprintf(
        "<script>window.chahriyti_ajax = { url: '%s', nonce: '%s' };</script>\n</head>",
        esc_url(admin_url('admin-ajax.php')),
        wp_create_nonce('chahriyti_order_nonce')
    );
    $html = preg_replace('/<\/head>/i', $head, $html, 1);

    // The nonce is in the page: a cached copy would stop accepting orders
    nocache_headers();
    status_header(200);
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
}

// Orders can't be placed without the orders plugin
add_action('admin_notices', function () {
    if (current_user_can('activate_plugins') && !function_exists('chahriyti_create_order')) {
        echo '<div class="notice notice-error"><p>صفحة /demo تحتاج البلاقن <strong>Chahriyti Book Landing Orders</strong> مفعّل باش تستقبل الطلبات.</p></div>';
    }
});
