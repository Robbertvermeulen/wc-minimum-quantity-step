/**
 * WooCommerce Minimum Quantity Step - Frontend JavaScript
 *
 * Handles dynamic quantity validation and step adjustment
 */

(function($) {
    'use strict';

    /**
     * Simple sprintf implementation for string formatting
     */
    function sprintf(str, value) {
        return str.replace('%d', value);
    }

    /**
     * Main quantity step handler
     */
    var WCMinimumQuantityStep = {

        /**
         * Current step value
         */
        currentStep: 1,

        /**
         * Current minimum quantity
         */
        currentMin: 0,

        /**
         * Current maximum quantity
         */
        currentMax: 0,

        /**
         * Current product ID
         */
        currentProductId: null,

        /**
         * Previous quantity value for direction detection
         */
        previousQuantity: 1,

        /**
         * Flag to prevent recursive event loops when updating values
         */
        isUpdating: false,

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
            this.currentMin = parseInt(wcMinQtyStep.min) || 0;
            this.currentMax = parseInt(wcMinQtyStep.max) || 0;
            this.currentProductId = wcMinQtyStep.product_id;

            // Only initialize if there are restrictions
            if (this.currentStep <= 1 && this.currentMin <= 0 && this.currentMax <= 0) {
                return;
            }

            // Create notice container
            this.createNoticeContainer();

            // Set up event handlers
            this.bindEvents();

            // Initialize quantity input
            this.initQuantityInput();

            // Store initial quantity as previous
            var $quantityInput = $(this.quantityInputSelector);
            if ($quantityInput.length) {
                this.previousQuantity = parseInt($quantityInput.val()) || 1;
            }
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
                // Disable HTML5 validation on form level instead of changing step attribute
                // This keeps step intact for theme compatibility while preventing browser validation
                var $form = $quantityInput.closest('form');
                if ($form.length && !$form.attr('novalidate')) {
                    $form.attr('novalidate', 'novalidate');
                }

                // Set inputmode to numeric for better mobile experience
                if ($quantityInput.attr('inputmode') !== 'numeric') {
                    $quantityInput.attr('inputmode', 'numeric');
                }

                // Ensure min is 1 for Google Shopping compliance
                if ($quantityInput.attr('min') !== '1') {
                    $quantityInput.attr('min', '1');
                }
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
                    var validation = self.validateQuantity($quantityInput);

                    if (!validation.valid) {
                        e.preventDefault();
                        e.stopImmediatePropagation();

                        // Show all error messages
                        var errorMessage = validation.errors.join(' ');
                        self.showNotice(errorMessage);

                        // Scroll to notice
                        self.scrollToNotice();

                        return false;
                    }
                }
            });

            // Handle variation changes (for variable products)
            $(document).on('found_variation', function(e, variation) {
                // Update all restrictions from variation data
                self.currentStep = parseInt(variation.minimum_quantity_step) || 1;
                self.currentMin = parseInt(variation.minimum_quantity) || 0;
                self.currentMax = parseInt(variation.maximum_quantity) || 0;

                // Re-initialize quantity input
                self.initQuantityInput();

                // Update previous quantity to current value
                var $quantityInput = $(self.quantityInputSelector);
                if ($quantityInput.length) {
                    self.previousQuantity = parseInt($quantityInput.val()) || 1;
                }

                // Hide any existing notices
                self.hideNotice();
            });

            // Handle reset variations
            $(document).on('reset_data', function() {
                self.currentStep = parseInt(wcMinQtyStep.step) || 1;
                self.currentMin = parseInt(wcMinQtyStep.min) || 0;
                self.currentMax = parseInt(wcMinQtyStep.max) || 0;
                self.initQuantityInput();

                // Update previous quantity to current value
                var $quantityInput = $(self.quantityInputSelector);
                if ($quantityInput.length) {
                    self.previousQuantity = parseInt($quantityInput.val()) || 1;
                }

                self.hideNotice();
            });
        },

        /**
         * Handle quantity input change
         */
        handleQuantityChange: function($input) {
            // Prevent recursive event loops when we programmatically update the value
            if (this.isUpdating) {
                return;
            }

            var quantity = parseInt($input.val()) || 1;

            // Hide any existing error messages (since user is adjusting)
            this.hideNotice();

            // Auto-snap to nearest valid quantity based on step
            if (this.currentStep > 1) {
                // Determine direction based on previous value
                var direction = quantity > this.previousQuantity ? 'up' :
                               quantity < this.previousQuantity ? 'down' : 'nearest';

                var nearestValid = this.getNearestValidQuantity(quantity, direction);

                // Only update if different to avoid cursor jumping on manual typing
                if (quantity !== nearestValid) {
                    // Set flag to prevent recursive calls
                    this.isUpdating = true;

                    // Update the input value
                    $input.val(nearestValid);

                    // Release flag after a short delay to allow event propagation
                    var self = this;
                    setTimeout(function() {
                        self.isUpdating = false;
                    }, 50);

                    // Update previous quantity to the snapped value
                    this.previousQuantity = nearestValid;
                } else {
                    // Update previous quantity even if not changed
                    this.previousQuantity = quantity;
                }
            } else {
                // No step restriction, just track the value
                this.previousQuantity = quantity;
            }
        },

        /**
         * Get nearest valid quantity based on step and direction
         * Respects min/max boundaries
         *
         * @param {number} quantity - The current quantity
         * @param {string} direction - 'up', 'down', or 'nearest'
         */
        getNearestValidQuantity: function(quantity, direction) {
            if (this.currentStep <= 1) {
                return quantity;
            }

            direction = direction || 'nearest';
            var nearestValid;

            // Round based on direction
            if (direction === 'up') {
                // Round up to next valid multiple
                nearestValid = Math.ceil(quantity / this.currentStep) * this.currentStep;
            } else if (direction === 'down') {
                // Round down to previous valid multiple
                nearestValid = Math.floor(quantity / this.currentStep) * this.currentStep;
            } else {
                // Round to nearest multiple (default)
                nearestValid = Math.round(quantity / this.currentStep) * this.currentStep;
            }

            // Ensure minimum is at least the step value
            if (nearestValid < this.currentStep) {
                nearestValid = this.currentStep;
            }

            // Respect minimum quantity boundary
            if (this.currentMin > 0 && nearestValid < this.currentMin) {
                // Find next valid multiple >= minimum
                nearestValid = Math.ceil(this.currentMin / this.currentStep) * this.currentStep;
            }

            // Respect maximum quantity boundary
            if (this.currentMax > 0 && nearestValid > this.currentMax) {
                // Find previous valid multiple <= maximum
                nearestValid = Math.floor(this.currentMax / this.currentStep) * this.currentStep;
            }

            return nearestValid;
        },

        /**
         * Validate quantity
         * Returns object with {valid: boolean, errors: []}
         */
        validateQuantity: function($input) {
            var quantity = parseInt($input.val()) || 1;
            var errors = [];

            // Check step (multiples)
            if (this.currentStep > 1 && quantity % this.currentStep !== 0) {
                errors.push(sprintf(wcMinQtyStep.i18n.error_step, this.currentStep));
            }

            // Check minimum
            if (this.currentMin > 0 && quantity < this.currentMin) {
                errors.push(sprintf(wcMinQtyStep.i18n.error_min, this.currentMin));
            }

            // Check maximum
            if (this.currentMax > 0 && quantity > this.currentMax) {
                errors.push(sprintf(wcMinQtyStep.i18n.error_max, this.currentMax));
            }

            return {
                valid: errors.length === 0,
                errors: errors
            };
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
