<?php

namespace WcDPD;

defined('ABSPATH') || exit;

/**
 * Class that handles WooCommerce Blocks integration
 */
class Blocks
{
    public static function init()
    {
        // If WooCommerce Blocks is not active, do not proceed
        if (!is_woocommerce_blocks_enabled()) {
            return;
        }

        // Hook into Store API validation - this is the main validation for blocks checkout
        add_action('woocommerce_store_api_checkout_update_order_from_request', [__CLASS__, 'validateParcelShopOnStoreApi'], 10, 2);

        // Additional validation hook for checkout processing
        add_action('woocommerce_rest_checkout_process_payment_with_context', [__CLASS__, 'validateParcelShopBeforePayment'], 5, 2);
    }

    /**
     * Validate parcel shop selection for Store API requests
     *
     * @param \WC_Order $order
     * @param \WP_REST_Request $request
     */
    public static function validateParcelShopOnStoreApi($order, $request)
    {
        self::validateParcelShopSelection($order);
    }

    /**
     * Validate parcel shop selection before payment processing
     *
     * @param \Automattic\WooCommerce\StoreApi\Utilities\CheckoutTrait $context
     * @param \Automattic\WooCommerce\StoreApi\Utilities\PaymentResult $result
     */
    public static function validateParcelShopBeforePayment($context, $result)
    {
        if (isset($context->order)) {
            self::validateParcelShopSelection($context->order);
        }
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
                        throw new \Exception(__("You have to choose a parcelshop.", "wc-dpd"));
                    }
                } else {
                    // Validate session data completeness using the correct field names
                    $required_fields = [
                        DpdParcelShopShippingMethod::PARCELSHOP_PUS_ID_META_KEY => __("Parcel shop ID is required.", "wc-dpd"),
                        DpdParcelShopShippingMethod::PARCELSHOP_NAME_META_KEY => __("Parcel shop name is required.", "wc-dpd"),
                        DpdParcelShopShippingMethod::PARCELSHOP_STREET_META_KEY => __("Parcel shop street is required.", "wc-dpd"),
                        DpdParcelShopShippingMethod::PARCELSHOP_ZIP_META_KEY => __("Parcel shop ZIP code is required.", "wc-dpd"),
                        DpdParcelShopShippingMethod::PARCELSHOP_CITY_META_KEY => __("Parcel shop city is required.", "wc-dpd"),
                        DpdParcelShopShippingMethod::PARCELSHOP_COUNTRY_CODE_META_KEY => __("Parcel shop country code is required.", "wc-dpd")
                    ];

                    foreach ($required_fields as $field => $error_message) {
                        if (empty($chosen_parcelshop_data[$field])) {
                            throw new \Exception($error_message);
                        }
                    }
                }

                break;
            }
        }
    }

    /**
     * Get template content for JavaScript injection
     *
     * @return string The HTML content for the parcelshop template
     */
    public static function getTemplateContent()
    {
        // Get template data using shared method
        $chosen_parcelshop_data = WC()->session ? WC()->session->get(Shipping::SESSION_CHOSEN_PARCELSHOP_KEY) : null;
        $template_data = Shipping::prepareParcelshopTemplateData($chosen_parcelshop_data);

        ob_start();
        echo include_template('parcelshop-shipping-method-content.php', $template_data);
        $content = ob_get_clean();

        // Return the raw content
        return $content;
    }
}
