<?php
/**
 * Plugin Name: 3D Shirt Configurator Integration
 * Description: Integrates the 3D shirt configurator with WooCommerce
 * Version: 1.1
 * Author: Mehmed Cherkez
 * Text Domain: shirt-configurator
 * Requires WooCommerce: 4.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Shirt_Configurator_Integration {
    
    // Base prices for different models
    private $model_prices = [
        'men1' => 119.00,
        'men2' => 129.00,
        'men3' => 139.00,
        'women1' => 119.00,
        'women2' => 129.00
    ];
    
    // Model display names
    private $model_names = [
        'men1' => 'Мъжка Риза Модел 6(ДР-ОЯ)',
        'men2' => 'Мъжка Риза Модел 4(КР)',
        'men3' => 'Мъжка Риза Модел 6-1(ДР-ПЯ)',
        'women1' => 'Дамска Риза Модел 5(КР)',
        'women2' => 'Дамска Риза Модел 7(ДР-ОЯ)'
    ];
    
    public function __construct() {
        // Register activation hook for database setup
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        
        // Register REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Add custom data to cart items
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_cart_item_data'), 10, 3);
        
        // Calculate item price based on customizations
        add_filter('woocommerce_before_calculate_totals', array($this, 'calculate_custom_price'), 10, 1);
        
        // Display custom data in cart
        add_filter('woocommerce_get_item_data', array($this, 'display_cart_item_custom_data'), 10, 2);
        
        // Add custom data to order items
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_custom_data_to_order_items'), 10, 4);
        
        // Add admin settings page
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Delete texture
        add_action('wp_ajax_delete_texture', array($this, 'ajax_delete_texture'));

        add_action('wp_ajax_delete_all_textures', array($this, 'ajax_delete_all_textures'));

        // Delete Custom Pricing
        add_action('wp_ajax_delete_custom_pricing', array($this, 'ajax_delete_custom_pricing'));
        
        // Add headers for static files
        add_action('init', function() {
            if (preg_match('/\.(glb|jpg|jpeg|png|svg)$/', $_SERVER['REQUEST_URI'])) {
                $allowed_origins = array(
                    'http://localhost:3000',
                    'http://127.0.0.1:3000',
                    'https://3d.kidn3y.com',
                    'https://kidn3y.com'
                );
                
                $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
                
                if (in_array($origin, $allowed_origins)) {
                    header("Access-Control-Allow-Origin: {$origin}");
                    header("Access-Control-Allow-Methods: GET, OPTIONS");
                    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
                }
            }
        });

        // Add admin-ajax handlers
        add_action('wp_ajax_add_shirt_to_cart', array($this, 'ajax_add_to_cart'));
        add_action('wp_ajax_nopriv_add_shirt_to_cart', array($this, 'ajax_add_to_cart'));
        
        add_action('wp_ajax_get_shirt_config', array($this, 'ajax_get_config'));
        add_action('wp_ajax_nopriv_get_shirt_config', array($this, 'ajax_get_config'));
        
        // Add admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        
        // Add AJAX handlers for admin actions
        add_action('wp_ajax_save_model', array($this, 'ajax_save_model'));
        add_action('wp_ajax_delete_model', array($this, 'ajax_delete_model'));
        add_action('wp_ajax_save_texture', array($this, 'ajax_save_texture'));
        add_action('wp_ajax_delete_texture', array($this, 'ajax_delete_texture'));
    }
    
    /**
     * Plugin activation: set up database tables
     */
    public function activate_plugin() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create models table
        $table_name = $wpdb->prefix . 'shirt_models';
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        model_id varchar(50) NOT NULL,
        name varchar(100) NOT NULL,
        file_path varchar(255) NOT NULL,
        base_price decimal(10,2) NOT NULL DEFAULT 119.00,
        thumbnail varchar(255),
        active tinyint(1) NOT NULL DEFAULT 1,
        date_created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY model_id (model_id)
    ) $charset_collate;";
    
    // Create textures table
    $table_name_textures = $wpdb->prefix . 'shirt_textures';
    $sql .= "CREATE TABLE $table_name_textures (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        texture_id varchar(50) NOT NULL,
        name varchar(100) NOT NULL,
        file_path varchar(255) NOT NULL,
        material varchar(100),
        color varchar(100),
        style varchar(100),
        price_adjustment decimal(10,2) NOT NULL DEFAULT 0.00,
        thumbnail varchar(255),
        active tinyint(1) NOT NULL DEFAULT 1,
        date_created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY texture_id (texture_id)
    ) $charset_collate;";
    
    // Create model-texture pricing table for specific combinations
    $table_name_pricing = $wpdb->prefix . 'shirt_pricing';
    $sql .= "CREATE TABLE $table_name_pricing (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        model_id varchar(50) NOT NULL,
        texture_id varchar(50) NOT NULL,
        price decimal(10,2) NOT NULL,
        date_created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY model_texture (model_id, texture_id)
    ) $charset_collate;";
    
    // Create model sizes table
    $table_name_sizes = $wpdb->prefix . 'shirt_model_sizes';
    $sql .= "CREATE TABLE $table_name_sizes (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        model_id varchar(50) NOT NULL,
        size_value varchar(20) NOT NULL,
        size_label varchar(50) NOT NULL,
        active tinyint(1) NOT NULL DEFAULT 1,
        date_created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY model_size (model_id, size_value)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
        
        // Insert default models if table is empty
        $table_name = $wpdb->prefix . 'shirt_models';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        if ($count == 0) {
            foreach ($this->model_names as $model_id => $name) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'model_id' => $model_id,
                        'name' => $name,
                        'file_path' => '/models/' . $model_id . '.glb',
                        'base_price' => $this->model_prices[$model_id],
                        'thumbnail' => '/textures/' . substr($model_id, 0, 1) . substr($model_id, -1) . '.svg',
                    )
                );
            }
        }
        
        // Insert default textures if table is empty
        $table_name = $wpdb->prefix . 'shirt_textures';
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        if ($count == 0) {
            // Insert some default textures
            for ($i = 1; $i <= 75; $i++) {
                $material = '96% памук 4% еластан';
                
                // Assign colors in a pattern
                $colors = array('СИН', 'СИВ', 'ЗЕЛЕН/СИН/ЛИЛАВ', 'ТЮРКОАЗ', 'БЯЛ', 'КАРО', 'КАРО-СИН');
                $color = $colors[$i % count($colors)];
                
                $wpdb->insert(
                    $table_name,
                    array(
                        'texture_id' => 'fabric' . $i,
                        'name' => 'Плат ' . sprintf('%02d', $i),
                        'file_path' => '/textures/' . $i . '.jpg',
                        'material' => $material,
                        'color' => $color,
                        'style' => 'ЕЛЕГАНТ',
                        'price_adjustment' => 0.00,
                        'thumbnail' => '/textures/' . $i . '.jpg',
                    )
                );
            }
        }
        // Insert default sizes if table is empty
    $table_name = $wpdb->prefix . 'shirt_model_sizes';
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    
    if ($count == 0) {
        // Default sizes for men1
        $men1_sizes = array('XS', 'S', 'M', 'L', 'XL', 'XXL');
        foreach ($men1_sizes as $size) {
            $wpdb->insert(
                $table_name,
                array(
                    'model_id' => 'men1',
                    'size_value' => $size,
                    'size_label' => $size
                )
            );
        }
        
        // Default sizes for men2
        $men2_sizes = array('40', '42', '44', '46', '48');
        foreach ($men2_sizes as $size) {
            $wpdb->insert(
                $table_name,
                array(
                    'model_id' => 'men2',
                    'size_value' => $size,
                    'size_label' => $size
                )
            );
        }
        
        // Default sizes for men3
        $men3_sizes = array('XS', 'S', 'M', 'L', 'XL');
        foreach ($men3_sizes as $size) {
            $wpdb->insert(
                $table_name,
                array(
                    'model_id' => 'men3',
                    'size_value' => $size,
                    'size_label' => $size
                )
            );
        }
        
        // Default sizes for women1
        $women1_sizes = array('36', '38', '40', '42', '44');
        foreach ($women1_sizes as $size) {
            $wpdb->insert(
                $table_name,
                array(
                    'model_id' => 'women1',
                    'size_value' => $size,
                    'size_label' => $size
                )
            );
        }
        
        // Default sizes for women2
        $women2_sizes = array('XS', 'S', 'M', 'L', 'XL');
        foreach ($women2_sizes as $size) {
            $wpdb->insert(
                $table_name,
                array(
                    'model_id' => 'women2',
                    'size_value' => $size,
                    'size_label' => $size
                )
            );
        }
    }
    }
    
    /**
 * Register REST API endpoints
 */
public function register_rest_routes() {
    // Endpoint to get configuration data
    register_rest_route('shirt-configurator/v1', '/get-config', array(
        'methods' => 'GET',
        'callback' => array($this, 'get_config_data'),
        'permission_callback' => '__return_true'
    ));
    
    // Add the init endpoint
    register_rest_route('shirt-configurator/v1', '/init', array(
        'methods' => 'GET',
        'callback' => array($this, 'get_init_data'),
        'permission_callback' => '__return_true'
    ));
    
    // Endpoint to add configured shirt to cart
    register_rest_route('shirt-configurator/v1', '/add-to-cart', array(
        'methods' => 'POST',
        'callback' => array($this, 'add_to_cart'),
        'permission_callback' => '__return_true'
    ));
    
    // New endpoint to get all models
    register_rest_route('shirt-configurator/v1', '/models', array(
        'methods' => 'GET',
        'callback' => array($this, 'get_models'),
        'permission_callback' => '__return_true'
    ));
    
    // New endpoint to get all textures
    register_rest_route('shirt-configurator/v1', '/textures', array(
        'methods' => 'GET',
        'callback' => array($this, 'get_textures'),
        'permission_callback' => '__return_true'
    ));
}

    
    /**
     * Get all models from database
     */
    public function get_models() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'shirt_models';
        
        $models = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE active = 1 ORDER BY name ASC",
            ARRAY_A
        );
        
        return array(
            'success' => true,
            'models' => $models
        );
    }
    
    /**
     * Get all textures from database
     */
    public function get_textures() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'shirt_textures';
        
        $textures = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE active = 1 ORDER BY name ASC",
            ARRAY_A
        );
        
        return array(
            'success' => true,
            'textures' => $textures
        );
    }
    
    /**
 * Get initialization data for the frontend
 */
