# WooCommerce Minimum Quantity Step

A WooCommerce plugin that allows you to set minimum/maximum quantities and quantity steps per product, while keeping the default quantity at 1 for Google Shopping compliance.

## Features

- ✅ Set custom quantity steps per product (e.g., must order in multiples of 2, 3, 5, etc.)
- ✅ Set minimum and maximum quantities per product and variation
- ✅ Default quantity remains at 1 (compliant with Google Shopping requirements)
- ✅ Real-time JavaScript validation with user-friendly error messages
- ✅ Server-side PHP validation for add-to-cart and cart updates
- ✅ Cart quantity checking (validates existing + new quantity)
- ✅ Variable products: choose if parent max applies to all variations combined or per variation
- ✅ Dynamic step adjustment after valid quantity selection
- ✅ Works with both simple and variable products
- ✅ Compatible with most WooCommerce themes and custom quantity buttons
- ✅ Universal implementation using standard WooCommerce hooks and classes
- ✅ Lightweight and performance-optimized
- ✅ Multilingual ready with translation support

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

### Setting Up Quantity Restrictions for a Product

1. Go to **Products** in your WordPress admin
2. Edit any product (simple or variable)
3. Scroll to the **Product Data** section
4. Click on the **Inventory** tab
5. Find the **Quantity Restrictions** section with the following fields:
   - **Minimum Quantity Step**: Multiples in which the product must be ordered (e.g., `2` for pairs)
   - **Minimum Quantity**: Minimum number of items per order (optional)
   - **Maximum Quantity**: Maximum number of items per order (optional)
6. Enter your desired values (all fields are optional)
7. Click **Update** to save

### Setting Up Variable Products

For variable products, you have two options:

**Option 1: Apply parent restrictions to all variations**
1. Set restrictions on the parent product level
2. Check the box "Apply these restrictions to all variations"
3. All variations will use the parent restrictions

**Option 2: Individual variation restrictions (default)**
1. Leave the checkbox unchecked
2. Set restrictions on each variation individually
3. When parent max is set, it applies to ALL variations combined as a total limit

### Example Scenarios

#### Scenario 1: Product Must Be Ordered in Pairs

**Setting:**
- Minimum Quantity Step = `2`

**Behavior:**
- Default quantity shows: 1
- Customer tries to add 1: ❌ Error message appears
- Customer changes to 2: ✅ Can add to cart
- Quantity field now steps by 2 (2, 4, 6, 8...)

#### Scenario 2: Product with Min and Max Limits

**Setting:**
- Minimum Quantity = `3`
- Maximum Quantity = `10`

**Behavior:**
- Customer tries to add 1 or 2: ❌ Error - minimum 3 required
- Customer tries to add 3-10: ✅ Allowed
- Customer tries to add 11: ❌ Error - maximum 10 allowed
- Cart checks include existing quantities

#### Scenario 3: Variable Product with Total Family Limit

**Setting (Parent product):**
- Maximum Quantity = `6`
- Checkbox unchecked (default)

**Setting (Variations):**
- Variation A: Maximum Quantity = `1`
- Variation B: Maximum Quantity = `1`

**Behavior:**
- Customer adds 1x Variation A: ✅ Allowed
- Customer tries to add another Variation A: ❌ Max 1 of this variation
- Customer adds 1x Variation B: ✅ Allowed (total now 2)
- Customer can add max 6 items total across all variations combined
- Once 6 total items in cart: ❌ Parent max reached

#### Scenario 4: No Restrictions

**Setting:** Leave all fields empty

**Behavior:**
- Works as standard WooCommerce (any quantity allowed)

## How It Works

### Frontend Validation (JavaScript)

The plugin uses JavaScript to provide instant feedback:

