<?php

namespace ArDesign\DPD;

use Exception;

defined('ABSPATH') || exit;

/**
 * Ajax class
 */
class Ajax
{
    private const LEGACY_NONCE_ACTION = 'wc-dpd-parcelshop';
    private const NONCE_ACTION = 'ar-design-dpd-parcelshop';

    public static function init()
    {
        add_action('wp_ajax_wc_dpd_update_chosen_parcelshop', [__CLASS__, 'updateChosenParcelShop']);
        add_action('wp_ajax_nopriv_wc_dpd_update_chosen_parcelshop', [__CLASS__, 'updateChosenParcelShop']);
        add_action('wp_ajax_ard_dpd_update_chosen_parcelshop', [__CLASS__, 'updateChosenParcelShop']);
        add_action('wp_ajax_nopriv_ard_dpd_update_chosen_parcelshop', [__CLASS__, 'updateChosenParcelShop']);

        if (is_map_widget_enabled()) {
            return;
        }

        add_action('wp_ajax_wc_dpd_parcelshop_search', [__CLASS__, 'parcelShopSearch']);
        add_action('wp_ajax_nopriv_wc_dpd_parcelshop_search', [__CLASS__, 'parcelShopSearch']);
        add_action('wp_ajax_ard_dpd_parcelshop_search', [__CLASS__, 'parcelShopSearch']);
        add_action('wp_ajax_nopriv_ard_dpd_parcelshop_search', [__CLASS__, 'parcelShopSearch']);
    }

    private static function verifyNonce(): bool
    {
        $nonce = isset($_REQUEST['wp_nonce']) ? (string) wp_unslash($_REQUEST['wp_nonce']) : '';

        return '' !== $nonce && (
            wp_verify_nonce($nonce, self::NONCE_ACTION) ||
            wp_verify_nonce($nonce, self::LEGACY_NONCE_ACTION)
        );
    }

