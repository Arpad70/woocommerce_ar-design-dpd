<?php

namespace ArDesign\DPD;

defined('ABSPATH') || exit;

/**
 * Order class
 */
class Order
{
    public const BANK_ID_META_KEY = 'dpd_bank_id';
    public const ADDRESS_ID_META_KEY = 'dpd_address_id';
    public const SHIPPING_META_KEY = 'dpd_shipping';
    public const NOTIFICATION_META_KEY = 'dpd_notification';
    public const REFERENCE_1_META_KEY = 'dpd_refrence_1';
    public const REFERENCE_2_META_KEY = 'dpd_refrence_2';
    public const TRACKING_NUMBER_META_KEY = 'dpd_tracking_number';
    public const PACKAGE_WEIGHT_META_KEY = 'dpd_package_weight';
    public const EXPORT_STATUS_META_KEY = 'dpd_export_status';
    public const EXPORT_SUCCESS_STATUS = 'success';
    public const EXPORT_FAILED_STATUS = 'failed';
    public const EXPORT_LAST_ERROR_META_KEY = 'dpd_export_last_error';
    public const EXPORT_LABEL_URL_META_KEY = 'dpd_export_label_url';
    public const EXPORT_PACKAGE_NUMBER_META_KEY = 'dpd_export_package_number';
    public const EXPORT_MPSID_META_KEY = 'dpd_export_mpsid';
    public const EXPORT_SHIPMENT_ID_META_KEY = 'dpd_export_shipment_id';
    public const EXPORT_LAST_WARNING_META_KEY = 'dpd_export_last_warning';

    public static function init()
    {
        add_action('woocommerce_checkout_update_order_meta', [__CLASS__, 'saveParcelShopShippingMethodFieldsToOrder'], 10, 2);
        add_action('woocommerce_store_api_checkout_order_processed', [__CLASS__, 'saveParcelShopShippingMethodFieldsToOrder'], 10, 1);
        add_action('woocommerce_order_details_after_order_table', [__CLASS__, 'displayParcelShopShippingOrderTableInfo'], 10, 1);
        add_action('woocommerce_admin_order_data_after_billing_address', [__CLASS__, 'displayParcelShopShippingAdminOrderInfo'], 10, 1);
    }

    /**
     * Export order to DPD
     *
     * @param integer $order_id
     *
     * @return bool
     */
    public static function export(int $order_id = 0): bool
    {
        if (!$order_id) {
            return false;
        }

        $order = wc_get_order($order_id);

        if (!$order instanceof \WC_Order) {
            return false;
        }

        if (!self::canExportOrder($order)) {
            Notice::error(sprintf(
                /* translators: %d: WooCommerce order ID. */
                __('Order %d is already exported to DPD.', 'ar-design-dpd'),
                $order_id
            ));

            return false;
        }

        try {
            $data = self::getOrderExportData($order);

            ard_dpd_do_action('wc_dpd_before_order_export', 'ard_dpd_before_order_export', $order, $data);

            $response = DpdExport::doExport($data, $order);

            $order->update_meta_data(Order::EXPORT_LABEL_URL_META_KEY, $response[Order::EXPORT_LABEL_URL_META_KEY]);
            $order->update_meta_data(Order::EXPORT_PACKAGE_NUMBER_META_KEY, $response[Order::EXPORT_PACKAGE_NUMBER_META_KEY]);
            $order->update_meta_data(Order::EXPORT_MPSID_META_KEY, $response[Order::EXPORT_MPSID_META_KEY]);
            $order->update_meta_data(Order::EXPORT_STATUS_META_KEY, $response[Order::EXPORT_STATUS_META_KEY]);
            $order->delete_meta_data(Order::EXPORT_LAST_ERROR_META_KEY);
            if (isset($response[Order::EXPORT_SHIPMENT_ID_META_KEY])) {
                $order->update_meta_data(Order::EXPORT_SHIPMENT_ID_META_KEY, $response[Order::EXPORT_SHIPMENT_ID_META_KEY]);
            }
            if (!empty($response[Client::RESPONSE_WARNING_MESSAGE_KEY])) {
                $order->update_meta_data(Order::EXPORT_LAST_WARNING_META_KEY, $response[Client::RESPONSE_WARNING_MESSAGE_KEY]);
            } else {
                $order->delete_meta_data(Order::EXPORT_LAST_WARNING_META_KEY);
            }
            $order->save_meta_data();

            $message = sprintf(
                /* translators: %d: WooCommerce order ID. */
                __('Order %d was successfully exported', 'ar-design-dpd'),
                $order_id
            );

            Notice::success($message);
            $order->add_order_note(Notice::PREFIX . $message);

            if (!empty($response[Client::RESPONSE_WARNING_MESSAGE_KEY])) {
                $warning_message = sanitize_text_field((string) $response[Client::RESPONSE_WARNING_MESSAGE_KEY]);
                Notice::add($warning_message, 'warning');
                $order->add_order_note(Notice::PREFIX . $warning_message);
            }

            ard_dpd_do_action('wc_dpd_after_order_export', 'ard_dpd_after_order_export', $order, $response);

            return true;
        } catch (\Exception $e) {
            $message = esc_html($e->getMessage());

            $order->update_meta_data(self::EXPORT_STATUS_META_KEY, self::EXPORT_FAILED_STATUS);
            $order->update_meta_data(self::EXPORT_LAST_ERROR_META_KEY, $message);
            $order->save_meta_data();

            Notice::error($message);
            $order->add_order_note(Notice::PREFIX . $message);

            ard_dpd_do_action('wc_dpd_order_export_error', 'ard_dpd_order_export_error', $order, $e);

            return false;
        }
    }

