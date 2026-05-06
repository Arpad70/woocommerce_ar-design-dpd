<?php

namespace WcDPD;

use Exception;

defined('ABSPATH') || exit;

/**
 * Ajax class
 */
class Ajax
{
    public static function init()
    {
        add_action('wp_ajax_wc_dpd_update_chosen_parcelshop', [__CLASS__, 'updateChosenParcelShop']);
        add_action('wp_ajax_nopriv_wc_dpd_update_chosen_parcelshop', [__CLASS__, 'updateChosenParcelShop']);

        if (is_map_widget_enabled()) {
            return;
        }

        add_action('wp_ajax_wc_dpd_parcelshop_search', [__CLASS__, 'parcelShopSearch']);
        add_action('wp_ajax_nopriv_wc_dpd_parcelshop_search', [__CLASS__, 'parcelShopSearch']);
    }

    /**
     * Parcelshop search ajax action
     *
     * @return void
     */
    public static function parcelShopSearch()
    {
        // Check nonce value.
        check_ajax_referer('wc-dpd-parcelshop', 'wp_nonce');

        $city = !empty($_REQUEST['city']) ? (string) wp_kses_post($_REQUEST['city']) : '';
        $zip = !empty($_REQUEST['zip']) ? (int) filter_var(wp_kses_post($_REQUEST['zip']), FILTER_SANITIZE_NUMBER_INT) : '';
        $country = !empty($_REQUEST['country']) ? (string) wp_kses_post($_REQUEST['country']) : '';

        if (
            !$city ||
            !$zip ||
            !$country
        ) {
            wp_send_json_error(['message' => __('Please fill all the required fields.', 'wc-dpd')]);
        }

        try {
            $client = new Client();
            $parcelshops = $client->searchParcelShop($city, $zip, $country);
            $countries = WC()->countries->countries;

            foreach ($parcelshops as $key => $parcelshop) {
                $country_code = !empty($parcelshop['country']['code']) ? strtoupper(wp_kses_post($parcelshop['country']['code'])) : '';

                if (!$country_code) {
                    continue;
                }

                $parcelshops[$key]['country']['code'] = strtolower($country_code);

                $country_name = !empty($countries[$country_code]) ? wp_kses_post($countries[$country_code]) : '';

                if (!$country_name) {
                    continue;
                }

                $parcelshops[$key]['country']['name'] = $country_name;
            }

            $parcelshops = apply_filters('wc_dpd_parcelshops_search', $parcelshops);

            wp_send_json_success(['parcelshops' => $parcelshops]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => __('No parcelshops was found. Try changing the input values and search again.', 'wc-dpd')]);
        }

