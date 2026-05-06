<?php

namespace WcDPD;

defined('ABSPATH') || exit;

class DpdParcelShopShippingMethod extends \WC_Shipping_Method
{
    public const SETTINGS_ID_KEY = 'wc_dpd_parcelshop';
    public const SHIPPING_PRICE_TYPE_OPTION_KEY = 'wc_dpd_shipping_price_type';
    public const FREE_FIXED_SHIPPING_OPTION_KEY = 'wc_dpd_free_shipping_price';
    public const FREE_WEIGHT_BASED_SHIPPING_OPTION_KEY = 'wc_dpd_free_weight_based_shipping_price';
    public const DISALLOW_SHOPS_OPTION_KEY = 'wc_dpd_disallow_shops';
    public const DISALLOW_LOCKERS_OPTION_KEY = 'wc_dpd_disallow_lockers';
    public const DISALLOW_DPD_PICKUP_STATIONS_OPTION_KEY = 'wc_dpd_disallow_dpd_pickup_stations';
    public const DISALLOW_SK_POST_OPTION_KEY = 'wc_dpd_disallow_sk_post';
    public const DISALLOW_ALZA_BOXES_OPTION_KEY = 'wc_dpd_disallow_alza_boxes';
    public const DISALLOW_ZBOX_OPTION_KEY = 'wc_dpd_disallow_zbox';
    public const PRODUCTS_WEIGHT_SHIPPING_RATES_OPTION_KEY = 'wc_dpd_products_weight_shipping_rates';
    public const PACKAGE_WEIGHT_SHIPPING_LIMITS_OPTION_KEY = 'wc_dpd_package_weight_shipping_limits';
    public const PACKAGE_WEIGHT_SHIPPING_LIMITS_MAX_WEIGHT_OPTION_KEY = 'wc_dpd_package_weight_shipping_limits_max_weight';
    public const PACKAGE_WEIGHT_SHIPPING_LIMITS_MAX_WEIGHT_ALZABOX_OPTION_KEY = 'wc_dpd_package_weight_shipping_limits_max_weight_alzabox';
    public const PACKAGE_WEIGHT_SHIPPING_LIMITS_MAX_WEIGHT_SLOVENSKA_POSTA_OPTION_KEY = 'wc_dpd_package_weight_shipping_limits_max_weight_slovenska_posta';
    public const PACKAGE_WEIGHT_SHIPPING_LIMITS_MAX_WEIGHT_ZBOX_OPTION_KEY = 'wc_dpd_package_weight_shipping_limits_max_weight_zbox';
    public const PACKAGE_DIMENSION_SHIPPING_LIMITS_OPTION_KEY = 'wc_dpd_package_dimensions_shipping_limits';
    public const PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_WIDTH_OPTION_KEY = 'wc_dpd_package_dimensions_shipping_limits_max_width';
    public const PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_HEIGHT_OPTION_KEY = 'wc_dpd_package_dimensions_shipping_limits_max_height';
    public const PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_LENGTH_OPTION_KEY = 'wc_dpd_package_dimensions_shipping_limits_max_length';
    public const PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_WIDTH_ALZABOX_OPTION_KEY = 'wc_dpd_package_dimensions_shipping_limits_max_width_alzabox';
    public const PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_HEIGHT_ALZABOX_OPTION_KEY = 'wc_dpd_package_dimensions_shipping_limits_max_height_alzabox';
    public const PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_LENGTH_ALZABOX_OPTION_KEY = 'wc_dpd_package_dimensions_shipping_limits_max_length_alzabox';
    public const PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_WIDTH_SLOVENSKA_POSTA_OPTION_KEY = 'wc_dpd_package_dimensions_shipping_limits_max_width_slovenska_posta';
    public const PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_HEIGHT_SLOVENSKA_POSTA_OPTION_KEY = 'wc_dpd_package_dimensions_shipping_limits_max_height_slovenska_posta';
    public const PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_LENGTH_SLOVENSKA_POSTA_OPTION_KEY = 'wc_dpd_package_dimensions_shipping_limits_max_length_slovenska_posta';
    public const PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_WIDTH_ZBOX_OPTION_KEY = 'wc_dpd_package_dimensions_shipping_limits_max_width_zbox';
    public const PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_HEIGHT_ZBOX_OPTION_KEY = 'wc_dpd_package_dimensions_shipping_limits_max_height_zbox';
    public const PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_LENGTH_ZBOX_OPTION_KEY = 'wc_dpd_package_dimensions_shipping_limits_max_length_zbox';
    public const PARCELSHOP_ID_META_KEY = 'wc_dpd_parcelshop_id';
    public const PARCELSHOP_PUS_ID_META_KEY = 'wc_dpd_parcelshop_pus_id';
    public const PARCELSHOP_NAME_META_KEY = 'wc_dpd_parcelshop_name';
    public const PARCELSHOP_STREET_META_KEY = 'wc_dpd_parcelshop_street';
    public const PARCELSHOP_ZIP_META_KEY = 'wc_dpd_parcelshop_zip';
    public const PARCELSHOP_CITY_META_KEY = 'wc_dpd_parcelshop_city';
    public const PARCELSHOP_COUNTRY_CODE_META_KEY = 'wc_dpd_parcelshop_country_code';
    public const PARCELSHOP_COUNTRY_NAME_META_KEY = 'wc_dpd_parcelshop_country_name';
    public const PARCELSHOP_MAX_WEIGHT_META_KEY = 'wc_dpd_parcelshop_max_weight';
    public const PARCELSHOP_COD_META_KEY = 'wc_dpd_parcelshop_cod';
    public const PARCELSHOP_CARD_META_KEY = 'wc_dpd_parcelshop_card';
    public const PARCELSHOP_IS_ALZABOX_ELIGIBLE_META_KEY = 'wc_dpd_parcelshop_is_alzabox_eligible';
    public const PARCELSHOP_IS_SLOVENSKA_POSTA_ELIGIBLE_META_KEY = 'wc_dpd_parcelshop_is_slovenska_posta_eligible';
    public const PARCELSHOP_IS_ZBOX_ELIGIBLE_META_KEY = 'wc_dpd_parcelshop_is_zbox_eligible';

