<?php

namespace ArDesign\DPD;

defined('ABSPATH') || exit;

/**
 * Class that handles WooCommerce Blocks integration
 */
class Blocks
{
    private const STORE_API_EXTENSION_NAMESPACE = 'ar-design-dpd';
    private const CHECKOUT_BLOCKS_REGISTRY_HOOKS = [
        'woocommerce_blocks_cart_block_registration',
        'woocommerce_blocks_checkout_block_registration',
    ];

    public static function init()
    {
        // If WooCommerce Blocks is not active, do not proceed
        if (!is_woocommerce_blocks_enabled()) {
            return;
        }

        if (interface_exists('\Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface')) {
            foreach (self::CHECKOUT_BLOCKS_REGISTRY_HOOKS as $registry_hook) {
                add_action($registry_hook, [__CLASS__, 'registerCheckoutBlocksIntegration']);
            }
        }

        add_action('woocommerce_store_api_checkout_update_order_from_request', [__CLASS__, 'persistParcelShopOnStoreApi'], 5, 2);
        // Hook into Store API validation - this is the main validation for blocks checkout
        add_action('woocommerce_store_api_checkout_update_order_from_request', [__CLASS__, 'validateParcelShopOnStoreApi'], 10, 2);

        // Additional validation hook for checkout processing
        add_action('woocommerce_rest_checkout_process_payment_with_context', [__CLASS__, 'validateParcelShopBeforePayment'], 5, 2);
    }

    public static function registerCheckoutBlocksIntegration($integration_registry): void
    {
        if (!is_object($integration_registry) || !method_exists($integration_registry, 'register')) {
            return;
        }

        if (!class_exists(__NAMESPACE__ . '\\BlocksIntegration') && defined('AR_DESIGN_DPD_PLUGIN_PATH')) {
            require_once AR_DESIGN_DPD_PLUGIN_PATH . 'includes' . DIRECTORY_SEPARATOR . 'BlocksIntegration.php';
        }

        if (method_exists($integration_registry, 'is_registered') && $integration_registry->is_registered('ard_dpd_checkout_blocks')) {
            return;
        }

        $integration_registry->register(new \ArDesign\DPD\BlocksIntegration());
    }

    public static function getCheckoutBlockScriptData(): array
    {
        return [
            'ready' => true,
            'extension_namespace' => self::STORE_API_EXTENSION_NAMESPACE,
            'storage_key' => 'ard_dpd_chosen_parcelshop',
            'template_html' => self::getTemplateContent(),
            'field_keys' => array_keys(Order::getParcelshopFieldsToSave()),
            'required_field_keys' => [
                DpdParcelShopShippingMethod::PARCELSHOP_PUS_ID_META_KEY,
                DpdParcelShopShippingMethod::PARCELSHOP_NAME_META_KEY,
                DpdParcelShopShippingMethod::PARCELSHOP_STREET_META_KEY,
                DpdParcelShopShippingMethod::PARCELSHOP_ZIP_META_KEY,
                DpdParcelShopShippingMethod::PARCELSHOP_CITY_META_KEY,
                DpdParcelShopShippingMethod::PARCELSHOP_COUNTRY_CODE_META_KEY,
            ],
            'template_class' => 'dpd-parcelshop-container',
            'template_selected_class' => 'is-selected',
            'option_selector' => '.wc-block-components-radio-control__option',
            'radio_selectors' => [
                'input[type="radio"][id*="wc_dpd_parcelshop"]',
                'input[type="radio"][id*="ard_dpd_parcelshop"]',
                'input[type="radio"][value*="dpd_parcelshop"]',
                'input[type="radio"][name*="dpd_parcelshop"]',
            ],
            'chosen_wrap_selector' => '.js-dpd-chosen-parcelshop-content',
            'chosen_text_selector' => '.js-dpd-chosen-parcelshop-chosen-parcelshop-text',
        ];
    }

    /**
     * Validate parcel shop selection for Store API requests
     *
     * @param \WC_Order $order
     * @param \WP_REST_Request $request
     */
    public static function validateParcelShopOnStoreApi($order, $request)
    {
        if ($order instanceof \WC_Order) {
            self::captureParcelshopDataFromStoreApiRequest($order, $request);
        }

        self::validateParcelShopSelection($order);
    }

    public static function persistParcelShopOnStoreApi($order, $request): void
    {
        if (!$order instanceof \WC_Order) {
            return;
        }

        self::captureParcelshopDataFromStoreApiRequest($order, $request);
    }

    /**
     * Validate parcel shop selection before payment processing
     *
     * @param \Automattic\WooCommerce\StoreApi\Utilities\CheckoutTrait $context
        * @param mixed $result
     */
    public static function validateParcelShopBeforePayment($context, $result)
    {
        $context_data = is_object($context) ? (array) $context : [];
        $order = $context_data['order'] ?? null;

        if ($order instanceof \WC_Order) {
            self::captureParcelshopDataFromStoreApiRequest($order);
            self::validateParcelShopSelection($order);
        }
    }

