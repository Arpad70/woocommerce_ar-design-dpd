<?php

namespace WcDPD;

use Exception;

defined('ABSPATH') || exit;

/**
 * Client class
 */
class Client
{
    public const RESPONSE_SUCCESS_STATUS = 'success';
    public const RESPONSE_ERROR_STATUS = 'error';

    /**
     * @var string
     */
    private $url = "https://api.dpd.sk/";

    /**
     * Sumit request
     *
     * @param string $method
     * @param string $endpoint
     * @param array $data
     * @param bool $log_request
     *
     * @throws Exceptino
     *
     * @return array|bool|false|string
     */
    private function call($method = 'get', $endpoint = '', $data = [], $log_request = false)
    {
        $method = strtolower(wp_kses_post($method));
        $methods = ['get', 'post'];

        if (!in_array($method, $methods)) {
            throw new Exception(sprintf(__('Use the correct request method. Possible values are: %s', 'wc-dpd'), implode(', ', $methods)));
        }

        $request_data = [
            'body' => json_encode($data),
            'timeout' => 45
        ];

        switch ($method) {
            case 'post':
                $response = wp_remote_post($this->url.$endpoint, $request_data);
                break;
            default:
                $response = wp_remote_get($this->url.$endpoint, $request_data);
                break;
        }

        $response_body = \wp_remote_retrieve_body($response);
        $response_body_decoded = json_decode($response_body, true);

        if ($log_request) {
            wc_dpd_log('Request log', [
                'data' => $data,
                'response' => json_encode($response),
            ]);
        }

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            throw new Exception(sprintf(__('Something went wrong: %s', 'wc-dpd'), $response->get_error_message()));
        }

        if (empty($response)) {
            throw new Exception(__('Something went wrong! Response is empty!', 'wc-dpd'), 400);
        }

        if (isset($response_body_decoded['error'])) {
            $error_message = isset($response_body_decoded['error']['message']) ? $response_body_decoded['error']['message'] : '';
            $error_code = isset($response_body_decoded['error']['code']) ? (int) $response_body_decoded['error']['code'] : '';


            if (!$error_message) {
                $error_message = isset($response_body_decoded['message']) ? $response_body_decoded['message'] : '';
            }

            if (!$error_code) {
                $error_code = isset($response_body_decoded['code']) ? (int) $response_body_decoded['code'] : '';
            }

            $error_message = apply_filters('wc_dpd_client_error_message', $error_message);

            throw new Exception(esc_html($error_message), $error_code ? $error_code : 400);
        }

        return $response;
    }

    /**
     * Export to DPD
     *
     * @param array $data
     *
     * @return array
     *
     * @throws Exception
     */
    public function export($data = [])
    {
        $response = false;

        try {
            $response = $this->call('post', "shipment/json", $data, true);
        } catch (Exception $e) {
            throw $e;
        }

        $response_body = \wp_remote_retrieve_body($response);
        $response_body = isset($response_body) ? json_decode(wp_kses_post_deep($response_body), true) : [];

        $success = isset($response_body['result']['result'][0]['success']) ? (bool) $response_body['result']['result'][0]['success'] : false;

        if (!$success) {
            $error_message = isset($response_body['result']['result'][0]['messages'][0]['value']) ? $response_body['result']['result'][0]['messages'][0]['value'] : null;

            if (!$error_message) {
                $error_message = isset($response_body['result']['result'][0]['messages'][0]) ? $response_body['result']['result'][0]['messages'][0] : __('Something went wrong!', 'wc-dpd');
            }

            $error_message = apply_filters('wc_dpd_client_error_message', $error_message);

            throw new Exception(esc_html($error_message), 400);
        }

        $label = isset($response_body['result']['result'][0]['label']) ? wp_kses_post($response_body['result']['result'][0]['label']) : '';
        $mpsid = isset($response_body['result']['result'][0]['mpsid']) ? wp_kses_post($response_body['result']['result'][0]['mpsid']) : '';
        $package_number = $mpsid ? substr($mpsid, 0, -8) : '';

        return [
            Order::EXPORT_STATUS_META_KEY => self::RESPONSE_SUCCESS_STATUS,
            Order::EXPORT_LABEL_URL_META_KEY => $label,
            Order::EXPORT_MPSID_META_KEY => $mpsid,
            Order::EXPORT_PACKAGE_NUMBER_META_KEY => $package_number,
        ];
    }

    /**
     * Search parcelshop
     *
     * @param string $city
     * @param string $zip
     * @param string $country
     *
     * @return array
     */
    public function searchParcelShop($city = '', $zip = '', $country = '')
    {
        $city = wp_kses_post($city);
        $zip = wp_kses_post($zip);
        $country = wp_kses_post($country);

        $data = [
            'jsonrpc' => '2.0',
            'method' => 'getByAddress',
            'params' => [
                "city" => $city,
                "zip" => $zip,
                "country" => $country,
                "radius" => 50,
            ]
        ];

        $response = false;

        try {
            $response = $this->call('post', "parcelshop/json", $data);
        } catch (Exception $e) {
            return [];
        }

        $response_body = \wp_remote_retrieve_body($response);
        $response_body = isset($response_body) ? json_decode(wp_kses_post_deep($response_body), true) : [];

        return !empty($response_body['result']['parcelshops']) ? (array) wp_kses_post_deep($response_body['result']['parcelshops']) : [];
    }

    /**
     * Bulk download labels
     *
     * @param array $package_numbers
     *
     * @return mixed
     */
    public function bulkDownloadLabels($package_numbers = [])
    {
        if (empty($package_numbers)) {
            return false;
        }

        $parcels = [];
        foreach ($package_numbers as $package_number) {
            $parcels[]['parcelno'] = $package_number;
        }

        $settings = DpdExportSettings::getDefaultSettings();

        $data = [
            'jsonrpc' => '2.0',
            'method' => 'printLabels',
            'params' => [
                'DPDSecurity' => [
                    'SecurityToken' => [
                        'ClientKey' => $settings[DpdExportSettings::API_KEY_OPTION_KEY],
                        'Email' =>  $settings[DpdExportSettings::EMAIL_OPTION_KEY],
                    ],
                ],
                'label' => [
                    'parcels' => [
                        'parcel' => $parcels
                    ],
                    'pageSize' => $settings[DpdExportSettings::LABELS_FORMAT_OPTION_KEY],
                    'position' => '1'
                ],
            ]
        ];

        $response = false;

        try {
            $response = $this->call('post', "shipment/json", $data);
        } catch (Exception $e) {
            return false;
        }

        $response_body = \wp_remote_retrieve_body($response);
        $success = $response_body && preg_match("/^%PDF-1./", $response_body) ? true : false;

        if (!$success) {
            return false;
        }

        return $response_body;
    }
}
