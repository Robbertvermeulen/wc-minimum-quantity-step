# WooCommerce Minimum Quantity Step

A WooCommerce plugin that allows you to set minimum quantity steps for products while keeping the default quantity at 1 for Google Shopping campaign compliance.

## Features

- ✅ Set custom quantity steps per product (e.g., must order in multiples of 2, 3, 5, etc.)
- ✅ Default quantity remains at 1 (compliant with Google Shopping requirements)
- ✅ Real-time JavaScript validation with user-friendly error messages
- ✅ Server-side PHP validation for add-to-cart and cart updates
- ✅ Dynamic step adjustment after valid quantity selection
- ✅ Works with variable products
- ✅ Compatible with most WooCommerce themes and custom quantity buttons
- ✅ Universal implementation using standard WooCommerce hooks and classes
- ✅ Lightweight and performance-optimized

## The Problem This Plugin Solves

When running Google Shopping campaigns, products must show a price that matches the minimum order quantity. If a product costs €59 but must be ordered in sets of 2, setting the default quantity to 2 would cause the product to show as €118 in shopping feeds, leading to campaign rejection.

This plugin solves this by:
1. Keeping the default quantity at 1 (price shows correctly in feeds)
2. Preventing checkout with invalid quantities
3. Guiding users to select valid quantities (multiples of your chosen step)

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher

## Installation

### Method 1: Manual Installation

1. Download or clone this repository
2. Upload the `wc-minimum-quantity-step` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to any product edit page to configure quantity steps

### Method 2: Via WordPress Admin

1. Go to Plugins > Add New
2. Click "Upload Plugin"
3. Choose the plugin ZIP file
4. Click "Install Now"
5. Activate the plugin

## Usage

### Setting Up Quantity Steps for a Product

1. Go to **Products** in your WordPress admin
2. Edit any product (simple or variable)
3. Scroll to the **Product Data** section
4. Click on the **Inventory** tab
5. Find the **Minimum Quantity Step** field
6. Enter your desired step (e.g., `2` for multiples of 2)
7. Click **Update** to save

### Example Scenarios

#### Scenario 1: Product Must Be Ordered in Pairs

**Setting:** Minimum Quantity Step = `2`

**Behavior:**
- Default quantity shows: 1
- Customer tries to add 1: ❌ Error message appears
- Customer changes to 2: ✅ Can add to cart
- Quantity field now steps by 2 (2, 4, 6, 8...)

#### Scenario 2: Product Must Be Ordered in Sets of 5

**Setting:** Minimum Quantity Step = `5`

**Behavior:**
- Default quantity shows: 1
- Customer tries to add 1, 2, 3, or 4: ❌ Error message
- Customer changes to 5: ✅ Can add to cart
- Quantity field now steps by 5 (5, 10, 15, 20...)

#### Scenario 3: No Restrictions

**Setting:** Leave empty or set to `1`

**Behavior:**
- Works as standard WooCommerce (any quantity allowed)

## How It Works

### Frontend Validation (JavaScript)

The plugin uses JavaScript to provide instant feedback:

1. **On Load:** Removes HTML5 step validation, sets input mode to numeric
2. **On Change:** Validates quantity against the step requirement
3. **Invalid Quantity:** Shows error message, prevents add to cart
4. **Valid Quantity:** Hides error, adjusts step attribute for easier selection
5. **Plus/Minus Buttons:** Automatically validated after click

### Backend Validation (PHP)

Server-side validation ensures security:

1. **Add to Cart:** Validates before adding item
2. **Cart Update:** Validates when changing quantities in cart
3. **Invalid Quantity:** Shows WooCommerce error notice, prevents action

### Compatibility

The plugin works with:
- Standard WooCommerce quantity inputs
- Custom theme quantity buttons
- Variable products with multiple variations
- AJAX add to cart
- Plus/minus quantity buttons
- Mobile and desktop interfaces

## Technical Implementation

### Hooks Used

**Admin Hooks:**
- `woocommerce_product_options_inventory_product_data` - Add custom field
- `woocommerce_process_product_meta` - Save custom field

**Frontend Hooks:**
- `wp_enqueue_scripts` - Load assets
- `woocommerce_available_variation` - Add step to variation data
- `woocommerce_add_to_cart_validation` - Validate add to cart
- `woocommerce_update_cart_validation` - Validate cart updates

### JavaScript Events

- `change` - Quantity input changes
- `click` - Add to cart button, plus/minus buttons
- `found_variation` - Variable product variation selected
- `reset_data` - Variable product variation reset

### File Structure

```
wc-minimum-quantity-step/
├── assets/
│   ├── css/
│   │   └── frontend.css
│   └── js/
│       └── frontend.js
├── wc-minimum-quantity-step.php
└── README.md
```

## Customization

### Changing Error Message

You can customize the error message using WordPress filters:

```php
add_filter('gettext', 'custom_wcmqs_error_message', 10, 3);
function custom_wcmqs_error_message($translation, $text, $domain) {
    if ($domain === 'wc-minimum-quantity-step' &&
        strpos($text, 'must be ordered in multiples') !== false) {
        return 'Custom error message here with %d placeholder';
    }
    return $translation;
}
```

### Styling the Notice

Add custom CSS to your theme:

```css
.wcmqs-notice-container {
    background-color: #your-color !important;
    border: 2px solid #your-border-color !important;
}
```

### Adding Custom Validation Logic

You can hook into the validation filters:

```php
add_filter('woocommerce_add_to_cart_validation', 'my_custom_validation', 20, 3);
function my_custom_validation($passed, $product_id, $quantity) {
    // Your custom logic here
    return $passed;
}
```

## Troubleshooting

### Issue: Validation Not Working

**Solutions:**
1. Clear browser cache and hard refresh (Ctrl+Shift+R)
2. Check browser console for JavaScript errors
3. Ensure WooCommerce is up to date
4. Disable other plugins to check for conflicts

### Issue: Plus/Minus Buttons Not Respecting Step

**Solutions:**
1. The plugin validates after button click
2. Some themes override quantity behavior - check theme settings
3. Try changing quantity manually to see validation

### Issue: Error Message Not Showing

**Solutions:**
1. Check if `.woocommerce-error` class is styled in your theme
2. Ensure JavaScript is enabled in browser
3. Check for JavaScript conflicts in browser console

### Issue: Variable Products Not Working

**Solutions:**
1. Set quantity step on each variation, not the parent product
2. Clear variation transients: WooCommerce > Status > Tools > Clear transients

## Support

For bug reports and feature requests, please use the [GitHub Issues](https://github.com/robbertvermeulen/wc-minimum-quantity-step/issues) page.

## Changelog

### 1.0.0 - 2024-11-07
- Initial release
- Simple and variable product support
- JavaScript and PHP validation
- Dynamic step adjustment
- Google Shopping compliance

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## Credits

Developed by [Robbert Vermeulen](https://github.com/robbertvermeulen)

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request
