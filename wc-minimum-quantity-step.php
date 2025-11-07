<?php
/**
 * Plugin Name: WooCommerce Minimum Quantity Step
 * Plugin URI: https://github.com/robbertvermeulen/wc-minimum-quantity-step
 * Description: Set minimum quantity steps per product while keeping default quantity at 1 for Google Shopping compliance
 * Version: 1.0.0
 * Author: Robbert Vermeulen
 * Author URI: https://github.com/robbertvermeulen
 * Text Domain: wc-minimum-quantity-step
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WC_MIN_QTY_STEP_VERSION', '1.0.0');
define('WC_MIN_QTY_STEP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_MIN_QTY_STEP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_MIN_QTY_STEP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Declare compatibility with WooCommerce features
 */
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

/**
 * Main plugin class
 */
class WC_Minimum_Quantity_Step {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Initialize immediately
        $this->init();
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Load text domain
        load_plugin_textdomain('wc-minimum-quantity-step', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Admin hooks - Add custom field to product
        add_action('woocommerce_product_options_inventory_product_data', array($this, 'add_quantity_step_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_quantity_step_field'));

        // Variable product hooks
        add_action('woocommerce_variation_options_pricing', array($this, 'add_variation_quantity_step_field'), 10, 3);
        add_action('woocommerce_save_product_variation', array($this, 'save_variation_quantity_step_field'), 10, 2);

        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_filter('woocommerce_available_variation', array($this, 'add_variation_quantity_step'), 10, 3);

        // Validation hooks
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_quantity_step'), 10, 3);
        add_filter('woocommerce_update_cart_validation', array($this, 'validate_cart_quantity_step'), 10, 4);

        // AJAX endpoint for getting step value
        add_action('wp_ajax_get_quantity_step', array($this, 'ajax_get_quantity_step'));
        add_action('wp_ajax_nopriv_get_quantity_step', array($this, 'ajax_get_quantity_step'));
    }

    /**
     * Display notice if WooCommerce is not active
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="error">
            <p><?php esc_html_e('WooCommerce Minimum Quantity Step requires WooCommerce to be installed and active.', 'wc-minimum-quantity-step'); ?></p>
        </div>
        <?php
    }

    /**
     * Add quantity step field to product inventory tab
     */
    public function add_quantity_step_field() {
        global $post;

        if (!$post || !$post->ID) {
            return;
        }

        echo '<div class="options_group show_if_simple show_if_variable">';

        woocommerce_wp_text_input(
            array(
                'id'          => '_minimum_quantity_step',
                'label'       => __('Minimum Quantity Step', 'wc-minimum-quantity-step'),
                'desc_tip'    => true,
                'description' => __('Set the minimum quantity step for this product (e.g., 2 means customers can only order in multiples of 2). Leave empty or set to 1 for no restrictions.', 'wc-minimum-quantity-step'),
                'type'        => 'number',
                'custom_attributes' => array(
                    'step' => '1',
                    'min'  => '1'
                ),
                'value'       => get_post_meta($post->ID, '_minimum_quantity_step', true)
            )
        );

        echo '</div>';
    }

    /**
     * Save quantity step field
     */
    public function save_quantity_step_field($post_id) {
        $step = isset($_POST['_minimum_quantity_step']) ? absint($_POST['_minimum_quantity_step']) : '';
        update_post_meta($post_id, '_minimum_quantity_step', $step);
    }

    /**
     * Add quantity step field to variation
     */
    public function add_variation_quantity_step_field($loop, $variation_data, $variation) {
        $variation_id = $variation->ID;
        $step_value = get_post_meta($variation_id, '_minimum_quantity_step', true);

        echo '<div class="form-row form-row-full">';

        woocommerce_wp_text_input(
            array(
                'id'            => '_minimum_quantity_step_' . $loop,
                'name'          => '_minimum_quantity_step[' . $loop . ']',
                'label'         => __('Minimum Quantity Step', 'wc-minimum-quantity-step'),
                'desc_tip'      => true,
                'description'   => __('Set the minimum quantity step for this variation (e.g., 2 means customers can only order in multiples of 2). Leave empty or set to 1 for no restrictions.', 'wc-minimum-quantity-step'),
                'type'          => 'number',
                'value'         => $step_value,
                'wrapper_class' => 'form-row form-row-full',
                'custom_attributes' => array(
                    'step' => '1',
                    'min'  => '1'
                )
            )
        );

        echo '</div>';
    }

