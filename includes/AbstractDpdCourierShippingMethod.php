<?php

namespace ArDesign\DPD;

defined('ABSPATH') || exit;

abstract class AbstractDpdCourierShippingMethod extends \WC_Shipping_Method
{
    public const SHIPPING_PRICE_TYPE_OPTION_KEY = 'wc_dpd_shipping_price_type';
    public const FREE_FIXED_SHIPPING_OPTION_KEY = 'wc_dpd_free_shipping_price';
    public const FREE_WEIGHT_BASED_SHIPPING_OPTION_KEY = 'wc_dpd_free_weight_based_shipping_price';
    public const PRODUCTS_WEIGHT_SHIPPING_RATES_OPTION_KEY = 'wc_dpd_products_weight_shipping_rates';

    protected const METHOD_ID = '';
    protected const PRODUCT_CODE = 0;
    protected const METHOD_TITLE = '';
    protected const METHOD_DESCRIPTION = '';
    protected const DEFAULT_TITLE = '';

    public $fee = 0.0;

    public function __construct(int $instance_id = 0)
    {
        parent::__construct();

        $this->id = static::METHOD_ID;
        $this->instance_id = absint($instance_id);
        $this->method_title = static::METHOD_TITLE;
        $this->method_description = static::METHOD_DESCRIPTION;
        $this->supports = [
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        ];

        $this->init();
    }

    public static function getProductCode(): int
    {
        return static::PRODUCT_CODE;
    }

    public static function getMethodId(): string
    {
        return static::METHOD_ID;
    }

    public function init(): void
    {
        $this->init_form_fields();
        $this->init_settings();

        $this->title = (string) $this->get_option('title', static::DEFAULT_TITLE);
        $this->tax_status = (string) $this->get_option('tax_status', 'none');

        $fee = (float) wp_kses_post($this->get_option('fee'));
        $this->fee = $fee ?: 0.0;

        add_action('admin_footer', [static::class, 'addScripts'], 10, 1);
        add_filter('woocommerce_generate_repeater_html', [$this, 'addRepeaterFieldHtml'], 10, 4);
        add_filter('woocommerce_shipping_' . static::METHOD_ID . '_instance_settings_values', [$this, 'adjustPostData'], 0, 2);
        add_action('woocommerce_update_options_shipping_' . static::METHOD_ID, [$this, 'process_admin_options']);
    }