    /**
     * Constructor for the shipping class
     *
     * @param integer $instance_id
     */
    public function __construct(int $instance_id = 0)
    {
        parent::__construct();

        $this->id = self::SETTINGS_ID_KEY;
        $this->instance_id = absint($instance_id);
        $this->method_title = __('DPD parcelshop', 'wc-dpd');
        $this->method_description = __('Allow customers to deliver to the DPD parcelshops.', 'wc-dpd');
        $this->supports = [
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        ];

        $this->init();
    }

    /**
     * Init function
     *
     * @return void
     */
    public function init()
    {
        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables.
        $this->title = __('DPD Pickup/Pickup Station', 'wc-dpd');
        $this->tax_status = $this->get_option('tax_status');

        $fee = (float) wp_kses_post($this->get_option('fee'));
        $this->fee = $fee ? $fee : 0;

        $this->init_form_fields();
        $this->init_settings();

        add_action('admin_footer', [__CLASS__, 'addScripts'], 10, 1);
        add_filter('woocommerce_generate_repeater_html', [$this, 'addRepeaterFieldHtml'], 10, 4);

        add_filter('woocommerce_shipping_' . self::SETTINGS_ID_KEY . '_instance_settings_values', [$this, 'adjustPostData'], 0, 2);
        add_action('woocommerce_update_options_shipping_' . self::SETTINGS_ID_KEY, [$this, 'process_admin_options']);
    }