    /**
     * Reset export data
     *
     * @param integer $order_id
     *
     * @return void
     */
    public static function reset(int $order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order instanceof \WC_Order) {
            return false;
        }

        $order->delete_meta_data(Order::EXPORT_LABEL_URL_META_KEY);
        $order->delete_meta_data(Order::EXPORT_PACKAGE_NUMBER_META_KEY);
        $order->delete_meta_data(Order::EXPORT_MPSID_META_KEY);
        $order->delete_meta_data(Order::EXPORT_STATUS_META_KEY);
        $order->delete_meta_data(Order::EXPORT_LAST_ERROR_META_KEY);
        $order->delete_meta_data(Order::EXPORT_LAST_WARNING_META_KEY);
        $order->delete_meta_data(Order::EXPORT_SHIPMENT_ID_META_KEY);
        $order->save_meta_data();

        $message = sprintf(
            /* translators: %d: WooCommerce order ID. */
            __('Order %d export data was successfully reset', 'ar-design-dpd'),
            $order_id
        );

        Notice::success($message);

        $order->add_order_note(Notice::PREFIX . $message);
    }

    /**
     * Get order data for export
     *
     * @param \WC_Order $order
     *
     * @return array
     */
    public static function getOrderExportData(\WC_Order $order)
    {
        $order_id = $order->get_id();
        $order = wc_get_order($order_id);
        $order_number = $order->get_order_number();
        $shipment_type = 'b2c';

        $billing_full_name = $order->get_formatted_billing_full_name();
        $shipping_full_name = $order->get_formatted_shipping_full_name();
        $full_name = trim($shipping_full_name) ? $shipping_full_name : $billing_full_name;

        // Throw error if full name is longer than 35 characters
        $full_name_allowed_length = 35;
        if (strlen($full_name) > $full_name_allowed_length) {
            throw new \Exception(sprintf(
                /* translators: 1: customer full name, 2: maximum allowed character count. */
                __('Full name %1$s is longer than %2$d characters. Please shorten it.', 'ar-design-dpd'),
                $full_name,
                $full_name_allowed_length
            ));
        }

        $billing_company = $order->get_billing_company();
        $shipping_company = $order->get_shipping_company();
        $company = trim($shipping_company) ? $shipping_company : $billing_company;
        $company_allowed_length = 35;

        if ($company) {
            // Throw error if company name is longer than 35 characters
            if (strlen($company) > $company_allowed_length) {
                throw new \Exception(sprintf(
                    /* translators: 1: company name, 2: maximum allowed character count. */
                    __('Company name %1$s is longer than %2$d characters. Please shorten it.', 'ar-design-dpd'),
                    $company,
                    $company_allowed_length
                ));
            }

            $shipment_type = 'b2b';
        }

        $billing_address_1 = $order->get_billing_address_1();
        $billing_address_2 = $order->get_billing_address_2();
        $shipping_address_1 = $order->get_shipping_address_1();
        $shipping_address_2 = $order->get_shipping_address_2();

        $street = $shipping_address_1 ? $shipping_address_1 : $billing_address_1;
        $street_2 = $shipping_address_2 ? $shipping_address_2 : $billing_address_2;

        $house_number = '';
        if ($street_2) {
            $house_number = $street_2;
        } elseif (preg_match('/^([^\d]*[^\d\s]) *(\d.*)$/', $street, $parsed_address_1)) {
            $street = !empty($parsed_address_1[1]) ? $parsed_address_1[1] : '';
            $house_number = !empty($parsed_address_1[2]) ? $parsed_address_1[2] : '';
        }

        $billing_postcode = $order->get_billing_postcode();
        $shipping_postcode = $order->get_shipping_postcode();
        $postcode = $shipping_postcode ? $shipping_postcode : $billing_postcode;

        $billing_city = $order->get_billing_city();
        $shipping_city = $order->get_shipping_city();
        $city = $shipping_city ? $shipping_city : $billing_city;

        $billing_country_code = $order->get_billing_country();
        $shipping_country_code = $order->get_shipping_country();
        $country_code = $shipping_country_code ? $shipping_country_code : $billing_country_code;

        $phone = $order->get_billing_phone();
        $email = $order->get_billing_email();
        $customer_note = $order->get_customer_note();
        $order_price = $order->get_total();
        $order_currency = $order->get_currency();
        $shipping_method = '';
        $order_shipping_methods = $order->get_shipping_methods();
        $first_shipping_method = reset($order_shipping_methods);
        if ($first_shipping_method && is_callable([$first_shipping_method, 'get_method_id'])) {
            $shipping_method = (string) $first_shipping_method->get_method_id();
        }
        $payment_method = $order->get_payment_method();
        $order_pickup_date = self::getPickupDate();
        $parcelshop_id = 0;
        $parcelshop_pus_id = 0;
        $parcelshop_cod_allowed = '';

        // If order has parcel shipping selected, change settings
        $has_parcelshop_shipping = Order::hasParcelShpping($order);
        if ($has_parcelshop_shipping) {
            $shipment_type = 'psd';
            $parcelshop_id = $order->get_meta(DpdParcelShopShippingMethod::PARCELSHOP_ID_META_KEY, true);
            $parcelshop_pus_id = $order->get_meta(DpdParcelShopShippingMethod::PARCELSHOP_PUS_ID_META_KEY, true);
            $parcelshop_cod_allowed = $order->get_meta(DpdParcelShopShippingMethod::PARCELSHOP_COD_META_KEY, true);
        }

        $bank_id = sanitize_text_field($order->get_meta(self::BANK_ID_META_KEY, true));
        $address_id = sanitize_text_field($order->get_meta(self::ADDRESS_ID_META_KEY, true));
        $reference_1 = sanitize_text_field($order->get_meta(self::REFERENCE_1_META_KEY, true));
        $reference_2 = sanitize_text_field($order->get_meta(self::REFERENCE_2_META_KEY, true));
        $package_weight = sanitize_text_field($order->get_meta(self::PACKAGE_WEIGHT_META_KEY, true));

        $notification = $order->get_meta(self::NOTIFICATION_META_KEY, true);
        $notification = $notification == 'yes' ? 'yes' : 'no';

        return [
            DpdExport::SHIPMENT_TYPE_KEY => $shipment_type,
            DpdExport::CUSTOMER_FULL_NAME_KEY => $full_name,
            DpdExport::CUSTOMER_COMPANY_KEY => $company,
            DpdExport::CUSTOMER_STREET_KEY => $street,
            DpdExport::CUSTOMER_HOUSE_NUMBER_KEY => $house_number,
            DpdExport::CUSTOMER_ZIP_KEY  => $postcode,
            DpdExport::CUSTOMER_CITY_KEY  => $city,
            DpdExport::CUSTOMER_COUNTRY_KEY => $country_code,
            DpdExport::CUSTOMER_PHONE_KEY => $phone,
            DpdExport::CUSTOMER_EMAIL_KEY => $email,
            DpdExport::ORDER_NOTE_KEY => $order_number,
            DpdExport::ORDER_REFERENCE_1_KEY => $reference_1,
            DpdExport::ORDER_REFERENCE_2_KEY => $reference_2,
            DpdExport::ORDER_PACKAGE_WEIGHT_KEY => $package_weight,
            DpdExport::ORDER_ID_KEY => $order_id,
            DpdExport::ORDER_PRICE_KEY => $order_price,
            DpdExport::ORDER_CURRENCY_KEY => $order_currency,
            DpdExport::ORDER_PAYMENT_METHOD_KEY => $payment_method,
            DpdExport::ORDER_HAS_PARCELSHOP_SHIPPING_KEY => $has_parcelshop_shipping,
            DpdExport::ORDER_SHIPPING_METHOD_KEY => $shipping_method,
            DpdExport::ORDER_PARCELSHOP_ID => $parcelshop_id,
            DpdExport::ORDER_PARCELSHOP_PUS_ID => $parcelshop_pus_id,
            DpdExport::ORDER_PARCELSHOP_COD_ALLOWED_KEY => $parcelshop_cod_allowed,
            DpdExport::ORDER_PICKUP_DATE_KEY => $order_pickup_date,
            DpdExportSettings::ADDRESS_ID_OPTION_KEY => $address_id,
            DpdExportSettings::BANK_ID_OPTION_KEY => $bank_id,
            DpdExportSettings::NOTIFICATION_OPTION_KEY => $notification,
        ];
    }

    /**
     * Check if order can be exported to DPD
     *
    * @param \WC_Order $order
     *
     * @return bool
     */
    public static function canExportOrder(\WC_Order $order): bool
    {
        $export_status = $order->get_meta(self::EXPORT_STATUS_META_KEY, true);

        if ($export_status == self::EXPORT_SUCCESS_STATUS) {
            return false;
        }

        return true;
    }

    /**
     * Save parcelshop fields to the order
     *
    * @param int|\WP_REST_Request $order_id
     *
     * @return void
     */
    public static function saveParcelShopShippingMethodFieldsToOrder(mixed $order_id)
    {
        // Get posted data either from $_POST, WP_REST_Request, or order object
        $posted_data = [];

        if ($order_id instanceof \WP_REST_Request) {
            $request_data = $order_id->get_json_params();
            $order_id = $request_data['order_id'] ?? 0;
            $posted_data['shipping_method'] = [$request_data['shipping_method'] ?? ''];

            // Map REST API fields to POST fields
            $dpd_fields = [
                DpdParcelShopShippingMethod::PARCELSHOP_ID_META_KEY,
                DpdParcelShopShippingMethod::PARCELSHOP_PUS_ID_META_KEY,
                DpdParcelShopShippingMethod::PARCELSHOP_NAME_META_KEY,
                DpdParcelShopShippingMethod::PARCELSHOP_STREET_META_KEY,
                DpdParcelShopShippingMethod::PARCELSHOP_ZIP_META_KEY,
                DpdParcelShopShippingMethod::PARCELSHOP_CITY_META_KEY,
                DpdParcelShopShippingMethod::PARCELSHOP_COUNTRY_CODE_META_KEY,
                DpdParcelShopShippingMethod::PARCELSHOP_MAX_WEIGHT_META_KEY,
                DpdParcelShopShippingMethod::PARCELSHOP_COD_META_KEY,
                DpdParcelShopShippingMethod::PARCELSHOP_CARD_META_KEY,
                DpdParcelShopShippingMethod::PARCELSHOP_IS_ALZABOX_ELIGIBLE_META_KEY,
                DpdParcelShopShippingMethod::PARCELSHOP_IS_SLOVENSKA_POSTA_ELIGIBLE_META_KEY,
                DpdParcelShopShippingMethod::PARCELSHOP_IS_ZBOX_ELIGIBLE_META_KEY,
            ];

            foreach ($dpd_fields as $field) {
                if (isset($request_data[$field])) {
                    $posted_data[$field] = $request_data[$field];
                }
            }
        } else {
            // Get order object
            $order = wc_get_order($order_id);
            if (!$order instanceof \WC_Order) {
                return;
            }

            // Get shipping method from order
            $shipping_methods = $order->get_shipping_methods();
            $shipping_method = reset($shipping_methods);

            if (!$shipping_method) {
                return;
            }

            $posted_data['shipping_method'] = [$shipping_method->get_method_id()];

            // Try to get data from POST first
            if (!empty($_POST)) {
                $posted_data = $_POST;
            } else {
                // Try to get data from WooCommerce session
                if (WC()->session) {
                    $chosen_parcelshop = WC()->session->get(Shipping::SESSION_CHOSEN_PARCELSHOP_KEY, []);

                    if (!empty($chosen_parcelshop)) {
                        // Update the mapping to match the actual session data keys
                        $posted_data[DpdParcelShopShippingMethod::PARCELSHOP_ID_META_KEY] = $chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_ID_META_KEY] ?? ($chosen_parcelshop['ard_dpd_parcelshop_id'] ?? '');
                        $posted_data[DpdParcelShopShippingMethod::PARCELSHOP_PUS_ID_META_KEY] = $chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_PUS_ID_META_KEY] ?? ($chosen_parcelshop['ard_dpd_parcelshop_pus_id'] ?? '');
                        $posted_data[DpdParcelShopShippingMethod::PARCELSHOP_NAME_META_KEY] = $chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_NAME_META_KEY] ?? ($chosen_parcelshop['ard_dpd_parcelshop_name'] ?? '');
                        $posted_data[DpdParcelShopShippingMethod::PARCELSHOP_STREET_META_KEY] = $chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_STREET_META_KEY] ?? ($chosen_parcelshop['ard_dpd_parcelshop_street'] ?? '');
                        $posted_data[DpdParcelShopShippingMethod::PARCELSHOP_ZIP_META_KEY] = $chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_ZIP_META_KEY] ?? ($chosen_parcelshop['ard_dpd_parcelshop_zip'] ?? '');
                        $posted_data[DpdParcelShopShippingMethod::PARCELSHOP_CITY_META_KEY] = $chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_CITY_META_KEY] ?? ($chosen_parcelshop['ard_dpd_parcelshop_city'] ?? '');
                        $posted_data[DpdParcelShopShippingMethod::PARCELSHOP_COUNTRY_CODE_META_KEY] = $chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_COUNTRY_CODE_META_KEY] ?? ($chosen_parcelshop['ard_dpd_parcelshop_country_code'] ?? '');
                        $posted_data[DpdParcelShopShippingMethod::PARCELSHOP_MAX_WEIGHT_META_KEY] = $chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_MAX_WEIGHT_META_KEY] ?? ($chosen_parcelshop['ard_dpd_parcelshop_max_weight'] ?? '');
                        $posted_data[DpdParcelShopShippingMethod::PARCELSHOP_COD_META_KEY] = $chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_COD_META_KEY] ?? ($chosen_parcelshop['ard_dpd_parcelshop_cod'] ?? '');
                        $posted_data[DpdParcelShopShippingMethod::PARCELSHOP_CARD_META_KEY] = $chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_CARD_META_KEY] ?? ($chosen_parcelshop['ard_dpd_parcelshop_card'] ?? '');
                        $posted_data[DpdParcelShopShippingMethod::PARCELSHOP_IS_ALZABOX_ELIGIBLE_META_KEY] = $chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_IS_ALZABOX_ELIGIBLE_META_KEY] ?? ($chosen_parcelshop['ard_dpd_parcelshop_is_alzabox_eligible'] ?? '');
                        $posted_data[DpdParcelShopShippingMethod::PARCELSHOP_IS_SLOVENSKA_POSTA_ELIGIBLE_META_KEY] = $chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_IS_SLOVENSKA_POSTA_ELIGIBLE_META_KEY] ?? ($chosen_parcelshop['ard_dpd_parcelshop_is_slovenska_posta_eligible'] ?? '');
                        $posted_data[DpdParcelShopShippingMethod::PARCELSHOP_IS_ZBOX_ELIGIBLE_META_KEY] = $chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_IS_ZBOX_ELIGIBLE_META_KEY] ?? ($chosen_parcelshop['ard_dpd_parcelshop_is_zbox_eligible'] ?? '');
                    }
                }
            }
        }

        if (is_admin()) {
            return;
        }

        if (empty($posted_data['shipping_method'][0])) {
            return;
        }

        if ($posted_data['shipping_method'][0] != DpdParcelShopShippingMethod::SETTINGS_ID_KEY) {
            return;
        }

        // Get order if not already retrieved
        if (!isset($order)) {
            $order = wc_get_order($order_id);
            if (!$order instanceof \WC_Order) {
                return;
            }
        }

        // Sanitize and save parcelshop data
        $fields_to_save = [
            DpdParcelShopShippingMethod::PARCELSHOP_ID_META_KEY => 'intval',
            DpdParcelShopShippingMethod::PARCELSHOP_PUS_ID_META_KEY => 'sanitize_text_field',
            DpdParcelShopShippingMethod::PARCELSHOP_NAME_META_KEY => 'sanitize_text_field',
            DpdParcelShopShippingMethod::PARCELSHOP_STREET_META_KEY => 'sanitize_text_field',
            DpdParcelShopShippingMethod::PARCELSHOP_ZIP_META_KEY => 'sanitize_text_field',
            DpdParcelShopShippingMethod::PARCELSHOP_CITY_META_KEY => 'sanitize_text_field',
            DpdParcelShopShippingMethod::PARCELSHOP_COUNTRY_CODE_META_KEY => 'sanitize_text_field',
            DpdParcelShopShippingMethod::PARCELSHOP_MAX_WEIGHT_META_KEY => 'sanitize_text_field',
            DpdParcelShopShippingMethod::PARCELSHOP_COD_META_KEY => 'sanitize_text_field',
            DpdParcelShopShippingMethod::PARCELSHOP_CARD_META_KEY => 'sanitize_text_field',
            DpdParcelShopShippingMethod::PARCELSHOP_IS_ALZABOX_ELIGIBLE_META_KEY => 'sanitize_text_field',
            DpdParcelShopShippingMethod::PARCELSHOP_IS_SLOVENSKA_POSTA_ELIGIBLE_META_KEY => 'sanitize_text_field',
            DpdParcelShopShippingMethod::PARCELSHOP_IS_ZBOX_ELIGIBLE_META_KEY => 'sanitize_text_field',
        ];

        foreach ($fields_to_save as $meta_key => $sanitize_callback) {
            $value = isset($posted_data[$meta_key]) ? $posted_data[$meta_key] : '';
            $sanitized_value = $sanitize_callback($value);
            $order->update_meta_data($meta_key, $sanitized_value);
        }

        $order->save_meta_data();

        // Clear chosen parcelshop session data
        if (WC()->session) {
            WC()->session->set(Shipping::SESSION_CHOSEN_PARCELSHOP_KEY, []);
        }
    }

    /**
     * Display parcelshop shipping info after order detail
     *
     * @param object $order
     *
     * @return void
     */
    public static function displayParcelShopShippingOrderTableInfo(object $order)
    {
        echo self::getParcelShopOrderHtmlDetails($order);
    }

    /**
     * Display parcelshop shipping info in the admin order detail
     *
     * @param object $order
     *
     * @return void
     */
    public static function displayParcelShopShippingAdminOrderInfo(object $order)
    {
        echo self::getParcelShopOrderHtmlDetails($order, 'admin');
    }

    /**
     * Get parcelshop order html details
     *
     * @param \WC_Order $order
     * @param string $type
     *
     * @return string
     */
    public static function getParcelShopOrderHtmlDetails($order, $type = '')
    {
        if (!$order instanceof \WC_Order) {
            return;
        }

        $order_id = (int) $order->get_ID();
        $has_parcelshop_shipping_method = self::hasParcelShpping($order);

        if (!$has_parcelshop_shipping_method) {
            return;
        }

        $parcelshop_id = (int) $order->get_meta(DpdParcelShopShippingMethod::PARCELSHOP_ID_META_KEY, true);
        $parcelshop_pus_id = (string) $order->get_meta(DpdParcelShopShippingMethod::PARCELSHOP_PUS_ID_META_KEY, true);
        $parcelshop_name = (string) $order->get_meta(DpdParcelShopShippingMethod::PARCELSHOP_NAME_META_KEY, true);
        $parcelshop_street = (string) $order->get_meta(DpdParcelShopShippingMethod::PARCELSHOP_STREET_META_KEY, true);
        $parcelshop_zip = (string) $order->get_meta(DpdParcelShopShippingMethod::PARCELSHOP_ZIP_META_KEY, true);
        $parcelshop_city = (string) $order->get_meta(DpdParcelShopShippingMethod::PARCELSHOP_CITY_META_KEY, true);
        $parcelshop_country_code = (string) $order->get_meta(DpdParcelShopShippingMethod::PARCELSHOP_COUNTRY_CODE_META_KEY, true);

        $countries = (array) WC()->countries->get_allowed_countries();
        $parcelshop_country_name = isset($countries[strtoupper($parcelshop_country_code)]) ? (string) $countries[strtoupper($parcelshop_country_code)] : '';

        return ard_dpd_include_template('chosen-parcelshop-order-data.php', [
            'type' => $type,
            'parcelshop_id' => $parcelshop_id,
            'parcelshop_pus_id' => $parcelshop_pus_id,
            'parcelshop_name' => $parcelshop_name,
            'parcelshop_street' => $parcelshop_street,
            'parcelshop_zip' => $parcelshop_zip,
            'parcelshop_city' => $parcelshop_city,
            'parcelshop_country_name' => $parcelshop_country_name,
            'parcelshop_country_code' => $parcelshop_country_code,
        ]);
    }

    /**
     * Check if order has parcel shipping method selected
     *
     * @param \WC_Order $order
     *
     * @return boolean
     */
    public static function hasParcelShpping(\WC_Order $order)
    {
        $order_shipping_methods = (array) $order->get_shipping_methods();

        foreach ($order_shipping_methods as $_key => $shipping_method) {
            if ($shipping_method->get_method_id() !== DpdParcelShopShippingMethod::SETTINGS_ID_KEY) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Bulk download labels
     *
     * @param array $order_ids
     *
     * @return bool
     */
    public static function bulkDownloadLabels($order_ids = [])
    {
        if (empty($order_ids)) {
            Notice::error(__('Please select at least one order.', 'ar-design-dpd'));

            return false;
        }

        $package_numbers = [];
        $processing_order_ids = [];
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);

            if (!$order instanceof \WC_Order) {
                continue;
            }

            $package_number = wp_kses_post($order->get_meta(self::EXPORT_PACKAGE_NUMBER_META_KEY, true));

            if (!$package_number) {
                Notice::error(sprintf(
                    /* translators: %d: WooCommerce order ID. */
                    __('Order %d does not have the package number and its label couldn\'t be printed.', 'ar-design-dpd'),
                    $order_id
                ));

                continue;
            }

            $package_numbers[] = $package_number;
            $processing_order_ids[] = $order_id;
        }

        if (empty($package_numbers)) {
            Notice::error(sprintf(__('None of your selected orders have a package number.', 'ar-design-dpd'), $order_id));

            return false;
        }

        if (count($package_numbers) > 1) {
            Notice::error(__('DPD label download currently supports one shipment label at a time. Please select a single exported DPD order.', 'ar-design-dpd'));

            return false;
        }

        $client = new Client();
        $pdf_content = $client->bulkDownloadLabels($package_numbers);

        if (!$pdf_content) {
            Notice::error(sprintf(
                /* translators: %s: comma-separated WooCommerce order IDs. */
                __('Something went wrong and the PDF content is not valid. Please check orders %s and verify that the package numbers are correct.', 'ar-design-dpd'),
                implode(', ', $processing_order_ids)
            ));

            return false;
        }

        // Generate pdf
        header('Content-type: application/pdf');
        header('Content-Disposition: attachment; filename="labels.pdf"');
        echo $pdf_content;

        exit;
    }

    /**
     * Get pickup date
     *
     * @return string
     */
    public static function getPickupDate()
    {
        $pickup_date = wp_date('Ymd');

        while (self::isDayOff($pickup_date)) {
            $pickup_date = wp_date('Ymd', strtotime($pickup_date . ' +1 day'));
        }

        return $pickup_date;
    }

    /**
     * Check if the given date is a day off (Saturday, Sunday, or a holiday in Slovakia)
     *
     * @param string $date
     *
     * @return bool
     */
    public static function isDayOff($date)
    {
        $day_of_week = wp_date('w', strtotime($date));

        // If it's Saturday or Sunday
        if ($day_of_week == 6 || $day_of_week == 0) {
            return true;
        }

        // If it's a holiday
        if (self::isHoliday($date)) {
            return true;
        }

        return false;
    }

    /**
     * Check if the given date is a holiday in Slovakia
     *
     * @param string $date
     *
     * @return bool
     */
    public static function isHoliday($date)
    {
        // Holidays in Slovakia (month-day format)
        $holidays = array(
            '01-01', // New Year's Day
            '01-06', // Epiphany
            '05-01', // International Workers' Day
            '05-08', // Victory in Europe Day
            '07-05', // St. Cyril and St. Methodius Day
            '08-29', // Slovak National Uprising Anniversary
            '09-15', // Day of Our Lady of Sorrows
            '10-28', // Day of the Establishment of the Slovak Republic
            '11-01', // All Saints' Day
            '11-17', // Struggle for Freedom and Democracy Day
            '12-24', // Christmas Eve
            '12-25', // Christmas Day
            '12-26', // St. Stephen's Day
        );

        // Extract the year from the given date
        $year = date('Y', strtotime($date));

        // Calculate Easter for the given year
        $easter_date = self::calculateEasterForYear($year);
        $easter_date_timestamp = strtotime($easter_date);

        // Add Easter-related holidays to the array
        $holidays[] = date('m-d', strtotime('-2 days', $easter_date_timestamp)); // Good Friday
        $holidays[] = date('m-d', $easter_date_timestamp); // Easter Sunday
        $holidays[] = date('m-d', strtotime('+1 day', $easter_date_timestamp)); // Easter Monday

        // Get the month and day from the given date
        $given_date = date('m-d', strtotime($date));

        // Check if the given date is in the holidays array
        if (in_array($given_date, $holidays)) {
            return true;
        }

        return false;
    }

    /**
     * Calculate the date of Easter for a given year
     *
     * @param int $year
     *
     * @return string
     */
    public static function calculateEasterForYear($year)
    {
        $base = new \DateTime("$year-03-21");
        $days = easter_days($year);

        return $base->add(new \DateInterval("P{$days}D"))->format('Y-m-d');
    }
}
