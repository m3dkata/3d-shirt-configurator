<?php
/**
 * Plugin Name: Персонализирано номериране на поръчки в Магазина
 * Plugin URI: https://kidn3y.com
 * Description: Персонализирано номериране на поръчки в Магазина с настройка на префикс, цифри, начално число и суфикс
 * Version: 1.0.1
 * Author: Мехмед Черкез
 * Text Domain: custom-order-number
 * Requires at least: 5.0
 * Tested up to: 6.3
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

class CustomWooOrderNumbering {
    
    private $plugin_name = 'custom-order-number';
    private $version = '1.0.1';
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    public function init() {
        if (class_exists('WooCommerce')) {
            add_filter('woocommerce_order_number', array($this, 'custom_order_number'), 1, 2);
            add_action('woocommerce_new_order', array($this, 'save_custom_order_number'), 10, 1);
            add_action('woocommerce_checkout_update_order_meta', array($this, 'save_custom_order_number_checkout'), 10, 1);
            add_filter('woocommerce_order_search_fields', array($this, 'add_search_field'));
            
            // HPOS compatibility hooks
            add_action('woocommerce_store_api_checkout_update_order_from_request', array($this, 'save_custom_order_number_hpos'), 10, 2);
        }
    }
    
    public function activate() {
        // Set default options
        $default_options = array(
            'prefix' => '',
            'digits' => 7,
            'start_number' => 1,
            'suffix' => 'K1/1',
            'current_number' => 1
        );
        
        foreach ($default_options as $key => $value) {
            if (get_option('cwon_' . $key) === false) {
                add_option('cwon_' . $key, $value);
            }
        }
    }
    
    public function custom_order_number($order_id, $order) {
        // Get order object if not provided
        if (!$order && is_numeric($order_id)) {
            $order = wc_get_order($order_id);
        }
        
        if (!$order) {
            return $order_id;
        }
        
        // HPOS compatible way to get meta
        $custom_number = $this->get_order_meta($order, '_custom_order_number');
        
        if (empty($custom_number)) {
            $custom_number = $this->generate_order_number($order->get_id());
            $this->update_order_meta($order, '_custom_order_number', $custom_number);
        }
        
        return $custom_number;
    }
    
    public function generate_order_number($order_id) {
        $prefix = get_option('cwon_prefix', '');
        $digits = get_option('cwon_digits', 7);
        $suffix = get_option('cwon_suffix', 'K1/1');
        $current_number = get_option('cwon_current_number', 1);
        
        // Generate the formatted number
        $formatted_number = $prefix . sprintf('%0' . $digits . 'd', $current_number) . $suffix;
        
        // Increment the current number for next order
        update_option('cwon_current_number', $current_number + 1);
        
        return $formatted_number;
    }
    
    public function save_custom_order_number($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $custom_number = $this->get_order_meta($order, '_custom_order_number');
        if (empty($custom_number)) {
            $custom_number = $this->generate_order_number($order_id);
            $this->update_order_meta($order, '_custom_order_number', $custom_number);
        }
    }
    
    public function save_custom_order_number_checkout($order_id) {
        $this->save_custom_order_number($order_id);
    }
    
    // HPOS compatibility for Store API
    public function save_custom_order_number_hpos($order, $request) {
        if ($order && is_a($order, 'WC_Order')) {
            $this->save_custom_order_number($order->get_id());
        }
    }
    
    // HPOS compatible meta functions
    private function get_order_meta($order, $key) {
        if (method_exists($order, 'get_meta')) {
            return $order->get_meta($key, true);
        } else {
            return get_post_meta($order->get_id(), $key, true);
        }
    }
    
    private function update_order_meta($order, $key, $value) {
        if (method_exists($order, 'update_meta_data')) {
            $order->update_meta_data($key, $value);
            $order->save_meta_data();
        } else {
            update_post_meta($order->get_id(), $key, $value);
        }
    }
    
    public function add_search_field($search_fields) {
        $search_fields[] = '_custom_order_number';
        return $search_fields;
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Custom Order Numbering',
            'Order Numbering',
            'manage_options',
            'custom-order-numbering',
            array($this, 'admin_page')
        );
    }
    
    public function settings_init() {
        register_setting('cwon_settings', 'cwon_prefix');
        register_setting('cwon_settings', 'cwon_digits');
        register_setting('cwon_settings', 'cwon_start_number');
        register_setting('cwon_settings', 'cwon_suffix');
        register_setting('cwon_settings', 'cwon_current_number');
        
        add_settings_section(
            'cwon_settings_section',
            'Order Numbering Configuration',
            array($this, 'settings_section_callback'),
            'cwon_settings'
        );
        
        add_settings_field(
            'cwon_prefix',
            'Prefix',
            array($this, 'prefix_render'),
            'cwon_settings',
            'cwon_settings_section'
        );
        
        add_settings_field(
            'cwon_digits',
            'Number of Digits',
            array($this, 'digits_render'),
            'cwon_settings',
            'cwon_settings_section'
        );
        
        add_settings_field(
            'cwon_start_number',
            'Start Number',
            array($this, 'start_number_render'),
            'cwon_settings',
            'cwon_settings_section'
        );
        
        add_settings_field(
            'cwon_current_number',
            'Current Number',
            array($this, 'current_number_render'),
            'cwon_settings',
            'cwon_settings_section'
        );
        
        add_settings_field(
            'cwon_suffix',
            'Suffix',
            array($this, 'suffix_render'),
            'cwon_settings',
            'cwon_settings_section'
        );
    }
    
    public function prefix_render() {
        $prefix = get_option('cwon_prefix', '');
        echo '<input type="text" name="cwon_prefix" value="' . esc_attr($prefix) . '" placeholder="e.g., ORD-" />';
        echo '<p class="description">Text to appear before the order number (optional)</p>';
    }
    
    public function digits_render() {
        $digits = get_option('cwon_digits', 7);
        echo '<input type="number" name="cwon_digits" value="' . esc_attr($digits) . '" min="1" max="10" />';
        echo '<p class="description">Number of digits for the order number (1-10)</p>';
    }
    
    public function start_number_render() {
        $start_number = get_option('cwon_start_number', 1);
        echo '<input type="number" name="cwon_start_number" value="' . esc_attr($start_number) . '" min="1" />';
        echo '<p class="description">Starting number for new installations</p>';
    }
    
    public function current_number_render() {
        $current_number = get_option('cwon_current_number', 1);
        echo '<input type="number" name="cwon_current_number" value="' . esc_attr($current_number) . '" min="1" />';
        echo '<p class="description">Current number (next order will use this number)</p>';
    }
    
    public function suffix_render() {
        $suffix = get_option('cwon_suffix', 'K1/1');
        echo '<input type="text" name="cwon_suffix" value="' . esc_attr($suffix) . '" placeholder="e.g., K1/1" />';
        echo '<p class="description">Text to appear after the order number (optional)</p>';
    }
    
    public function settings_section_callback() {
        echo '<p>Configure your custom order numbering format. Preview will be shown below.</p>';
    }
    
    public function admin_page() {
        if (isset($_POST['submit'])) {
            // Verify nonce for security
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'cwon_settings-options')) {
                wp_die('Security check failed');
            }
            
            // Handle form submission
            update_option('cwon_prefix', sanitize_text_field($_POST['cwon_prefix']));
            update_option('cwon_digits', intval($_POST['cwon_digits']));
            update_option('cwon_start_number', intval($_POST['cwon_start_number']));
            update_option('cwon_current_number', intval($_POST['cwon_current_number']));
            update_option('cwon_suffix', sanitize_text_field($_POST['cwon_suffix']));
            
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        if (isset($_POST['reset_numbering'])) {
            // Verify nonce for security
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'reset_numbering')) {
                wp_die('Security check failed');
            }
            
            $start_number = get_option('cwon_start_number', 1);
            update_option('cwon_current_number', $start_number);
            echo '<div class="notice notice-success"><p>Order numbering reset to start number!</p></div>';
        }
        
        // Generate preview
        $prefix = get_option('cwon_prefix', '');
        $digits = get_option('cwon_digits', 7);
        $current_number = get_option('cwon_current_number', 1);
        $suffix = get_option('cwon_suffix', 'K1/1');
        $preview = $prefix . sprintf('%0' . $digits . 'd', $current_number) . $suffix;
        
        ?>
        <div class="wrap">
            <h1>Custom Order Numbering Settings</h1>
            
            <div class="card" style="max-width: 600px; margin: 20px 0;">
                <h2>Preview</h2>
                <p>Next order number will be: <strong style="font-size: 18px; color: #0073aa;" class="preview-number"><?php echo esc_html($preview); ?></strong></p>
            </div>
            
            <form action="" method="post">
                <?php
                settings_fields('cwon_settings');
                do_settings_sections('cwon_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Prefix</th>
                        <td>
                            <?php $this->prefix_render(); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Number of Digits</th>
                        <td>
                            <?php $this->digits_render(); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Start Number</th>
                        <td>
                            <?php $this->start_number_render(); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Current Number</th>
                        <td>
                            <?php $this->current_number_render(); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Suffix</th>
                        <td>
                            <?php $this->suffix_render(); ?>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save Settings'); ?>
            </form>
            
            <form action="" method="post" style="margin-top: 20px;">
                <?php wp_nonce_field('reset_numbering'); ?>
                <input type="hidden" name="reset_numbering" value="1">
                <?php submit_button('Reset Numbering to Start Number', 'secondary', 'reset_numbering'); ?>
            </form>
            
            <div class="card" style="max-width: 800px; margin: 20px 0;">
                <h2>Examples</h2>
                <ul>
                    <li><strong>Prefix:</strong> "ORD-", <strong>Digits:</strong> 5, <strong>Suffix:</strong> "-2024" → ORD-00001-2024</li>
                    <li><strong>Prefix:</strong> "", <strong>Digits:</strong> 7, <strong>Suffix:</strong> "K1/1" → 0000001K1/1</li>
                    <li><strong>Prefix:</strong> "WC", <strong>Digits:</strong> 4, <strong>Suffix:</strong> "" → WC0001</li>
                </ul>
            </div>
            
            <div class="card" style="max-width: 800px; margin: 20px 0;">
                <h2>HPOS Compatibility</h2>
                <p style="color: green;">✅ This plugin is compatible with WooCommerce High-Performance Order Storage (HPOS)</p>
                <p>The plugin will work with both traditional WordPress posts and the new HPOS system.</p>
            </div>
        </div>
        
        <script>
        // Live preview update
        jQuery(document).ready(function($) {
            function updatePreview() {
                var prefix = $('input[name="cwon_prefix"]').val() || '';
                var digits = parseInt($('input[name="cwon_digits"]').val()) || 7;
                var currentNumber = parseInt($('input[name="cwon_current_number"]').val()) || 1;
                var suffix = $('input[name="cwon_suffix"]').val() || '';
                
                var paddedNumber = currentNumber.toString().padStart(digits, '0');
                var preview = prefix + paddedNumber + suffix;
                
                $('.preview-number').text(preview);
            }
            
            // Update preview on input change
            $('input[name="cwon_prefix"], input[name="cwon_digits"], input[name="cwon_current_number"], input[name="cwon_suffix"]').on('input', updatePreview);
            
            // Initial preview update
            updatePreview();
        });
        </script>
        
        <style>
        .form-table th {
            width: 200px;
        }
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .card h2 {
            margin-top: 0;
        }
        .preview-number {
            background: #f0f6fc;
            padding: 10px 15px;
            border-radius: 4px;
            border-left: 4px solid #0073aa;
            display: inline-block;
            font-family: monospace;
        }
        </style>
        <?php
    }
}

// Initialize the plugin
new CustomWooOrderNumbering();

// Additional HPOS compatibility functions
if (!function_exists('cwon_get_order_custom_number')) {
    /**
     * Helper function to get custom order number
     * @param int|WC_Order $order Order ID or order object
     * @return string Custom order number
     */
    function cwon_get_order_custom_number($order) {
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }
        
        if (!$order) {
            return '';
        }
        
        // Try to get from meta first
        $custom_number = $order->get_meta('_custom_order_number', true);
        
        if (empty($custom_number)) {
            // Generate if not exists
            $prefix = get_option('cwon_prefix', '');
            $digits = get_option('cwon_digits', 7);
            $suffix = get_option('cwon_suffix', 'K1/1');
            $current_number = get_option('cwon_current_number', 1);
            
            $custom_number = $prefix . sprintf('%0' . $digits . 'd', $current_number) . $suffix;
            
            // Save it
            $order->update_meta_data('_custom_order_number', $custom_number);
            $order->save_meta_data();
            
            // Increment counter
            update_option('cwon_current_number', $current_number + 1);
        }
        
        return $custom_number;
    }
}

