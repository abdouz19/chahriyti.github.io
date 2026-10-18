<?php
/**
 * Plugin Name: SAHARA Landing Orders
 * Description: Handles order submissions from the SAHARA landing page
 * Version: 1.0
 * Author: Bled Mood
 */

defined('ABSPATH') || exit;

// ─── Register the page template from this plugin ───
add_filter('theme_page_templates', function ($templates) {
    $templates['sahara-landing'] = 'SAHARA Landing Page';
    return $templates;
});

add_filter('template_include', function ($template) {
    if (get_page_template_slug() === 'sahara-landing') {
        $plugin_template = plugin_dir_path(__FILE__) . 'page-sahara-landing.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
});

// ─── AJAX handler (logged-in and guest users) ───
add_action('wp_ajax_sahara_create_order', 'sahara_create_order');
add_action('wp_ajax_nopriv_sahara_create_order', 'sahara_create_order');

function sahara_create_order() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'sahara_order_nonce')) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }

    // Check WooCommerce is active
    if (!class_exists('WooCommerce')) {
        wp_send_json_error(['message' => 'WooCommerce is not active']);
        return;
    }

    // Sanitize inputs
    $color    = sanitize_text_field($_POST['color'] ?? '');
    $size     = sanitize_text_field($_POST['size'] ?? '');
    $name     = sanitize_text_field($_POST['fullName'] ?? '');
    $phone    = sanitize_text_field($_POST['phone'] ?? '');
    $wilaya   = sanitize_text_field($_POST['wilaya'] ?? '');
    $commune  = sanitize_text_field($_POST['commune'] ?? '');
    $quantity = absint($_POST['quantity'] ?? 1);

    // Validate required fields
    if (empty($color) || empty($size) || empty($name) || empty($phone) || empty($wilaya) || empty($commune)) {
        wp_send_json_error(['message' => 'عمّر كل الخانات المطلوبة']);
        return;
    }

    // Validate phone format (Algerian)
    if (!preg_match('/^0[5-7][0-9]{8}$/', $phone)) {
        wp_send_json_error(['message' => 'رقم الهاتف غير صحيح']);
        return;
    }

    if ($quantity < 1) $quantity = 1;
    if ($quantity > 10) $quantity = 10;

    // ─── Product config ───
    $product_id = 6009; // SAHARA T-SHIRT BLED
    $product = wc_get_product($product_id);

    if (!$product) {
        wp_send_json_error(['message' => 'المنتج غير موجود']);
        return;
    }

    // ─── Create WooCommerce order ───
    $order = wc_create_order();

    if (is_wp_error($order)) {
        wp_send_json_error(['message' => 'خطأ في إنشاء الطلب']);
        return;
    }

    // Try to find matching variation
    $variation_id = 0;
    if ($product->is_type('variable')) {
        $variation_id = sahara_find_variation($product, $color, $size);
    }

    if ($variation_id) {
        // Add the exact variation
        $order->add_product(wc_get_product($variation_id), $quantity);
    } else {
        // Add parent product + custom meta for color/size
        $item_id = $order->add_product($product, $quantity);
        if ($item_id) {
            wc_add_order_item_meta($item_id, 'اللون', $color);
            wc_add_order_item_meta($item_id, 'المقاس', $size);
        }
    }

    // ─── Set customer details ───
    $name_parts = explode(' ', $name, 2);
    $first_name = $name_parts[0];
    $last_name  = isset($name_parts[1]) ? $name_parts[1] : '';

    // Billing
    $order->set_billing_first_name($first_name);
    $order->set_billing_last_name($last_name);
    $order->set_billing_phone($phone);
    $order->set_billing_country('DZ');
    $order->set_billing_state($wilaya);
    $order->set_billing_city($commune);

    // Shipping (same as billing)
    $order->set_shipping_first_name($first_name);
    $order->set_shipping_last_name($last_name);
    $order->set_shipping_country('DZ');
    $order->set_shipping_state($wilaya);
    $order->set_shipping_city($commune);

    // Payment method: Cash on Delivery
    $order->set_payment_method('cod');
    $order->set_payment_method_title('الدفع عند الاستلام');

    // Order note with details
    $order->add_order_note(sprintf(
        "🏷️ طلب من صفحة SAHARA Landing Page\n" .
        "اللون: %s\n" .
        "المقاس: %s\n" .
        "الكمية: %d\n" .
        "الهاتف: %s\n" .
        "العنوان: %s، %s",
        $color, $size, $quantity, $phone, $commune, $wilaya
    ));

    // Set status to on-hold (awaiting confirmation)
    $order->set_status('processing');

    // Calculate totals and save
    $order->calculate_totals();
    $order->save();

    wp_send_json_success([
        'order_id'     => $order->get_id(),
        'order_number' => $order->get_order_number(),
        'total'        => $order->get_total(),
        'message'      => 'تم تسجيل طلبك بنجاح'
    ]);
}

/**
 * Try to find a matching product variation by color and size.
 * Adjust the attribute mapping below to match your WooCommerce attribute slugs/values.
 */
function sahara_find_variation($product, $color_ar, $size) {
    // ─── Map Arabic color names to your WooCommerce attribute values ───
    // Check your product edit page for the exact attribute slug and values.
    // Example: if your color attribute slug is "pa_color" with values "sand", "black", etc.
    $color_map = [
        'رمال الصحراء' => 'beige',
        'أسود الليل'   => 'black',
        'أبيض الأطلس'  => 'white',
        'زيتوني جرجرة' => 'green',
    ];

    // ─── Map sizes to your WooCommerce attribute values ───
    // Example: if your size attribute slug is "pa_taille" with values "s", "m", "l", etc.
    // Taille values from WooCommerce look like: "Taille 1 | 55KG-80KG | S-L"
    // Map the simple size label from the landing page to the full taille slug
    $size_map = [
        'S'   => 'taille-1-55kg-80kg-s-l',
        'M'   => 'taille-2-75kg-100kg-l-xl',
        'L'   => 'taille-2-75kg-100kg-l-xl',
        'XL'  => 'taille-3-95kg-120kg-xl-xxl',
        'XXL' => 'taille-3-95kg-120kg-xl-xxl',
    ];

    $color_val = $color_map[$color_ar] ?? '';
    $size_val  = $size_map[$size] ?? strtolower($size);

    if (empty($color_val)) return 0;

    // ─── Try to match variation ───
    // Adjust attribute slugs to match your product's actual attribute names.
    // You can find these in: WooCommerce → Products → Edit Product → Attributes tab
    $match_attributes = [
        'attribute_pa_color'  => $color_val,  // Change "pa_color" to your color attribute slug
        'attribute_pa_taille' => $size_val,   // Change "pa_taille" to your size attribute slug
    ];

    $data_store = WC_Data_Store::load('product');
    $variation_id = $data_store->find_matching_product_variation(
        $product,
        $match_attributes
    );

    return $variation_id ?: 0;
}