    /**
     * Save variation quantity step field
     */
    public function save_variation_quantity_step_field($variation_id, $loop) {
        if (isset($_POST['_minimum_quantity_step'][$loop])) {
            $step = absint($_POST['_minimum_quantity_step'][$loop]);
            update_post_meta($variation_id, '_minimum_quantity_step', $step);
        }
    }

    /**
     * Get quantity step for a product
     */
    public function get_product_quantity_step($product_id) {
        $step = get_post_meta($product_id, '_minimum_quantity_step', true);
        return !empty($step) && $step > 1 ? absint($step) : 1;
    }

    /**
     * Add quantity step to variation data
     */
    public function add_variation_quantity_step($variation_data, $product, $variation) {
        $step = $this->get_product_quantity_step($variation->get_id());
        $variation_data['minimum_quantity_step'] = $step;
        return $variation_data;
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        if (is_product()) {
            // Enqueue CSS
            wp_enqueue_style(
                'wc-minimum-quantity-step',
                WC_MIN_QTY_STEP_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                WC_MIN_QTY_STEP_VERSION
            );

            // Enqueue JS
            wp_enqueue_script(
                'wc-minimum-quantity-step',
                WC_MIN_QTY_STEP_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                WC_MIN_QTY_STEP_VERSION,
                true
            );

            // Get current product
            global $post;
            $product = wc_get_product($post->ID);

            // Prepare data for JS
            $step = $this->get_product_quantity_step($post->ID);

            wp_localize_script('wc-minimum-quantity-step', 'wcMinQtyStep', array(
                'product_id' => $post->ID,
                'step' => $step,
                'ajax_url' => admin_url('admin-ajax.php'),
                'i18n' => array(
                    'error_message' => sprintf(
                        __('This product must be ordered in multiples of %d.', 'wc-minimum-quantity-step'),
                        $step
                    ),
                    'error_message_dynamic' => __('This product must be ordered in multiples of %d.', 'wc-minimum-quantity-step')
                )
            ));
        }
    }

    /**
     * AJAX handler to get quantity step for a product
     */
    public function ajax_get_quantity_step() {
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

        if (!$product_id) {
            wp_send_json_error(array('message' => 'Invalid product ID'));
        }

        $step = $this->get_product_quantity_step($product_id);

        wp_send_json_success(array(
            'step' => $step,
            'message' => sprintf(
                __('This product must be ordered in multiples of %d.', 'wc-minimum-quantity-step'),
                $step
            )
        ));
    }

    /**
     * Validate quantity when adding to cart
     */
    public function validate_quantity_step($passed, $product_id, $quantity) {
        $step = $this->get_product_quantity_step($product_id);

        // If step is 1 or empty, no validation needed
        if ($step <= 1) {
            return $passed;
        }

        // Check if quantity is a multiple of step
        if ($quantity % $step !== 0) {
            wc_add_notice(
                sprintf(
                    __('"%s" must be ordered in multiples of %d. Please adjust the quantity.', 'wc-minimum-quantity-step'),
                    get_the_title($product_id),
                    $step
                ),
                'error'
            );
            return false;
        }

        return $passed;
    }

    /**
     * Validate quantity when updating cart
     */
    public function validate_cart_quantity_step($passed, $cart_item_key, $values, $quantity) {
        $product_id = $values['product_id'];
        $step = $this->get_product_quantity_step($product_id);

        // If step is 1 or empty, no validation needed
        if ($step <= 1) {
            return $passed;
        }

        // Check if quantity is a multiple of step
        if ($quantity % $step !== 0) {
            wc_add_notice(
                sprintf(
                    __('"%s" must be ordered in multiples of %d. Please adjust the quantity.', 'wc-minimum-quantity-step'),
                    get_the_title($product_id),
                    $step
                ),
                'error'
            );
            return false;
        }

        return $passed;
    }
}

/**
 * Initialize the plugin
 */
function wc_minimum_quantity_step_init() {
    return WC_Minimum_Quantity_Step::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'wc_minimum_quantity_step_init');
