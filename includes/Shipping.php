<?php

namespace WcDPD;

defined('ABSPATH') || exit;

/**
 * Shipping class
 */
class Shipping
{
    public const SESSION_CHOSEN_PARCELSHOP_KEY = 'wc_dpd_chosen_parcelshop';
    public const FRAGMENTS_ELEMENT_ID = 'wc_dpd_fragments';

    public static function init()
    {
        add_filter('woocommerce_shipping_methods', [__CLASS__, 'registerShippingMethodsAndSettings'], 10, 1);
        add_filter('woocommerce_after_shipping_rate', [__CLASS__, 'displayDpdParcelShopShippingMethodAdditionalContent'], 10, 2);
        add_action('wp_footer', [__CLASS__, 'addParcelShopPopup'], 10, 2);
        add_action('woocommerce_checkout_process', [__CLASS__, 'validateParcelShopShippingMethodFields'], 10, 1);
        add_action('woocommerce_package_rates', [__CLASS__, 'maybeHideShippingMethodBasedOnWeightAndVolume'], 10, 2);
        add_action('woocommerce_cart_updated', [__CLASS__, 'maybeClearChosenParcelshopData'], 10, 1);

        if (is_map_widget_enabled()) {
            return;
        }

        add_filter('woocommerce_add_to_cart_fragments', [__CLASS__, 'maybeClearChosenParcelshopDataFragments'], 10, 1);
        add_action('wp_enqueue_scripts', [__CLASS__, 'maybeEnqueueCartFragments'], 10, 1);
        add_action('wc_dpd_parcelshops_search', [__CLASS__, 'maybeHideParcelshopsBasedOnWeightAndVolume'], 10, 1);
    }

    /**
     * Register dpd export settings
     *
     * @return array
     */
    public static function registerShippingMethodsAndSettings($methods)
    {
        $methods[DpdExportSettings::SETTINGS_ID_KEY] = new DpdExportSettings();
        $methods[DpdParcelShopShippingMethod::SETTINGS_ID_KEY] = new DpdParcelShopShippingMethod();

        return $methods;
    }

    /**
     * Validate parcelshop shipping method required fields
     *
     * @return void
     */
    public static function validateParcelShopShippingMethodFields()
    {
        if (empty($_POST['shipping_method'][0])) {
            return;
        }

        if ($_POST['shipping_method'][0] != DpdParcelShopShippingMethod::SETTINGS_ID_KEY) {
            return;
        }

        $parcelshop_pus_id = !empty($_POST[DpdParcelShopShippingMethod::PARCELSHOP_PUS_ID_META_KEY]) ? (string) wp_kses_post($_POST[DpdParcelShopShippingMethod::PARCELSHOP_PUS_ID_META_KEY]) : '';
        $parcelshop_name = !empty($_POST[DpdParcelShopShippingMethod::PARCELSHOP_NAME_META_KEY]) ? (string) wp_kses_post($_POST[DpdParcelShopShippingMethod::PARCELSHOP_NAME_META_KEY]) : '';
        $parcelshop_street = !empty($_POST[DpdParcelShopShippingMethod::PARCELSHOP_STREET_META_KEY]) ? (string) wp_kses_post($_POST[DpdParcelShopShippingMethod::PARCELSHOP_STREET_META_KEY]) : '';
        $parcelshop_zip = !empty($_POST[DpdParcelShopShippingMethod::PARCELSHOP_ZIP_META_KEY]) ? (string) wp_kses_post($_POST[DpdParcelShopShippingMethod::PARCELSHOP_ZIP_META_KEY]) : '';
        $parcelshop_city = !empty($_POST[DpdParcelShopShippingMethod::PARCELSHOP_CITY_META_KEY]) ? (string) wp_kses_post($_POST[DpdParcelShopShippingMethod::PARCELSHOP_CITY_META_KEY]) : '';
        $parcelshop_country_code = !empty($_POST[DpdParcelShopShippingMethod::PARCELSHOP_COUNTRY_CODE_META_KEY]) ? (string) wp_kses_post($_POST[DpdParcelShopShippingMethod::PARCELSHOP_COUNTRY_CODE_META_KEY]) : '';

        if (
            !$parcelshop_pus_id ||
            !$parcelshop_name ||
            !$parcelshop_street ||
            !$parcelshop_zip ||
            !$parcelshop_city ||
            !$parcelshop_country_code
        ) {
            wc_add_notice(__("You have to choose a parcelshop.", "wc-dpd"), 'error');
        }
    }

