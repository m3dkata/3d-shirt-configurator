<?php
/**
 * Plugin Name: WooCommerce Production Label (Производствен етикет)
 * Description: Adds a button to WooCommerce order admin to print a production label PDF in Bulgarian.
 * Version: 1.0
 * Author: Mehmed Cherkez
 * Text Domain: shirt-configurator
 */

if (!defined('ABSPATH')) exit;

// 1. Add the button to the order admin page
add_action('woocommerce_admin_order_actions_end', function($order){
    if (!current_user_can('manage_woocommerce')) return;
    $order_id = $order->get_id();
    $url = wp_nonce_url(
        admin_url("admin-ajax.php?action=print_production_label&order_id=$order_id"),
        'print_production_label_' . $order_id
    );
    echo '<a class="button button-primary" style="margin-left:5px;" target="_blank" href="'.esc_url($url).'">Производствен етикет</a>';
});

// 2. Handle the AJAX request to generate and output the PDF
add_action('wp_ajax_print_production_label', function() {
    if (empty($_GET['order_id']) || !current_user_can('manage_woocommerce')) {
        wp_die('Нямате права.');
    }
    $order_id = intval($_GET['order_id']);
    if (!wp_verify_nonce($_GET['_wpnonce'], 'print_production_label_' . $order_id)) {
        wp_die('Грешен nonce.');
    }
    $order = wc_get_order($order_id);
    if (!$order) wp_die('Поръчката не е намерена.');

    // --- Build the HTML for the PDF ---
    ob_start();
    ?>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; }
        .label-header { text-align:center; font-size:22px; font-weight:bold; margin-bottom:10px; }
        .label-section { margin-bottom: 15px; }
        .label-table { width:100%; border-collapse:collapse; }
        .label-table th, .label-table td { border:1px solid #222; padding:6px 8px; font-size:14px; }
        .label-table th { background:#f5f5f5; }
        .label-customer, .label-shipping { font-size:15px; }
        .label-footer { margin-top:20px; font-size:13px; text-align:right; }
    </style>
    <div class="production-label">
        <div class="label-header">Производствен етикет</div>
        <div class="label-section label-customer">
            <strong>Поръчка №:</strong> <?php echo $order->get_order_number(); ?><br>
            <strong>Дата:</strong> <?php echo wc_format_datetime($order->get_date_created(), 'd.m.Y H:i'); ?><br>
            <strong>Клиент:</strong> <?php echo esc_html($order->get_formatted_billing_full_name()); ?><br>
            <strong>Телефон:</strong> <?php echo esc_html($order->get_billing_phone()); ?><br>
            <strong>Имейл:</strong> <?php echo esc_html($order->get_billing_email()); ?><br>
        </div>
        <div class="label-section label-shipping">
            <strong>Адрес за доставка:</strong><br>
            <?php echo nl2br(esc_html($order->get_formatted_shipping_address())); ?>
        </div>
        <div class="label-section">
            <table class="label-table">
                <thead>
                    <tr>
                        <th>Артикул</th>
                        <th>SKU</th>
                        <th>Количество</th>
                        <th>Детайли</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order->get_items() as $item): 
                        $product = $item->get_product();
                        ?>
                        <tr>
                            <td><?php echo esc_html($item->get_name()); ?></td>
                            <td><?php echo $product ? esc_html($product->get_sku()) : ''; ?></td>
                            <td><?php echo esc_html($item->get_quantity()); ?></td>
                            <td>
                                <?php
                                // Show meta (custom fields, e.g. shirt config)
                                foreach ($item->get_formatted_meta_data('') as $meta) {
                                    echo '<div><strong>' . esc_html($meta->display_key) . ':</strong> ' . esc_html($meta->display_value) . '</div>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="label-footer">
            <strong>Обща сума:</strong> <?php echo wc_price($order->get_total()); ?>
        </div>
    </div>
    <?php
    $html = ob_get_clean();

    // --- Generate PDF with Dompdf ---
    if (!class_exists('\Dompdf\Dompdf')) {
        // Try to load Dompdf if bundled in /vendor
        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
        } else {
            wp_die('Липсва библиотеката Dompdf. Моля, инсталирайте я чрез composer или я добавете в папката на плъгина.');
        }
    }
    $dompdf = new \Dompdf\Dompdf([
        'isHtml5ParserEnabled' => true,
        'isRemoteEnabled' => true
    ]);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A5', 'portrait');
    $dompdf->render();

    // Output PDF to browser
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="etiket-order-'.$order->get_order_number().'.pdf"');
    echo $dompdf->output();
    exit;
});