// Hook for order emails to show custom number
add_filter('woocommerce_email_order_meta_fields', 'cwon_add_custom_order_number_to_emails', 10, 3);
function cwon_add_custom_order_number_to_emails($fields, $sent_to_admin, $order) {
    $custom_number = cwon_get_order_custom_number($order);
    if ($custom_number) {
        $fields['custom_order_number'] = array(
            'label' => __('Order Number', 'custom-order-number'),
            'value' => $custom_number,
        );
    }
    return $fields;
}

// Display custom order number in order details
add_action('woocommerce_order_details_before_order_table', 'cwon_display_custom_order_number_in_order_details');
function cwon_display_custom_order_number_in_order_details($order) {
    $custom_number = cwon_get_order_custom_number($order);
    if ($custom_number) {
        echo '<p><strong>' . __('Order Number:', 'custom-order-number') . '</strong> ' . esc_html($custom_number) . '</p>';
    }
}

// Add custom order number column to admin orders list
add_filter('manage_edit-shop_order_columns', 'cwon_add_custom_order_number_column');
function cwon_add_custom_order_number_column($columns) {
    $new_columns = array();
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        if ($key === 'order_number') {
            $new_columns['custom_order_number'] = __('Custom Order #', 'custom-order-number');
        }
    }
    return $new_columns;
}