    /**
     * Display parcelshop shipping method additional content
     *
     * @param object $method
     * @param int $index
     *
     * @return object
     */
    public static function displayDpdParcelShopShippingMethodAdditionalContent($method, $index)
    {
        if (is_admin() || !isset($method->id) || $method->id !== DpdParcelShopShippingMethod::SETTINGS_ID_KEY) {
            return $method;
        }

        if (!isset(WC()->session) || !isset(WC()->session->chosen_shipping_methods) ||
            empty(WC()->session->chosen_shipping_methods[0]) ||
            WC()->session->chosen_shipping_methods[0] !== DpdParcelShopShippingMethod::SETTINGS_ID_KEY) {
            return $method;
        }

        // Get template data using shared method
        $template_data = self::prepareParcelshopTemplateData();

        echo include_template('parcelshop-shipping-method-content.php', $template_data);

        return $method;
    }

    /**
     * Add parcelshop shipping method popup html code
     *
     * @return void
     */
    public static function addParcelShopPopup()
    {
        if (is_admin()) {
            return;
        }

        if (!is_cart_or_checkout_page()) {
            return;
        }

        if (is_map_widget_enabled()) {
            $settings = DpdExportSettings::getDefaultSettings();
            $api_key = isset($settings[DpdExportSettings::MAP_API_KEY_OPTION_KEY]) ? (string) $settings[DpdExportSettings::MAP_API_KEY_OPTION_KEY] : '';
            $language = isset($settings[DpdExportSettings::LANGUAGE_OPTION_KEY]) ? (string) $settings[DpdExportSettings::LANGUAGE_OPTION_KEY] : 'sk';

            // Include map widget html
            echo include_template('parcelshop-map-widget.php', [
                'api_key' => $api_key,
                'language' => $language,
            ]);
        } else {
            // Include popup html
            echo include_template('parcelshop-popup.php', [
                'countries' => (array) WC()->countries->get_allowed_countries(),
                'base_country_code' => (string) WC()->countries->get_base_country()
            ]);
        }

        return;
    }

    /**
     * Show/Hide parcelshop shipping method based on products weight and volume
     *
     * @param array $available_shipping_methods
     *
     * @return array
     */
    public static function maybeHideShippingMethodBasedOnWeightAndVolume($available_shipping_methods)
    {
        $is_package_eligible_for_parcelbox = self::checkIfPackageIsEligibleForAGeneralParcelshop();

        if (!$is_package_eligible_for_parcelbox) {
            unset($available_shipping_methods[DpdParcelShopShippingMethod::SETTINGS_ID_KEY]);
        }

        return $available_shipping_methods;

    }

    /**
     * Show/Hide parcelshops based on products weight and volume
     *
     * @param array $parcelshops
     *
     * @return array
     */
    public static function maybeHideParcelshopsBasedOnWeightAndVolume($parcelshops)
    {
        $is_package_eligible_for_alzabox = self::checkIfPackageIsEligibleForAnAlzabox();
        $is_package_eligible_for_slovenska_posta_box = self::checkIfPackageIsEligibleForASlovenskaPostaBox();
        $is_package_eligible_for_zbox = self::checkIfPackageIsEligibleForAZBox();

        if (!$is_package_eligible_for_alzabox || !$is_package_eligible_for_slovenska_posta_box || !$is_package_eligible_for_zbox) {
            foreach ($parcelshops as $parcelshop_id => $parcelshop_data) {
                $name = !empty($parcelshop_data['name']) ? sanitize_title($parcelshop_data['name']) : '';

                if (!$is_package_eligible_for_alzabox) {
                    if (strpos($name, 'alza') !== false) {
                        unset($parcelshops[$parcelshop_id]);
                    }
                }

                if (!$is_package_eligible_for_slovenska_posta_box) {
                    if (strpos($name, 'box-sl-posty') !== false) {
                        unset($parcelshops[$parcelshop_id]);
                    }
                }

                if (!$is_package_eligible_for_zbox) {
                    if (strpos($name, 'z-box') !== false) {
                        unset($parcelshops[$parcelshop_id]);
                    }
                }
            }
        }

        return $parcelshops;
    }