    /**
     * Process and validate admin options
     *
     * @return bool
     */
    public function process_admin_options()
    {
        // Validate that at least one pickup point type is enabled
        $disallow_shops = isset($_POST[self::DISALLOW_SHOPS_OPTION_KEY]) && $_POST[self::DISALLOW_SHOPS_OPTION_KEY] === '1';
        $disallow_lockers = isset($_POST[self::DISALLOW_LOCKERS_OPTION_KEY]) && $_POST[self::DISALLOW_LOCKERS_OPTION_KEY] === '1';

        // Check individual locker types
        $disallow_dpd_pickup_stations = isset($_POST[self::DISALLOW_DPD_PICKUP_STATIONS_OPTION_KEY]) && $_POST[self::DISALLOW_DPD_PICKUP_STATIONS_OPTION_KEY] === '1';
        $disallow_sk_post = isset($_POST[self::DISALLOW_SK_POST_OPTION_KEY]) && $_POST[self::DISALLOW_SK_POST_OPTION_KEY] === '1';
        $disallow_alza_boxes = isset($_POST[self::DISALLOW_ALZA_BOXES_OPTION_KEY]) && $_POST[self::DISALLOW_ALZA_BOXES_OPTION_KEY] === '1';
        $disallow_zbox = isset($_POST[self::DISALLOW_ZBOX_OPTION_KEY]) && $_POST[self::DISALLOW_ZBOX_OPTION_KEY] === '1';

        $all_individual_locker_types_disabled = $disallow_dpd_pickup_stations && $disallow_sk_post && $disallow_alza_boxes && $disallow_zbox;

        // Check if shops are disabled AND (either global lockers are disabled OR all individual locker types are disabled)
        if ($disallow_shops && ($disallow_lockers || $all_individual_locker_types_disabled)) {
            \WC_Admin_Settings::add_error(__('At least one pickup point type must remain enabled. You cannot disable shops and all locker types at the same time.', 'wc-dpd'));

            // Prevent saving the invalid configuration
            if ($disallow_lockers) {
                unset($_POST[self::DISALLOW_LOCKERS_OPTION_KEY]);
            } else {
                // Unset the last individual locker type that was checked
                unset($_POST[self::DISALLOW_ZBOX_OPTION_KEY]);
            }

            // Still save other settings
            return parent::process_admin_options();
        }

        // Validate that we cannot have all individual locker types disabled AND the global locker disable checked (redundant configuration)
        if ($disallow_lockers && $all_individual_locker_types_disabled) {
            \WC_Admin_Settings::add_error(__('You cannot disable all lockers globally and also disable all individual locker types at the same time. This configuration is redundant.', 'wc-dpd'));

            // Prevent saving by unsetting the global locker checkbox
            unset($_POST[self::DISALLOW_LOCKERS_OPTION_KEY]);

            // Still save other settings
            return parent::process_admin_options();
        }

        // Call parent method to save settings
        return parent::process_admin_options();
    }

