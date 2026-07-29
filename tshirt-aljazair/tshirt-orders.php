<?php
/**
 * Plugin Name: Tshirt Aljazair Landing Orders
 * Description: Handles order submissions from the Tshirt Aljazair landing page via WooCommerce
 * Version: 1.0
 * Author: Bled Mood
 */

defined('ABSPATH') || exit;

// ─── Register page template from this plugin ───
add_filter('theme_page_templates', function ($templates) {
    $templates['tshirt-aljazair-landing'] = 'Tshirt Aljazair Landing Page';
    return $templates;
});

add_filter('template_include', function ($template) {
    if (get_page_template_slug() === 'tshirt-aljazair-landing') {
        $plugin_template = plugin_dir_path(__FILE__) . 'page-tshirt-landing.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
});

// ─── AJAX handlers (logged-in + guests) ───
add_action('wp_ajax_tshirt_create_order',        'tshirt_create_order');
add_action('wp_ajax_nopriv_tshirt_create_order', 'tshirt_create_order');

function tshirt_create_order() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'tshirt_order_nonce')) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }

    // WooCommerce check
    if (!class_exists('WooCommerce')) {
        wp_send_json_error(['message' => 'WooCommerce غير مفعّل']);
        return;
    }

    // Sanitize inputs
    $color   = sanitize_text_field($_POST['color']    ?? '');
    $size    = sanitize_text_field($_POST['size']     ?? '');
    $name    = sanitize_text_field($_POST['fullName'] ?? '');
    $phone   = sanitize_text_field($_POST['phone']    ?? '');
    $wilaya  = sanitize_text_field($_POST['wilaya']   ?? '');
    $commune = sanitize_text_field($_POST['commune']  ?? '');

    // Validate required
    if (empty($color) || empty($size) || empty($name) || empty($phone) || empty($wilaya) || empty($commune)) {
        wp_send_json_error(['message' => 'عمّر كل الخانات المطلوبة']);
        return;
    }

    // Algerian phone format
    if (!preg_match('/^0[5-7][0-9]{8}$/', $phone)) {
        wp_send_json_error(['message' => 'رقم الهاتف غير صحيح (مثال: 0700000000)']);
        return;
    }

    // ─── Product ───
    // Product ID 7991 — Tshirt El Djazayer on bledmood.com
    $product_id = 7991;
    $product    = wc_get_product($product_id);

    if (!$product) {
        wp_send_json_error(['message' => 'المنتج غير موجود — تحقق من Product ID']);
        return;
    }

    // ─── Create order ───
    $order = wc_create_order();
    if (is_wp_error($order)) {
        wp_send_json_error(['message' => 'خطأ في إنشاء الطلب']);
        return;
    }

    // Try to find matching variation if product is variable
    $variation_id = 0;
    if ($product->is_type('variable')) {
        $variation_id = tshirt_find_variation($product, $color, $size);
    }

    if ($variation_id) {
        $order->add_product(wc_get_product($variation_id), 1);
    } else {
        // Simple product or variation not found — add with meta
        $item_id = $order->add_product($product, 1);
        if ($item_id) {
            wc_add_order_item_meta($item_id, 'اللون',   $color);
            wc_add_order_item_meta($item_id, 'المقاس',  $size);
        }
    }

    // ─── Customer details ───
    $name_parts = explode(' ', $name, 2);
    $first_name = $name_parts[0];
    $last_name  = $name_parts[1] ?? '';

    $order->set_billing_first_name($first_name);
    $order->set_billing_last_name($last_name);
    $order->set_billing_phone($phone);
    $order->set_billing_country('DZ');
    $order->set_billing_state($wilaya);
    $order->set_billing_city($commune);

    $order->set_shipping_first_name($first_name);
    $order->set_shipping_last_name($last_name);
    $order->set_shipping_country('DZ');
    $order->set_shipping_state($wilaya);
    $order->set_shipping_city($commune);

    // Cash on delivery
    $order->set_payment_method('cod');
    $order->set_payment_method_title('الدفع عند الاستلام');

    // Order note
    $order->add_order_note(sprintf(
        "🇩🇿 طلب من صفحة Tshirt Aljazair Landing Page\n" .
        "اللون: %s\n" .
        "المقاس: %s\n" .
        "الهاتف: %s\n" .
        "العنوان: %s — %s",
        $color, $size, $phone, $commune, $wilaya
    ));

    $order->set_status('processing');
    $order->calculate_totals();
    $order->save();

    wp_send_json_success([
        'order_id'     => $order->get_id(),
        'order_number' => $order->get_order_number(),
        'total'        => $order->get_total(),
        'message'      => 'تم تسجيل طلبك بنجاح',
    ]);
}

/**
 * Find a matching variation by color and size.
 *
 * ─── HOW TO CONFIGURE ───────────────────────────────────────────────────────
 * 1. Go to WooCommerce → Products → Edit Product #7991 → Attributes tab
 * 2. Note the exact attribute SLUG (e.g. pa_color, pa_loun, pa_maqas, etc.)
 * 3. Note the exact attribute VALUES (e.g. "أخضر", "green", "vert")
 * 4. Update $color_map and $size_map + $match_attributes below accordingly
 * ────────────────────────────────────────────────────────────────────────────
 */
function tshirt_find_variation($product, $color_ar, $size_full) {
    // Map Arabic color display names → WooCommerce attribute values
    // Adjust right-hand values to match exactly what's in WC
    $color_map = [
        'أخضر' => 'أخضر',  // or 'green' / 'vert' — check WC attribute value
        'أبيض' => 'أبيض',  // or 'white' / 'blanc'
    ];

    // Map the full size string from the form → WC attribute value/slug
    // The form sends e.g. "Taille 1 l 55KG-80KG l S-L"
    // Check your WC attribute slug for مقاس (likely pa_maqas or pa_size)
    $size_map = [
        'Taille 1 l 55KG-80KG l S-L'     => 'Taille 1 l 55KG-80KG l S-L',
        'Taille 2 l 75KG-100KG l L-XL'   => 'Taille 2 l 75KG-100KG l L-XL',
        'Taille 3 l 95KG-120KG l XL-XXL' => 'Taille 3 l 95KG-120KG l XL-XXL',
    ];

    $color_val = $color_map[$color_ar] ?? $color_ar;
    $size_val  = $size_map[$size_full] ?? $size_full;

    // ─── Attribute slugs — ADJUST to match your WC product attributes ───
    // Common slugs: pa_color, pa_loun, pa_colour | pa_maqas, pa_size, pa_taille
    $match_attributes = [
        'attribute_pa_color' => $color_val,  // ← change if needed
        'attribute_pa_maqas' => $size_val,   // ← change if needed
    ];

    $data_store   = WC_Data_Store::load('product');
    $variation_id = $data_store->find_matching_product_variation($product, $match_attributes);

    return $variation_id ?: 0;
}