1. **On Load:** Removes HTML5 step validation, sets input mode to numeric
2. **On Change:** Validates quantity against step, min, and max requirements
3. **Invalid Quantity:** Shows validation message (color #F9BB3F), prevents add to cart
4. **Valid Quantity:** Hides message, adjusts step attribute for easier selection
5. **Plus/Minus Buttons:** Automatically validated after click
6. **Scroll Behavior:** Only scrolls to message if it's outside viewport

### Backend Validation (PHP)

Server-side validation ensures security:

1. **Add to Cart:** Validates before adding item (checks existing cart quantity + new quantity)
2. **Cart Update:** Validates when changing quantities in cart
3. **Checkout:** Validates all items at checkout
4. **Variable Products:** Validates parent max as total for all variations (when checkbox unchecked)
5. **Invalid Quantity:** Shows WooCommerce error notice, prevents action

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
- `woocommerce_product_options_inventory_product_data` - Add custom fields to simple products
- `woocommerce_process_product_meta` - Save product meta fields (with array detection to prevent parent overwrite)
- `woocommerce_variation_options_pricing` - Add custom fields to variations
- `woocommerce_save_product_variation` - Save variation meta fields

**Frontend Hooks:**
- `wp_enqueue_scripts` - Load assets
- `woocommerce_available_variation` - Add restrictions to variation data
- `woocommerce_add_to_cart_validation` - Validate add to cart (5 params including variation_id)
- `woocommerce_update_cart_validation` - Validate cart updates
- `woocommerce_checkout_process` - Validate quantities at checkout

**AJAX Endpoints:**
- `wp_ajax_get_quantity_step` - Get quantity step for product
- `wp_ajax_nopriv_get_quantity_step` - Public endpoint for non-logged in users

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
│   │   └── frontend.css          # Validation message styling
│   └── js/
│       └── frontend.js            # Client-side validation logic
├── languages/
│   ├── wc-minimum-quantity-step-{locale}.po   # Translation files
│   ├── wc-minimum-quantity-step-{locale}.mo   # Compiled translations
│   └── wc-minimum-quantity-step.pot           # Translation template
├── wc-minimum-quantity-step.php   # Main plugin file
└── README.md
```

### Key Features Implementation

**Cart Quantity Checking:**
- `get_cart_quantity_for_item()` - Gets existing cart quantity for specific product/variation
- `get_cart_quantity_for_product_family()` - Gets total quantity for all variations of a parent product
- Validates existing quantity + new quantity against limits

**Variable Product Family Limits:**
- When checkbox unchecked: Parent max applies to ALL variations combined
- When checkbox checked: Parent restrictions apply per individual variation
- Array detection in POST data prevents parent settings from being overwritten when saving variations

## Customization

### Changing Validation Messages

You can customize the validation messages using WordPress filters or by editing the language files:

**Option 1: Using gettext filter**
```php
add_filter('gettext', 'custom_wcmqs_error_message', 10, 3);
function custom_wcmqs_error_message($translation, $text, $domain) {
    if ($domain === 'wc-minimum-quantity-step') {
        if (strpos($text, 'must be ordered in multiples') !== false) {
            return 'Custom message with %d placeholder';
        }
    }
    return $translation;
}
```

**Option 2: Edit language files**
- Create your own translation files in the `languages/` directory
- Use tools like Poedit to edit `.po` files
- Compile to `.mo` files after changes
- Load your translations using WordPress i18n system

### Styling the Validation Message

Add custom CSS to your theme:

```css
.wcmqs-validation-message {
    color: #your-color !important;
    font-size: 1em !important;
    font-weight: bold !important;
}
```

Default styling uses color `#F9BB3F` for validation messages.

### Adding Custom Validation Logic

You can hook into the validation filters:

```php
add_filter('woocommerce_add_to_cart_validation', 'my_custom_validation', 20, 5);
function my_custom_validation($passed, $product_id, $quantity, $variation_id, $variations) {
    // Your custom logic here
    // Note: Use $variation_id when available (for variable products)
    $actual_product_id = $variation_id > 0 ? $variation_id : $product_id;
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

### Issue: Parent Product Settings Get Overwritten When Saving Variations

**Solutions:**
1. This was fixed in version 1.2.3
2. Update to the latest version
3. The plugin now detects array POST data to prevent parent overwrites

### Issue: Cart Quantity Not Being Checked

**Solutions:**
1. This feature was added in version 1.2.0
2. Update to the latest version
3. The plugin now checks existing cart quantity + new quantity

### Issue: Validation Message Not Showing

**Solutions:**
1. Check if `.wcmqs-validation-message` class is styled in your theme
2. Ensure JavaScript is enabled in browser
3. Check for JavaScript conflicts in browser console

### Issue: Variable Products - Parent Max Not Working Correctly

**Solutions:**
1. Check the "Apply these restrictions to all variations" checkbox:
   - **Unchecked (default):** Parent max applies to ALL variations combined as total
   - **Checked:** Parent restrictions apply per individual variation
2. Ensure you're using version 1.2.0 or later

### Issue: Translation Not Working

**Solutions:**
1. Ensure your translation `.mo` file exists in the `languages/` directory
2. Check that the file naming convention is correct: `wc-minimum-quantity-step-{locale}.mo`
3. Set your WordPress language in Settings > General
4. Clear any translation caches
5. Use WordPress translation plugins like Loco Translate for easier management

## Support

For bug reports and feature requests, please use the [GitHub Issues](https://github.com/robbertvermeulen/wc-minimum-quantity-step/issues) page.

## Changelog

### 1.2.3 - 2024-11-11
- Fixed: Parent product settings being overwritten when saving variations
- Improved: Array detection in POST data to distinguish variation saves from parent saves

### 1.2.2 - 2024-11-11
- Attempted fix for parent settings overwrite issue (replaced by 1.2.3)

### 1.2.1 - 2024-11-11
- Fixed: Variation ID not being used in add-to-cart validation
- Improved: Now properly uses variation_id parameter from WooCommerce hook

### 1.2.0 - 2024-11-11
- Added: Cart quantity checking (existing + new quantity validation)
- Added: Parent max validation for variable products (applies to all variations combined when checkbox OFF)
- Added: Checkbox to apply parent restrictions to all variations
- Improved: Better validation messaging with cart context

### 1.1.0 - 2024-11-11
- Added: Minimum and Maximum quantity fields (in addition to step)
- Added: Multilingual support with translation-ready architecture
- Changed: Validation message styling to simple text with customizable color
- Changed: All admin fields are now optional (can be left empty)
- Added: Checkout validation
- Added: Scroll behavior only when message is outside viewport

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