public function get_init_data() {
    global $wpdb;
    
    // Get models from database
    $table_models = $wpdb->prefix . 'shirt_models';
    $models = $wpdb->get_results(
        "SELECT model_id, name, base_price, file_path, thumbnail FROM $table_models WHERE active = 1",
        ARRAY_A
    );
    
    // Format models for frontend
    $model_data = array();
    $model_prices = array();
    $model_names = array();
    
    foreach ($models as $model) {
        $model_data[$model['model_id']] = array(
            'name' => $model['name'],
            'file' => $model['file_path'],
            'thumbnail' => $model['thumbnail']
        );
        
        $model_prices[$model['model_id']] = floatval($model['base_price']);
        $model_names[$model['model_id']] = $model['name'];
    }
    
    // Get textures from database
    $table_textures = $wpdb->prefix . 'shirt_textures';
    $textures = $wpdb->get_results(
        "SELECT texture_id, name, file_path, material, color, style, price_adjustment, thumbnail FROM $table_textures WHERE active = 1",
        ARRAY_A
    );
    
    // Format textures for frontend
    $texture_data = array();
    
    foreach ($textures as $texture) {
        $texture_data[$texture['texture_id']] = array(
            'name' => $texture['name'],
            'file' => $texture['file_path'],
            'material' => $texture['material'],
            'color' => $texture['color'],
            'style' => $texture['style'],
            'price_adjustment' => floatval($texture['price_adjustment']),
            'thumbnail' => $texture['thumbnail']
        );
    }
    
    // Get custom pricing for model-texture combinations
    $table_pricing = $wpdb->prefix . 'shirt_pricing';
    $pricing = $wpdb->get_results(
        "SELECT model_id, texture_id, price FROM $table_pricing",
        ARRAY_A
    );
    
    $models_array = array();
    foreach ($model_data as $id => $model) {
        $models_array[] = array(
            'id' => $id,
            'name' => $model['name'],
            'image_url' => $model['thumbnail']
        );
    }

    // Format pricing for frontend
    $custom_pricing = array();
    
    foreach ($pricing as $price) {
        if (!isset($custom_pricing[$price['model_id']])) {
            $custom_pricing[$price['model_id']] = array();
        }
        
        $custom_pricing[$price['model_id']][$price['texture_id']] = floatval($price['price']);
    }

    // Get model sizes
    $table_sizes = $wpdb->prefix . 'shirt_model_sizes';
    $sizes = $wpdb->get_results(
        "SELECT model_id, size_value, size_label FROM $table_sizes WHERE active = 1",
        ARRAY_A
    );
    
    // Format sizes for frontend
    $model_sizes = array();
    
    foreach ($sizes as $size) {
        if (!isset($model_sizes[$size['model_id']])) {
            $model_sizes[$size['model_id']] = array();
        }
        
        $model_sizes[$size['model_id']][] = array(
            'value' => $size['size_value'],
            'label' => $size['size_label']
        );
    }
    
    // If no sizes are found in the database, provide default sizes
    if (empty($model_sizes)) {
        $model_sizes = array(
            'men1' => $this->get_default_sizes('men1'),
            'men2' => $this->get_default_sizes('men2'),
            'men3' => $this->get_default_sizes('men3'),
            'women1' => $this->get_default_sizes('women1'),
            'women2' => $this->get_default_sizes('women2')
        );
    }

    // Add CORS headers for cross-domain access
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');
    header('Access-Control-Allow-Headers: Content-Type');
    
    return array(
        'success' => true,
        'nonce' => wp_create_nonce('wc_store_api'),
        'productId' => get_option('shirt_configurator_product_id', 0),
        'variationId' => 0,
        'models' => $models_array,
        'textures' => $texture_data,
        'model_prices' => $model_prices,
        'model_names' => $model_names,
        'model_sizes' => $model_sizes,
        'custom_pricing' => $custom_pricing
    );
}

    /**
 * Get default sizes for a model
 */