        die();
    }

    /**
     * Update chosen parcelshop session data
     *
     * @return void
     */
    public static function updateChosenParcelShop()
    {
        // Check nonce value.
        check_ajax_referer('wc-dpd-parcelshop', 'wp_nonce');

        $parcelshop_id = !empty($_POST[DpdParcelShopShippingMethod::PARCELSHOP_ID_META_KEY]) ? (int) wp_kses_post($_POST[DpdParcelShopShippingMethod::PARCELSHOP_ID_META_KEY]) : '';
        $parcelshop_pus_id = !empty($_POST[DpdParcelShopShippingMethod::PARCELSHOP_PUS_ID_META_KEY]) ? (string) wp_kses_post($_POST[DpdParcelShopShippingMethod::PARCELSHOP_PUS_ID_META_KEY]) : '';
        $parcelshop_name = !empty($_POST[DpdParcelShopShippingMethod::PARCELSHOP_NAME_META_KEY]) ? (string) wp_kses_post($_POST[DpdParcelShopShippingMethod::PARCELSHOP_NAME_META_KEY]) : '';
        $parcelshop_street = !empty($_POST[DpdParcelShopShippingMethod::PARCELSHOP_STREET_META_KEY]) ? (string) wp_kses_post($_POST[DpdParcelShopShippingMethod::PARCELSHOP_STREET_META_KEY]) : '';
        $parcelshop_zip = !empty($_POST[DpdParcelShopShippingMethod::PARCELSHOP_ZIP_META_KEY]) ? (string) wp_kses_post($_POST[DpdParcelShopShippingMethod::PARCELSHOP_ZIP_META_KEY]) : '';
        $parcelshop_city = !empty($_POST[DpdParcelShopShippingMethod::PARCELSHOP_CITY_META_KEY]) ? (string) wp_kses_post($_POST[DpdParcelShopShippingMethod::PARCELSHOP_CITY_META_KEY]) : '';
        $parcelshop_country_code = !empty($_POST[DpdParcelShopShippingMethod::PARCELSHOP_COUNTRY_CODE_META_KEY]) ? (string) wp_kses_post($_POST[DpdParcelShopShippingMethod::PARCELSHOP_COUNTRY_CODE_META_KEY]) : '';
        $parcelshop_max_weight = !empty($_POST[DpdParcelShopShippingMethod::PARCELSHOP_MAX_WEIGHT_META_KEY]) ? (int) wp_kses_post($_POST[DpdParcelShopShippingMethod::PARCELSHOP_MAX_WEIGHT_META_KEY]) : '';
        $parcelshop_cod = isset($_POST[DpdParcelShopShippingMethod::PARCELSHOP_COD_META_KEY]) ? (string) wp_kses_post($_POST[DpdParcelShopShippingMethod::PARCELSHOP_COD_META_KEY]) : '';
        $parcelshop_card = isset($_POST[DpdParcelShopShippingMethod::PARCELSHOP_CARD_META_KEY]) ? (string) wp_kses_post($_POST[DpdParcelShopShippingMethod::PARCELSHOP_CARD_META_KEY]) : '';
        $parcelshop_is_alzabox_eligible = isset($_POST[DpdParcelShopShippingMethod::PARCELSHOP_IS_ALZABOX_ELIGIBLE_META_KEY]) ? (string) wp_kses_post($_POST[DpdParcelShopShippingMethod::PARCELSHOP_IS_ALZABOX_ELIGIBLE_META_KEY]) : '';
        $parcelshop_is_slovenska_posta_eligible = isset($_POST[DpdParcelShopShippingMethod::PARCELSHOP_IS_SLOVENSKA_POSTA_ELIGIBLE_META_KEY]) ? (string) wp_kses_post($_POST[DpdParcelShopShippingMethod::PARCELSHOP_IS_SLOVENSKA_POSTA_ELIGIBLE_META_KEY]) : '';
        $parcelshop_is_zbox_eligible = isset($_POST[DpdParcelShopShippingMethod::PARCELSHOP_IS_ZBOX_ELIGIBLE_META_KEY]) ? (string) wp_kses_post($_POST[DpdParcelShopShippingMethod::PARCELSHOP_IS_ZBOX_ELIGIBLE_META_KEY]) : '';

        $chosen_parcelshop_data = [
            DpdParcelShopShippingMethod::PARCELSHOP_ID_META_KEY => $parcelshop_id,
            DpdParcelShopShippingMethod::PARCELSHOP_PUS_ID_META_KEY => $parcelshop_pus_id,
            DpdParcelShopShippingMethod::PARCELSHOP_NAME_META_KEY => $parcelshop_name,
            DpdParcelShopShippingMethod::PARCELSHOP_STREET_META_KEY => $parcelshop_street,
            DpdParcelShopShippingMethod::PARCELSHOP_ZIP_META_KEY => $parcelshop_zip,
            DpdParcelShopShippingMethod::PARCELSHOP_CITY_META_KEY => $parcelshop_city,
            DpdParcelShopShippingMethod::PARCELSHOP_COUNTRY_CODE_META_KEY => $parcelshop_country_code,
            DpdParcelShopShippingMethod::PARCELSHOP_MAX_WEIGHT_META_KEY => $parcelshop_max_weight,
            DpdParcelShopShippingMethod::PARCELSHOP_COD_META_KEY => $parcelshop_cod,
            DpdParcelShopShippingMethod::PARCELSHOP_CARD_META_KEY => $parcelshop_card,
            DpdParcelShopShippingMethod::PARCELSHOP_IS_ALZABOX_ELIGIBLE_META_KEY => $parcelshop_is_alzabox_eligible,
            DpdParcelShopShippingMethod::PARCELSHOP_IS_SLOVENSKA_POSTA_ELIGIBLE_META_KEY => $parcelshop_is_slovenska_posta_eligible,
            DpdParcelShopShippingMethod::PARCELSHOP_IS_ZBOX_ELIGIBLE_META_KEY => $parcelshop_is_zbox_eligible,
        ];

        WC()->session->set(Shipping::SESSION_CHOSEN_PARCELSHOP_KEY, $chosen_parcelshop_data);

        wp_send_json_success();
    }
}