    /**
     * Maybe clear selected parcelbox after cart update
     *
     * @return void
     */
    public static function maybeClearChosenParcelshopData()
    {
        // Check if the cart is being updated
        if (!is_cart_or_checkout_page()) {
            return;
        }

        $is_package_eligible_for_a_general_parcelbox = self::checkIfPackageIsEligibleForAGeneralParcelshop();
        $is_package_eligible_for_alzabox = self::checkIfPackageIsEligibleForAnAlzabox();
        $is_package_eligible_for_slovenska_posta_box = self::checkIfPackageIsEligibleForASlovenskaPostaBox();
        $is_package_eligible_for_zbox = self::checkIfPackageIsEligibleForAZBox();

        if (!$is_package_eligible_for_a_general_parcelbox) {
            WC()->session->set(Shipping::SESSION_CHOSEN_PARCELSHOP_KEY, []);
        }

        $chosen_parcelshop = WC()->session->get(Shipping::SESSION_CHOSEN_PARCELSHOP_KEY, []);

        if (is_map_widget_enabled()) {
            $is_alzabox_eligible = isset($chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_IS_ALZABOX_ELIGIBLE_META_KEY]) ? filter_var($chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_IS_ALZABOX_ELIGIBLE_META_KEY], FILTER_VALIDATE_BOOL) : true;
            $is_slovenska_posta_eligible = isset($chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_IS_SLOVENSKA_POSTA_ELIGIBLE_META_KEY]) ? filter_var($chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_IS_SLOVENSKA_POSTA_ELIGIBLE_META_KEY], FILTER_VALIDATE_BOOL) : true;
            $is_zbox_eligible = isset($chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_IS_ZBOX_ELIGIBLE_META_KEY]) ? filter_var($chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_IS_ZBOX_ELIGIBLE_META_KEY], FILTER_VALIDATE_BOOL) : true;

            if ($is_alzabox_eligible && !$is_package_eligible_for_alzabox) {
                WC()->session->set(Shipping::SESSION_CHOSEN_PARCELSHOP_KEY, []);
            } elseif ($is_slovenska_posta_eligible && !$is_package_eligible_for_slovenska_posta_box) {
                WC()->session->set(Shipping::SESSION_CHOSEN_PARCELSHOP_KEY, []);
            } elseif ($is_zbox_eligible && !$is_package_eligible_for_zbox) {
                WC()->session->set(Shipping::SESSION_CHOSEN_PARCELSHOP_KEY, []);
            }

            $chosen_payment_method = WC()->session->get('chosen_payment_method');

            // Check if COD is required if chosen woocommerce shipping method is cod
            $cod_payment_ids = (array) apply_filters('wc_dpd_cod_id', ['cod']);
            $is_cod_required = in_array($chosen_payment_method, $cod_payment_ids);

            // Check if card payment is required
            $card_payment_ids = (array) apply_filters('wc_dpd_card_payment_ids', []);
            $is_card_required = in_array($chosen_payment_method, $card_payment_ids);

            $parcelshop_cod_allowed = isset($chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_COD_META_KEY]) ? filter_var($chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_COD_META_KEY], FILTER_VALIDATE_BOOL) : true;
            $parcelshop_card_allowed = isset($chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_CARD_META_KEY]) ? filter_var($chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_CARD_META_KEY], FILTER_VALIDATE_BOOL) : true;

            if ($is_cod_required && !$parcelshop_cod_allowed) {
                WC()->session->set(Shipping::SESSION_CHOSEN_PARCELSHOP_KEY, []);
            } elseif ($is_card_required && !$parcelshop_card_allowed) {
                WC()->session->set(Shipping::SESSION_CHOSEN_PARCELSHOP_KEY, []);
            }
        } else {
            $chosen_parcelshop_name = !empty($chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_NAME_META_KEY]) ? (string) wp_kses_post($chosen_parcelshop[DpdParcelShopShippingMethod::PARCELSHOP_NAME_META_KEY]) : '';

            if (!$is_package_eligible_for_alzabox && strpos(sanitize_title($chosen_parcelshop_name), 'alza') !== false) {
                WC()->session->set(Shipping::SESSION_CHOSEN_PARCELSHOP_KEY, []);
            }

            if (!$is_package_eligible_for_slovenska_posta_box && strpos(sanitize_title($chosen_parcelshop_name), 'box-sl-posty') !== false) {
                WC()->session->set(Shipping::SESSION_CHOSEN_PARCELSHOP_KEY, []);
            }

            if (!$is_package_eligible_for_zbox && strpos(sanitize_title($chosen_parcelshop_name), 'z-box') !== false) {
                WC()->session->set(Shipping::SESSION_CHOSEN_PARCELSHOP_KEY, []);
            }
        }

        return;
    }

    /**
     * Maybe enqueue cart fragments if not enqueued on cart page
     *
     * @return void
     */
    public static function maybeEnqueueCartFragments()
    {
        if (!function_exists('is_cart') || !is_cart()) {
            return;
        }

        // Check if the script is already enqueued
        if (!wp_script_is('wc-cart-fragments', 'enqueued')) {
            // Enqueue the script
            wp_enqueue_script('wc-cart-fragments');
        }
    }

    /**
     * Trigger parcelshops reset if there is not chosen parcelshop.
     * Trigger parcelshops search if there is a chance to display more parcelshops.
     *
     * @param array $fragments
     *
     * @return array
     */
    public static function maybeClearChosenParcelshopDataFragments($fragments)
    {
        if (empty(WC()->session->get(Shipping::SESSION_CHOSEN_PARCELSHOP_KEY))) {
            $fragments['#' . self::FRAGMENTS_ELEMENT_ID] = '<script id="' . self::FRAGMENTS_ELEMENT_ID . '">window.dpdParcelShopPopup.resetPopupParcels();</script>';
        }

        $is_package_eligible_for_a_general_parcelbox = self::checkIfPackageIsEligibleForAGeneralParcelshop();
        $is_package_eligible_for_alzabox = self::checkIfPackageIsEligibleForAnAlzabox();
        $is_package_eligible_for_slovenska_posta_box = self::checkIfPackageIsEligibleForASlovenskaPostaBox();
        $is_package_eligible_for_zbox = self::checkIfPackageIsEligibleForAZBox();

        if ($is_package_eligible_for_a_general_parcelbox || $is_package_eligible_for_alzabox || $is_package_eligible_for_slovenska_posta_box || $is_package_eligible_for_zbox) {
            $fragments['#' . self::FRAGMENTS_ELEMENT_ID] = '<script id="' . self::FRAGMENTS_ELEMENT_ID . '">window.dpdParcelShopPopup.triggerSearchParcelshops();</script>';
        }

        return $fragments;
    }

    /**
     * Check if a package is eligible for a general parcelshop based on weight and dimensions.
     *
     * @return bool
     */
    public static function checkIfPackageIsEligibleForAGeneralParcelshop()
    {
        $parcelshop_shipping_method_settings = DpdParcelShopShippingMethod::getSettings();

        $max_package_weight = 0;
        if (
            isset($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_WEIGHT_SHIPPING_LIMITS_OPTION_KEY]) &&
            $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_WEIGHT_SHIPPING_LIMITS_OPTION_KEY] == 'yes'
        ) {
            $max_package_weight = !empty($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_WEIGHT_SHIPPING_LIMITS_MAX_WEIGHT_OPTION_KEY]) ? (float) $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_WEIGHT_SHIPPING_LIMITS_MAX_WEIGHT_OPTION_KEY] : 0;
        }

        $max_package_width = 0;
        $max_package_height = 0;
        $max_package_length = 0;
        $max_package_volume = 0;
        if (
            isset($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_OPTION_KEY]) &&
            $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_OPTION_KEY] == 'yes'
        ) {
            $max_package_width = !empty($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_WIDTH_OPTION_KEY]) ? (float) $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_WIDTH_OPTION_KEY] : 0;
            $max_package_height = !empty($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_HEIGHT_OPTION_KEY]) ? (float) $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_HEIGHT_OPTION_KEY] : 0;
            $max_package_length = !empty($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_LENGTH_OPTION_KEY]) ? (float) $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_LENGTH_OPTION_KEY] : 0;

            $max_package_volume = $max_package_width * $max_package_height * $max_package_length; // Maximum volume in cm^3 (width * height * length)
        }

        return self::checkIfPackageIsEligibleForAParcelshop(
            $max_package_weight,
            $max_package_height,
            $max_package_length,
            $max_package_width,
            $max_package_volume,
        );
    }

    /**
     * Check if a package is eligible for an alzabox parcelshop based on weight and dimensions.
     *
     * @return bool
     */
    public static function checkIfPackageIsEligibleForAnAlzabox()
    {
        $parcelshop_shipping_method_settings = DpdParcelShopShippingMethod::getSettings();

        $max_alzabox_package_weight = 0;
        if (
            isset($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_WEIGHT_SHIPPING_LIMITS_OPTION_KEY]) &&
            $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_WEIGHT_SHIPPING_LIMITS_OPTION_KEY] == 'yes'
        ) {
            $max_alzabox_package_weight = !empty($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_WEIGHT_SHIPPING_LIMITS_MAX_WEIGHT_ALZABOX_OPTION_KEY]) ? (float) $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_WEIGHT_SHIPPING_LIMITS_MAX_WEIGHT_ALZABOX_OPTION_KEY] : 0;
        }

        $max_alzabox_package_width = 0;
        $max_alzabox_package_height = 0;
        $max_alzabox_package_length = 0;
        $max_alzabox_package_volume = 0;
        if (
            isset($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_OPTION_KEY]) &&
            $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_OPTION_KEY] == 'yes'
        ) {
            $max_alzabox_package_width = !empty($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_WIDTH_ALZABOX_OPTION_KEY]) ? (float) $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_WIDTH_ALZABOX_OPTION_KEY] : 0;
            $max_alzabox_package_height = !empty($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_HEIGHT_ALZABOX_OPTION_KEY]) ? (float) $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_HEIGHT_ALZABOX_OPTION_KEY] : 0;
            $max_alzabox_package_length = !empty($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_LENGTH_ALZABOX_OPTION_KEY]) ? (float) $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_LENGTH_ALZABOX_OPTION_KEY] : 0;

            $max_alzabox_package_volume = $max_alzabox_package_width * $max_alzabox_package_height * $max_alzabox_package_length; // Maximum volume in cm^3 (width * height * length)
        }

        return self::checkIfPackageIsEligibleForAParcelshop(
            $max_alzabox_package_weight,
            $max_alzabox_package_height,
            $max_alzabox_package_length,
            $max_alzabox_package_width,
            $max_alzabox_package_volume,
        );
    }

    /**
     * Check if a package is eligible for a slovenska posta parcelshop based on weight and dimensions.
     *
     * @return bool
     */
    public static function checkIfPackageIsEligibleForASlovenskaPostaBox()
    {
        $parcelshop_shipping_method_settings = DpdParcelShopShippingMethod::getSettings();

        $max_slovenska_posta_package_weight = 0;
        if (
            isset($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_WEIGHT_SHIPPING_LIMITS_OPTION_KEY]) &&
            $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_WEIGHT_SHIPPING_LIMITS_OPTION_KEY] == 'yes'
        ) {
            $max_slovenska_posta_package_weight = !empty($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_WEIGHT_SHIPPING_LIMITS_MAX_WEIGHT_SLOVENSKA_POSTA_OPTION_KEY]) ? (float) $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_WEIGHT_SHIPPING_LIMITS_MAX_WEIGHT_SLOVENSKA_POSTA_OPTION_KEY] : 0;
        }

        $max_slovenska_posta_package_width = 0;
        $max_slovenska_posta_package_height = 0;
        $max_slovenska_posta_package_length = 0;
        $max_slovenska_posta_package_volume = 0;
        if (
            isset($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_OPTION_KEY]) &&
            $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_OPTION_KEY] == 'yes'
        ) {
            $max_slovenska_posta_package_width = !empty($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_WIDTH_SLOVENSKA_POSTA_OPTION_KEY]) ? (float) $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_WIDTH_SLOVENSKA_POSTA_OPTION_KEY] : 0;
            $max_slovenska_posta_package_height = !empty($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_HEIGHT_SLOVENSKA_POSTA_OPTION_KEY]) ? (float) $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_HEIGHT_SLOVENSKA_POSTA_OPTION_KEY] : 0;
            $max_slovenska_posta_package_length = !empty($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_LENGTH_SLOVENSKA_POSTA_OPTION_KEY]) ? (float) $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_LENGTH_SLOVENSKA_POSTA_OPTION_KEY] : 0;

            $max_slovenska_posta_package_volume = $max_slovenska_posta_package_width * $max_slovenska_posta_package_height * $max_slovenska_posta_package_length; // Maximum volume in cm^3 (width * height * length)
        }

        return self::checkIfPackageIsEligibleForAParcelshop(
            $max_slovenska_posta_package_weight,
            $max_slovenska_posta_package_height,
            $max_slovenska_posta_package_length,
            $max_slovenska_posta_package_width,
            $max_slovenska_posta_package_volume,
        );
    }

    /**
     * Check if a package is eligible for a Z-Box parcelshop based on weight and dimensions.
     *
     * @return bool
     */
    public static function checkIfPackageIsEligibleForAZBox()
    {
        $parcelshop_shipping_method_settings = DpdParcelShopShippingMethod::getSettings();

        $max_zbox_package_weight = 0;
        if (
            isset($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_WEIGHT_SHIPPING_LIMITS_OPTION_KEY]) &&
            $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_WEIGHT_SHIPPING_LIMITS_OPTION_KEY] == 'yes'
        ) {
            $max_zbox_package_weight = !empty($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_WEIGHT_SHIPPING_LIMITS_MAX_WEIGHT_ZBOX_OPTION_KEY]) ? (float) $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_WEIGHT_SHIPPING_LIMITS_MAX_WEIGHT_ZBOX_OPTION_KEY] : 0;
        }

        $max_zbox_package_width = 0;
        $max_zbox_package_height = 0;
        $max_zbox_package_length = 0;
        $max_zbox_package_volume = 0;
        if (
            isset($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_OPTION_KEY]) &&
            $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_OPTION_KEY] == 'yes'
        ) {
            $max_zbox_package_width = !empty($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_WIDTH_ZBOX_OPTION_KEY]) ? (float) $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_WIDTH_ZBOX_OPTION_KEY] : 0;
            $max_zbox_package_height = !empty($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_HEIGHT_ZBOX_OPTION_KEY]) ? (float) $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_HEIGHT_ZBOX_OPTION_KEY] : 0;
            $max_zbox_package_length = !empty($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_LENGTH_ZBOX_OPTION_KEY]) ? (float) $parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::PACKAGE_DIMENSION_SHIPPING_LIMITS_MAX_LENGTH_ZBOX_OPTION_KEY] : 0;

            $max_zbox_package_volume = $max_zbox_package_width * $max_zbox_package_height * $max_zbox_package_length; // Maximum volume in cm^3 (width * height * length)
        }

        return self::checkIfPackageIsEligibleForAParcelshop(
            $max_zbox_package_weight,
            $max_zbox_package_height,
            $max_zbox_package_length,
            $max_zbox_package_width,
            $max_zbox_package_volume,
        );
    }

    /**
     * Check if a package is eligible for a parcelshop based on weight and dimensions.
     *
     * @param float $max_weight
     * @param float $max_height
     * @param float $max_length
     * @param float $max_width
     * @param float $max_volume
     *
     * @return bool
     */
    public static function checkIfPackageIsEligibleForAParcelshop($max_weight = 0, $max_height = 0, $max_length = 0, $max_width = 0, $max_volume = 0)
    {
        if (!$max_weight && !$max_volume && !$max_width && !$max_height && !$max_length) {
            return true;
        }

        $cart_total_weight = WC()->cart->get_cart_contents_weight(); // Total weight of products in cart
        $cart_total_volume = self::calculateProductsVolumeInCart(); // Total volume of products in cart

        if (
            ($max_weight && $cart_total_weight > $max_weight) ||
            ($max_volume && $cart_total_volume > $max_volume)
        ) {
            return false;
        }

        if (!$max_width && !$max_height && !$max_length) {
            return true;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = !empty($cart_item['data']) ? $cart_item['data'] : '';

            if (!$product instanceof \WC_Product) {
                continue;
            }

            $product_width = (float) $product->get_width();
            $product_height = (float) $product->get_height();
            $product_length = (float) $product->get_length();

            if (
                ($max_width && $product_width > $max_width) ||
                ($max_height && $product_height > $max_height) ||
                ($max_length && $product_length > $max_length)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate products volume in cart
     *
     * @return float
     */
    public static function calculateProductsVolumeInCart()
    {
        $cart_total_volume = 0;

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = !empty($cart_item['data']) ? $cart_item['data'] : '';

            if (!$product instanceof \WC_Product) {
                continue;
            }

            $width   = (float) $product->get_width();
            $height  = (float) $product->get_height();
            $length   = (float) $product->get_length();

            $cart_total_volume += $width * $height * $length * $cart_item['quantity'];
        }

        return $cart_total_volume;
    }

    /**
     * Prepare parcelshop template data
     *
     * @param array $chosen_parcelshop_data Optional parcelshop data from session
     * @return array Prepared template data
     */
    public static function prepareParcelshopTemplateData($chosen_parcelshop_data = null)
    {
        if (
            !isset(WC()->session) ||
            !isset(WC()->session->chosen_shipping_methods) ||
            empty(WC()->session->chosen_shipping_methods[0])
        ) {
            return [];
        }

        $chosen_shipping_method = wp_kses_post(WC()->session->chosen_shipping_methods[0]);
        if ($chosen_shipping_method !== DpdParcelShopShippingMethod::SETTINGS_ID_KEY) {
            return [];
        }

        $parcelshop_shipping_method_settings = DpdParcelShopShippingMethod::getSettings();

        // Get allowed countries codes in lowercase
        $countries = (array) WC()->countries->get_allowed_countries();
        $allowed_countries = array_keys($countries);
        $allowed_countries = array_map('strtolower', $allowed_countries);

        // Check eligibility for Alzabox, Slovenska posta boxes, and Z-Box
        $is_eligible_for_alzabox = self::checkIfPackageIsEligibleForAnAlzabox();
        $is_eligible_for_slovenska_posta_box = self::checkIfPackageIsEligibleForASlovenskaPostaBox();
        $is_eligible_for_zbox = self::checkIfPackageIsEligibleForAZBox();

        // Check if the parcelshop is already chosen
        $chosen_parcelshop_data = WC()->session->get(self::SESSION_CHOSEN_PARCELSHOP_KEY);

        $chosen_parcelshop_id = !empty($chosen_parcelshop_data[DpdParcelShopShippingMethod::PARCELSHOP_ID_META_KEY]) ? (int) wp_kses_post($chosen_parcelshop_data[DpdParcelShopShippingMethod::PARCELSHOP_ID_META_KEY]) : '';
        $chosen_parcelshop_pus_id = !empty($chosen_parcelshop_data[DpdParcelShopShippingMethod::PARCELSHOP_PUS_ID_META_KEY]) ? (string) wp_kses_post($chosen_parcelshop_data[DpdParcelShopShippingMethod::PARCELSHOP_PUS_ID_META_KEY]) : '';
        $chosen_parcelshop_name = !empty($chosen_parcelshop_data[DpdParcelShopShippingMethod::PARCELSHOP_NAME_META_KEY]) ? (string) wp_kses_post($chosen_parcelshop_data[DpdParcelShopShippingMethod::PARCELSHOP_NAME_META_KEY]) : '';
        $chosen_parcelshop_street = !empty($chosen_parcelshop_data[DpdParcelShopShippingMethod::PARCELSHOP_STREET_META_KEY]) ? (string) wp_kses_post($chosen_parcelshop_data[DpdParcelShopShippingMethod::PARCELSHOP_STREET_META_KEY]) : '';
        $chosen_parcelshop_zip = !empty($chosen_parcelshop_data[DpdParcelShopShippingMethod::PARCELSHOP_ZIP_META_KEY]) ? (string) wp_kses_post($chosen_parcelshop_data[DpdParcelShopShippingMethod::PARCELSHOP_ZIP_META_KEY]) : '';
        $chosen_parcelshop_city = !empty($chosen_parcelshop_data[DpdParcelShopShippingMethod::PARCELSHOP_CITY_META_KEY]) ? (string) wp_kses_post($chosen_parcelshop_data[DpdParcelShopShippingMethod::PARCELSHOP_CITY_META_KEY]) : '';
        $chosen_parcelshop_zip_and_city = $chosen_parcelshop_zip;
        $chosen_parcelshop_zip_and_city .= $chosen_parcelshop_zip_and_city ? ' ' . $chosen_parcelshop_city : $chosen_parcelshop_city;

        $chosen_parcelshop_country_code = !empty($chosen_parcelshop_data[DpdParcelShopShippingMethod::PARCELSHOP_COUNTRY_CODE_META_KEY]) ? (string) wp_kses_post($chosen_parcelshop_data[DpdParcelShopShippingMethod::PARCELSHOP_COUNTRY_CODE_META_KEY]) : '';
        $chosen_parcelshop_country_name = isset($countries[strtoupper($chosen_parcelshop_country_code)]) ? $countries[strtoupper($chosen_parcelshop_country_code)] : '';

        $chosen_parcelshop_text = esc_html(implode(', ', array_filter([$chosen_parcelshop_name, $chosen_parcelshop_street, $chosen_parcelshop_zip_and_city, $chosen_parcelshop_country_name ])));

        // If one of the parcelshop ids is missing, set the other one
        if (!$chosen_parcelshop_id && $chosen_parcelshop_pus_id) {
            $chosen_parcelshop_id = $chosen_parcelshop_pus_id;
        } elseif ($chosen_parcelshop_id && !$chosen_parcelshop_pus_id) {
            $chosen_parcelshop_pus_id = $chosen_parcelshop_id;
        }

        $chosen_payment_method = WC()->session->get('chosen_payment_method');

        // Check if COD is required if chosen woocommerce shipping method is cod
        $cod_payment_ids = (array) apply_filters('wc_dpd_cod_id', ['cod']);
        $is_cod_required = in_array($chosen_payment_method, $cod_payment_ids);

        // Check if card payment is required
        $card_payment_ids = (array) apply_filters('wc_dpd_card_payment_ids', []);
        $is_card_required = in_array($chosen_payment_method, $card_payment_ids);

        // Get customer zip, try to get from shipping address, if not available, get from billing address
        $customer_zip = (string) WC()->customer->get_shipping_postcode();
        if (!$customer_zip) {
            $customer_zip = (string) WC()->customer->get_billing_postcode();
        }

        // Get customer country code, try to get from shipping address, if not available, get from billing address

        // Check if shipping to different address is enabled
        $base_country_code = (string) WC()->countries->get_base_country();
        $billing_country_code = (string) WC()->customer->get_billing_country();

        if ($billing_country_code) {
            $base_country_code = $billing_country_code;
        }

        $shipping_to_different_address = WC()->checkout->get_value('ship_to_different_address');

        if ($shipping_to_different_address) {
            $shipping_country_code = (string) WC()->customer->get_shipping_country();

            if ($shipping_country_code) {
                $base_country_code = $shipping_country_code;
            }
        }

        $wc_weight_unit = get_option('woocommerce_weight_unit');
        $cart_weight = (float) WC()->cart->get_cart_contents_weight();

        // Convert weight to kg if necessary
        $min_weight_kg = $cart_weight;
        switch ($wc_weight_unit) {
            case 'g':
                $min_weight_kg = $cart_weight / 1000;
                break;
            case 'lbs':
                $min_weight_kg = $cart_weight * 0.45359237;
                break;
            case 'oz':
                $min_weight_kg = $cart_weight * 0.02834952;
                break;
                // 'kg' doesn't need conversion
        }

        $disallow_shops = isset($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::DISALLOW_SHOPS_OPTION_KEY]) ? filter_var($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::DISALLOW_SHOPS_OPTION_KEY], FILTER_VALIDATE_BOOL) : false;
        $disallow_lockers = isset($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::DISALLOW_LOCKERS_OPTION_KEY]) ? filter_var($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::DISALLOW_LOCKERS_OPTION_KEY], FILTER_VALIDATE_BOOL) : false;
        $disallow_dpd_pickup_stations = isset($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::DISALLOW_DPD_PICKUP_STATIONS_OPTION_KEY]) ? filter_var($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::DISALLOW_DPD_PICKUP_STATIONS_OPTION_KEY], FILTER_VALIDATE_BOOL) : false;
        $disallow_sk_post = isset($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::DISALLOW_SK_POST_OPTION_KEY]) ? filter_var($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::DISALLOW_SK_POST_OPTION_KEY], FILTER_VALIDATE_BOOL) : false;
        $disallow_alza_boxes = isset($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::DISALLOW_ALZA_BOXES_OPTION_KEY]) ? filter_var($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::DISALLOW_ALZA_BOXES_OPTION_KEY], FILTER_VALIDATE_BOOL) : false;
        $disallow_zbox = isset($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::DISALLOW_ZBOX_OPTION_KEY]) ? filter_var($parcelshop_shipping_method_settings[DpdParcelShopShippingMethod::DISALLOW_ZBOX_OPTION_KEY], FILTER_VALIDATE_BOOL) : false;

        return [
            'chosen_parcelshop_id' =>  $chosen_parcelshop_id,
            'chosen_parcelshop_pus_id' =>  $chosen_parcelshop_pus_id,
            'chosen_parcelshop_name' =>  $chosen_parcelshop_name,
            'chosen_parcelshop_street' =>  $chosen_parcelshop_street,
            'chosen_parcelshop_city' =>  $chosen_parcelshop_city,
            'chosen_parcelshop_zip' =>  $chosen_parcelshop_zip,
            'chosen_parcelshop_country_code' =>  $chosen_parcelshop_country_code,
            'chosen_parcelshop_text' =>  $chosen_parcelshop_text,
            'customer_zip' => (string) $customer_zip,
            'countries' => $countries,
            'allowed_countries' => (array) $allowed_countries,
            'base_country_code' => (string) strtolower($base_country_code),
            'min_weight' => (float) $min_weight_kg,
            'is_eligible_for_alzabox' => $is_eligible_for_alzabox,
            'is_eligible_for_slovenska_posta_box' => $is_eligible_for_slovenska_posta_box,
            'is_eligible_for_zbox' => $is_eligible_for_zbox,
            'is_cod_required' => $is_cod_required,
            'is_card_required' => $is_card_required,
            'disallow_shops' => $disallow_shops,
            'disallow_lockers' => $disallow_lockers,
            'disallow_dpd_pickup_stations' => $disallow_dpd_pickup_stations,
            'disallow_sk_post' => $disallow_sk_post,
            'disallow_alza_boxes' => $disallow_alza_boxes,
            'disallow_zbox' => $disallow_zbox,
        ];
    }

    /**
     * Helper method to check if a payment method is required
     */
    private static function isPaymentMethodRequired($type = 'cod')
    {
        $chosen_payment_method = WC()->session ? WC()->session->get('chosen_payment_method') : '';

        if ($type === 'cod') {
            $payment_ids = (array) apply_filters('wc_dpd_cod_id', ['cod']);
        } else {
            $payment_ids = (array) apply_filters('wc_dpd_card_payment_ids', []);
        }

        return in_array($chosen_payment_method, $payment_ids);
    }
}
