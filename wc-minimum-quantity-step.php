<?php
/**
 * Plugin Name: WooCommerce Minimum Quantity Step
 * Plugin URI: https://github.com/robbertvermeulen/wc-minimum-quantity-step
 * Description: Set minimum/maximum quantities and quantity steps per product while keeping default quantity at 1 for Google Shopping compliance
 * Version: 1.2.3
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
define('WC_MIN_QTY_STEP_VERSION', '1.2.3');
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
        // Load text domain on init hook for translations
        add_action('init', array($this, 'load_textdomain'));

        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

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
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_quantity_step'), 10, 5);
        add_filter('woocommerce_update_cart_validation', array($this, 'validate_cart_quantity_step'), 10, 4);
        add_action('woocommerce_checkout_process', array($this, 'validate_checkout_quantities'));

        // AJAX endpoint for getting step value
        add_action('wp_ajax_get_quantity_step', array($this, 'ajax_get_quantity_step'));
        add_action('wp_ajax_nopriv_get_quantity_step', array($this, 'ajax_get_quantity_step'));
    }

    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain('wc-minimum-quantity-step', false, dirname(plugin_basename(__FILE__)) . '/languages');
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
     * Add quantity restriction fields to product inventory tab
     */
    public function add_quantity_step_field() {
        global $post;

        if (!$post || !$post->ID) {
            return;
        }

        echo '<div class="options_group show_if_simple show_if_variable">';

        // Minimum Quantity Step
        woocommerce_wp_text_input(
            array(
                'id'          => '_minimum_quantity_step',
                'label'       => __('Minimum Quantity Step', 'wc-minimum-quantity-step'),
                'desc_tip'    => true,
                'description' => __('Customers can only order in multiples of this number (e.g., 2 means 2, 4, 6, 8...). Leave empty or 1 for no restriction.', 'wc-minimum-quantity-step'),
                'type'        => 'number',
                'custom_attributes' => array(
                    'step' => '1',
                    'min'  => '0'
                ),
                'value'       => get_post_meta($post->ID, '_minimum_quantity_step', true),
                'placeholder' => '1'
            )
        );

        // Minimum Quantity
        woocommerce_wp_text_input(
            array(
                'id'          => '_minimum_quantity',
                'label'       => __('Minimum Quantity', 'wc-minimum-quantity-step'),
                'desc_tip'    => true,
                'description' => __('Minimum number of items that must be ordered. Leave empty for no minimum.', 'wc-minimum-quantity-step'),
                'type'        => 'number',
                'custom_attributes' => array(
                    'step' => '1',
                    'min'  => '0'
                ),
                'value'       => get_post_meta($post->ID, '_minimum_quantity', true),
                'placeholder' => ''
            )
        );

        // Maximum Quantity
        woocommerce_wp_text_input(
            array(
                'id'          => '_maximum_quantity',
                'label'       => __('Maximum Quantity', 'wc-minimum-quantity-step'),
                'desc_tip'    => true,
                'description' => __('Maximum number of items that can be ordered. Leave empty for no maximum.', 'wc-minimum-quantity-step'),
                'type'        => 'number',
                'custom_attributes' => array(
                    'step' => '1',
                    'min'  => '0'
                ),
                'value'       => get_post_meta($post->ID, '_maximum_quantity', true),
                'placeholder' => ''
            )
        );

        // Apply to all variations checkbox (only for variable products)
        $product = wc_get_product($post->ID);
        if ($product && $product->is_type('variable')) {
            woocommerce_wp_checkbox(
                array(
                    'id'          => '_apply_restrictions_to_variations',
                    'label'       => __('Apply to all variations', 'wc-minimum-quantity-step'),
                    'description' => __('When enabled, all variations will use the above quantity restrictions instead of their individual settings.', 'wc-minimum-quantity-step'),
                    'wrapper_class' => 'show_if_variable',
                    'value'       => get_post_meta($post->ID, '_apply_restrictions_to_variations', true)
                )
            );
        }

        echo '</div>';
    }

    /**
     * Save quantity restriction fields
     */
    public function save_quantity_step_field($post_id) {
        // Check if we're saving variation data (arrays) instead of parent data
        // Variation fields come as arrays: _maximum_quantity[0], _maximum_quantity[1], etc.
        if (isset($_POST['_minimum_quantity_step']) && is_array($_POST['_minimum_quantity_step'])) {
            return; // This is a variation save, don't touch parent
        }
        if (isset($_POST['_minimum_quantity']) && is_array($_POST['_minimum_quantity'])) {
            return; // This is a variation save, don't touch parent
        }
        if (isset($_POST['_maximum_quantity']) && is_array($_POST['_maximum_quantity'])) {
            return; // This is a variation save, don't touch parent
        }

        // Only save if the parent product fields were actually submitted
        // When editing variations only, these fields won't be in POST
        if (!isset($_POST['_minimum_quantity_step']) && !isset($_POST['_minimum_quantity']) && !isset($_POST['_maximum_quantity'])) {
            return;
        }

        // Save step
        if (isset($_POST['_minimum_quantity_step'])) {
            $step = absint($_POST['_minimum_quantity_step']);
            update_post_meta($post_id, '_minimum_quantity_step', $step);
        }

        // Save minimum
        if (isset($_POST['_minimum_quantity'])) {
            $min = absint($_POST['_minimum_quantity']);
            update_post_meta($post_id, '_minimum_quantity', $min);
        }

        // Save checkbox for applying to variations
        if (isset($_POST['_apply_restrictions_to_variations'])) {
            update_post_meta($post_id, '_apply_restrictions_to_variations', 'yes');
        } else {
            // Only update if we're actually saving parent fields
            if (isset($_POST['_minimum_quantity_step']) || isset($_POST['_minimum_quantity']) || isset($_POST['_maximum_quantity'])) {
                update_post_meta($post_id, '_apply_restrictions_to_variations', 'no');
            }
        }

        // Save maximum
        if (isset($_POST['_maximum_quantity'])) {
            $max = absint($_POST['_maximum_quantity']);
            update_post_meta($post_id, '_maximum_quantity', $max);
        }
    }

    /**
     * Add quantity restriction fields to variation
     */
    public function add_variation_quantity_step_field($loop, $variation_data, $variation) {
        $variation_id = $variation->ID;

        echo '<div class="form-row form-row-full">';

        // Step
        woocommerce_wp_text_input(
            array(
                'id'            => '_minimum_quantity_step_' . $loop,
                'name'          => '_minimum_quantity_step[' . $loop . ']',
                'label'         => __('Min. Quantity Step', 'wc-minimum-quantity-step'),
                'desc_tip'      => true,
                'description'   => __('Order in multiples (e.g., 2 = 2, 4, 6...).', 'wc-minimum-quantity-step'),
                'type'          => 'number',
                'value'         => get_post_meta($variation_id, '_minimum_quantity_step', true),
                'wrapper_class' => 'form-row form-row-first',
                'placeholder'   => '1',
                'custom_attributes' => array(
                    'step' => '1',
                    'min'  => '0'
                )
            )
        );

        // Minimum
        woocommerce_wp_text_input(
            array(
                'id'            => '_minimum_quantity_' . $loop,
                'name'          => '_minimum_quantity[' . $loop . ']',
                'label'         => __('Min. Quantity', 'wc-minimum-quantity-step'),
                'desc_tip'      => true,
                'description'   => __('Minimum number required.', 'wc-minimum-quantity-step'),
                'type'          => 'number',
                'value'         => get_post_meta($variation_id, '_minimum_quantity', true),
                'wrapper_class' => 'form-row form-row-last',
                'placeholder'   => '',
                'custom_attributes' => array(
                    'step' => '1',
                    'min'  => '0'
                )
            )
        );

        echo '</div><div class="form-row form-row-full">';

        // Maximum
        woocommerce_wp_text_input(
            array(
                'id'            => '_maximum_quantity_' . $loop,
                'name'          => '_maximum_quantity[' . $loop . ']',
                'label'         => __('Max. Quantity', 'wc-minimum-quantity-step'),
                'desc_tip'      => true,
                'description'   => __('Maximum number allowed.', 'wc-minimum-quantity-step'),
                'type'          => 'number',
                'value'         => get_post_meta($variation_id, '_maximum_quantity', true),
                'wrapper_class' => 'form-row form-row-full',
                'placeholder'   => '',
                'custom_attributes' => array(
                    'step' => '1',
                    'min'  => '0'
                )
            )
        );

        echo '</div>';
    }

    /**
     * Save variation quantity restriction fields
     */
    public function save_variation_quantity_step_field($variation_id, $loop) {
        // Save step
        if (isset($_POST['_minimum_quantity_step'][$loop])) {
            $step = absint($_POST['_minimum_quantity_step'][$loop]);
            update_post_meta($variation_id, '_minimum_quantity_step', $step);
        }

        // Save minimum
        if (isset($_POST['_minimum_quantity'][$loop])) {
            $min = absint($_POST['_minimum_quantity'][$loop]);
            update_post_meta($variation_id, '_minimum_quantity', $min);
        }

        // Save maximum
        if (isset($_POST['_maximum_quantity'][$loop])) {
            $max = absint($_POST['_maximum_quantity'][$loop]);
            update_post_meta($variation_id, '_maximum_quantity', $max);
        }
    }

    /**
     * Get quantity step for a product
     * If it's a variation and parent has "apply to variations" enabled, use parent value
     */
    public function get_product_quantity_step($product_id) {
        // Check if this is a variation and if parent wants to apply restrictions
        $product = wc_get_product($product_id);
        if ($product && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $apply_to_variations = get_post_meta($parent_id, '_apply_restrictions_to_variations', true);

            if ($apply_to_variations === 'yes') {
                // Use parent value
                $step = get_post_meta($parent_id, '_minimum_quantity_step', true);
                return !empty($step) && $step > 1 ? absint($step) : 1;
            }
        }

        // Use product's own value
        $step = get_post_meta($product_id, '_minimum_quantity_step', true);
        return !empty($step) && $step > 1 ? absint($step) : 1;
    }

    /**
     * Get minimum quantity for a product
     * If it's a variation and parent has "apply to variations" enabled, use parent value
     */
    public function get_product_minimum_quantity($product_id) {
        // Check if this is a variation and if parent wants to apply restrictions
        $product = wc_get_product($product_id);
        if ($product && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $apply_to_variations = get_post_meta($parent_id, '_apply_restrictions_to_variations', true);

            if ($apply_to_variations === 'yes') {
                // Use parent value
                $min = get_post_meta($parent_id, '_minimum_quantity', true);
                return !empty($min) && $min > 0 ? absint($min) : 0;
            }
        }

        // Use product's own value
        $min = get_post_meta($product_id, '_minimum_quantity', true);
        return !empty($min) && $min > 0 ? absint($min) : 0;
    }

    /**
     * Get maximum quantity for a product
     * If it's a variation and parent has "apply to variations" enabled, use parent value
     */
    public function get_product_maximum_quantity($product_id) {
        // Check if this is a variation and if parent wants to apply restrictions
        $product = wc_get_product($product_id);
        if ($product && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $apply_to_variations = get_post_meta($parent_id, '_apply_restrictions_to_variations', true);

            if ($apply_to_variations === 'yes') {
                // Use parent value
                $max = get_post_meta($parent_id, '_maximum_quantity', true);
                return !empty($max) && $max > 0 ? absint($max) : 0;
            }
        }

        // Use product's own value
        $max = get_post_meta($product_id, '_maximum_quantity', true);
        return !empty($max) && $max > 0 ? absint($max) : 0;
    }

    /**
     * Get current cart quantity for a specific product/variation
     *
     * @param int $product_id Product or variation ID
     * @return int Current quantity in cart
     */
    public function get_cart_quantity_for_item($product_id) {
        $cart = WC()->cart;

        if (!$cart || $cart->is_empty()) {
            return 0;
        }

        $quantity = 0;

        foreach ($cart->get_cart() as $cart_item) {
            $cart_product_id = isset($cart_item['variation_id']) && $cart_item['variation_id'] > 0
                ? $cart_item['variation_id']
                : $cart_item['product_id'];

            if ($cart_product_id == $product_id) {
                $quantity += $cart_item['quantity'];
            }
        }

        return $quantity;
    }

    /**
     * Get total cart quantity for all variations of a parent product
     *
     * @param int $parent_id Parent product ID
     * @return int Total quantity of all variations in cart
     */
    public function get_cart_quantity_for_product_family($parent_id) {
        $cart = WC()->cart;

        if (!$cart || $cart->is_empty()) {
            return 0;
        }

        $quantity = 0;

        foreach ($cart->get_cart() as $cart_item) {
            // Check if this cart item belongs to the parent product
            if ($cart_item['product_id'] == $parent_id) {
                $quantity += $cart_item['quantity'];
            }
        }

        return $quantity;
    }

    /**
     * Add quantity restrictions to variation data
     */
    public function add_variation_quantity_step($variation_data, $product, $variation) {
        $variation_id = $variation->get_id();
        $variation_data['minimum_quantity_step'] = $this->get_product_quantity_step($variation_id);
        $variation_data['minimum_quantity'] = $this->get_product_minimum_quantity($variation_id);
        $variation_data['maximum_quantity'] = $this->get_product_maximum_quantity($variation_id);
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

            // Get all quantity restrictions
            $step = $this->get_product_quantity_step($post->ID);
            $min = $this->get_product_minimum_quantity($post->ID);
            $max = $this->get_product_maximum_quantity($post->ID);

            wp_localize_script('wc-minimum-quantity-step', 'wcMinQtyStep', array(
                'product_id' => $post->ID,
                'step' => $step,
                'min' => $min,
                'max' => $max,
                'ajax_url' => admin_url('admin-ajax.php'),
                'i18n' => array(
                    'error_step' => __('Dit product moet besteld worden in veelvouden van %d.', 'wc-minimum-quantity-step'),
                    'error_min' => __('Dit product vereist minimaal %d stuks.', 'wc-minimum-quantity-step'),
                    'error_max' => __('Dit product staat maximaal %d stuks toe.', 'wc-minimum-quantity-step')
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
    public function validate_quantity_step($passed, $product_id, $quantity, $variation_id = 0, $variations = array()) {
        // Use variation ID if it exists, otherwise use product ID
        $actual_product_id = $variation_id > 0 ? $variation_id : $product_id;

        // Get current cart quantity for this item (Feature 2)
        $cart_quantity = $this->get_cart_quantity_for_item($actual_product_id);
        $total_quantity = $cart_quantity + $quantity;

        $step = $this->get_product_quantity_step($actual_product_id);
        $min = $this->get_product_minimum_quantity($actual_product_id);
        $max = $this->get_product_maximum_quantity($actual_product_id);

        // Get product object for proper name
        $product = wc_get_product($actual_product_id);
        $product_name = $product ? $product->get_name() : get_the_title($actual_product_id);

        // Check step (multiples) with total quantity
        if ($step > 1 && $total_quantity % $step !== 0) {
            wc_add_notice(
                sprintf(
                    __('"%s" moet besteld worden in veelvouden van %d. Je hebt al %d in je winkelwagen.', 'wc-minimum-quantity-step'),
                    $product_name,
                    $step,
                    $cart_quantity
                ),
                'error'
            );
            $passed = false;
        }

        // Check minimum with total quantity
        if ($min > 0 && $total_quantity < $min) {
            wc_add_notice(
                sprintf(
                    __('"%s" vereist minimaal %d stuks. Je hebt al %d in je winkelwagen.', 'wc-minimum-quantity-step'),
                    $product_name,
                    $min,
                    $cart_quantity
                ),
                'error'
            );
            $passed = false;
        }

        // Check maximum with total quantity
        if ($max > 0 && $total_quantity > $max) {
            wc_add_notice(
                sprintf(
                    __('"%s" staat maximaal %d stuks toe. Je hebt al %d in je winkelwagen.', 'wc-minimum-quantity-step'),
                    $product_name,
                    $max,
                    $cart_quantity
                ),
                'error'
            );
            $passed = false;
        }

        // Feature 1: Check parent max for variations (only if apply_to_variations is OFF)
        if ($product && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $apply_to_variations = get_post_meta($parent_id, '_apply_restrictions_to_variations', true);

            // Only check parent max if vinkje is OFF
            if ($apply_to_variations !== 'yes') {
                $parent_max = get_post_meta($parent_id, '_maximum_quantity', true);
                $parent_max = !empty($parent_max) && $parent_max > 0 ? absint($parent_max) : 0;

                if ($parent_max > 0) {
                    // Get total quantity of ALL variations of this parent in cart
                    $family_cart_quantity = $this->get_cart_quantity_for_product_family($parent_id);
                    $family_total_quantity = $family_cart_quantity + $quantity;

                    if ($family_total_quantity > $parent_max) {
                        $parent_product = wc_get_product($parent_id);
                        $parent_name = $parent_product ? $parent_product->get_name() : get_the_title($parent_id);

                        wc_add_notice(
                            sprintf(
                                __('"%s" (alle variaties samen) staat maximaal %d stuks toe. Je hebt al %d in je winkelwagen.', 'wc-minimum-quantity-step'),
                                $parent_name,
                                $parent_max,
                                $family_cart_quantity
                            ),
                            'error'
                        );
                        $passed = false;
                    }
                }
            }
        }

        return $passed;
    }

    /**
     * Validate quantity when updating cart
     */
    public function validate_cart_quantity_step($passed, $cart_item_key, $values, $quantity) {
        // Use variation ID if it exists, otherwise use product ID
        $product_id = isset($values['variation_id']) && $values['variation_id'] > 0
            ? $values['variation_id']
            : $values['product_id'];

        $step = $this->get_product_quantity_step($product_id);
        $min = $this->get_product_minimum_quantity($product_id);
        $max = $this->get_product_maximum_quantity($product_id);

        // Get product object for proper name
        $product = wc_get_product($product_id);
        $product_name = $product ? $product->get_name() : get_the_title($product_id);

        // Check step (multiples)
        if ($step > 1 && $quantity % $step !== 0) {
            wc_add_notice(
                sprintf(
                    __('"%s" moet besteld worden in veelvouden van %d.', 'wc-minimum-quantity-step'),
                    $product_name,
                    $step
                ),
                'error'
            );
            $passed = false;
        }

        // Check minimum
        if ($min > 0 && $quantity < $min) {
            wc_add_notice(
                sprintf(
                    __('"%s" vereist minimaal %d stuks.', 'wc-minimum-quantity-step'),
                    $product_name,
                    $min
                ),
                'error'
            );
            $passed = false;
        }

        // Check maximum
        if ($max > 0 && $quantity > $max) {
            wc_add_notice(
                sprintf(
                    __('"%s" staat maximaal %d stuks toe.', 'wc-minimum-quantity-step'),
                    $product_name,
                    $max
                ),
                'error'
            );
            $passed = false;
        }

        // Feature 1: Check parent max for variations (only if apply_to_variations is OFF)
        if ($product && $product->is_type('variation')) {
            $parent_id = $product->get_parent_id();
            $apply_to_variations = get_post_meta($parent_id, '_apply_restrictions_to_variations', true);

            // Only check parent max if vinkje is OFF
            if ($apply_to_variations !== 'yes') {
                $parent_max = get_post_meta($parent_id, '_maximum_quantity', true);
                $parent_max = !empty($parent_max) && $parent_max > 0 ? absint($parent_max) : 0;

                if ($parent_max > 0) {
                    $cart = WC()->cart;
                    if ($cart && !$cart->is_empty()) {
                        // Calculate total quantity of all variations except current item
                        $family_total = 0;
                        foreach ($cart->get_cart() as $item_key => $cart_item) {
                            if ($cart_item['product_id'] == $parent_id) {
                                // Skip current item being updated
                                if ($item_key === $cart_item_key) {
                                    continue;
                                }
                                $family_total += $cart_item['quantity'];
                            }
                        }

                        // Add new quantity
                        $family_total += $quantity;

                        if ($family_total > $parent_max) {
                            $parent_product = wc_get_product($parent_id);
                            $parent_name = $parent_product ? $parent_product->get_name() : get_the_title($parent_id);

                            wc_add_notice(
                                sprintf(
                                    __('"%s" (alle variaties samen) staat maximaal %d stuks toe.', 'wc-minimum-quantity-step'),
                                    $parent_name,
                                    $parent_max
                                ),
                                'error'
                            );
                            $passed = false;
                        }
                    }
                }
            }
        }

        return $passed;
    }

    /**
     * Validate all cart quantities during checkout process
     * This is a final safety check before order placement
     */
    public function validate_checkout_quantities() {
        $cart = WC()->cart;

        if (!$cart || $cart->is_empty()) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            // Use variation ID if it exists, otherwise use product ID
            $product_id = isset($cart_item['variation_id']) && $cart_item['variation_id'] > 0
                ? $cart_item['variation_id']
                : $cart_item['product_id'];

            $quantity = $cart_item['quantity'];
            $step = $this->get_product_quantity_step($product_id);
            $min = $this->get_product_minimum_quantity($product_id);
            $max = $this->get_product_maximum_quantity($product_id);

            // Get product object for proper name
            $product = wc_get_product($product_id);
            $product_name = $product ? $product->get_name() : get_the_title($product_id);

            // Check step (multiples)
            if ($step > 1 && $quantity % $step !== 0) {
                wc_add_notice(
                    sprintf(
                        __('"%s" moet besteld worden in veelvouden van %d. Update je winkelwagen.', 'wc-minimum-quantity-step'),
                        $product_name,
                        $step
                    ),
                    'error'
                );
            }

            // Check minimum
            if ($min > 0 && $quantity < $min) {
                wc_add_notice(
                    sprintf(
                        __('"%s" vereist minimaal %d stuks. Update je winkelwagen.', 'wc-minimum-quantity-step'),
                        $product_name,
                        $min
                    ),
                    'error'
                );
            }

            // Check maximum
            if ($max > 0 && $quantity > $max) {
                wc_add_notice(
                    sprintf(
                        __('"%s" staat maximaal %d stuks toe. Update je winkelwagen.', 'wc-minimum-quantity-step'),
                        $product_name,
                        $max
                    ),
                    'error'
                );
            }
        }

        // Feature 1: Check parent max totals for variable products (only if apply_to_variations is OFF)
        // This prevents checkout if total variations exceed parent max
        $checked_parents = array(); // Track which parents we've already checked

        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['variation_id']) && $cart_item['variation_id'] > 0) {
                $parent_id = $cart_item['product_id'];

                // Skip if we already checked this parent
                if (in_array($parent_id, $checked_parents)) {
                    continue;
                }

                $checked_parents[] = $parent_id;

                $apply_to_variations = get_post_meta($parent_id, '_apply_restrictions_to_variations', true);

                // Only check parent max if vinkje is OFF
                if ($apply_to_variations !== 'yes') {
                    $parent_max = get_post_meta($parent_id, '_maximum_quantity', true);
                    $parent_max = !empty($parent_max) && $parent_max > 0 ? absint($parent_max) : 0;

                    if ($parent_max > 0) {
                        // Calculate total quantity for all variations of this parent
                        $family_total = $this->get_cart_quantity_for_product_family($parent_id);

                        if ($family_total > $parent_max) {
                            $parent_product = wc_get_product($parent_id);
                            $parent_name = $parent_product ? $parent_product->get_name() : get_the_title($parent_id);

                            wc_add_notice(
                                sprintf(
                                    __('"%s" (alle variaties samen) staat maximaal %d stuks toe. Update je winkelwagen.', 'wc-minimum-quantity-step'),
                                    $parent_name,
                                    $parent_max
                                ),
                                'error'
                            );
                        }
                    }
                }
            }
        }
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
