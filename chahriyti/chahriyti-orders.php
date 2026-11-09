<?php
/**
 * Plugin Name: Chahriyti Book Landing Orders
 * Description: Handles order submissions from the Chahriyti book landing page via WooCommerce
 * Version: 1.4
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

    // cod: printed book delivered. card / baridimob: the digital copy (PDF + code)
    $method = sanitize_key($_POST['payment_method'] ?? 'cod');
    if (!in_array($method, ['cod', 'card', 'baridimob'], true)) {
        $method = 'cod';
    }
    $pay_by_card = $method === 'card';
    $digital     = $method !== 'cod';

    // Validate required. A digital order has no address
    if (empty($name) || empty($phone) || (!$digital && (empty($wilaya) || empty($commune) || empty($delivery_type)))) {
        wp_send_json_error(['message' => 'عمّر كل الخانات المطلوبة']);
        return;
    }

    // Algerian phone format
    if (!preg_match('/^0[5-7][0-9]{8}$/', $phone)) {
        wp_send_json_error(['message' => 'رقم الهاتف غير صحيح (مثال: 0700000000)']);
        return;
    }

    // Delivery fee from the tariff file, never from the browser: a card
    // payment charges exactly this amount
    $delivery_fee = $digital ? 0 : chahriyti_delivery_fee($wilaya, $delivery_type);
    if ($delivery_fee === null) {
        wp_send_json_error(['message' => 'التوصيل غير متوفر لهذه الولاية بهذه الطريقة']);
        return;
    }

    if ($pay_by_card && chahriyti_chargily_secret() === '') {
        wp_send_json_error(['message' => 'الدفع بالبطاقة غير متاح حالياً، اختر BaridiMob أو النسخة الورقية']);
        return;
    }

    // ─── Product ───
    // The book product, chosen in Settings → Chahriyti Payments
    $product_id = absint(get_option('chahriyti_product_id', 12));
    $product    = wc_get_product($product_id);

    if (!$product) {
        wp_send_json_error(['message' => 'المنتج غير موجود — تحقق من Product ID في الإعدادات']);
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

    if (!$digital) {
        $order->set_billing_state($wilaya);
        $order->set_billing_city($commune);
        $order->set_shipping_first_name($first_name);
        $order->set_shipping_last_name($last_name);
        $order->set_shipping_country('DZ');
        $order->set_shipping_state($wilaya);
        $order->set_shipping_city($commune);
    }

    if ($pay_by_card) {
        $order->set_payment_method('chargily');
        $order->set_payment_method_title('البطاقة الذهبية / CIB (Chargily) — نسخة PDF');
    } elseif ($method === 'baridimob') {
        $order->set_payment_method('baridimob');
        $order->set_payment_method_title('BaridiMob (واتساب) — نسخة PDF');
    } else {
        $order->set_payment_method('cod');
        $order->set_payment_method_title('الدفع عند الاستلام');
    }

    // Order note
    $total_price = intval($product->get_price()) + $delivery_fee;

    if ($pay_by_card) {
        $note_text = sprintf(
            "📄 طلب نسخة رقمية من صفحة شهريتي\n" .
            "المنتج: كتاب شهريتي PDF + كود تفعيل التطبيق (بدون توصيل)\n" .
            "الهاتف: %s\n" .
            "الإجمالي: %d ج.د — الدفع بالبطاقة (Chargily)\n" .
            "بعد الدفع: يُرسَل PDF والكود تلقائياً على البريد الإلكتروني",
            $phone, $total_price
        );
    } elseif ($method === 'baridimob') {
        $note_text = sprintf(
            "📲 طلب نسخة رقمية — الدفع بـ BaridiMob عبر واتساب\n" .
            "المنتج: كتاب شهريتي PDF + كود تفعيل التطبيق (بدون توصيل)\n" .
            "الهاتف: %s\n" .
            "الإجمالي: %d ج.د — في انتظار التحويل\n" .
            "بعد التأكد من التحويل: ابعث PDF والكود للزبون",
            $phone, $total_price
        );
    } else {
        $delivery_label = ($delivery_type === 'bureau') ? 'للمكتب (Bureau)' : 'للبيت (Domicile)';
        $note_text = sprintf(
            "📖 طلب من صفحة كتاب شهريتي Landing Page\n" .
            "المنتج: كتاب شهريتي + كود تفعيل التطبيق\n" .
            "الهاتف: %s\n" .
            "العنوان: %s — %s\n" .
            "التوصيل: %s — %d ج.د\n" .
            "الإجمالي: %d ج.د — الدفع عند الاستلام",
            $phone, $commune, $wilaya,
            $delivery_label, $delivery_fee, $total_price
        );
    }
    $order->add_order_note($note_text);

    // A card order waits for Chargily's webhook, a BaridiMob one for the
    // transfer to be checked by hand
    $order->set_status(['card' => 'pending', 'baridimob' => 'on-hold', 'cod' => 'processing'][$method]);
    $order->calculate_totals();
    $order->save();

    $response = [
        'order_id'     => $order->get_id(),
        'order_number' => $order->get_order_number(),
        'total'        => $order->get_total(),
        'message'      => 'تم تسجيل طلبك بنجاح',
    ];

    if ($pay_by_card) {
        $return_url   = wp_validate_redirect(esc_url_raw($_POST['return_url'] ?? ''), home_url('/'));
        $checkout_url = chahriyti_create_checkout($order, strtok($return_url, '?'));
        if (is_wp_error($checkout_url)) {
            $order->update_status('cancelled', 'تعذّر إنشاء صفحة الدفع: ' . $checkout_url->get_error_message());
            $message = 'تعذّر فتح صفحة الدفع، حاول مرة أخرى أو اختر BaridiMob';
            if (current_user_can('manage_options')) {
                // Admins testing the page see the real reason
                $message .= "\n\n[admin] " . $checkout_url->get_error_message();
            }
            wp_send_json_error(['message' => $message]);
            return;
        }
        $response['checkout_url'] = $checkout_url;
    }

    wp_send_json_success($response);
}

// ─── Delivery fee ───
// Wilaya comes as "16 - الجزائر"; null when that delivery type isn't offered
function chahriyti_delivery_fee($wilaya, $delivery_type) {
    $code = intval($wilaya);
    $json = file_get_contents(plugin_dir_path(__FILE__) . 'Tarifs_Wilayas_Domicile_Bureau.json');
    $key  = $delivery_type === 'bureau' ? 'Livraison bureau' : 'Livraison à domicile';

    foreach ((array) json_decode($json, true) as $row) {
        if (intval($row['Code'] ?? 0) === $code) {
            $fee = intval($row[$key] ?? 0);
            return $fee > 0 ? $fee : null;
        }
    }
    return null;
}

// ─── Chargily Pay ───
// Keys are set in Settings → Chahriyti Payments. CHARGILY_SECRET_KEY and
// CHARGILY_MODE in wp-config.php, when defined, take precedence.
function chahriyti_chargily_mode() {
    if (defined('CHARGILY_MODE')) {
        return CHARGILY_MODE === 'live' ? 'live' : 'test';
    }
    return get_option('chahriyti_chargily_mode') === 'live' ? 'live' : 'test';
}

function chahriyti_chargily_secret() {
    if (defined('CHARGILY_SECRET_KEY')) {
        return CHARGILY_SECRET_KEY;
    }
    return (string) get_option('chahriyti_chargily_' . chahriyti_chargily_mode() . '_secret', '');
}

function chahriyti_chargily_api() {
    return chahriyti_chargily_mode() === 'live'
        ? 'https://pay.chargily.net/api/v2'
        : 'https://pay.chargily.net/test/api/v2';
}

// ─── Settings page ───
add_action('admin_menu', function () {
    add_options_page('Chahriyti Payments', 'Chahriyti Payments', 'manage_options', 'chahriyti-payments', 'chahriyti_settings_page');
});

add_action('admin_post_chahriyti_save_payments', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Not allowed');
    }
    check_admin_referer('chahriyti_payments');

    update_option('chahriyti_chargily_mode', ($_POST['mode'] ?? '') === 'live' ? 'live' : 'test', false);
    update_option('chahriyti_product_id', absint($_POST['product_id'] ?? 0), false);

    // An empty field keeps the saved key, so it never has to be shown again
    foreach (['test', 'live'] as $mode) {
        $key = trim(sanitize_text_field($_POST[$mode . '_secret'] ?? ''));
        if ($key !== '') {
            update_option('chahriyti_chargily_' . $mode . '_secret', $key, false);
        }
    }

    update_option('chahriyti_fulfill_url', esc_url_raw(trim($_POST['fulfill_url'] ?? '')), false);
    $fulfill_secret = trim(sanitize_text_field($_POST['fulfill_secret'] ?? ''));
    if ($fulfill_secret !== '') {
        update_option('chahriyti_fulfill_secret', $fulfill_secret, false);
    }

    wp_safe_redirect(admin_url('options-general.php?page=chahriyti-payments&saved=1'));
    exit;
});

function chahriyti_settings_page() {
    $mode = chahriyti_chargily_mode();
    $mask = function ($mode) {
        $key = (string) get_option('chahriyti_chargily_' . $mode . '_secret', '');
        return $key === '' ? 'غير محفوظ' : 'محفوظ (…' . esc_html(substr($key, -4)) . ')';
    };
    ?>
    <div class="wrap" dir="rtl">
        <h1>Chahriyti — الدفع بالبطاقة (Chargily)</h1>
        <?php if (isset($_GET['saved'])) : ?>
            <div class="notice notice-success"><p>تم الحفظ.</p></div>
        <?php endif; ?>
        <?php if (defined('CHARGILY_SECRET_KEY') || defined('CHARGILY_MODE')) : ?>
            <div class="notice notice-warning"><p>المفاتيح معرّفة في wp-config.php وهي المستعملة بدل هذه الإعدادات.</p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="chahriyti_save_payments">
            <?php wp_nonce_field('chahriyti_payments'); ?>
            <table class="form-table">
                <tr>
                    <th>رقم المنتج (Product ID)</th>
                    <td>
                        <?php $pid = absint(get_option('chahriyti_product_id', 12)); $p = function_exists('wc_get_product') ? wc_get_product($pid) : null; ?>
                        <input type="number" name="product_id" value="<?php echo esc_attr($pid); ?>" class="small-text" min="1">
                        <p class="description">
                            <?php echo $p ? '✅ ' . esc_html($p->get_name()) . ' — ' . esc_html($p->get_price()) . ' د.ج' : '❌ لا يوجد منتج بهذا الرقم'; ?>
                            — الرقم يظهر في المنتجات عند تمرير الفأرة على اسم المنتج (ID).
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>الوضع</th>
                    <td>
                        <label><input type="radio" name="mode" value="test" <?php checked($mode, 'test'); ?>> تجربة (Test)</label><br>
                        <label><input type="radio" name="mode" value="live" <?php checked($mode, 'live'); ?>> حقيقي (Live)</label>
                    </td>
                </tr>
                <tr>
                    <th>المفتاح السري — Test</th>
                    <td>
                        <input type="password" name="test_secret" class="regular-text" autocomplete="off" placeholder="test_sk_...">
                        <p class="description"><?php echo $mask('test'); ?> — اتركه فارغاً للإبقاء على المفتاح المحفوظ.</p>
                    </td>
                </tr>
                <tr>
                    <th>المفتاح السري — Live</th>
                    <td>
                        <input type="password" name="live_secret" class="regular-text" autocomplete="off" placeholder="live_sk_...">
                        <p class="description"><?php echo $mask('live'); ?> — اتركه فارغاً للإبقاء على المفتاح المحفوظ.</p>
                    </td>
                </tr>
                <tr>
                    <th>رابط إرسال الكود والكتاب</th>
                    <td>
                        <input type="url" name="fulfill_url" class="regular-text" dir="ltr" value="<?php echo esc_attr(chahriyti_fulfill_url()); ?>">
                        <p class="description">دالة fulfillDigitalOrder في Firebase — تختار كود غير مطبوع وترسله مع الكتاب PDF لبريد الزبون.</p>
                    </td>
                </tr>
                <tr>
                    <th>المفتاح المشترك (DIGITAL_ORDER_SECRET)</th>
                    <td>
                        <input type="password" name="fulfill_secret" class="regular-text" autocomplete="off">
                        <p class="description"><?php echo get_option('chahriyti_fulfill_secret', '') === '' ? 'غير محفوظ' : 'محفوظ'; ?> — نفس القيمة المحفوظة في Firebase. اتركه فارغاً للإبقاء عليه.</p>
                    </td>
                </tr>
                <tr>
                    <th>رابط الـ Webhook</th>
                    <td><code dir="ltr"><?php echo esc_html(rest_url('chahriyti/v1/chargily-webhook')); ?></code>
                        <p class="description">يُرسَل تلقائياً مع كل عملية دفع، لا حاجة لإضافته في Chargily.</p></td>
                </tr>
            </table>
            <?php submit_button('حفظ'); ?>
        </form>
    </div>
    <?php
}

// Creates a hosted checkout for the order and returns its URL
function chahriyti_create_checkout($order, $return_url) {
    $order_id = $order->get_id();
    $body = [
        'amount'           => (int) round($order->get_total()),
        'currency'         => 'dzd',
        'locale'           => 'ar',
        'description'      => 'كتاب شهريتي PDF + كود التفعيل — طلب #' . $order->get_order_number(),
        'success_url'      => add_query_arg(['payment' => 'success', 'order' => $order->get_order_number()], $return_url),
        'failure_url'      => add_query_arg(['payment' => 'failed', 'order' => $order->get_order_number()], $return_url),
        'webhook_endpoint' => rest_url('chahriyti/v1/chargily-webhook'),
        'metadata'         => ['order_id' => (string) $order_id],
    ];

    $res = wp_remote_post(chahriyti_chargily_api() . '/checkouts', [
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Bearer ' . chahriyti_chargily_secret(),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ],
        'body'    => wp_json_encode($body),
    ]);

    if (is_wp_error($res)) {
        return $res;
    }

    $data = json_decode(wp_remote_retrieve_body($res), true);
    if (empty($data['id']) || empty($data['checkout_url'])) {
        $detail = is_array($data) ? ($data['message'] ?? wp_json_encode($data['errors'] ?? $data)) : wp_remote_retrieve_body($res);
        return new WP_Error('chargily', 'HTTP ' . wp_remote_retrieve_response_code($res) . ' — ' . mb_substr((string) $detail, 0, 300));
    }

    $order->update_meta_data('_chargily_checkout_id', $data['id']);
    $order->add_order_note('صفحة الدفع Chargily: ' . $data['id']);
    $order->save();

    return $data['checkout_url'];
}

// ─── Chargily webhook ───
// The only thing that marks a card order paid: the success redirect can be faked
add_action('rest_api_init', function () {
    register_rest_route('chahriyti/v1', '/chargily-webhook', [
        'methods'             => 'POST',
        'callback'            => 'chahriyti_chargily_webhook',
        'permission_callback' => '__return_true',
    ]);
});

function chahriyti_chargily_webhook(WP_REST_Request $request) {
    $payload   = $request->get_body();
    $signature = (string) $request->get_header('signature');
    $secret    = chahriyti_chargily_secret();

    if ($secret === '' || $signature === '' || !hash_equals(hash_hmac('sha256', $payload, $secret), $signature)) {
        return new WP_REST_Response(['error' => 'invalid signature'], 403);
    }

    $event    = json_decode($payload, true);
    $checkout = $event['data'] ?? [];
    $order    = wc_get_order(absint($checkout['metadata']['order_id'] ?? 0));

    // Only act on the checkout we created for this order
    if (!$order || $order->get_meta('_chargily_checkout_id') !== ($checkout['id'] ?? '')) {
        return new WP_REST_Response(['ok' => true], 200);
    }

    switch ($event['type'] ?? '') {
        case 'checkout.paid':
            if ($order->is_paid()) {
                break; // Webhook retried
            }
            if ((int) ($checkout['amount'] ?? 0) !== (int) round($order->get_total())) {
                $order->update_status('on-hold', 'مبلغ الدفع لا يطابق الطلب: ' . ($checkout['amount'] ?? '?') . ' د.ج');
                break;
            }
            $order->update_meta_data('_chargily_customer_id', (string) ($checkout['customer_id'] ?? ''));
            $order->payment_complete($checkout['id']);
            $order->add_order_note('✅ تم الدفع عبر Chargily (' . ($checkout['payment_method'] ?? 'card') . ')');
            // Emailing a 13 MB PDF takes too long to do inside the webhook
            wp_schedule_single_event(time(), 'chahriyti_fulfill_order', [$order->get_id(), 1]);
            spawn_cron();
            break;

        case 'checkout.failed':
            if ($order->has_status('pending')) {
                $order->update_status('failed', 'فشل الدفع عبر Chargily');
            }
            break;

        case 'checkout.canceled':
        case 'checkout.expired':
            if ($order->has_status('pending')) {
                $order->update_status('cancelled', 'أُلغي الدفع عبر Chargily');
            }
            break;
    }

    return new WP_REST_Response(['ok' => true], 200);
}

// ─── Digital delivery: licence + book PDF by email ───
// A Firebase function takes an unprinted, unused licence from the pool,
// assigns it to the order and emails it with the book. It is idempotent
// per order, so retries and the manual "resend" never use a second code.
function chahriyti_fulfill_url() {
    return (string) get_option('chahriyti_fulfill_url', 'https://us-central1-chahriyati.cloudfunctions.net/fulfillDigitalOrder');
}

add_action('chahriyti_fulfill_order', 'chahriyti_fulfill_order', 10, 2);

function chahriyti_fulfill_order($order_id, $attempt = 1, $resend = false) {
    $order = wc_get_order($order_id);
    if (!$order || !$order->is_paid() || $order->get_payment_method() !== 'chargily') {
        return;
    }

    $result = chahriyti_send_digital_copy($order, $resend);
    if (is_wp_error($result)) {
        $order->add_order_note('⚠️ لم يُرسَل الكود والكتاب (محاولة ' . $attempt . '): ' . $result->get_error_message());
        // Back off 5, 10, 20, 40 minutes, then leave it to the manual action
        if ($attempt < 5) {
            wp_schedule_single_event(time() + 300 * (2 ** ($attempt - 1)), 'chahriyti_fulfill_order', [$order_id, $attempt + 1]);
        }
        return;
    }

    $order->update_meta_data('_chahriyti_license_key', $result['licenseKey']);
    $order->add_order_note('📧 أُرسل كود التفعيل ' . $result['licenseKey'] . ' وكتاب PDF إلى ' . $order->get_billing_email());
    if (!$order->has_status('completed')) {
        $order->update_status('completed');
    }
    $order->save();
}

function chahriyti_send_digital_copy($order, $resend = false) {
    $secret = (string) get_option('chahriyti_fulfill_secret', '');
    if ($secret === '') {
        return new WP_Error('config', 'المفتاح المشترك غير محفوظ في الإعدادات');
    }

    // The customer types their email on the Chargily page, so read it from there
    $email = $order->get_billing_email();
    if ($email === '') {
        $email = chahriyti_chargily_customer_email($order->get_meta('_chargily_customer_id'));
        if (is_wp_error($email)) {
            return $email;
        }
        $order->set_billing_email($email);
        $order->save();
    }

    $body = wp_json_encode([
        'orderId' => (string) $order->get_order_number(),
        'email'   => $email,
        'name'    => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
        'phone'   => $order->get_billing_phone(),
        'resend'  => $resend,
    ]);

    $res = wp_remote_post(chahriyti_fulfill_url(), [
        'timeout' => 90,
        'headers' => [
            'Content-Type'          => 'application/json',
            'X-Chahriyti-Signature' => hash_hmac('sha256', $body, $secret),
        ],
        'body'    => $body,
    ]);
    if (is_wp_error($res)) {
        return $res;
    }

    $code = wp_remote_retrieve_response_code($res);
    $data = json_decode(wp_remote_retrieve_body($res), true);
    if ($code !== 200 || empty($data['licenseKey'])) {
        $error = $data['error'] ?? mb_substr(wp_remote_retrieve_body($res), 0, 200);
        if ($error === 'pool_empty') {
            $error = 'لا توجد أكواد غير مطبوعة — ولّد دفعة جديدة في المنصة';
        }
        return new WP_Error('fulfill', 'HTTP ' . $code . ' — ' . $error);
    }
    return $data;
}

function chahriyti_chargily_customer_email($customer_id) {
    if ($customer_id === '') {
        return new WP_Error('chargily', 'لا يوجد customer_id من Chargily');
    }
    $res = wp_remote_get(chahriyti_chargily_api() . '/customers/' . rawurlencode($customer_id), [
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Bearer ' . chahriyti_chargily_secret(),
            'Accept'        => 'application/json',
        ],
    ]);
    if (is_wp_error($res)) {
        return $res;
    }
    $email = sanitize_email(json_decode(wp_remote_retrieve_body($res), true)['email'] ?? '');
    return is_email($email) ? $email : new WP_Error('chargily', 'لم يُعثر على بريد الزبون في Chargily');
}

// Manual resend from the order screen: same code, sent again
add_filter('woocommerce_order_actions', function ($actions, $order = null) {
    if ($order && $order->get_payment_method() === 'chargily' && $order->is_paid()) {
        $actions['chahriyti_resend_digital'] = 'إرسال كود التفعيل والكتاب PDF للزبون';
    }
    return $actions;
}, 10, 2);

add_action('woocommerce_order_action_chahriyti_resend_digital', function ($order) {
    chahriyti_fulfill_order($order->get_id(), 5, true);
});
