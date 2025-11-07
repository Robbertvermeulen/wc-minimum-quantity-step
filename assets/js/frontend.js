/**
 * WooCommerce Minimum Quantity Step - Frontend JavaScript
 *
 * Handles dynamic quantity validation and step adjustment
 */

(function($) {
    'use strict';

    /**
     * Main quantity step handler
     */
    var WCMinimumQuantityStep = {

        /**
         * Current step value
         */
        currentStep: 1,

        /**
         * Current product ID
         */
        currentProductId: null,

        /**
         * Quantity input selector - works with most WooCommerce themes
         */
        quantityInputSelector: 'input.qty, input[name="quantity"]',

        /**
         * Add to cart button selector
         */
        addToCartSelector: 'button.single_add_to_cart_button, button[name="add-to-cart"]',

        /**
         * Form selector
         */
        formSelector: 'form.cart',

        /**
         * Notice container
         */
        noticeContainer: null,

        /**
         * Initialize
         */
        init: function() {
            // Check if we have step data
            if (typeof wcMinQtyStep === 'undefined') {
                return;
            }

            this.currentStep = parseInt(wcMinQtyStep.step) || 1;
            this.currentProductId = wcMinQtyStep.product_id;

            // Only initialize if step is greater than 1
            if (this.currentStep <= 1) {
                return;
            }

            // Create notice container
            this.createNoticeContainer();

            // Set up event handlers
            this.bindEvents();

            // Initialize quantity input
            this.initQuantityInput();
        },

        /**
         * Create notice container for validation messages
         */
        createNoticeContainer: function() {
            var $form = $(this.formSelector);

            if ($form.length) {
                // Check if notice container already exists
                if ($form.find('.wcmqs-notice-container').length === 0) {
                    this.noticeContainer = $('<div class="wcmqs-notice-container woocommerce-info" style="display:none;"></div>');
                    $form.prepend(this.noticeContainer);
                } else {
                    this.noticeContainer = $form.find('.wcmqs-notice-container');
                }
            }
        },

        /**
         * Initialize quantity input - remove HTML5 validation attributes
         */
        initQuantityInput: function() {
            var $quantityInput = $(this.quantityInputSelector);

            if ($quantityInput.length) {
                // Remove step attribute to prevent HTML5 validation
                $quantityInput.removeAttr('step');

                // Set inputmode to numeric for better mobile experience
                $quantityInput.attr('inputmode', 'numeric');

                // Ensure min is 1 for Google Shopping compliance
                $quantityInput.attr('min', '1');
            }
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Handle quantity input changes
            $(document).on('change', this.quantityInputSelector, function(e) {
                self.handleQuantityChange($(this));
            });

            // Handle add to cart button click
            $(document).on('click', this.addToCartSelector, function(e) {
                var $quantityInput = $(self.quantityInputSelector);

                if ($quantityInput.length) {
                    var isValid = self.validateQuantity($quantityInput);

                    if (!isValid) {
                        e.preventDefault();
                        e.stopImmediatePropagation();

                        // Show error message
                        self.showNotice(wcMinQtyStep.i18n.error_message);

                        // Scroll to notice
                        self.scrollToNotice();

                        return false;
                    }
                }
            });

            // Handle variation changes (for variable products)
            $(document).on('found_variation', function(e, variation) {
                if (variation.minimum_quantity_step) {
                    self.currentStep = parseInt(variation.minimum_quantity_step);

                    // Re-initialize quantity input
                    self.initQuantityInput();

                    // Hide any existing notices
                    self.hideNotice();
                }
            });

            // Handle reset variations
            $(document).on('reset_data', function() {
                self.currentStep = parseInt(wcMinQtyStep.step) || 1;
                self.initQuantityInput();
                self.hideNotice();
            });

            // Handle plus/minus buttons (common in WooCommerce themes)
            $(document).on('click', '.plus, .minus, [class*="quantity__button"]', function(e) {
                // Give the quantity input time to update
                setTimeout(function() {
                    var $quantityInput = $(self.quantityInputSelector);
                    if ($quantityInput.length) {
                        self.handleQuantityChange($quantityInput);
                    }
                }, 50);
            });
        },

        /**
         * Handle quantity input change
         */
        handleQuantityChange: function($input) {
            var quantity = parseInt($input.val()) || 1;
            var self = this;

            // Hide any existing error messages (since user is adjusting)
            this.hideNotice();

            // If quantity is valid and is a multiple of step, update the step attribute
            // This ensures subsequent increments/decrements follow the step
            if (quantity % this.currentStep === 0 && quantity >= this.currentStep) {
                // Dynamically update step after user selects valid quantity
                $input.attr('step', this.currentStep);
            }
        },

        /**
         * Validate quantity
         */
        validateQuantity: function($input) {
            var quantity = parseInt($input.val()) || 1;

            // If step is 1 or less, always valid
            if (this.currentStep <= 1) {
                return true;
            }

            // Check if quantity is a multiple of step
            return quantity % this.currentStep === 0;
        },

        /**
         * Show notice message
         */
        showNotice: function(message) {
            if (this.noticeContainer) {
                this.noticeContainer.html('<p>' + message + '</p>').slideDown(300);
            } else {
                // Fallback: show alert if container not available
                alert(message);
            }
        },

        /**
         * Hide notice message
         */
        hideNotice: function() {
            if (this.noticeContainer) {
                this.noticeContainer.slideUp(300);
            }
        },

        /**
         * Scroll to notice
         */
        scrollToNotice: function() {
            if (this.noticeContainer && this.noticeContainer.is(':visible')) {
                $('html, body').animate({
                    scrollTop: this.noticeContainer.offset().top - 100
                }, 500);
            }
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        WCMinimumQuantityStep.init();
    });

})(jQuery);
