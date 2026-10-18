<?php
/**
 * Template Name: SAHARA Landing Page
 * Template Post Type: page
 *
 * Canvas-style page — outputs the SAHARA landing page
 * without any theme header/footer (Astra or otherwise).
 * Select this template when creating the page in WordPress.
 */

defined('ABSPATH') || exit;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAHARA — لبس تراثك</title>
    <meta name="description" content="تيشيرت SAHARA — وين التراث الجزائري يتلاقى مع الستريت وير. تطريز أمازيغي يدوي على قماش فاخر.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400;1,500&family=Space+Grotesk:wght@300;400;500;600;700&family=Amiri:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    <script>
        window.sahara_ajax = {
            url:   '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            nonce: '<?php echo wp_create_nonce('sahara_order_nonce'); ?>'
        };
    </script>
    <?php wp_head(); ?>
</head>
<body class="sahara-landing">
<?php
/**
 * Include the full body content of sahara.html.
 *
 * Upload sahara-body.html to this same directory.
 * sahara-body.html = sahara.html with ONLY the content between <body> and </body>
 * (everything from the <style> tag through the closing </script>, without
 * the <!DOCTYPE>, <html>, <head>, <body>, </body>, </html> wrappers).
 */
$body_file = plugin_dir_path(__FILE__) . 'sahara-body.html';
if (file_exists($body_file)) {
    readfile($body_file);
} else {
    echo '<p style="text-align:center;padding:60px;font-family:Cairo,sans-serif;font-size:18px;">ملف المحتوى غير موجود — ارفع <code>sahara-body.html</code></p>';
}
?>
<?php wp_footer(); ?>
</body>
</html>