    /**
     * Initialize method options fields
     *
     * @return void
     */
    public function init_form_fields()
    {
        $weight_unit = (string) get_option('woocommerce_weight_unit');

        $this->instance_form_fields = [
            'tax_status' => array(
                'title'   => __('Tax status', 'wc-dpd'),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'default' => 'none',
                'options' => array(
                    'none'    => _x('None', 'Tax status', 'wc-dpd'),
                    'taxable' => __('Taxable', 'wc-dpd'),
                ),
            ),
            self::SHIPPING_PRICE_TYPE_OPTION_KEY => [
                'title' => __('Shipping type', 'wc-dpd'),
                'type' => 'select',
                'options' => [
                    'fixed' => __('Fixed shipping price', 'wc-dpd'),
                    'products_weight_based' => __('Products weight based shipping price', 'wc-dpd'),
                ],
                'description' => __('Choose type of the shipping.', 'wc-dpd'),
                'desc_tip' => true,
                'class' => 'js-dpd-shipping-type-select'
            ],
            'fee' => [
                'title' => __('Delivery fee', 'wc-dpd'),
                'type' => 'price',
                'description' => __('What fee do you want to charge for shipping to the parcelshop.', 'wc-dpd'),
                'default' => '',
                'desc_tip' => true,
                'placeholder' => wc_format_localized_price(0),
                'class' => 'js-dpd-fixed-shipping-type'
            ],
            self::FREE_FIXED_SHIPPING_OPTION_KEY => [
                'title' => __('Free shipping from', 'wc-dpd'),
                'type' => 'price',
                'description' => __('Set minimum cart value for free shipping. Leave empty to disable free shipping entirely.', 'wc-dpd'),
                'default' => '',
                'desc_tip' => true,
                'placeholder' => wc_format_localized_price(0),
                'class' => 'js-dpd-fixed-shipping-type'
            ],
            self::PRODUCTS_WEIGHT_SHIPPING_RATES_OPTION_KEY => [
                'title' => __('Products weight based shipping rates', 'wc-dpd'),
                'type' => 'repeater',
                'description' => __('Add shipping rates based on the weight of products in the cart.', 'wc-dpd'),
                'desc_tip' => true,
                'label_text' => __('Shipping rate', 'wc-dpd'),
                'min_weight_input_text' => sprintf(__('Min weight (%s)', 'wc-dpd'), $weight_unit),
                'max_weight_input_text' => sprintf(__('Max weight (%s)', 'wc-dpd'), $weight_unit),
                'price_input_text' => __('Price', 'wc-dpd') . ' ' . (wc_prices_include_tax() ? __('with', 'wc-dpd') : __('without', 'wc-dpd')) . ' ' . __('tax', 'wc-dpd'),
                'min_weight_input_placeholder_text' => __('Min weight', 'wc-dpd'),
                'max_weight_input_placeholder_text' => __('Max weight', 'wc-dpd'),
                'price_input_placeholder_text' => __('Price', 'wc-dpd'),
                'add_btn_text' => __('Add a shipping rate', 'wc-dpd'),
                'class' => 'js-dpd-weight-based-shipping-type'
            ],
            self::FREE_WEIGHT_BASED_SHIPPING_OPTION_KEY => [
                'title' => __('Free shipping from', 'wc-dpd'),
                'type' => 'price',
                'description' => __('Set minimum cart value for free shipping. Leave empty to disable free shipping entirely.', 'wc-dpd'),
                'default' => '',
                'desc_tip' => true,
                'placeholder' => wc_format_localized_price(0),
                'class' => 'js-dpd-weight-based-shipping-type'
            ],
            self::PACKAGE_WEIGHT_SHIPPING_LIMITS_OPTION_KEY => [
                'title' => __('Setting the weight limits for packages', 'wc-dpd'),
                'description' => __('If the shipment does not meet the conditions for delivery in a parcelbox, this shipping method will not be displayed.', 'wc-dpd'),
                'type' => 'checkbox',
                'default' => false,
                'desc_tip' => true,
                'class' => 'js-dpd-checkbox-weight-limit',
            ],
            self::PACKAGE_WEIGHT_SHIPPING_LIMITS_MAX_WEIGHT_OPTION_KEY => [
                'title' => __('Maximum weight', 'wc-dpd'),
                'type' => 'number',
                'default' => '',
                'desc_tip' => true,
                'class' => 'js-dpd-weight-limit-shipping-type',
            ],
            self::PACKAGE_WEIGHT_SHIPPING_LIMITS_MAX_WEIGHT_ALZABOX_OPTION_KEY => [
                'title' => __('Maximum weight for Alzabox', 'wc-dpd'),
                'type' => 'number',
                'default' => '',
                'desc_tip' => true,
                'class' => 'js-dpd-weight-limit-shipping-type',
            ],
            self::PACKAGE_WEIGHT_SHIPPING_LIMITS_MAX_WEIGHT_SLOVENSKA_POSTA_OPTION_KEY => [
                'title' => __('Maximum weight for Slovenska Posta box', 'wc-dpd'),
                'type' => 'number',
                'default' => '',
                'desc_tip' => true,
                'class' => 'js-dpd-weight-limit-shipping-type',
            ],
            self::PACKAGE_WEIGHT_SHIPPING_LIMITS_MAX_WEIGHT_ZBOX_OPTION_KEY => [
                'title' => __('Maximum weight for Z-Box (Packeta)', 'wc-dpd'),
                'type' => 'number',
                'default' => '',
                'desc_tip' => true,
                'class' => 'js-dpd-weight-limit-shipping-type',
            ],
            self::PACKAGE_DIMENSION_SHIPPING_LIMITS_OPTION_KEY => [
                'title' => __('Setting the dimension limits for packages', 'wc-dpd'),
                'description' => __('If the shipment does not meet the conditions for delivery in a box, this shipping method will not be displayed.', 'wc-dpd'),
                'type' => 'checkbox',
                'default' => false,
                'desc_tip' => true,
                'class' => 'js-dpd-checkbox-dimension-limit',
            ],
            self::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_WIDTH_OPTION_KEY => [
                'title' => __('Maximum width', 'wc-dpd'),
                'type' => 'number',
                'class' => 'js-dpd-dimension-limit-shipping-type',
            ],
            self::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_HEIGHT_OPTION_KEY => [
                'title' => __('Maximum height', 'wc-dpd'),
                'type' => 'number',
                'class' => 'js-dpd-dimension-limit-shipping-type',
            ],
            self::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_LENGTH_OPTION_KEY => [
                'title' => __('Maximum length', 'wc-dpd'),
                'type' => 'number',
                'class' => 'js-dpd-dimension-limit-shipping-type',
            ],
            self::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_WIDTH_ALZABOX_OPTION_KEY => [
                'title' => __('Maximum width for Alzabox', 'wc-dpd'),
                'type' => 'number',
                'class' => 'js-dpd-dimension-limit-shipping-type',
            ],
            self::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_HEIGHT_ALZABOX_OPTION_KEY => [
                'title' => __('Maximum height for Alzabox', 'wc-dpd'),
                'type' => 'number',
                'class' => 'js-dpd-dimension-limit-shipping-type',
            ],
            self::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_LENGTH_ALZABOX_OPTION_KEY => [
                'title' => __('Maximum length for Alzabox', 'wc-dpd'),
                'type' => 'number',
                'class' => 'js-dpd-dimension-limit-shipping-type',
            ],
            self::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_WIDTH_SLOVENSKA_POSTA_OPTION_KEY => [
                'title' => __('Maximum width for Slovenska Posta box', 'wc-dpd'),
                'type' => 'number',
                'class' => 'js-dpd-dimension-limit-shipping-type',
            ],
            self::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_HEIGHT_SLOVENSKA_POSTA_OPTION_KEY => [
                'title' => __('Maximum height for Slovenska Posta box', 'wc-dpd'),
                'type' => 'number',
                'class' => 'js-dpd-dimension-limit-shipping-type',
            ],
            self::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_LENGTH_SLOVENSKA_POSTA_OPTION_KEY => [
                'title' => __('Maximum length for Slovenska Posta box', 'wc-dpd'),
                'type' => 'number',
                'class' => 'js-dpd-dimension-limit-shipping-type',
            ],
            self::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_WIDTH_ZBOX_OPTION_KEY => [
                'title' => __('Maximum width for Z-Box (Packeta)', 'wc-dpd'),
                'type' => 'number',
                'class' => 'js-dpd-dimension-limit-shipping-type',
            ],
            self::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_HEIGHT_ZBOX_OPTION_KEY => [
                'title' => __('Maximum height for Z-Box (Packeta)', 'wc-dpd'),
                'type' => 'number',
                'class' => 'js-dpd-dimension-limit-shipping-type',
            ],
            self::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_LENGTH_ZBOX_OPTION_KEY => [
                'title' => __('Maximum length for Z-Box (Packeta)', 'wc-dpd'),
                'type' => 'number',
                'class' => 'js-dpd-dimension-limit-shipping-type',
            ],
            self::DISALLOW_SHOPS_OPTION_KEY => [
                'title' => __('Disallow Shops', 'wc-dpd'),
                'type' => 'checkbox',
                'default' => false,
                'desc_tip' => true,
                'description' => __('If checked, DPD Pickup shops will be disabled.', 'wc-dpd'),
                'class' => 'js-dpd-disallow-shops-checkbox',
            ],
            self::DISALLOW_LOCKERS_OPTION_KEY => [
                'title' => __('Disallow Lockers', 'wc-dpd'),
                'type' => 'checkbox',
                'default' => false,
                'desc_tip' => true,
                'description' => __('If checked, all locker types will be disabled.', 'wc-dpd'),
                'class' => 'js-dpd-disallow-lockers-checkbox',
            ],
            self::DISALLOW_DPD_PICKUP_STATIONS_OPTION_KEY => [
                'title' => __('Disallow DPD Pickup Stations', 'wc-dpd'),
                'type' => 'checkbox',
                'default' => false,
                'desc_tip' => true,
                'description' => __('If checked, DPD Pickup Stations will be disabled.', 'wc-dpd'),
                'class' => 'js-dpd-disallow-dpd-pickup-stations-checkbox',
            ],
            self::DISALLOW_SK_POST_OPTION_KEY => [
                'title' => __('Disallow Slovenská Pošta Boxes', 'wc-dpd'),
                'type' => 'checkbox',
                'default' => false,
                'desc_tip' => true,
                'description' => __('If checked, Slovenská Pošta boxes will be disabled.', 'wc-dpd'),
                'class' => 'js-dpd-disallow-sk-post-checkbox',
            ],
            self::DISALLOW_ALZA_BOXES_OPTION_KEY => [
                'title' => __('Disallow Alza Boxes', 'wc-dpd'),
                'type' => 'checkbox',
                'default' => false,
                'desc_tip' => true,
                'description' => __('If checked, Alza Boxes will be disabled.', 'wc-dpd'),
                'class' => 'js-dpd-disallow-alza-boxes-checkbox',
            ],
            self::DISALLOW_ZBOX_OPTION_KEY => [
                'title' => __('Disallow Z-Box (Packeta)', 'wc-dpd'),
                'type' => 'checkbox',
                'default' => false,
                'desc_tip' => true,
                'description' => __('If checked, Z-Box (Packeta) will be disabled.', 'wc-dpd'),
                'class' => 'js-dpd-disallow-zbox-checkbox',
            ],
        ];
    }