// Populate custom order number column
add_action('manage_shop_order_posts_custom_column', 'cwon_populate_custom_order_number_column');
function cwon_populate_custom_order_number_column($column) {
    global $post;
    
    if ($column === 'custom_order_number') {
        $order = wc_get_order($post->ID);
        if ($order) {
            $custom_number = cwon_get_order_custom_number($order);
            echo '<strong>' . esc_html($custom_number) . '</strong>';
        }
    }
}

// HPOS compatibility for admin columns
add_filter('woocommerce_shop_order_list_table_columns', 'cwon_add_hpos_custom_order_number_column');
function cwon_add_hpos_custom_order_number_column($columns) {
    $new_columns = array();
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        if ($key === 'order_number') {
            $new_columns['custom_order_number'] = __('Custom Order #', 'custom-order-number');
        }
    }
    return $new_columns;
}

// Populate HPOS custom order number column
add_action('woocommerce_shop_order_list_table_custom_column', 'cwon_populate_hpos_custom_order_number_column', 10, 2);
function cwon_populate_hpos_custom_order_number_column($column, $order) {
    if ($column === 'custom_order_number') {
        $custom_number = cwon_get_order_custom_number($order);
        echo '<strong>' . esc_html($custom_number) . '</strong>';
    }
}

// Make custom order number column sortable
add_filter('manage_edit-shop_order_sortable_columns', 'cwon_make_custom_order_number_column_sortable');
function cwon_make_custom_order_number_column_sortable($columns) {
    $columns['custom_order_number'] = 'custom_order_number';
    return $columns;
}

// Handle sorting by custom order number
add_action('pre_get_posts', 'cwon_handle_custom_order_number_sorting');
function cwon_handle_custom_order_number_sorting($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    if ($query->get('orderby') === 'custom_order_number') {
        $query->set('meta_key', '_custom_order_number');
        $query->set('orderby', 'meta_value');
    }
}

// Add search functionality for custom order numbers
add_filter('woocommerce_shop_order_search_fields', 'cwon_add_custom_order_number_search_field');
function cwon_add_custom_order_number_search_field($search_fields) {
    $search_fields[] = '_custom_order_number';
    return $search_fields;
}

// Plugin deactivation cleanup (optional)
register_deactivation_hook(__FILE__, 'cwon_deactivation_cleanup');
function cwon_deactivation_cleanup() {
    // Optionally clean up options on deactivation
    // Uncomment the lines below if you want to remove all plugin data on deactivation
    /*
    delete_option('cwon_prefix');
    delete_option('cwon_digits');
    delete_option('cwon_start_number');
    delete_option('cwon_suffix');
    delete_option('cwon_current_number');
    */
}