private function get_default_sizes($model_id) {
    $default_sizes = array(
        'men1' => array(
            array('value' => 'XS', 'label' => 'XS'),
            array('value' => 'S', 'label' => 'S'),
            array('value' => 'M', 'label' => 'M'),
            array('value' => 'L', 'label' => 'L'),
            array('value' => 'XL', 'label' => 'XL'),
            array('value' => 'XXL', 'label' => 'XXL')
        ),
        'men2' => array(
            array('value' => '40', 'label' => '40'),
            array('value' => '42', 'label' => '42'),
            array('value' => '44', 'label' => '44'),
            array('value' => '46', 'label' => '46'),
            array('value' => '48', 'label' => '48')
        ),
        'men3' => array(
            array('value' => 'XS', 'label' => 'XS'),
            array('value' => 'S', 'label' => 'S'),
            array('value' => 'M', 'label' => 'M'),
            array('value' => 'L', 'label' => 'L'),
            array('value' => 'XL', 'label' => 'XL')
        ),
        'women1' => array(
            array('value' => '36', 'label' => '36'),
            array('value' => '38', 'label' => '38'),
            array('value' => '40', 'label' => '40'),
            array('value' => '42', 'label' => '42'),
            array('value' => '44', 'label' => '44')
        ),
        'women2' => array(
            array('value' => 'XS', 'label' => 'XS'),
            array('value' => 'S', 'label' => 'S'),
            array('value' => 'M', 'label' => 'M'),
            array('value' => 'L', 'label' => 'L'),
            array('value' => 'XL', 'label' => 'XL')
        )
    );
    
    return isset($default_sizes[$model_id]) ? $default_sizes[$model_id] : $default_sizes['men1'];
}
    /**
     * Get configuration data for the frontend
     */
    public function get_config_data() {
        try {
            // Just call get_init_data to avoid duplication
            return $this->get_init_data();
        } catch (Exception $e) {
            error_log('Shirt Configurator error in get_config_data: ' . $e->getMessage());
            return new WP_Error('server_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
 * AJAX handler for add_to_cart
 */
public function ajax_add_to_cart() {
    try {
        // Get customization data
        $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : 'men1';
        $texture = isset($_POST['texture']) ? sanitize_text_field($_POST['texture']) : 'fabric1';
        $size = isset($_POST['size']) ? sanitize_text_field($_POST['size']) : 'M';
        // Add quantity parameter with default value of 1
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        $button_color = sanitize_text_field($_POST['button_color'] ?? 'Бели');
        // Ensure quantity is at least 1
        if ($quantity < 1) {
            $quantity = 1;
        }
        
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            wp_send_json(array(
                'success' => false,
                'message' => 'WooCommerce is not active'
            ));
            return;
        }
        
        // Ensure WooCommerce cart is loaded
        if (!function_exists('WC') || !WC()->cart) {
            // Try to initialize WooCommerce cart
            if (!function_exists('WC')) {
                wp_send_json(array(
                    'success' => false,
                    'message' => 'WooCommerce is not loaded'
                ));
                return;
            }
            
            // Check if session is missing
            if (!WC()->session) {
                WC()->initialize_session();
            }
            
            // Check if cart is still missing
            if (!WC()->cart) {
                WC()->initialize_cart();
            }
            
            // Final check
            if (!WC()->cart) {
                wp_send_json(array(
                    'success' => false,
                    'message' => 'WooCommerce cart is not available'
                ));
                return;
            }
        }
        
        // Get product ID (use a placeholder product or create one dynamically)
        $product_id = $this->get_or_create_product();
        
        if (!$product_id) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Could not find or create product'
            ));
            return;
        }
        
        // Get model name from database
        global $wpdb;
        $table_models = $wpdb->prefix . 'shirt_models';
        $model_name = $wpdb->get_var($wpdb->prepare(
            "SELECT name FROM $table_models WHERE model_id = %s",
            $model
        ));
        
        if (!$model_name) {
            $model_name = isset($this->model_names[$model]) ? $this->model_names[$model] : 'Custom Shirt';
        }
        
        // Get texture name from database
        $table_textures = $wpdb->prefix . 'shirt_textures';
        $texture_data = $wpdb->get_row($wpdb->prepare(
            "SELECT name, material, color, style FROM $table_textures WHERE texture_id = %s",
            $texture
        ), ARRAY_A);
        
        $texture_name = $texture_data ? $texture_data['name'] : $this->get_texture_name($texture);
        
        // Calculate price based on model, texture, and any custom pricing
        $price = $this->calculate_price($model, $texture);
        
        // Prepare custom data
        $cart_item_data = array(
            'shirt_customization' => array(
                'model' => $model,
                'model_name' => $model_name,
                'texture' => $texture,
                'texture_name' => $texture_name,
                'size' => $size,
                'button_color' => $button_color,
                'custom_price' => $price,
                'material' => $texture_data ? $texture_data['material'] : '',
                'color' => $texture_data ? $texture_data['color'] : '',
                'style' => $texture_data ? $texture_data['style'] : ''
            )
        );
        
        // Add to cart with the specified quantity
        $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, 0, array(), $cart_item_data);
        
        if (!$cart_item_key) {
            wp_send_json(array(
                'success' => false,
                'message' => 'Could not add to cart'
            ));
            return;
        }
        
        wp_send_json(array(
            'success' => true,
            'cart_url' => wc_get_cart_url(),
            'cart_count' => WC()->cart->get_cart_contents_count()
        ));
    } catch (Exception $e) {
        wp_send_json(array(
            'success' => false,
            'message' => $e->getMessage()
        ));
    }
    
    wp_die();
}


    /**
     * AJAX handler for get_config
     */
    public function ajax_get_config() {
        try {
            // Get data from database
            global $wpdb;
            
            // Get models from database
            $table_models = $wpdb->prefix . 'shirt_models';
            $models = $wpdb->get_results(
                "SELECT model_id, name, base_price FROM $table_models WHERE active = 1",
                ARRAY_A
            );
            
            // Format models for frontend
            $model_prices = array();
            $model_names = array();
            
            foreach ($models as $model) {
                $model_prices[$model['model_id']] = floatval($model['base_price']);
                $model_names[$model['model_id']] = $model['name'];
            }
            
            wp_send_json(array(
                'success' => true,
                'model_prices' => $model_prices,
                'model_names' => $model_names
            ));
        } catch (Exception $e) {
            wp_send_json(array(
                'success' => false,
                'message' => $e->getMessage()
            ));
        }
        
        wp_die();
    }
    
    /**
     * Get or create a product for the configurator
     */
    private function get_or_create_product() {
        $product_id = get_option('shirt_configurator_product_id', 0);
        
        // If we have a valid product ID, use it
        if ($product_id > 0 && get_post_type($product_id) === 'product') {
            return $product_id;
        }
        
        // Otherwise, create a new product
        $product = new WC_Product_Simple();
        $product->set_name('Custom Shirt');
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_price(119.00);
        $product->set_regular_price(119.00);
        $product->set_sold_individually(false);
        $product->set_virtual(false);
        $product->set_description('Custom shirt created with the 3D Shirt Configurator.');
        
        // Save the product
        $product_id = $product->save();
        
        // Store the product ID in options
        update_option('shirt_configurator_product_id', $product_id);
        
        return $product_id;
    }
    
    /**
     * Calculate price based on model and texture
     */
    private function calculate_price($model, $texture) {
        global $wpdb;
        
        // First check if there's a custom price for this specific combination
        $table_pricing = $wpdb->prefix . 'shirt_pricing';
        $custom_price = $wpdb->get_var($wpdb->prepare(
            "SELECT price FROM $table_pricing WHERE model_id = %s AND texture_id = %s",
            $model, $texture
        ));
        
        if ($custom_price !== null) {
            return floatval($custom_price);
        }
        
        // If no custom price, calculate based on model base price and texture adjustment
        $table_models = $wpdb->prefix . 'shirt_models';
        $base_price = $wpdb->get_var($wpdb->prepare(
            "SELECT base_price FROM $table_models WHERE model_id = %s",
            $model
        ));
        
        if (!$base_price) {
            $base_price = isset($this->model_prices[$model]) ? $this->model_prices[$model] : 119.00;
        }
        
        $table_textures = $wpdb->prefix . 'shirt_textures';
        $price_adjustment = $wpdb->get_var($wpdb->prepare(
            "SELECT price_adjustment FROM $table_textures WHERE texture_id = %s",
            $texture
        ));
        
        if ($price_adjustment === null) {
            $price_adjustment = 0;
        }
        
        return floatval($base_price) + floatval($price_adjustment);
    }
    
    /**
     * Get texture name from ID
     */
    private function get_texture_name($texture_id) {
        // Extract the number from fabric ID (e.g., "fabric12" -> "12")
        $texture_number = preg_replace('/[^0-9]/', '', $texture_id);
        
        return 'Fabric ' . $texture_number;
    }
    
    /**
     * Add custom data to cart item
     */
    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        // This is handled by the add_to_cart method
        return $cart_item_data;
    }
    
    /**
     * Calculate custom price for cart items
     */
    public function calculate_custom_price($cart) {
        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['shirt_customization']) && isset($cart_item['shirt_customization']['custom_price'])) {
                $cart_item['data']->set_price($cart_item['shirt_customization']['custom_price']);
            }
        }
    }
    
    /**
     * Display custom data in cart
     */
    public function display_cart_item_custom_data($item_data, $cart_item) {
        if (isset($cart_item['shirt_customization'])) {
            $customization = $cart_item['shirt_customization'];
            
            if (isset($customization['model_name'])) {
                $item_data[] = array(
                    'key' => __('Модел', 'shirt-configurator'),
                    'value' => $customization['model_name']
                );
            }
            
            if (isset($customization['texture_name'])) {
                $item_data[] = array(
                    'key' => __('Плат', 'shirt-configurator'),
                    'value' => $customization['texture_name']
                );
            }
            
            if (isset($customization['size'])) {
                $item_data[] = array(
                    'key' => __('Размер', 'shirt-configurator'),
                    'value' => $customization['size']
                );
            }
            
            if (isset($customization['material']) && !empty($customization['material'])) {
                $item_data[] = array(
                    'key' => __('Материал', 'shirt-configurator'),
                    'value' => $customization['material']
                );
            }
            
            if (isset($customization['color']) && !empty($customization['color'])) {
                $item_data[] = array(
                    'key' => __('Цвят', 'shirt-configurator'),
                    'value' => $customization['color']
                );
            }
            if (isset($customization['button_color']) && !empty($customization['button_color'])) {
                $item_data[] = array(
                    'key' => __('Копчета', 'shirt-configurator'),
                    'value' => $customization['button_color']
                );
            }
            if (isset($customization['style']) && !empty($customization['style'])) {
                $item_data[] = array(
                    'key' => __('Стил', 'shirt-configurator'),
                    'value' => $customization['style']
                );
            }
        }
        
        return $item_data;
    }
    
    /**
     * Add custom data to order items
     */
    public function add_custom_data_to_order_items($item, $cart_item_key, $values, $order) {
        if (isset($values['shirt_customization'])) {
            $customization = $values['shirt_customization'];
            
            if (isset($customization['model_name'])) {
                $item->add_meta_data(__('Модел', 'shirt-configurator'), $customization['model_name']);
            }
            
            if (isset($customization['texture_name'])) {
                $item->add_meta_data(__('Плат', 'shirt-configurator'), $customization['texture_name']);
            }
            
            if (isset($customization['size'])) {
                $item->add_meta_data(__('Размер', 'shirt-configurator'), $customization['size']);
            }
            
            if (isset($customization['material']) && !empty($customization['material'])) {
                $item->add_meta_data(__('Материал', 'shirt-configurator'), $customization['material']);
            }
            
            if (isset($customization['color']) && !empty($customization['color'])) {
                $item->add_meta_data(__('Цвят', 'shirt-configurator'), $customization['color']);
            }

            if (isset($customization['button_color']) && !empty($customization['button_color'])) {
                $item->add_meta_data(__('Копчета', 'shirt-configurator'), $customization['button_color']);
            }
            
            if (isset($customization['style']) && !empty($customization['style'])) {
                $item->add_meta_data(__('Стил', 'shirt-configurator'), $customization['style']);
            }
            
            // Store full customization data for reference
            $item->add_meta_data('_shirt_customization', $customization, true);
        }
    }
    
/**
 * Enqueue admin scripts and styles
 */