    public function init_form_fields(): void
    {
        $weight_unit = (string) get_option('woocommerce_weight_unit');

        $this->instance_form_fields = [
            'title' => [
                'title' => __('Method title', 'ar-design-dpd'),
                'type' => 'text',
                'default' => static::DEFAULT_TITLE,
                'description' => __('Title shown to customers during checkout.', 'ar-design-dpd'),
                'desc_tip' => true,
            ],
            'tax_status' => [
                'title'   => __('Tax status', 'ar-design-dpd'),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'default' => 'none',
                'options' => [
                    'none' => _x('None', 'Tax status', 'ar-design-dpd'),
                    'taxable' => __('Taxable', 'ar-design-dpd'),
                ],
            ],
            self::SHIPPING_PRICE_TYPE_OPTION_KEY => [
                'title' => __('Shipping type', 'ar-design-dpd'),
                'type' => 'select',
                'options' => [
                    'fixed' => __('Fixed shipping price', 'ar-design-dpd'),
                    'products_weight_based' => __('Products weight based shipping price', 'ar-design-dpd'),
                ],
                'description' => __('Choose how this DPD shipping price should be calculated.', 'ar-design-dpd'),
                'desc_tip' => true,
                'class' => 'js-dpd-shipping-type-select',
                'default' => 'fixed',
            ],
            'fee' => [
                'title' => __('Delivery fee', 'ar-design-dpd'),
                'type' => 'price',
                'description' => __('Base shipping fee charged for this DPD method.', 'ar-design-dpd'),
                'default' => '',
                'desc_tip' => true,
                'placeholder' => wc_format_localized_price(0),
                'class' => 'js-dpd-fixed-shipping-type',
            ],
            self::FREE_FIXED_SHIPPING_OPTION_KEY => [
                'title' => __('Free shipping from', 'ar-design-dpd'),
                'type' => 'price',
                'description' => __('Minimum cart value for free shipping. Leave empty to disable free shipping.', 'ar-design-dpd'),
                'default' => '',
                'desc_tip' => true,
                'placeholder' => wc_format_localized_price(0),
                'class' => 'js-dpd-fixed-shipping-type',
            ],
            self::PRODUCTS_WEIGHT_SHIPPING_RATES_OPTION_KEY => [
                'title' => __('Products weight based shipping rates', 'ar-design-dpd'),
                'type' => 'repeater',
                'description' => __('Add shipping rates based on the total weight of products in the cart.', 'ar-design-dpd'),
                'desc_tip' => true,
                'label_text' => __('Shipping rate', 'ar-design-dpd'),
                'min_weight_input_text' => sprintf(__('Min weight (%s)', 'ar-design-dpd'), $weight_unit),
                'max_weight_input_text' => sprintf(__('Max weight (%s)', 'ar-design-dpd'), $weight_unit),
                'price_input_text' => __('Price', 'ar-design-dpd') . ' ' . (wc_prices_include_tax() ? __('with', 'ar-design-dpd') : __('without', 'ar-design-dpd')) . ' ' . __('tax', 'ar-design-dpd'),
                'min_weight_input_placeholder_text' => __('Min weight', 'ar-design-dpd'),
                'max_weight_input_placeholder_text' => __('Max weight', 'ar-design-dpd'),
                'price_input_placeholder_text' => __('Price', 'ar-design-dpd'),
                'add_btn_text' => __('Add a shipping rate', 'ar-design-dpd'),
                'class' => 'js-dpd-weight-based-shipping-type',
            ],
            self::FREE_WEIGHT_BASED_SHIPPING_OPTION_KEY => [
                'title' => __('Free shipping from', 'ar-design-dpd'),
                'type' => 'price',
                'description' => __('Minimum cart value for free shipping. Leave empty to disable free shipping.', 'ar-design-dpd'),
                'default' => '',
                'desc_tip' => true,
                'placeholder' => wc_format_localized_price(0),
                'class' => 'js-dpd-weight-based-shipping-type',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $package
     *
     * @return void
     */
    public function calculate_shipping($package = [])
    {
        $free_shipping_is_set = isset($this->instance_settings[self::FREE_FIXED_SHIPPING_OPTION_KEY])
            && $this->instance_settings[self::FREE_FIXED_SHIPPING_OPTION_KEY] !== ''
            && $this->instance_settings[self::FREE_FIXED_SHIPPING_OPTION_KEY] !== null;

        $free_shipping = $free_shipping_is_set ? (float) $this->instance_settings[self::FREE_FIXED_SHIPPING_OPTION_KEY] : false;
        $cart_subtotal = WC()->cart ? (float) WC()->cart->get_cart_contents_total() : 0.0;
        $cart_subtotal_tax = WC()->cart ? (float) WC()->cart->get_cart_contents_tax() : 0.0;
        $cart_total = wc_prices_include_tax() ? ($cart_subtotal + $cart_subtotal_tax) : $cart_subtotal;

        if ($free_shipping_is_set && wc_prices_include_tax() && WC()->cart) {
            $free_shipping -= WC()->cart->get_cart_contents_tax();
        }

        $rate = [
            'id' => $this->id,
            'label' => $this->title,
            'cost' => ($free_shipping_is_set && $cart_total >= $free_shipping) ? 0 : (float) $this->fee,
            'calc_tax' => 'per_order',
        ];

        if (
            isset($this->instance_settings[self::SHIPPING_PRICE_TYPE_OPTION_KEY])
            && $this->instance_settings[self::SHIPPING_PRICE_TYPE_OPTION_KEY] === 'products_weight_based'
            && !empty($this->instance_settings[self::PRODUCTS_WEIGHT_SHIPPING_RATES_OPTION_KEY])
        ) {
            $free_weight_shipping_is_set = isset($this->instance_settings[self::FREE_WEIGHT_BASED_SHIPPING_OPTION_KEY])
                && $this->instance_settings[self::FREE_WEIGHT_BASED_SHIPPING_OPTION_KEY] !== ''
                && $this->instance_settings[self::FREE_WEIGHT_BASED_SHIPPING_OPTION_KEY] !== null;

            $free_weight_shipping = $free_weight_shipping_is_set ? (float) $this->instance_settings[self::FREE_WEIGHT_BASED_SHIPPING_OPTION_KEY] : false;

            if ($free_weight_shipping_is_set && wc_prices_include_tax() && WC()->cart) {
                $free_weight_shipping -= WC()->cart->get_cart_contents_tax();
            }

            if ($free_weight_shipping_is_set && $cart_total >= $free_weight_shipping) {
                $rate['cost'] = 0;
            } else {
                $items_total_weight = static::getCartTotalWeight();
                $products_weight_based_rates = maybe_unserialize($this->instance_settings[self::PRODUCTS_WEIGHT_SHIPPING_RATES_OPTION_KEY]);

                if (!empty($products_weight_based_rates)) {
                    foreach ($products_weight_based_rates as $products_weight_rate) {
                        $weight_rate_from = !empty($products_weight_rate['min']) ? (float) $products_weight_rate['min'] : 0;
                        $weight_rate_to = !empty($products_weight_rate['max']) ? (float) $products_weight_rate['max'] : 999999;
                        $weight_price = !empty($products_weight_rate['price']) ? number_format((float) $products_weight_rate['price'], wc_get_price_decimals(), '.', '') : 0;

                        if ($items_total_weight >= $weight_rate_from) {
                            $rate['cost'] = $weight_price;
                        }

                        if ($items_total_weight >= $weight_rate_from && $items_total_weight <= $weight_rate_to) {
                            $rate['cost'] = $weight_price;
                        }
                    }
                }
            }
        }

        if (WC()->cart) {
            foreach (WC()->cart->get_applied_coupons() as $coupon_code) {
                $coupon = new \WC_Coupon($coupon_code);
                if ($coupon->get_free_shipping()) {
                    $rate['cost'] = 0;
                    break;
                }
            }
        }

        $this->add_rate($rate);
    }

    public static function getCartTotalWeight(): float
    {
        $cart = WC()->cart;
        if (!$cart) {
            return 0.0;
        }

        $cart_items = $cart->get_cart();
        if (!$cart_items) {
            return 0.0;
        }

        $total_weight = 0.0;
        foreach ($cart_items as $cart_item) {
            $product = $cart_item['data'] ?? null;
            if (!$product instanceof \WC_Product) {
                continue;
            }

            $product_weight = (float) $product->get_weight();
            if (!$product_weight) {
                continue;
            }

            $quantity = !empty($cart_item['quantity']) ? (int) $cart_item['quantity'] : 1;
            $total_weight += ($product_weight * $quantity);
        }

        return $total_weight;
    }

    public function adjustPostData(array $settings, mixed $instance): array
    {
        if (empty($_POST['data'])) {
            return $settings;
        }

        $post_data = $_POST['data'];
        $repeater_values = [];
        $field_prefix = 'woocommerce_' . static::METHOD_ID . '_' . self::PRODUCTS_WEIGHT_SHIPPING_RATES_OPTION_KEY;

        if (!empty($post_data[$field_prefix . '_min'])) {
            foreach ($post_data[$field_prefix . '_min'] as $key => $value) {
                $repeater_values[$key]['min'] = (float) sanitize_text_field($value);
            }
            unset($post_data[$field_prefix . '_min']);
        }

        if (!empty($post_data[$field_prefix . '_max'])) {
            foreach ($post_data[$field_prefix . '_max'] as $key => $value) {
                $repeater_values[$key]['max'] = (float) sanitize_text_field($value);
            }
            unset($post_data[$field_prefix . '_max']);
        }

        if (!empty($post_data[$field_prefix . '_price'])) {
            foreach ($post_data[$field_prefix . '_price'] as $key => $value) {
                $repeater_values[$key]['price'] = number_format((float) $value, wc_get_price_decimals(), '.', '');
            }
            unset($post_data[$field_prefix . '_price']);
        }

        $settings[self::PRODUCTS_WEIGHT_SHIPPING_RATES_OPTION_KEY] = serialize($repeater_values);

        return $settings;
    }

    public function addRepeaterFieldHtml($html = '', $key = '', $data = [], $wc_settings = null)
    {
        if ($key !== self::PRODUCTS_WEIGHT_SHIPPING_RATES_OPTION_KEY) {
            return $html;
        }

        $field_key = $this->get_field_key($key);
        $defaults = [
            'title' => '',
            'disabled' => false,
            'class' => '',
            'label_text' => '',
            'desc_tip' => false,
            'type' => 'repeater',
            'add_btn_text' => '',
            'description' => '',
            'repeater_description' => '',
            'min_weight_input_text' => '',
            'max_weight_input_text' => '',
            'price_input_text' => '',
            'min_weight_input_placeholder_text' => '',
            'max_weight_input_placeholder_text' => '',
            'price_input_placeholder_text' => '',
        ];

        $data = wp_parse_args($data, $defaults);
        $values = static::getRepeaterOptions($key, $wc_settings);
        $values = htmlspecialchars(json_encode($values), ENT_QUOTES, 'UTF-8');

        $props = [
            'minWeightInputText' => $data['min_weight_input_text'],
            'maxWeightInputText' => $data['max_weight_input_text'],
            'priceInputText' => $data['price_input_text'],
            'minWeightInputPlaceholderText' => $data['min_weight_input_placeholder_text'],
            'maxWeightInputPlaceholderText' => $data['max_weight_input_placeholder_text'],
            'priceInputPlaceholderText' => $data['price_input_placeholder_text'],
            'inputName' => $field_key,
            'labelText' => $data['label_text'],
            'removeLabel' => __('Remove', 'ar-design-dpd'),
            'title' => __('Title', 'ar-design-dpd'),
        ];
        $props = htmlspecialchars(json_encode($props), ENT_QUOTES, 'UTF-8');

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); ?></label>
            </th>
            <td class="forminp">
                <fieldset class="repeatable-field repeatable-field--<?php echo esc_attr($key); ?> <?php echo esc_attr($data['class']); ?>" data-component="field-repeater" data-props="<?php echo $props; ?>" data-inputs-data="<?php echo $values; ?>" tabindex="0">
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>
                    <?php if (!empty($data['repeater_description'])) : ?>
                        <p><small><?php echo wp_kses_post($data['repeater_description']); ?></small></p>
                    <?php endif; ?>
                    <ol class="repeatable-field__rows" data-ref="rowList"></ol>
                    <div class="repeatable-field__bottom">
                        <button class="repeatable-field__add-button button" data-ref="addButton" type="button">+ <?php echo esc_attr($data['add_btn_text']); ?></button>
                    </div>
                    <?php echo $this->get_description_html($data); ?>
                </fieldset>
            </td>
        </tr>
        <?php

        return ob_get_clean();
    }

    public static function getRepeaterOptions(string $option_key = '', mixed $wc_settings = null)
    {
        return maybe_unserialize($wc_settings->get_option($option_key));
    }

    public static function addScripts(): void
    {
        global $pagenow;

        if ($pagenow !== 'admin.php') {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : '';

        if ($page !== 'wc-settings' || $tab !== 'shipping') {
            return;
        }

        wp_enqueue_script(static::METHOD_ID . '_repeater_field', AR_DESIGN_DPD_PLUGIN_ASSETS_URL . 'scripts/dpd-parcelshop-shipping-method-weight-by-package-repeater.js', [], ard_dpd_get_plugin_version(), true);
        wp_enqueue_style(static::METHOD_ID . '_repeater_field', AR_DESIGN_DPD_PLUGIN_ASSETS_URL . 'styles/dpd-export-repeater-settings-field.css', [], ard_dpd_get_plugin_version(), 'all');
    }
}
