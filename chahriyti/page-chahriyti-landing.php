<?php
/**
 * Template Name: Chahriyti Book Landing Page
 * Template Post Type: page
 *
 * Canvas-style page — outputs the Chahriyti book landing page
 * without any theme header/footer.
 */

defined('ABSPATH') || exit;

// ─── Read index.html and extract parts ───
$html_file     = plugin_dir_path(__FILE__) . 'index.html';
$inline_styles = '';
$body_content  = '';

if (file_exists($html_file)) {
    $html = file_get_contents($html_file);

    // Extract all <style>...</style> blocks from <head>
    if (preg_match_all('/<style[^>]*>([\s\S]*?)<\/style>/i', $html, $sm)) {
        foreach ($sm[0] as $block) {
            $inline_styles .= $block . "\n";
        }
    }

    // Extract <body> content
    if (preg_match('/<body[^>]*>([\s\S]*)<\/body>/i', $html, $bm)) {
        $body_content = $bm[1];

        // Replace all relative paths with plugin URL
        $plugin_url = plugin_dir_url(__FILE__);

        // Images (bundled inside plugin)
        $body_content = str_replace('"../CHAHRIYTI png.png"', '"' . $plugin_url . 'CHAHRIYTI png.png"', $body_content);
        $body_content = str_replace('"../screenshots/', '"' . $plugin_url . 'screenshots/', $body_content);

        // Data files (bundled inside plugin)
        $body_content = str_replace("'./algeria_cities.csv'", "'" . $plugin_url . "algeria_cities.csv'", $body_content);
        $body_content = str_replace("'./Tarifs_Wilayas_Domicile_Bureau.json'", "'" . $plugin_url . "Tarifs_Wilayas_Domicile_Bureau.json'", $body_content);
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>شهريتي — تفعيل مدى الحياة 📢🔥 + الكتاب 📗</title>
    <link rel="icon" type="image/png" href="<?php echo esc_url(plugin_dir_url(__FILE__) . 'favicon.png'); ?>">
    <link rel="apple-touch-icon" href="<?php echo esc_url(plugin_dir_url(__FILE__) . 'apple-touch-icon.png'); ?>">
    <meta name="description" content="كتاب شهريتي + تطبيق إدارة الراتب مع كود تفعيل مدى الحياة. تحكّم في مصاريفك، وفّر أكثر، وعيش براحة بال.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <!-- Styles from index.html -->
    <?php echo $inline_styles; ?>

    <!-- WooCommerce AJAX config — only thing we need from WP -->
    <script>
        window.chahriyti_ajax = {
            url:   '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            nonce: '<?php echo wp_create_nonce('chahriyti_order_nonce'); ?>'
        };
    </script>
</head>
<body>

<?php
if (!empty($body_content)) {
    echo $body_content;
} else {
    echo '<p style="text-align:center;padding:60px;font-family:Cairo,sans-serif;font-size:18px;color:white;background:#080E10;">
        ملف المحتوى غير موجود — تأكد أن <code>index.html</code> موجود في مجلد البلاقن
    </p>';
}
?>

</body>
</html>