    /**
     * Calculate shipping cost
     *
     * @param array $package
     *
     * @return void
     */
    public function calculate_shipping($package = [])
    {
        // Check if free shipping threshold is set (including 0 as a valid value)
        $free_shipping_is_set = isset($this->instance_settings[self::FREE_FIXED_SHIPPING_OPTION_KEY]) &&
                                $this->instance_settings[self::FREE_FIXED_SHIPPING_OPTION_KEY] !== '' &&
                                $this->instance_settings[self::FREE_FIXED_SHIPPING_OPTION_KEY] !== null;

        $free_shipping = $free_shipping_is_set ? (float) $this->instance_settings[self::FREE_FIXED_SHIPPING_OPTION_KEY] : false;

        // Get cart subtotal including tax
        $cart_subtotal_incl_tax = WC()->cart->get_cart_contents_total(true);

        // Adjust cart total based on whether prices are entered with tax
        if (wc_prices_include_tax()) {
            $cart_total = $cart_subtotal_incl_tax;
        } else {
            $cart_total = WC()->cart->get_cart_contents_total();
        }

        // Deduct tax from free shipping threshold if prices include tax
        if ($free_shipping_is_set && wc_prices_include_tax()) {
            $tax_total = WC()->cart->get_cart_contents_tax();
            $free_shipping -= $tax_total;
        }

        $rate = [
            'id' => $this->id,
            'label' => $this->title,
            'cost' => ($free_shipping_is_set && $cart_total >= $free_shipping) ? 0 : (float) $this->fee,
            'calc_tax' => 'per_order'
        ];

        // Maybe calculate Products weight based shipping
        if (
            isset($this->instance_settings[self::SHIPPING_PRICE_TYPE_OPTION_KEY]) &&
            $this->instance_settings[self::SHIPPING_PRICE_TYPE_OPTION_KEY] == 'products_weight_based' &&
            !empty($this->instance_settings[self::PRODUCTS_WEIGHT_SHIPPING_RATES_OPTION_KEY])
        ) {
            // Check if free shipping threshold for weight-based is set
            $free_weight_shipping_is_set = isset($this->instance_settings[self::FREE_WEIGHT_BASED_SHIPPING_OPTION_KEY]) &&
                                           $this->instance_settings[self::FREE_WEIGHT_BASED_SHIPPING_OPTION_KEY] !== '' &&
                                           $this->instance_settings[self::FREE_WEIGHT_BASED_SHIPPING_OPTION_KEY] !== null;

            $free_weight_shipping = $free_weight_shipping_is_set ? (float) $this->instance_settings[self::FREE_WEIGHT_BASED_SHIPPING_OPTION_KEY] : false;

            // Deduct tax from free shipping threshold if prices include tax
            if ($free_weight_shipping_is_set && wc_prices_include_tax()) {
                $tax_total = WC()->cart->get_cart_contents_tax();
                $free_weight_shipping -= $tax_total;
            }

            // Check if cart total qualifies for free shipping
            if ($free_weight_shipping_is_set && $cart_total >= $free_weight_shipping) {
                $rate['cost'] = 0;
            } else {
                // Calculate weight-based rate
                $items_total_weight = self::getCartTotalWeight();
                $products_weight_based_rates = maybe_unserialize($this->instance_settings[self::PRODUCTS_WEIGHT_SHIPPING_RATES_OPTION_KEY]);

                if (!empty($products_weight_based_rates)) {
                    foreach ($products_weight_based_rates as $products_weight_rate) {
                        $weight_rate_from = !empty($products_weight_rate['min']) ? (int) $products_weight_rate['min'] : 0;
                        $weight_rate_to = !empty($products_weight_rate['max']) ? (int) $products_weight_rate['max'] : 999;

                        if ($items_total_weight >= $weight_rate_from) {
                            $rate['cost'] = !empty($products_weight_rate['price']) ? number_format((float) $products_weight_rate['price'], wc_get_price_decimals(), '.', '') : 0;
                        }

                        if ($items_total_weight >= $weight_rate_from && $items_total_weight <= $weight_rate_to) {
                            $rate['cost'] = !empty($products_weight_rate['price']) ? number_format((float) $products_weight_rate['price'], wc_get_price_decimals(), '.', '') : 0;
                        }
                    }
                }
            }
        }

        // Check if a free shipping coupon is applied
        $applied_coupons = WC()->cart->get_applied_coupons();
        $free_shipping_coupon_applied = false;

        foreach ($applied_coupons as $coupon_code) {
            $coupon = new \WC_Coupon($coupon_code);
            if ($coupon->get_free_shipping()) {
                $free_shipping_coupon_applied = true;
                break;
            }
        }

        // If free shipping coupon applied, set rate cost to 0
        if ($free_shipping_coupon_applied) {
            $rate['cost'] = 0;
        }

        $this->add_rate($rate);
    }