    private static function requestField(array $keys)
    {
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                return wp_unslash($_POST[$key]);
            }
        }

        return null;
    }

    private static function normalizeBooleanString($value): string
    {
        if ($value === '' || $value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1 ? 'true' : 'false';
        }

        $normalized = strtolower(trim((string) $value));

        if (in_array($normalized, ['1', 'true', 'yes', 'y'], true)) {
            return 'true';
        }

        if (in_array($normalized, ['0', 'false', 'no', 'n'], true)) {
            return 'false';
        }

        return '';
    }

    private static function extractParcelshopProperty(array $parcelshop, string $propertyKey)
    {
        $properties = $parcelshop['properties'] ?? null;

        if (!is_array($properties)) {
            return null;
        }

        if (array_key_exists($propertyKey, $properties)) {
            return $properties[$propertyKey];
        }

        foreach ($properties as $property) {
            if (!is_array($property)) {
                continue;
            }

            $candidateKey = isset($property['key']) ? (string) $property['key'] : (string) ($property['name'] ?? '');
            if ($candidateKey !== $propertyKey) {
                continue;
            }

            return $property['value'] ?? null;
        }

        return null;
    }

    private static function parcelshopHasService(array $parcelshop, string $serviceCode): bool
    {
        $services = $parcelshop['services'] ?? null;

        if (!is_array($services)) {
            return false;
        }

        $codes = $services['code'] ?? $services;
        if (!is_array($codes)) {
            return false;
        }

        return in_array($serviceCode, array_map('strval', $codes), true);
    }

    private static function findMatchingParcelshop(array $parcelshops, array $chosenParcelshopData): ?array
    {
        $parcelshopId = (string) ($chosenParcelshopData[DpdParcelShopShippingMethod::PARCELSHOP_ID_META_KEY] ?? '');
        $parcelshopPusId = (string) ($chosenParcelshopData[DpdParcelShopShippingMethod::PARCELSHOP_PUS_ID_META_KEY] ?? '');

        foreach ($parcelshops as $parcelshop) {
            if (!is_array($parcelshop)) {
                continue;
            }

            $candidateId = isset($parcelshop['id']) ? (string) $parcelshop['id'] : '';
            $candidatePusId = isset($parcelshop['pusId']) ? (string) $parcelshop['pusId'] : '';

            if ($parcelshopId !== '' && $candidateId === $parcelshopId) {
                return $parcelshop;
            }

            if ($parcelshopPusId !== '' && $candidatePusId === $parcelshopPusId) {
                return $parcelshop;
            }
        }

        return null;
    }

    private static function enrichParcelshopCapabilities(array $chosenParcelshopData): array
    {
        $hasCapabilityData =
            ($chosenParcelshopData[DpdParcelShopShippingMethod::PARCELSHOP_COD_META_KEY] ?? '') !== '' ||
            ($chosenParcelshopData[DpdParcelShopShippingMethod::PARCELSHOP_CARD_META_KEY] ?? '') !== '' ||
            ($chosenParcelshopData[DpdParcelShopShippingMethod::PARCELSHOP_MAX_WEIGHT_META_KEY] ?? '') !== '';

        if ($hasCapabilityData) {
            return $chosenParcelshopData;
        }

        $city = (string) ($chosenParcelshopData[DpdParcelShopShippingMethod::PARCELSHOP_CITY_META_KEY] ?? '');
        $zip = (string) ($chosenParcelshopData[DpdParcelShopShippingMethod::PARCELSHOP_ZIP_META_KEY] ?? '');
        $country = (string) ($chosenParcelshopData[DpdParcelShopShippingMethod::PARCELSHOP_COUNTRY_CODE_META_KEY] ?? '');

        if ($city === '' || $zip === '' || $country === '') {
            return $chosenParcelshopData;
        }

        try {
            $parcelshops = (new Client())->searchParcelShop($city, $zip, $country);
        } catch (\Throwable $exception) {
            return $chosenParcelshopData;
        }

        $matchedParcelshop = self::findMatchingParcelshop($parcelshops, $chosenParcelshopData);
        if ($matchedParcelshop === null) {
            return $chosenParcelshopData;
        }

        if (($chosenParcelshopData[DpdParcelShopShippingMethod::PARCELSHOP_MAX_WEIGHT_META_KEY] ?? '') === '') {
            $maxWeight = self::extractParcelshopProperty($matchedParcelshop, 'max_weight');
            if ($maxWeight !== null && $maxWeight !== '') {
                $chosenParcelshopData[DpdParcelShopShippingMethod::PARCELSHOP_MAX_WEIGHT_META_KEY] = sanitize_text_field((string) $maxWeight);
            }
        }

        if (($chosenParcelshopData[DpdParcelShopShippingMethod::PARCELSHOP_COD_META_KEY] ?? '') === '') {
            $codSupport = self::extractParcelshopProperty($matchedParcelshop, 'allow_pickup_cod');
            if ($codSupport === null) {
                $codSupport = self::parcelshopHasService($matchedParcelshop, 'dropoff:cod');
            }

            $normalizedCodSupport = self::normalizeBooleanString($codSupport);
            if ($normalizedCodSupport !== '') {
                $chosenParcelshopData[DpdParcelShopShippingMethod::PARCELSHOP_COD_META_KEY] = $normalizedCodSupport;
            }
        }

        if (($chosenParcelshopData[DpdParcelShopShippingMethod::PARCELSHOP_CARD_META_KEY] ?? '') === '') {
            $cardSupport = self::extractParcelshopProperty($matchedParcelshop, 'pos_terminal');
            if ($cardSupport === null) {
                $cardSupport = self::parcelshopHasService($matchedParcelshop, 'cardPayment');
            }

            $normalizedCardSupport = self::normalizeBooleanString($cardSupport);
            if ($normalizedCardSupport !== '') {
                $chosenParcelshopData[DpdParcelShopShippingMethod::PARCELSHOP_CARD_META_KEY] = $normalizedCardSupport;
            }
        }

        return $chosenParcelshopData;
    }

    /**
     * Parcelshop search ajax action
     *
     * @return void
     */
    public static function parcelShopSearch()
    {
        if (!self::verifyNonce()) {
            wp_send_json_error(['message' => __('Security check failed.', 'ar-design-dpd')], 403);
        }

        $city = !empty($_REQUEST['city']) ? (string) wp_kses_post(wp_unslash($_REQUEST['city'])) : '';
        $zip = !empty($_REQUEST['zip']) ? (int) filter_var(wp_kses_post(wp_unslash($_REQUEST['zip'])), FILTER_SANITIZE_NUMBER_INT) : '';
        $country = !empty($_REQUEST['country']) ? (string) wp_kses_post(wp_unslash($_REQUEST['country'])) : '';

        if (
            !$city ||
            !$zip ||
            !$country
        ) {
            wp_send_json_error(['message' => __('Please fill all the required fields.', 'ar-design-dpd')]);
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

            $parcelshops = ard_dpd_apply_filters('wc_dpd_parcelshops_search', 'ard_dpd_parcelshops_search', $parcelshops);

            wp_send_json_success(['parcelshops' => $parcelshops]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => __('No parcelshops was found. Try changing the input values and search again.', 'ar-design-dpd')]);
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
        if (!self::verifyNonce()) {
            wp_send_json_error(['message' => __('Security check failed.', 'ar-design-dpd')], 403);
        }

        $parcelshop_id = (int) filter_var((string) (self::requestField([DpdParcelShopShippingMethod::PARCELSHOP_ID_META_KEY, 'ard_dpd_parcelshop_id']) ?? ''), FILTER_SANITIZE_NUMBER_INT);
        $parcelshop_pus_id = (string) wp_kses_post((string) (self::requestField([DpdParcelShopShippingMethod::PARCELSHOP_PUS_ID_META_KEY, 'ard_dpd_parcelshop_pus_id']) ?? ''));
        $parcelshop_name = (string) wp_kses_post((string) (self::requestField([DpdParcelShopShippingMethod::PARCELSHOP_NAME_META_KEY, 'ard_dpd_parcelshop_name']) ?? ''));
        $parcelshop_street = (string) wp_kses_post((string) (self::requestField([DpdParcelShopShippingMethod::PARCELSHOP_STREET_META_KEY, 'ard_dpd_parcelshop_street']) ?? ''));
        $parcelshop_zip = (string) wp_kses_post((string) (self::requestField([DpdParcelShopShippingMethod::PARCELSHOP_ZIP_META_KEY, 'ard_dpd_parcelshop_zip']) ?? ''));
        $parcelshop_city = (string) wp_kses_post((string) (self::requestField([DpdParcelShopShippingMethod::PARCELSHOP_CITY_META_KEY, 'ard_dpd_parcelshop_city']) ?? ''));
        $parcelshop_country_code = (string) wp_kses_post((string) (self::requestField([DpdParcelShopShippingMethod::PARCELSHOP_COUNTRY_CODE_META_KEY, 'ard_dpd_parcelshop_country_code']) ?? ''));
        $parcelshop_max_weight = (int) filter_var((string) (self::requestField([DpdParcelShopShippingMethod::PARCELSHOP_MAX_WEIGHT_META_KEY, 'ard_dpd_parcelshop_max_weight']) ?? ''), FILTER_SANITIZE_NUMBER_INT);
        $parcelshop_cod = (string) wp_kses_post((string) (self::requestField([DpdParcelShopShippingMethod::PARCELSHOP_COD_META_KEY, 'ard_dpd_parcelshop_cod']) ?? ''));
        $parcelshop_card = (string) wp_kses_post((string) (self::requestField([DpdParcelShopShippingMethod::PARCELSHOP_CARD_META_KEY, 'ard_dpd_parcelshop_card']) ?? ''));
        $parcelshop_is_alzabox_eligible = (string) wp_kses_post((string) (self::requestField([DpdParcelShopShippingMethod::PARCELSHOP_IS_ALZABOX_ELIGIBLE_META_KEY, 'ard_dpd_parcelshop_is_alzabox_eligible']) ?? ''));
        $parcelshop_is_slovenska_posta_eligible = (string) wp_kses_post((string) (self::requestField([DpdParcelShopShippingMethod::PARCELSHOP_IS_SLOVENSKA_POSTA_ELIGIBLE_META_KEY, 'ard_dpd_parcelshop_is_slovenska_posta_eligible']) ?? ''));
        $parcelshop_is_zbox_eligible = (string) wp_kses_post((string) (self::requestField([DpdParcelShopShippingMethod::PARCELSHOP_IS_ZBOX_ELIGIBLE_META_KEY, 'ard_dpd_parcelshop_is_zbox_eligible']) ?? ''));

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

        $chosen_parcelshop_data = self::enrichParcelshopCapabilities($chosen_parcelshop_data);

        WC()->session->set(Shipping::SESSION_CHOSEN_PARCELSHOP_KEY, $chosen_parcelshop_data);

        wp_send_json_success();
    }
}
