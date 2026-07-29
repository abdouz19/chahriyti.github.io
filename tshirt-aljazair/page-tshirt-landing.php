<?php
/**
 * Template Name: Tshirt Aljazair Landing Page
 * Template Post Type: page
 *
 * Canvas-style page — outputs the Tshirt Aljazair landing page
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

        // Replace relative image paths with absolute GitHub Pages URLs
        $cdn = 'https://abdouz19.github.io/chahriyti.github.io/tshirt-aljazair/';
        $images = ['green.png', 'white.png', 'image.png', 'image copy.png'];
        foreach ($images as $img) {
            $body_content = str_replace(
                '"' . $img . '"',
                '"' . $cdn . $img . '"',
                $body_content
            );
        }
    }
}
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

    <!-- WooCommerce AJAX config -->
    <script>
        window.tshirt_ajax = {
            url:   '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            nonce: '<?php echo wp_create_nonce('tshirt_order_nonce'); ?>'
        };
    </script>

    <?php wp_head(); ?>

    <!-- Styles extracted from index.html — loaded AFTER theme CSS to win cascade -->
    <?php echo $inline_styles; ?>
    <style>
        /* Force Cairo over any theme font overrides */
        html, body, * { font-family: 'Cairo', sans-serif !important; }
    </style>
</head>
<body class="tshirt-landing">

<?php
if (!empty($body_content)) {
    echo $body_content;
} else {
    echo '<p style="text-align:center;padding:60px;font-family:Cairo,sans-serif;font-size:18px;color:white;background:#0A0F0A;">
        ملف المحتوى غير موجود — تأكد أن <code>index.html</code> موجود في مجلد البلاقن
    </p>';
}
?>

<?php wp_footer(); ?>
</body>
</html>
