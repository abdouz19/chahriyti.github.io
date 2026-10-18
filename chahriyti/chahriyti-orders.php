<?php
/**
 * Plugin Name: Chahriyti Book Landing Orders
 * Description: Handles order submissions from the Chahriyti book landing page via WooCommerce
 * Version: 1.0
 * Author: Chahriyti
 */

defined('ABSPATH') || exit;

// ─── Register page template from this plugin ───
// Works with both classic and block themes
add_filter('theme_page_templates', function ($templates, $theme, $post) {
    $templates['chahriyti-landing'] = 'Chahriyti Book Landing Page';
    return $templates;
}, 10, 3);

// Also register for block themes via get_block_templates
add_filter('theme_page_templates', function ($templates) {
    $templates['chahriyti-landing'] = 'Chahriyti Book Landing Page';
    return $templates;
});

add_filter('template_include', function ($template) {
    if (get_page_template_slug() === 'chahriyti-landing') {
        $plugin_template = plugin_dir_path(__FILE__) . 'page-chahriyti-landing.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
});

// ─── AJAX handlers (logged-in + guests) ───
add_action('wp_ajax_chahriyti_create_order',        'chahriyti_create_order');
add_action('wp_ajax_nopriv_chahriyti_create_order', 'chahriyti_create_order');

function chahriyti_create_order() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'chahriyti_order_nonce')) {
        wp_send_json_error(['message' => 'Security check failed']);
        return;
    }

    // WooCommerce check
    if (!class_exists('WooCommerce')) {
        wp_send_json_error(['message' => 'WooCommerce غير مفعّل']);
        return;
    }

    // Sanitize inputs
    $name          = sanitize_text_field($_POST['fullName']      ?? '');
    $phone         = sanitize_text_field($_POST['phone']         ?? '');
    $wilaya        = sanitize_text_field($_POST['wilaya']        ?? '');
    $commune       = sanitize_text_field($_POST['commune']       ?? '');
    $delivery_type = sanitize_text_field($_POST['delivery_type'] ?? '');
    $delivery_fee  = absint($_POST['delivery_fee'] ?? 0);

    // Validate required
    if (empty($name) || empty($phone) || empty($wilaya) || empty($commune) || empty($delivery_type)) {
        wp_send_json_error(['message' => 'عمّر كل الخانات المطلوبة']);
        return;
    }

    // Algerian phone format
    if (!preg_match('/^0[5-7][0-9]{8}$/', $phone)) {
        wp_send_json_error(['message' => 'رقم الهاتف غير صحيح (مثال: 0700000000)']);
        return;
    }

    // ─── Product ───
    // Product ID 12 — Chahriyati book/app
    $product_id = 12;
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

    // Add product (simple product)
    $item = new WC_Order_Item_Product();
    $item->set_product($product);
    $item->set_quantity(1);
    $item->set_subtotal($product->get_price());
    $item->set_total($product->get_price());
    $order->add_item($item);

    // Add shipping fee
    if ($delivery_fee > 0) {
        $shipping = new WC_Order_Item_Shipping();
        $delivery_label = ($delivery_type === 'bureau') ? 'توصيل للمكتب' : 'توصيل للبيت';
        $shipping->set_method_title($delivery_label);
        $shipping->set_total($delivery_fee);
        $order->add_item($shipping);
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
    $delivery_label = ($delivery_type === 'bureau') ? 'للمكتب (Bureau)' : 'للبيت (Domicile)';
    $total_price    = intval($product->get_price()) + $delivery_fee;

    $note_text = sprintf(
        "📖 طلب من صفحة كتاب شهريتي Landing Page\n" .
        "المنتج: كتاب شهريتي + كود تفعيل التطبيق\n" .
        "الهاتف: %s\n" .
        "العنوان: %s — %s\n" .
        "التوصيل: %s — %d ج.د\n" .
        "الإجمالي: %d ج.د",
        $phone, $commune, $wilaya,
        $delivery_label, $delivery_fee, $total_price
    );
    $order->add_order_note($note_text);

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