public function admin_scripts($hook) {
    // Only load on our plugin's admin page
    if ($hook != 'woocommerce_page_shirt-configurator-settings') {
        return;
    }
    
    // Enqueue WordPress media uploader
    wp_enqueue_media();
    
    // Add inline script to define ajaxurl
    wp_add_inline_script('jquery', 'var ajaxurl = "' . admin_url('admin-ajax.php') . '";', 'before');
    
    // Enqueue admin CSS
    wp_enqueue_style(
        'shirt-configurator-admin-css',
        plugin_dir_url(__FILE__) . 'assets/css/admin.css',
        array(),
        '1.0.0'
    );
    
    // Pass data to JavaScript - THIS IS CRITICAL
    wp_localize_script(
        'shirt-configurator-admin-js',
        'shirt_config',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('shirt_configurator_admin'),
            'site_url' => site_url()
        )
    );
    
    // Enqueue admin CSS
    wp_enqueue_style(
        'shirt-configurator-admin-css',
        plugin_dir_url(__FILE__) . 'assets/css/admin.css',
        array(),
        '1.0.0'
    );
}


    
    /**
     * AJAX handler for saving a model
     */
    public function ajax_save_model() {
        check_ajax_referer('shirt_configurator_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $model_id = isset($_POST['model_id']) ? sanitize_text_field($_POST['model_id']) : '';
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $file_path = isset($_POST['file_path']) ? sanitize_text_field($_POST['file_path']) : '';
        $base_price = isset($_POST['base_price']) ? floatval($_POST['base_price']) : 119.00;
        $thumbnail = isset($_POST['thumbnail']) ? sanitize_text_field($_POST['thumbnail']) : '';
        $active = isset($_POST['active']) ? intval($_POST['active']) : 1;
        
        if (empty($model_id) || empty($name) || empty($file_path)) {
            wp_send_json_error('Missing required fields');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'shirt_models';
        
        // Check if model_id already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE model_id = %s",
            $model_id
        ));
        
        if ($exists) {
            // Update existing model
            $result = $wpdb->update(
                $table_name,
                array(
                    'name' => $name,
                    'file_path' => $file_path,
                    'base_price' => $base_price,
                    'thumbnail' => $thumbnail,
                    'active' => $active
                ),
                array('model_id' => $model_id)
            );
        } else {
            // Insert new model
            $result = $wpdb->insert(
                $table_name,
                array(
                    'model_id' => $model_id,
                    'name' => $name,
                    'file_path' => $file_path,
                    'base_price' => $base_price,
                    'thumbnail' => $thumbnail,
                    'active' => $active
                )
            );
        }
        
        if ($result === false) {
            wp_send_json_error('Database error: ' . $wpdb->last_error);
        } else {
            wp_send_json_success(array(
                'message' => $exists ? 'Model updated successfully' : 'Model added successfully',
                'model_id' => $model_id
            ));
        }
    }
    
    /**
     * AJAX handler for deleting a model
     */
    public function ajax_delete_model() {
        check_ajax_referer('shirt_configurator_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $model_id = isset($_POST['model_id']) ? sanitize_text_field($_POST['model_id']) : '';
        
        if (empty($model_id)) {
            wp_send_json_error('Missing model ID');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'shirt_models';
        
        // Instead of deleting, set active to 0
        $result = $wpdb->update(
            $table_name,
            array('active' => 0),
            array('model_id' => $model_id)
        );
        
        if ($result === false) {
            wp_send_json_error('Database error: ' . $wpdb->last_error);
        } else {
            wp_send_json_success(array(
                'message' => 'Model deleted successfully',
                'model_id' => $model_id
            ));
        }
    }
    
    /**
     * AJAX handler for saving a texture
     */
    public function ajax_save_texture() {
        check_ajax_referer('shirt_configurator_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $texture_id = isset($_POST['texture_id']) ? sanitize_text_field($_POST['texture_id']) : '';
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $file_path = isset($_POST['file_path']) ? sanitize_text_field($_POST['file_path']) : '';
        $material = isset($_POST['material']) ? sanitize_text_field($_POST['material']) : '';
        $color = isset($_POST['color']) ? sanitize_text_field($_POST['color']) : '';
        $style = isset($_POST['style']) ? sanitize_text_field($_POST['style']) : '';
        $price_adjustment = isset($_POST['price_adjustment']) ? floatval($_POST['price_adjustment']) : 0.00;
        $thumbnail = isset($_POST['thumbnail']) ? sanitize_text_field($_POST['thumbnail']) : '';
        $active = isset($_POST['active']) ? intval($_POST['active']) : 1;
        
        if (empty($texture_id) || empty($name) || empty($file_path)) {
            wp_send_json_error('Missing required fields');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'shirt_textures';
        
        // Check if texture_id already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE texture_id = %s",
            $texture_id
        ));
        
        if ($exists) {
            // Update existing texture
            $result = $wpdb->update(
                $table_name,
                array(
                    'name' => $name,
                    'file_path' => $file_path,
                    'material' => $material,
                    'color' => $color,
                    'style' => $style,
                    'price_adjustment' => $price_adjustment,
                    'thumbnail' => $thumbnail,
                    'active' => $active
                ),
                array('texture_id' => $texture_id)
            );
        } else {
            // Insert new texture
            $result = $wpdb->insert(
                $table_name,
                array(
                    'texture_id' => $texture_id,
                    'name' => $name,
                    'file_path' => $file_path,
                    'material' => $material,
                    'color' => $color,
                    'style' => $style,
                    'price_adjustment' => $price_adjustment,
                    'thumbnail' => $thumbnail,
                    'active' => $active
                )
            );
        }
        
        if ($result === false) {
            wp_send_json_error('Database error: ' . $wpdb->last_error);
        } else {
            wp_send_json_success(array(
                'message' => $exists ? 'Texture updated successfully' : 'Texture added successfully',
                'texture_id' => $texture_id
            ));
        }
    }
    
    /**
 * AJAX handler for deleting all textures
 */
public function ajax_delete_all_textures() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'shirt_configurator_admin')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'shirt_textures';
    
    // Set all textures to inactive instead of deleting
    $result = $wpdb->update(
        $table_name,
        array('active' => 0),
        array('active' => 1),
        array('%d'),
        array('%d')
    );
    
    if ($result === false) {
        wp_send_json_error('Database error: ' . $wpdb->last_error);
    } else {
        wp_send_json_success(array(
            'message' => 'All textures deleted successfully',
            'count' => $result
        ));
    }
}

    /**
     * AJAX handler for deleting a texture
     */
    public function ajax_delete_texture() {
    // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'shirt_configurator_admin')) {
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
            return;
        }
        
        $texture_id = isset($_POST['texture_id']) ? sanitize_text_field($_POST['texture_id']) : '';
        
        if (empty($texture_id)) {
            wp_send_json_error('Missing texture ID');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'shirt_textures';
        
        // Instead of deleting, set active to 0
        $result = $wpdb->update(
            $table_name,
            array('active' => 0),
            array('texture_id' => $texture_id),
            array('%d'),
            array('%s')
        );
        
        if ($result === false) {
            wp_send_json_error('Database error: ' . $wpdb->last_error);
        } else {
            wp_send_json_success(array(
                'message' => 'Texture deleted successfully',
                'texture_id' => $texture_id
            ));
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Настройки на конфигуратора на риза', 'shirt-configurator'),
            __('Конфигуратор на ризи', 'shirt-configurator'),
            'manage_options',
            'shirt-configurator-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        global $wpdb;
        
        // Handle custom pricing form submission
        if (isset($_POST['save_custom_pricing'])) {
            check_admin_referer('shirt_configurator_pricing');
            
            $model_id = sanitize_text_field($_POST['model_id']);
            $texture_id = sanitize_text_field($_POST['texture_id']);
            $price = floatval($_POST['price']);
            
            $table_pricing = $wpdb->prefix . 'shirt_pricing';
            
            // Check if this combination already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_pricing WHERE model_id = %s AND texture_id = %s",
                $model_id, $texture_id
            ));
            
            if ($exists) {
                $wpdb->update(
                    $table_pricing,
                    array('price' => $price),
                    array('model_id' => $model_id, 'texture_id' => $texture_id)
                );
            } else {
                $wpdb->insert(
                    $table_pricing,
                    array(
                        'model_id' => $model_id,
                        'texture_id' => $texture_id,
                        'price' => $price
                    )
                );
            }
            
            echo '<div class="notice notice-success"><p>' . __('Custom pricing saved successfully!', 'shirt-configurator') . '</p></div>';
        }
        
        // Get models from database
        $table_models = $wpdb->prefix . 'shirt_models';
        $models = $wpdb->get_results(
            "SELECT * FROM $table_models ORDER BY name ASC",
            ARRAY_A
        );
        
        // Get textures from database
        $table_textures = $wpdb->prefix . 'shirt_textures';
        $textures = $wpdb->get_results(
            "SELECT * FROM $table_textures WHERE active = 1 ORDER BY name ASC",
            ARRAY_A
        );
        
        // Get custom pricing
        $table_pricing = $wpdb->prefix . 'shirt_pricing';
        $pricing = $wpdb->get_results(
            "SELECT * FROM $table_pricing",
            ARRAY_A
        );
        
        // Format pricing for easier access
        $custom_pricing = array();
        foreach ($pricing as $price) {
            $custom_pricing[$price['model_id'] . '_' . $price['texture_id']] = $price['price'];
        }
        
        // Get product ID
        $product_id = get_option('shirt_configurator_product_id', 0);
        
        ?>
        <div class="wrap shirt-configurator-admin">
            <h1><?php _e('Настройки на конфигуратора на риза', 'shirt-configurator'); ?></h1>
            
            <div class="nav-tab-wrapper">
                <a href="#models-tab" class="nav-tab nav-tab-active"><?php _e('Модели', 'shirt-configurator'); ?></a>
                <a href="#textures-tab" class="nav-tab"><?php _e('Платове', 'shirt-configurator'); ?></a>
                <a href="#pricing-tab" class="nav-tab"><?php _e('Цени', 'shirt-configurator'); ?></a>
                <a href="#sizes-tab" class="nav-tab"><?php _e('Размери', 'shirt-configurator'); ?></a>
                <a href="#settings-tab" class="nav-tab"><?php _e('Настройки', 'shirt-configurator'); ?></a>
            </div>
            
            <div class="tab-content">
                <!-- Models Tab -->
                <div id="models-tab" class="tab-pane active">
                    <h2><?php _e('3D Модели', 'shirt-configurator'); ?></h2>
                    <p><?php _e('Управлявайте 3D моделите, налични в конфигуратора на ризи.', 'shirt-configurator'); ?></p>
                    
                    <div class="add-new-model">
                        <h3><?php _e('Добавяне/редактиране на модел', 'shirt-configurator'); ?></h3>
                        <form id="model-form" class="configurator-form">
                            <input type="hidden" id="model-action" value="add">
                            
                            <div class="form-field">
                                <label for="model-id"><?php _e('Модел ID', 'shirt-configurator'); ?> <span class="required">*</span></label>
                                <input type="text" id="model-id" name="model_id" required placeholder="напр. men1, women2">
                                <p class="description"><?php _e('Уникален идентификатор за модела (използван в кода)', 'shirt-configurator'); ?></p>
                            </div>
                            
                            <div class="form-field">
                                <label for="model-name"><?php _e('Име на модела', 'shirt-configurator'); ?> <span class="required">*</span></label>
                                <input type="text" id="model-name" name="name" required placeholder="напр. Mъжка класическа риза">
                                <p class="description"><?php _e('Екранно име за модела', 'shirt-configurator'); ?></p>
                            </div>
                            
                            <div class="form-field">
                                <label for="model-file"><?php _e('3D модел', 'shirt-configurator'); ?> <span class="required">*</span></label>
                                <div class="file-upload-field">
                                    <input type="text" id="model-file" name="file_path" required placeholder="напр. /models/men1.glb">
                                    <button type="button" class="button upload-file-button" data-target="model-file"><?php _e('Качване', 'shirt-configurator'); ?></button>
                                </div>
                                <p class="description"><?php _e('Път до GLB файла (спрямо корена на сайта)', 'shirt-configurator'); ?></p>
                            </div>
                            
                            <div class="form-field">
                                <label for="model-thumbnail"><?php _e('Миниатюра', 'shirt-configurator'); ?></label>
                                <div class="file-upload-field">
                                    <input type="text" id="model-thumbnail" name="thumbnail" placeholder="напр. /textures/m1.svg">
                                    <button type="button" class="button upload-file-button" data-target="model-thumbnail"><?php _e('Качване', 'shirt-configurator'); ?></button>
                                </div>
                                <p class="description"><?php _e('Път до миниатюрното изображение (спрямо корена на сайта)', 'shirt-configurator'); ?></p>
                            </div>
                            
                            <div class="form-field">
                                <label for="model-price"><?php _e('Базова цена', 'shirt-configurator'); ?></label>
                                <input type="number" id="model-price" name="base_price" step="0.01" min="0" value="119.00">
                                <p class="description"><?php _e('Базова цена за този модел', 'shirt-configurator'); ?></p>
                            </div>
                            
                            <div class="form-field">
                                <label for="model-active"><?php _e('Активен', 'shirt-configurator'); ?></label>
                                <select id="model-active" name="active">
                                    <option value="1" selected><?php _e('Да', 'shirt-configurator'); ?></option>
                                    <option value="0"><?php _e('Не', 'shirt-configurator'); ?></option>
                                </select>
                                <p class="description"><?php _e('Дали този модел ще бъде наличен в конфигуратора', 'shirt-configurator'); ?></p>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="button button-primary"><?php _e('Запазване на модела', 'shirt-configurator'); ?></button>
                                <button type="button" id="cancel-model-edit" class="button" style="display:none;"><?php _e('Отказ', 'shirt-configurator'); ?></button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="models-list">
                        <h3><?php _e('Създадени модели', 'shirt-configurator'); ?></h3>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('ID', 'shirt-configurator'); ?></th>
                                    <th><?php _e('Име', 'shirt-configurator'); ?></th>
                                    <th><?php _e('Път до файла', 'shirt-configurator'); ?></th>
                                    <th><?php _e('Базова цена', 'shirt-configurator'); ?></th>
                                    <th><?php _e('Статус', 'shirt-configurator'); ?></th>
                                    <th><?php _e('Действия', 'shirt-configurator'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($models)): ?>
                                    <tr>
                                        <td colspan="6"><?php _e('Няма намерени модели.', 'shirt-configurator'); ?></td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($models as $model): ?>
                                        <tr data-id="<?php echo esc_attr($model['model_id']); ?>" class="<?php echo $model['active'] ? '' : 'inactive'; ?>">
                                            <td><?php echo esc_html($model['model_id']); ?></td>
                                            <td><?php echo esc_html($model['name']); ?></td>
                                            <td><?php echo esc_html($model['file_path']); ?></td>
                                            <td><?php echo wc_price($model['base_price']); ?></td>
                                            <td><?php echo $model['active'] ? __('Активен', 'shirt-configurator') : __('Неактивен', 'shirt-configurator'); ?></td>
                                            <td>
                                                <button type="button" class="button edit-model" 
                                                    data-id="<?php echo esc_attr($model['model_id']); ?>"
                                                    data-name="<?php echo esc_attr($model['name']); ?>"
                                                    data-file="<?php echo esc_attr($model['file_path']); ?>"
                                                    data-thumbnail="<?php echo esc_attr($model['thumbnail']); ?>"
                                                    data-price="<?php echo esc_attr($model['base_price']); ?>"
                                                    data-active="<?php echo esc_attr($model['active']); ?>"
                                                ><?php _e('Редактирай', 'shirt-configurator'); ?></button>
                                                
                                                <button type="button" class="button delete-model" 
                                                    data-id="<?php echo esc_attr($model['model_id']); ?>"
                                                ><?php _e('Изтрий', 'shirt-configurator'); ?></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Sizes Tab -->
        <div id="sizes-tab" class="tab-pane">
            <h2><?php _e('Размери по модел', 'shirt-configurator'); ?></h2>
            <p><?php _e('Управлявайте размерите, налични за всеки модел.', 'shirt-configurator'); ?></p>
            
            <?php
            // Handle form submission for sizes
            // Handle form submission for sizes
            if (isset($_POST['save_model_sizes'])) {
                check_admin_referer('shirt_configurator_sizes');
                
                $model_id = sanitize_text_field($_POST['model_id']);
                $size_values = isset($_POST['size_value']) ? $_POST['size_value'] : array();
                $size_labels = isset($_POST['size_label']) ? $_POST['size_label'] : array();
                $active_statuses = isset($_POST['size_active']) ? $_POST['size_active'] : array();
                
                // Delete existing sizes for this model
                $table_sizes = $wpdb->prefix . 'shirt_model_sizes';
                
                // Check if table exists
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_sizes'") === $table_sizes;
                
                if (!$table_exists) {
                    // Create the table if it doesn't exist
                    $charset_collate = $wpdb->get_charset_collate();
                    $sql = "CREATE TABLE $table_sizes (
                        id mediumint(9) NOT NULL AUTO_INCREMENT,
                        model_id varchar(50) NOT NULL,
                        size_value varchar(20) NOT NULL,
                        size_label varchar(50) NOT NULL,
                        active tinyint(1) NOT NULL DEFAULT 1,
                        date_created datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                        PRIMARY KEY  (id),
                        UNIQUE KEY model_size (model_id, size_value)
                    ) $charset_collate;";
                    
                    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                    dbDelta($sql);
                    
                    // Check if table was created successfully
                    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_sizes'") === $table_sizes;
                    
                    if (!$table_exists) {
                        echo '<div class="notice notice-error"><p>' . __('Грешка при създаване на таблицата за размери!', 'shirt-configurator') . '</p></div>';
                        return;
                    }
                }
                
                // Delete existing sizes with error handling
                $delete_result = $wpdb->delete($table_sizes, array('model_id' => $model_id));
                
                if ($delete_result === false) {
                    echo '<div class="notice notice-error"><p>' . __('Грешка при изтриване на съществуващите размери!', 'shirt-configurator') . ' ' . $wpdb->last_error . '</p></div>';
                    return;
                }
                
                // Insert new sizes with error handling
                $success = true;
                
                for ($i = 0; $i < count($size_values); $i++) {
                    if (!empty($size_values[$i])) {
                        $insert_result = $wpdb->insert(
                            $table_sizes,
                            array(
                                'model_id' => $model_id,
                                'size_value' => sanitize_text_field($size_values[$i]),
                                'size_label' => sanitize_text_field($size_labels[$i]),
                                'active' => in_array($i, $active_statuses) ? 1 : 0
                            )
                        );
                        
                        if ($insert_result === false) {
                            $success = false;
                            echo '<div class="notice notice-error"><p>' . __('Грешка при запазване на размер:', 'shirt-configurator') . ' ' . $size_values[$i] . ' - ' . $wpdb->last_error . '</p></div>';
                        }
                    }
                }
                
                if ($success) {
                    echo '<div class="notice notice-success"><p>' . __('Размерите са запазени успешно!', 'shirt-configurator') . '</p></div>';
                }
            }


            
            // Get all models
            $table_models = $wpdb->prefix . 'shirt_models';
            $models = $wpdb->get_results(
                "SELECT model_id, name FROM $table_models WHERE active = 1 ORDER BY name ASC",
                ARRAY_A
            );
            
            // Get selected model
            $selected_model = isset($_GET['model']) ? $_GET['model'] : (isset($models[0]) ? $models[0]['model_id'] : '');
            
            // Get sizes for selected model
            $table_sizes = $wpdb->prefix . 'shirt_model_sizes';
            $sizes = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $table_sizes WHERE model_id = %s ORDER BY id ASC",
                    $selected_model
                ),
                ARRAY_A
            );
            ?>
            
            <div class="model-selector">
                <form method="get">
                    <input type="hidden" name="page" value="shirt-configurator-settings">
                    <select name="model" onchange="this.form.submit()">
                        <?php foreach ($models as $model): ?>
                            <option value="<?php echo esc_attr($model['model_id']); ?>" <?php selected($selected_model, $model['model_id']); ?>>
                                <?php echo esc_html($model['name']); ?> (<?php echo esc_html($model['model_id']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            
            <form method="post" class="sizes-form">
                <?php wp_nonce_field('shirt_configurator_sizes'); ?>
                <input type="hidden" name="model_id" value="<?php echo esc_attr($selected_model); ?>">
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Стойност', 'shirt-configurator'); ?></th>
                            <th><?php _e('Етикет', 'shirt-configurator'); ?></th>
                            <th><?php _e('Активен', 'shirt-configurator'); ?></th>
                            <th><?php _e('Действия', 'shirt-configurator'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="sizes-rows">
                        <?php if (empty($sizes)): ?>
                            <tr class="size-row">
                                <td><input type="text" name="size_value[]" required></td>
                                <td><input type="text" name="size_label[]" required></td>
                                <td><input type="checkbox" name="size_active[]" value="0" checked></td>
                                <td><button type="button" class="button remove-size"><?php _e('Премахни', 'shirt-configurator'); ?></button></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sizes as $index => $size): ?>
                                <tr class="size-row">
                                    <td><input type="text" name="size_value[]" value="<?php echo esc_attr($size['size_value']); ?>" required></td>
                                    <td><input type="text" name="size_label[]" value="<?php echo esc_attr($size['size_label']); ?>" required></td>
                                    <td><input type="checkbox" name="size_active[]" value="<?php echo $index; ?>" <?php checked($size['active'], 1); ?>></td>
                                    <td><button type="button" class="button remove-size"><?php _e('Премахни', 'shirt-configurator'); ?></button></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <p>
                    <button type="button" id="add-size" class="button"><?php _e('Добави размер', 'shirt-configurator'); ?></button>
                </p>
                
                <p class="submit">
                    <input type="submit" name="save_model_sizes" class="button button-primary" value="<?php _e('Запази размерите', 'shirt-configurator'); ?>">
                </p>
            </form>

            <script>
                jQuery(document).ready(function($) {
                    // Add new size row
                    $('#add-size').on('click', function() {
                        var rowCount = $('.size-row').length;
                        var newRow = `
                            <tr class="size-row">
                                <td><input type="text" name="size_value[]" required></td>
                                <td><input type="text" name="size_label[]" required></td>
                                <td><input type="checkbox" name="size_active[]" value="${rowCount}" checked></td>
                                <td><button type="button" class="button remove-size"><?php _e('Премахни', 'shirt-configurator'); ?></button></td>
                            </tr>
                        `;
                        $('#sizes-rows').append(newRow);
                    });

                    // Remove size row
                    $(document).on('click', '.remove-size', function() {
                        if ($('.size-row').length > 1) {
                            $(this).closest('tr').remove();
                        } else {
                            alert('<?php _e('Трябва да има поне един размер', 'shirt-configurator'); ?>');
                        }
                    });
                });
            </script>
        </div>
                <!-- Textures Tab -->
                <div id="textures-tab" class="tab-pane">
                    <h2><?php _e('Артикули Платове', 'shirt-configurator'); ?></h2>
                    <p><?php _e('Управлявайте платовете, налични в конфигуратора на ризи.', 'shirt-configurator'); ?></p>
                    
                    <div class="textures-tab-content">
                        <!-- Left Column: Form -->
                        <div class="texture-form-column">
                            <h3><?php _e('Добавяне/Редактиране на плат', 'shirt-configurator'); ?></h3>
                            <form id="texture-form" class="configurator-form">
                                <!-- Your existing form fields here - keep all the existing form content -->
                                <input type="hidden" id="texture-action" value="add">
                                
                                <div class="form-field">
                                    <label for="texture-id"><?php _e('Плат ID', 'shirt-configurator'); ?> <span class="required">*</span></label>
                                    <input type="text" id="texture-id" name="texture_id" required placeholder="напр. fabric1, fabric2">
                                    <p class="description"><?php _e('Уникален идентификатор за плата (използван в кода)', 'shirt-configurator'); ?></p>
                                </div>
                                
                                <div class="form-field">
                                    <label for="texture-name"><?php _e('Име', 'shirt-configurator'); ?> <span class="required">*</span></label>
                                    <input type="text" id="texture-name" name="name" required placeholder="напр. Плат 1">
                                    <p class="description"><?php _e('Име за показване на плат', 'shirt-configurator'); ?></p>
                                </div>
                                
                                <div class="form-field">
                                    <label for="texture-file"><?php _e('Снимка на плата', 'shirt-configurator'); ?> <span class="required">*</span></label>
                                    <div class="file-upload-field">
                                        <input type="text" id="texture-file" name="file_path" required placeholder="/textures/1.jpg">
                                        <button type="button" class="button upload-file-button" data-target="texture-file"><?php _e('Качване', 'shirt-configurator'); ?></button>
                                    </div>
                                    <p class="description"><?php _e('Път до изображението на плата (относително към корена на сайта)', 'shirt-configurator'); ?></p>
                                </div>
                                
                                <div class="form-field">
                                    <label for="texture-thumbnail"><?php _e('Миниатюра', 'shirt-configurator'); ?></label>
                                    <div class="file-upload-field">
                                        <input type="text" id="texture-thumbnail" name="thumbnail" placeholder="/textures/1.jpg">
                                        <button type="button" class="button upload-file-button" data-target="texture-thumbnail"><?php _e('Качване', 'shirt-configurator'); ?></button>
                                    </div>
                                    <p class="description"><?php _e('Път до миниатюрата (може да е същото като за плата)', 'shirt-configurator'); ?></p>
                                </div>
                                
                                <div class="form-field">
                                    <label for="texture-material"><?php _e('Материал', 'shirt-configurator'); ?></label>
                                    <input type="text" id="texture-material" name="material" placeholder="напр. 96% памук 4% еластан">
                                    <p class="description"><?php _e('Състав на материала', 'shirt-configurator'); ?></p>
                                </div>
                                
                                <div class="form-field">
                                    <label for="texture-color"><?php _e('Цвят', 'shirt-configurator'); ?></label>
                                    <input type="text" id="texture-color" name="color" placeholder="напр. СИН, ЗЕЛЕН">
                                    <p class="description"><?php _e('Описание на цвета', 'shirt-configurator'); ?></p>
                                </div>
                                
                                <div class="form-field">
                                    <label for="texture-style"><?php _e('Стил', 'shirt-configurator'); ?></label>
                                    <input type="text" id="texture-style" name="style" placeholder="напр. ЕЛЕГАНТ">
                                    <p class="description"><?php _e('Описание на стила', 'shirt-configurator'); ?></p>
                                </div>
                                
                                <div class="form-field">
                                    <label for="texture-price"><?php _e('Надбавка за цена', 'shirt-configurator'); ?></label>
                                    <input type="number" id="texture-price" name="price_adjustment" step="0.01" value="0.00">
                                    <p class="description"><?php _e('Надбавка за този плат (добавена към базовата цена на модела)', 'shirt-configurator'); ?></p>
                                </div>
                                
                                <div class="form-field">
                                    <label for="texture-active"><?php _e('Активен', 'shirt-configurator'); ?></label>
                                    <select id="texture-active" name="active">
                                        <option value="1" selected><?php _e('Да', 'shirt-configurator'); ?></option>
                                        <option value="0"><?php _e('Не', 'shirt-configurator'); ?></option>
                                    </select>
                                    <p class="description"><?php _e('Дали този плат е наличен в конфигуратора', 'shirt-configurator'); ?></p>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="button button-primary"><?php _e('Запис', 'shirt-configurator'); ?></button>
                                    <button type="button" id="cancel-texture-edit" class="button" style="display:none;"><?php _e('Отказ', 'shirt-configurator'); ?></button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Right Column: List -->
                        <div class="texture-list-column">
                            <div class="textures-list">
                                <h3><?php _e('Съществуващи платове', 'shirt-configurator'); ?></h3>
                                <button type="button" id="delete-all-textures" class="button button-secondary" style="background: #dc3232; color: white; border-color: #dc3232;">
                                    <?php _e('ИЗТРИЙ ВСИЧКИ', 'shirt-configurator'); ?>
                                </button>
                                <table class="wp-list-table widefat fixed striped">
                                    <!-- Your existing table content here -->
                                    <thead>
                                        <tr>
                                            <th><?php _e('ID', 'shirt-configurator'); ?></th>
                                            <th><?php _e('Име', 'shirt-configurator'); ?></th>
                                            <th><?php _e('Преглед', 'shirt-configurator'); ?></th>
                                            <th><?php _e('Материал', 'shirt-configurator'); ?></th>
                                            <th><?php _e('Цвят', 'shirt-configurator'); ?></th>
                                            <th><?php _e('Надбавка за цена', 'shirt-configurator'); ?></th>
                                            <th><?php _e('Статус', 'shirt-configurator'); ?></th>
                                            <th><?php _e('Действия', 'shirt-configurator'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($textures)): ?>
                                            <tr>
                                                <td colspan="8"><?php _e('Няма налични платове.', 'shirt-configurator'); ?></td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($textures as $texture): ?>
                                                <tr data-id="<?php echo esc_attr($texture['texture_id']); ?>" class="<?php echo $texture['active'] ? '' : 'inactive'; ?>">
                                                    <td><?php echo esc_html($texture['texture_id']); ?></td>
                                                    <td><?php echo esc_html($texture['name']); ?></td>
                                                    <td>
                                                        <?php if (!empty($texture['thumbnail'])): ?>
                                                            <img src="<?php echo esc_url(site_url($texture['thumbnail'])); ?>" alt="<?php echo esc_attr($texture['name']); ?>" width="50" height="50">
                                                        <?php else: ?>
                                                            <?php _e('Няма превю', 'shirt-configurator'); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo esc_html($texture['material']); ?></td>
                                                    <td><?php echo esc_html($texture['color']); ?></td>
                                                    <td><?php echo wc_price($texture['price_adjustment']); ?></td>
                                                    <td><?php echo $texture['active'] ? __('Активен', 'shirt-configurator') : __('Неактивен', 'shirt-configurator'); ?></td>
                                                    <td>
                                                        <button type="button" class="button edit-texture" 
                                                            data-id="<?php echo esc_attr($texture['texture_id']); ?>"
                                                            data-name="<?php echo esc_attr($texture['name']); ?>"
                                                            data-file="<?php echo esc_attr($texture['file_path']); ?>"
                                                            data-thumbnail="<?php echo esc_attr($texture['thumbnail']); ?>"
                                                            data-material="<?php echo esc_attr($texture['material']); ?>"
                                                            data-color="<?php echo esc_attr($texture['color']); ?>"
                                                            data-style="<?php echo esc_attr($texture['style']); ?>"
                                                            data-price="<?php echo esc_attr($texture['price_adjustment']); ?>"
                                                            data-active="<?php echo esc_attr($texture['active']); ?>"
                                                        ><?php _e('Редактирай', 'shirt-configurator'); ?></button>
                                                        
                                                        <button type="button" class="button delete-texture" 
                                                            data-id="<?php echo esc_attr($texture['texture_id']); ?>"
                                                        ><?php _e('Изтрий', 'shirt-configurator'); ?></button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Custom Pricing Tab -->
                <div id="pricing-tab" class="tab-pane">
                    <h2><?php _e('Собствени цени', 'shirt-configurator'); ?></h2>
                    <p><?php _e('Задайте специфични цени за комбинации модел-плат.', 'shirt-configurator'); ?></p>
                    
                    <form method="post" class="custom-pricing-form">
                        <?php wp_nonce_field('shirt_configurator_pricing'); ?>
                        
                        <div class="form-field">
                            <label for="pricing-model"><?php _e('Модел', 'shirt-configurator'); ?></label>
                            <select id="pricing-model" name="model_id" required>
                                <option value=""><?php _e('Избери модел', 'shirt-configurator'); ?></option>
                                <?php foreach ($models as $model): ?>
                                    <option value="<?php echo esc_attr($model['model_id']); ?>">
                                        <?php echo esc_html($model['name']); ?> (<?php echo esc_html($model['model_id']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-field">
                            <label for="pricing-texture"><?php _e('Плат', 'shirt-configurator'); ?></label>
                            <select id="pricing-texture" name="texture_id" required>
                                <option value=""><?php _e('Избери плат', 'shirt-configurator'); ?></option>
                                <?php foreach ($textures as $texture): ?>
                                    <?php if ($texture['active']): ?>
                                        <option value="<?php echo esc_attr($texture['texture_id']); ?>">
                                            <?php echo esc_html($texture['name']); ?> (<?php echo esc_html($texture['texture_id']); ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-field">
                            <label for="pricing-price"><?php _e('Цена', 'shirt-configurator'); ?></label>
                            <input type="number" id="pricing-price" name="price" step="0.01" min="0" value="119.00" required>
                            <p class="description"><?php _e('Обща цена за тази комбинация (презаписва базовата цена на модела + корекцията на плат)', 'shirt-configurator'); ?></p>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="save_custom_pricing" class="button button-primary"><?php _e('Запази', 'shirt-configurator'); ?></button>
                        </div>
                    </form>
                    
                    <div class="custom-pricing-list">
                        <h3><?php _e('Създадени цени', 'shirt-configurator'); ?></h3>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Модел', 'shirt-configurator'); ?></th>
                                    <th><?php _e('Плат', 'shirt-configurator'); ?></th>
                                    <th><?php _e('Цена', 'shirt-configurator'); ?></th>
                                    <th><?php _e('Действия', 'shirt-configurator'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($pricing)): ?>
                                    <tr>
                                        <td colspan="4"><?php _e('Няма създадени цени.', 'shirt-configurator'); ?></td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($pricing as $price): ?>
                                        <?php 
                                            // Get model and texture names
                                            $model_name = '';
                                            foreach ($models as $model) {
                                                if ($model['model_id'] === $price['model_id']) {
                                                    $model_name = $model['name'];
                                                    break;
                                                }
                                            }
                                            
                                            $texture_name = '';
                                            foreach ($textures as $texture) {
                                                if ($texture['texture_id'] === $price['texture_id']) {
                                                    $texture_name = $texture['name'];
                                                    break;
                                                }
                                            }
                                        ?>
                                        <tr>
                                            <td><?php echo esc_html($model_name); ?> (<?php echo esc_html($price['model_id']); ?>)</td>
                                            <td><?php echo esc_html($texture_name); ?> (<?php echo esc_html($price['texture_id']); ?>)</td>
                                            <td><?php echo wc_price($price['price']); ?></td>
                                            <td>
                                                <button type="button" class="button edit-pricing" 
                                                    data-model="<?php echo esc_attr($price['model_id']); ?>"
                                                    data-texture="<?php echo esc_attr($price['texture_id']); ?>"
                                                    data-price="<?php echo esc_attr($price['price']); ?>"
                                                ><?php _e('Редактирай', 'shirt-configurator'); ?></button>
                                                
                                                <button type="button" class="button delete-pricing" 
                                                    data-id="<?php echo esc_attr($price['id']); ?>"
                                                ><?php _e('Изтрий', 'shirt-configurator'); ?></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Settings Tab -->
                <div id="settings-tab" class="tab-pane">
                    <h2><?php _e('Основни настройки', 'shirt-configurator'); ?></h2>
                    
                    <form method="post" action="">
                        <?php wp_nonce_field('shirt_configurator_settings'); ?>
                        
                        <h3><?php _e('Интеграция с WooCommerce', 'shirt-configurator'); ?></h3>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="product-id"><?php _e('ID на продукта', 'shirt-configurator'); ?></label>
                                </th>
                                <td>
                                    <?php if ($product_id > 0): ?>
                                        <p>
                                            <?php _e('ID на продукта на конфигуратора:', 'shirt-configurator'); ?> 
                                            <strong><?php echo esc_html($product_id); ?></strong>
                                            (<a href="<?php echo esc_url(get_edit_post_link($product_id)); ?>"><?php _e('Редактирай продукта', 'shirt-configurator'); ?></a>)
                                        </p>
                                    <?php else: ?>
                                        <p><?php _e('Продукт ще бъде създаден автоматично, когато първата конфигурирана риза бъде добавена в количката.', 'shirt-configurator'); ?></p>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="save_settings" class="button button-primary" value="<?php _e('Запази', 'shirt-configurator'); ?>">
                        </p>
                    </form>
                </div>
            </div>
        </div>
        
        <script>
            jQuery(document).ready(function($) {
                // Tab navigation
                var currentTab = window.location.hash || '#models-tab';
                var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
    
                // Show the current tab on page load
                $('.nav-tab-wrapper a').removeClass('nav-tab-active');
                $('.nav-tab-wrapper a[href="' + currentTab + '"]').addClass('nav-tab-active');
                $('.tab-pane').removeClass('active');
                $(currentTab).addClass('active');
                
                // Tab navigation
                $('.nav-tab-wrapper a').on('click', function(e) {
                    e.preventDefault();
                    
                    // Update active tab
                    $('.nav-tab-wrapper a').removeClass('nav-tab-active');
                    $(this).addClass('nav-tab-active');
                    
                    // Show corresponding tab content
                    var target = $(this).attr('href');
                    $('.tab-pane').removeClass('active');
                    $(target).addClass('active');
                    
                    // Update URL hash without triggering page reload
                    window.location.hash = target;
                });
                
                // File upload buttons
                $('.upload-file-button').on('click', function(e) {
                    e.preventDefault();
                    
                    var targetInput = $(this).data('target');
                    
                    var mediaUploader = wp.media({
                        title: '<?php _e('Select or Upload File', 'shirt-configurator'); ?>',
                        button: {
                            text: '<?php _e('Use this file', 'shirt-configurator'); ?>'
                        },
                        multiple: false
                    });
                    
                    mediaUploader.on('select', function() {
                        var attachment = mediaUploader.state().get('selection').first().toJSON();
                        var relativePath = attachment.url.replace(site_url, '');
                        $('#' + targetInput).val(relativePath);
                    });
                    
                    mediaUploader.open();
                });
                
                // Edit model button
                $('.edit-model').on('click', function() {
                    var id = $(this).data('id');
                    var name = $(this).data('name');
                    var file = $(this).data('file');
                    var thumbnail = $(this).data('thumbnail');
                    var price = $(this).data('price');
                    var active = $(this).data('active');
                    
                    $('#model-action').val('edit');
                    $('#model-id').val(id).prop('readonly', true);
                    $('#model-name').val(name);
                    $('#model-file').val(file);
                    $('#model-thumbnail').val(thumbnail);
                    $('#model-price').val(price);
                    $('#model-active').val(active);
                    
                    $('#cancel-model-edit').show();
                    
                    $('html, body').animate({
                        scrollTop: $('#model-form').offset().top - 50
                    }, 500);
                });
                
                // Cancel model edit
                $('#cancel-model-edit').on('click', function() {
                    $('#model-form')[0].reset();
                    $('#model-action').val('add');
                    $('#model-id').prop('readonly', false);
                    $(this).hide();
                });
                
                // Edit texture button
                $('.edit-texture').on('click', function() {
                    var id = $(this).data('id');
                    var name = $(this).data('name');
                    var file = $(this).data('file');
                    var thumbnail = $(this).data('thumbnail');
                    var material = $(this).data('material');
                    var color = $(this).data('color');
                    var style = $(this).data('style');
                    var price = $(this).data('price');
                    var active = $(this).data('active');
                    
                    $('#texture-action').val('edit');
                    $('#texture-id').val(id).prop('readonly', true);
                    $('#texture-name').val(name);
                    $('#texture-file').val(file);
                    $('#texture-thumbnail').val(thumbnail);
                    $('#texture-material').val(material);
                    $('#texture-color').val(color);
                    $('#texture-style').val(style);
                    $('#texture-price').val(price);
                    $('#texture-active').val(active);
                    
                    $('#cancel-texture-edit').show();
                    
                    $('html, body').animate({
                        scrollTop: $('#texture-form').offset().top - 50
                    }, 500);
                });
                
                // Cancel texture edit
                $('#cancel-texture-edit').on('click', function() {
                    $('#texture-form')[0].reset();
                    $('#texture-action').val('add');
                    $('#texture-id').prop('readonly', false);
                    $(this).hide();
                });
                
                // Edit pricing button
                $('.edit-pricing').on('click', function() {
                    var model = $(this).data('model');
                    var texture = $(this).data('texture');
                    var price = $(this).data('price');
                    
                    $('#pricing-model').val(model);
                    $('#pricing-texture').val(texture);
                    $('#pricing-price').val(price);
                    
                    $('html, body').animate({
                        scrollTop: $('.custom-pricing-form').offset().top - 50
                    }, 500);
                });
                
                // Delete pricing button
                $('.delete-pricing').on('click', function() {
                    if (confirm('<?php _e('Are you sure you want to delete this custom price?', 'shirt-configurator'); ?>')) {
                        var id = $(this).data('id');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'delete_custom_pricing',
                                id: id,
                                nonce: shirt_config.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    location.reload();
                                } else {
                                    alert(response.data);
                                }
                            }
                        });
                    }
                });
                
                // Model form submission
                $('#model-form').on('submit', function(e) {
                    e.preventDefault();
                    
                    var formData = $(this).serialize();
                    formData += '&action=save_model&nonce=<?php echo wp_create_nonce('shirt_configurator_admin'); ?>';
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: formData,
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                                window.location.href = window.location.href.split('#')[0] + '#models-tab';
                                location.reload();
                            } else {
                                alert(response.data);
                            }
                        }
                    });
                });
                
                // Texture form submission
                $('#texture-form').on('submit', function(e) {
                    e.preventDefault();
                    
                    var formData = $(this).serialize();
                    formData += '&action=save_texture&nonce=<?php echo wp_create_nonce('shirt_configurator_admin'); ?>';
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: formData,
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                                window.location.href = window.location.href.split('#')[0] + '#textures-tab';
                                location.reload();
                            } else {
                                alert(response.data);
                            }
                        }
                    });
                });
                
                // Delete model button
                $('.delete-model').on('click', function() {
                    if (confirm('<?php _e('Are you sure you want to delete this model?', 'shirt-configurator'); ?>')) {
                        var id = $(this).data('id');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'delete_model',
                                model_id: id,
                                nonce: '<?php echo wp_create_nonce('shirt_configurator_admin'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    window.location.href = window.location.href.split('#')[0] + '#models-tab';
                                    location.reload();
                                } else {
                                    alert(response.data);
                                }
                            }
                        });
                    }
                });
                
                // Delete texture button
                $('.delete-texture').on('click', function() {
                    console.log('Delete button clicked');
                    var id = $(this).data('id');
                    console.log('Texture ID:', id);
                    console.log('AJAX URL:', ajaxurl);
                    
                    if (confirm('<?php _e('Are you sure you want to delete this texture?', 'shirt-configurator'); ?>')) {
                        console.log('User confirmed deletion');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'delete_texture',
                                texture_id: id,
                                nonce: '<?php echo wp_create_nonce('shirt_configurator_admin'); ?>'
                            },
                            beforeSend: function() {
                                console.log('AJAX request starting...');
                            },
                            success: function(response) {
                                console.log('AJAX Success response:', response);
                                if (response.success) {
                                    // Just reload the page - the texture should be gone
                                    location.reload();
                                } else {
                                    alert('Error: ' + response.data);
                                }
                            },
                            error: function(xhr, status, error) {
                                console.log('AJAX Error:', xhr, status, error);
                                console.log('Response Text:', xhr.responseText);
                                alert('AJAX error occurred: ' + error);
                            }
                        });
                    } else {
                        console.log('User cancelled deletion');
                    }
                });
                // Delete all textures button
                $('#delete-all-textures').on('click', function() {
                    if (confirm('<?php _e('Сигурни ли сте, че искате да изтриете ВСИЧКИ платове? Това действие не може да бъде отменено!', 'shirt-configurator'); ?>')) {
                        if (confirm('<?php _e('ПОСЛЕДНО ПРЕДУПРЕЖДЕНИЕ: Това ще изтрие всички платове! Продължавате ли?', 'shirt-configurator'); ?>')) {
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'delete_all_textures',
                                    nonce: '<?php echo wp_create_nonce('shirt_configurator_admin'); ?>'
                                },
                                beforeSend: function() {
                                    $('#delete-all-textures').prop('disabled', true).text('<?php _e('Изтриване...', 'shirt-configurator'); ?>');
                                },
                                success: function(response) {
                                    if (response.success) {
                                        alert('<?php _e('Всички платове са изтрити успешно!', 'shirt-configurator'); ?>');
                                        location.reload();
                                    } else {
                                        alert('Error: ' + response.data);
                                        $('#delete-all-textures').prop('disabled', false).text('<?php _e('ИЗТРИЙ ВСИЧКИ', 'shirt-configurator'); ?>');
                                    }
                                },
                                error: function() {
                                    alert('<?php _e('Възникна грешка при изтриването!', 'shirt-configurator'); ?>');
                                    $('#delete-all-textures').prop('disabled', false).text('<?php _e('ИЗТРИЙ ВСИЧКИ', 'shirt-configurator'); ?>');
                                }
                            });
                        }
                    }
                });


            });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for deleting custom pricing
     */
    public function ajax_delete_custom_pricing() {
        check_ajax_referer('shirt_configurator_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (empty($id)) {
            wp_send_json_error('Missing ID');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'shirt_pricing';
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $id)
        );
        
        if ($result === false) {
            wp_send_json_error('Database error: ' . $wpdb->last_error);
        } else {
            wp_send_json_success(array(
                'message' => 'Custom price deleted successfully'
            ));
        }
    }
    
    /**
     * Create database tables on plugin activation
     */
    public static function activate() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create models table
        $table_models = $wpdb->prefix . 'shirt_models';
        $sql_models = "CREATE TABLE $table_models (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            model_id varchar(50) NOT NULL,
            name varchar(100) NOT NULL,
            file_path varchar(255) NOT NULL,
            thumbnail varchar(255) DEFAULT '' NOT NULL,
            base_price decimal(10,2) DEFAULT 119.00 NOT NULL,
            active tinyint(1) DEFAULT 1 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY model_id (model_id)
        ) $charset_collate;";
        
        // Create textures table
        $table_textures = $wpdb->prefix . 'shirt_textures';
        $sql_textures = "CREATE TABLE $table_textures (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            texture_id varchar(50) NOT NULL,
            name varchar(100) NOT NULL,
            file_path varchar(255) NOT NULL,
            thumbnail varchar(255) DEFAULT '' NOT NULL,
            material varchar(100) DEFAULT '' NOT NULL,
            color varchar(100) DEFAULT '' NOT NULL,
            style varchar(100) DEFAULT '' NOT NULL,
            price_adjustment decimal(10,2) DEFAULT 0.00 NOT NULL,
            active tinyint(1) DEFAULT 1 NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY texture_id (texture_id)
        ) $charset_collate;";
        
        // Create custom pricing table
        $table_pricing = $wpdb->prefix . 'shirt_pricing';
        $sql_pricing = "CREATE TABLE $table_pricing (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            model_id varchar(50) NOT NULL,
            texture_id varchar(50) NOT NULL,
            price decimal(10,2) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY model_texture (model_id, texture_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_models);
        dbDelta($sql_textures);
        dbDelta($sql_pricing);
        
        // Insert default models if table is empty
        $count_models = $wpdb->get_var("SELECT COUNT(*) FROM $table_models");
        if ($count_models == 0) {
            $default_models = array(
                array(
                    'model_id' => 'men1',
                    'name' => 'Мъжка Риза Модел 6(ДР-ОЯ)',
                    'file_path' => '/models/men1.glb',
                    'thumbnail' => '/textures/m1.svg',
                    'base_price' => 119.00
                ),
                array(
                    'model_id' => 'men2',
                    'name' => 'Мъжка Риза Модел 4(КР)',
                    'file_path' => '/models/men2.glb',
                    'thumbnail' => '/textures/m2.svg',
                    'base_price' => 129.00
                ),
                array(
                    'model_id' => 'men3',
                    'name' => 'Мъжка Риза Модел 6-1(ДР-ПЯ)',
                    'file_path' => '/models/men3.glb',
                    'thumbnail' => '/textures/m3.svg',
                    'base_price' => 139.00
                ),
                array(
                    'model_id' => 'women1',
                    'name' => 'Дамска Риза Модел 5(КР)',
                    'file_path' => '/models/women1.glb',
                    'thumbnail' => '/textures/w1.svg',
                    'base_price' => 119.00
                ),
                array(
                    'model_id' => 'women2',
                    'name' => 'Дамска Риза Модел 7(ДР-ОЯ)',
                    'file_path' => '/models/women2.glb',
                    'thumbnail' => '/textures/w2.svg',
                    'base_price' => 129.00
                )
            );
            
            foreach ($default_models as $model) {
                $wpdb->insert($table_models, $model);
            }
        }
        
        // Insert default textures if table is empty
        $count_textures = $wpdb->get_var("SELECT COUNT(*) FROM $table_textures");
        if ($count_textures == 0) {
            // Extract texture data from HTML
            $textures = self::extract_textures_from_html();
            
            foreach ($textures as $texture) {
                $wpdb->insert($table_textures, $texture);
            }
        }
    }
    
    /**
     * Extract texture data from HTML
     */
    private static function extract_textures_from_html() {
        $textures = array();
        
        // Path to index.html
        $index_path = plugin_dir_path(__FILE__) . '../index.html';
        
        if (file_exists($index_path)) {
            $html = file_get_contents($index_path);
            
            // Use regex to extract texture data
            preg_match_all('/<div class="texture-btn" data-texture="(fabric\d+)">\s*<div class="texture-image" style="background-image: url\(\'textures\/(\d+)\.jpg\'\)"><\/div>\s*<div class="texture-info">\s*<div class="texture-name">(.*?)<\/div>\s*<div class="texture-properties">(.*?)<\/div>\s*<div class="texture-properties">(.*?)<\/div>\s*<div class="texture-properties">(.*?)<\/div>/s', $html, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $texture_id = $match[1];
                $file_num = $match[2];
                $name = $match[3];
                $material = $match[4];
                $color = $match[5];
                $style = $match[6];
                
                $textures[] = array(
                    'texture_id' => $texture_id,
                    'name' => $name,
                    'file_path' => '/textures/' . $file_num . '.jpg',
                    'thumbnail' => '/textures/' . $file_num . '.jpg',
                    'material' => $material,
                    'color' => $color,
                    'style' => $style,
                    'price_adjustment' => 0.00
                );
            }
        }
        
        // If no textures were extracted, add some defaults
        if (empty($textures)) {
            for ($i = 1; $i <= 14; $i++) {
                $textures[] = array(
                    'texture_id' => 'fabric' . $i,
                    'name' => 'Плат ' . sprintf('%02d', $i),
                    'file_path' => '/textures/' . $i . '.jpg',
                    'thumbnail' => '/textures/' . $i . '.jpg',
                    'material' => '96% памук 4% еластан',
                    'color' => 'СИН',
                    'style' => 'ЕЛЕГАНТ',
                    'price_adjustment' => 0.00
                );
            }
        }
        
        return $textures;
    }
    