    private static function captureParcelshopDataFromStoreApiRequest(\WC_Order $order, $request = null): void
    {
        $request_parcelshop_data = self::getParcelshopDataFromRequest($request);

        if ($request_parcelshop_data !== []) {
            \ArDesign\DPD\Order::storeChosenParcelshopSessionData($request_parcelshop_data);
            \ArDesign\DPD\Order::persistParcelshopDataToOrder($order, $request_parcelshop_data);
        }

        \ArDesign\DPD\Order::persistChosenParcelshopSessionData($order);
    }

    private static function getParcelshopDataFromRequest($request): array
    {
        if (!$request instanceof \WP_REST_Request) {
            return [];
        }

        $extensions = $request->get_param('extensions');
        $extension_data = [];

        if (is_array($extensions)) {
            $extension_candidate = $extensions[self::STORE_API_EXTENSION_NAMESPACE] ?? null;
            if (is_array($extension_candidate)) {
                $extension_data = $extension_candidate;
            }
        }

        $request_data = [];

        foreach (array_keys(\ArDesign\DPD\Order::getParcelshopFieldsToSave()) as $field_key) {
            $candidate = $request->get_param($field_key);

            if (($candidate === null || $candidate === '') && array_key_exists($field_key, $extension_data)) {
                $candidate = $extension_data[$field_key];
            }

            if ($candidate === null || $candidate === '') {
                continue;
            }

            $request_data[$field_key] = $candidate;
        }

        return \ArDesign\DPD\Order::sanitizeParcelshopData($request_data);
    }

    /**
     * Core validation logic for parcel shop selection
     *
     * @param \WC_Order $order
     * @throws \Exception
     */
    private static function validateParcelShopSelection($order)
    {
        $shipping_methods = $order->get_shipping_methods();

        foreach ($shipping_methods as $shipping_method) {
            if ($shipping_method->get_method_id() === DpdParcelShopShippingMethod::SETTINGS_ID_KEY) {
                // Check if parcel shop data exists in session
                $chosen_parcelshop_data = WC()->session ? WC()->session->get(Shipping::SESSION_CHOSEN_PARCELSHOP_KEY) : null;

                // If no session data, also check if data was passed in the request
                if (empty($chosen_parcelshop_data)) {
                    // Check if parcel shop data was passed directly in the order meta or request
                    $parcelshop_pus_id = $order->get_meta(DpdParcelShopShippingMethod::PARCELSHOP_PUS_ID_META_KEY);
                    $parcelshop_name = $order->get_meta(DpdParcelShopShippingMethod::PARCELSHOP_NAME_META_KEY);

                    if (empty($parcelshop_pus_id) && empty($parcelshop_name)) {
                        throw new \Exception(__("You have to choose a parcelshop.", "ar-design-dpd"));
                    }
                } else {
                    // Validate session data completeness using the correct field names
                    $required_fields = [
                        DpdParcelShopShippingMethod::PARCELSHOP_PUS_ID_META_KEY => __("Parcel shop ID is required.", "ar-design-dpd"),
                        DpdParcelShopShippingMethod::PARCELSHOP_NAME_META_KEY => __("Parcel shop name is required.", "ar-design-dpd"),
                        DpdParcelShopShippingMethod::PARCELSHOP_STREET_META_KEY => __("Parcel shop street is required.", "ar-design-dpd"),
                        DpdParcelShopShippingMethod::PARCELSHOP_ZIP_META_KEY => __("Parcel shop ZIP code is required.", "ar-design-dpd"),
                        DpdParcelShopShippingMethod::PARCELSHOP_CITY_META_KEY => __("Parcel shop city is required.", "ar-design-dpd"),
                        DpdParcelShopShippingMethod::PARCELSHOP_COUNTRY_CODE_META_KEY => __("Parcel shop country code is required.", "ar-design-dpd")
                    ];

                    foreach ($required_fields as $field => $error_message) {
                        if (empty($chosen_parcelshop_data[$field])) {
                            throw new \Exception($error_message);
                        }
                    }

                    $payment_support_error = \ArDesign\DPD\Shipping::getParcelshopPaymentSupportError((array) $chosen_parcelshop_data);
                    if ($payment_support_error !== '') {
                        throw new \Exception($payment_support_error);
                    }
                }

                break;
            }
        }
    }

    /**
    * Get template content for JavaScript rendering
     *
     * @return string The HTML content for the parcelshop template
     */
    public static function getTemplateContent()
    {
        // Get template data using shared method
        $chosen_parcelshop_data = WC()->session ? WC()->session->get(Shipping::SESSION_CHOSEN_PARCELSHOP_KEY) : null;
        $template_data = Shipping::prepareParcelshopTemplateData($chosen_parcelshop_data);

        ob_start();
        echo ard_dpd_include_template('parcelshop-shipping-method-content.php', $template_data);
        $content = ob_get_clean();

        // Return the raw content
        return $content;
    }
}