    /**
     * Get total weight of products in cart
     *
     * @return float|int
     */
    public static function getCartTotalWeight()
    {
        $cart = WC()->cart;

        if (!$cart) {
            return 0;
        }

        // Get an array of cart item objects
        $cart_items = $cart->get_cart();

        if (!$cart_items) {
            return 0;
        }

        // Initialize the total weight variable
        $total_weight = 0;

        // Loop through each cart item and add its weight to the total weight
        foreach ($cart_items as $cart_item_key => $cart_item) {
            $product = !empty($cart_item['data']) ? $cart_item['data'] : null;

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

        return (float) $total_weight;
    }

    /**
     * Adjust repeater field values on save
     */
    public function adjustPostData($settings, $instance)
    {
        if (empty($_POST['data'])) {
            return;
        }

        $repeater_values = [];

        $post_data = $_POST['data'];

        if (!empty($post_data['woocommerce_' . self::SETTINGS_ID_KEY . '_' . self::PRODUCTS_WEIGHT_SHIPPING_RATES_OPTION_KEY . '_min'])) {
            foreach ($post_data['woocommerce_' . self::SETTINGS_ID_KEY . '_' . self::PRODUCTS_WEIGHT_SHIPPING_RATES_OPTION_KEY . '_min'] as $key => $value) {
                $repeater_values[$key]['min'] = (int) sanitize_text_field($value);
            }

            unset($post_data['woocommerce_' . self::SETTINGS_ID_KEY . '_' . self::PRODUCTS_WEIGHT_SHIPPING_RATES_OPTION_KEY . '_min']);
        }

        if (!empty($post_data['woocommerce_' . self::SETTINGS_ID_KEY . '_' . self::PRODUCTS_WEIGHT_SHIPPING_RATES_OPTION_KEY . '_max'])) {
            foreach ($post_data['woocommerce_' . self::SETTINGS_ID_KEY . '_' . self::PRODUCTS_WEIGHT_SHIPPING_RATES_OPTION_KEY . '_max'] as $key => $value) {
                $repeater_values[$key]['max'] = (int) sanitize_text_field($value);
            }

            unset($post_data['woocommerce_' . self::SETTINGS_ID_KEY . '_' . self::PRODUCTS_WEIGHT_SHIPPING_RATES_OPTION_KEY . '_max']);
        }

        if (!empty($post_data['woocommerce_' . self::SETTINGS_ID_KEY . '_' . self::PRODUCTS_WEIGHT_SHIPPING_RATES_OPTION_KEY . '_price'])) {
            foreach ($post_data['woocommerce_' . self::SETTINGS_ID_KEY . '_' . self::PRODUCTS_WEIGHT_SHIPPING_RATES_OPTION_KEY . '_price'] as $key => $value) {
                $repeater_values[$key]['price'] = number_format((float) $value, wc_get_price_decimals(), '.', '');
            }

            unset($post_data['woocommerce_' . self::SETTINGS_ID_KEY . '_' . self::PRODUCTS_WEIGHT_SHIPPING_RATES_OPTION_KEY . '_price']);
        }

        $settings[self::PRODUCTS_WEIGHT_SHIPPING_RATES_OPTION_KEY] = serialize($repeater_values);

        return $settings;
    }

    /**
    * Add repeater field html
    *
    * @param string $html
    * @param string $key
    * @param array $data
    * @param object $wc_settings
    *
    * @return string
    */
    public function addRepeaterFieldHtml($html = '', $key = '', $data = [], $wc_settings = null)
    {
        if ($key != self::PRODUCTS_WEIGHT_SHIPPING_RATES_OPTION_KEY) {
            return $html;
        }

        $field_key = $this->get_field_key($key);

        $defaults  = array(
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
        );

        $data = wp_parse_args($data, $defaults);

        $values = self::getRepeaterOptions($key, $wc_settings);
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
            'removeLabel' => __('Remove', 'wc-dpd'),
            'title' => __('Title', 'wc-dpd'),
        ];
        $props = htmlspecialchars(json_encode($props), ENT_QUOTES, 'UTF-8');

        ob_start();
        ?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?> <?php echo $this->get_tooltip_html($data); // WPCS: XSS ok.?></label>
				</th>

				<td class="forminp">
					<fieldset class="repeatable-field repeatable-field--<?php echo $key; ?> <?php echo esc_attr($data['class']); ?>" data-component="field-repeater" data-props="<?php echo $props; ?>" data-inputs-data="<?php echo $values; ?>" tabindex="0">
						<legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>

						<?php if (!empty($data['repeater_description'])) : ?>
							<p><small><?php echo wp_kses_post($data['repeater_description']); ?></small></p>
						<?php endif; ?>

						<ol class="repeatable-field__rows" data-ref="rowList"></ol>

						<div class="repeatable-field__bottom">
							<button class="repeatable-field__add-button button" data-ref="addButton" type="button">+ <?php echo esc_attr($data['add_btn_text']); ?></button>
						</div>

						<?php echo $this->get_description_html($data); // WPCS: XSS ok.?>
					</fieldset>
				</td>
			</tr>
		<?php

        return ob_get_clean();
    }