/**
 * Add CORS headers for all requests
 */
public function add_cors_headers() {
    // Always allow these origins
    $allowed_origins = array(
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'https://3d.kidn3y.com',
        'https://kidn3y.com',
        'https://(server-domain)'
    );
    
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    
    // If the origin is in our allowed list or we're in development mode
    if (in_array($origin, $allowed_origins) || defined('WP_DEBUG') && WP_DEBUG) {
        header("Access-Control-Allow-Origin: {$origin}");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Headers: X-WC-Store-API-Nonce, Content-Type, Authorization");
        
        // Handle preflight OPTIONS requests
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            status_header(200);
            exit;
        }
    }
}


    
}

// Initialize the plugin
$shirt_configurator = new Shirt_Configurator_Integration();

// Register activation hook
register_activation_hook(__FILE__, array('Shirt_Configurator_Integration', 'activate'));

// Add admin scripts
add_action('admin_enqueue_scripts', array($shirt_configurator, 'admin_scripts'));

// Register AJAX handlers for admin
add_action('wp_ajax_save_model', array($shirt_configurator, 'ajax_save_model'));
add_action('wp_ajax_delete_model', array($shirt_configurator, 'ajax_delete_model'));
add_action('wp_ajax_save_texture', array($shirt_configurator, 'ajax_save_texture'));
add_action('wp_ajax_delete_texture', array($shirt_configurator, 'ajax_delete_texture'));
add_action('wp_ajax_delete_custom_pricing', array($shirt_configurator, 'ajax_delete_custom_pricing'));

// Add admin scripts
add_action('admin_enqueue_scripts', array($shirt_configurator, 'admin_scripts'));