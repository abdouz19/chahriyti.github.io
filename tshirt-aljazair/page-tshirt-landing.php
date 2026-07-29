<?php
/**
 * Template Name: Tshirt Aljazair Landing Page
 * Template Post Type: page
 *
 * Canvas-style page — outputs the Tshirt Aljazair landing page
 * without any theme header/footer.
 * Select this template when creating the page in WordPress.
 *
 * ─── SETUP ──────────────────────────────────────────────────────────────────
 * 1. Upload this file + tshirt-orders.php + index.html + images to wp-content/plugins/tshirt-aljazair/
 * 2. Activate the "Tshirt Aljazair Landing Orders" plugin in WP Admin → Plugins
 * 3. Create a new Page in WordPress, set template to "Tshirt Aljazair Landing Page"
 * 4. Visit the page — the landing page will render with live WooCommerce orders
 * ────────────────────────────────────────────────────────────────────────────
 */

defined('ABSPATH') || exit;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تيشرت الجزائر — ثارت الموضة الجزائرية</title>
    <meta name="description" content="تيشرت الجزائر Oversize — مستوحى من ألوان الوطن. توصيل لكل الجزائر. الدفع عند الاستلام.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900;1000&display=swap" rel="stylesheet">

    <!-- Inject WooCommerce AJAX config for the order form -->
    <script>
        window.tshirt_ajax = {
            url:   '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            nonce: '<?php echo wp_create_nonce('tshirt_order_nonce'); ?>'
        };
    </script>

    <?php wp_head(); ?>
</head>
<body class="tshirt-landing">
<?php
/**
 * Read and output the landing page body content from index.html.
 * index.html lives in the same directory as this template.
 *
 * We extract only the content between <body> and </body> so we
 * don't duplicate the <html>/<head> structure.
 */
$html_file = plugin_dir_path(__FILE__) . 'index.html';

if (file_exists($html_file)) {
    $html = file_get_contents($html_file);

    // Extract content between <body ...> and </body>
    if (preg_match('/<body[^>]*>([\s\S]*)<\/body>/i', $html, $matches)) {
        echo $matches[1];
    } else {
        // Fallback: output the full file (won't break, just outputs dupe tags)
        echo $html;
    }
} else {
    echo '<p style="text-align:center;padding:60px;font-family:Cairo,sans-serif;font-size:18px;color:white;background:#0A0F0A;">
        ملف المحتوى غير موجود — ارفع <code>index.html</code> في نفس مجلد البلاقن
    </p>';
}
?>
<?php wp_footer(); ?>
</body>
</html>