    /**
     * Get repeater option list of options
     *
     * @param string $option_key
     * @param object $wc_settings
     *
     * @return array
     */
    public static function getRepeaterOptions($option_key = '', $wc_settings = null)
    {
        return maybe_unserialize($wc_settings->get_option($option_key));
    }

    /**
     * Add admin scripts
     *
     * @return void
     */
    public static function addScripts()
    {
        global $pagenow;

        if ($pagenow !== 'admin.php') {
            return;
        }

        $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        $tab  = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : '';

        if ($page !== 'wc-settings' || $tab !== 'shipping') {
            return;
        }

        // Repeater field assets (includes admin validation module)
        wp_enqueue_script(self::SETTINGS_ID_KEY . '_repeater_field', WCDPD_PLUGIN_ASSETS_URL . 'scripts/dpd-parcelshop-shipping-method-weight-by-package-repeater.js', [], wc_dpd_get_plugin_version(), true);
        wp_localize_script(self::SETTINGS_ID_KEY . '_repeater_field', 'wc_dpd_admin_validation_settings', [
            'pickup_types_validation_error' => __('At least one pickup point type must remain enabled. You cannot disable shops and all locker types at the same time.', 'wc-dpd'),
            'redundant_configuration_error' => __('You cannot disable all lockers globally and also disable all individual locker types at the same time. This configuration is redundant.', 'wc-dpd'),
        ]);
        wp_enqueue_style(self::SETTINGS_ID_KEY . '_repeater_field', WCDPD_PLUGIN_ASSETS_URL . 'styles/dpd-export-repeater-settings-field.css', [], wc_dpd_get_plugin_version(), 'all');
    }

    /**
     * Get parcelshop shipping method settings by shipping zone
     *
     * @return array
     */
    public static function getSettings()
    {
        // Get cart shipping packages
        $shipping_packages =  WC()->cart->get_shipping_packages();

        if (empty($shipping_packages)) {
            return [];
        }

        // Get the WC_Shipping_Zones instance object for the first package
        $shipping_zone = wc_get_shipping_zone(reset($shipping_packages));

        if (!$shipping_zone instanceof \WC_Shipping_Zone) {
            return [];
        }

        $shipping_zone_id = $shipping_zone->get_id(); // Get the zone ID

        if (!is_int($shipping_zone_id)) {
            return [];
        }

        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT instance_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE zone_id = %d AND method_id = %s",
            $shipping_zone_id,
            self::SETTINGS_ID_KEY
        );

        $instances = $wpdb->get_results($query);

        if (empty($instances[0]) || !isset($instances[0]->instance_id)) {
            return [];
        }

        $instance_id = $instances[0]->instance_id;

        $query = $wpdb->prepare(
            "SELECT option_value
    		FROM {$wpdb->prefix}options
    		WHERE option_name = %s",
            'woocommerce_' . self::SETTINGS_ID_KEY . '_' . $instance_id . '_settings'
        );

        $settings = $wpdb->get_results($query);

        if (empty($settings[0]) || !isset($settings[0]->option_value)) {
            return [];
        }

        $settings = maybe_unserialize($settings[0]->option_value);

        if (!is_array($settings)) {
            return [];
        }

        return $settings;
    }
}
