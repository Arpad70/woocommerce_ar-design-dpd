<?php

namespace ArDesign\DPD;

use Exception;

defined('ABSPATH') || exit;

/**
 * Client class
 */
class Client
{
    public const RESPONSE_SUCCESS_STATUS = 'success';
    public const RESPONSE_ERROR_STATUS = 'error';
    public const RESPONSE_WARNING_MESSAGE_KEY = 'warning_message';
    private const LEGACY_URL = 'https://api.dpd.sk/';
    private const SHIPPER_URL = 'https://capi.dpd.sk/shipment/json';

    /**
     * @var string
     */
    private $url = self::LEGACY_URL;

    /**
     * Sumit request
     *
     * @param string $method
     * @param string $endpoint
     * @param array $data
     * @param bool $log_request
     *
        * @throws Exception
     *
     * @return array|bool|false|string
     */
    private function call($method = 'get', $endpoint = '', $data = [], $log_request = false)
    {
        $method = strtolower(wp_kses_post($method));
        $methods = ['get', 'post'];

        if (!in_array($method, $methods)) {
            throw new Exception(sprintf(__('Use the correct request method. Possible values are: %s', 'ar-design-dpd'), implode(', ', $methods)));
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
            ard_dpd_log('Request log', [
                'data' => $data,
                'response' => json_encode($response),
            ]);
        }

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            throw new Exception(sprintf(__('Something went wrong: %s', 'ar-design-dpd'), $response->get_error_message()));
        }

        if (empty($response)) {
            throw new Exception(__('Something went wrong! Response is empty!', 'ar-design-dpd'), 400);
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

            $error_message = ard_dpd_apply_filters('wc_dpd_client_error_message', 'ard_dpd_client_error_message', $error_message);

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
        return $this->exportViaShipper(is_array($data) ? $data : []);
    }

    private function exportViaShipper(array $data = []): array
    {
        $response = $this->callShipper($data, true);
        $results = isset($response['result']['result']) && is_array($response['result']['result'])
            ? $response['result']['result']
            : [];
        $shipmentResult = isset($results[0]) && is_array($results[0]) ? $results[0] : [];

        $this->throwOnShipperErrors($response, $shipmentResult);

        $warningMessage = $this->extractShipperWarningMessage($shipmentResult);
        if ($warningMessage === '') {
            $warningMessage = $this->extractShipperWarningMessage($response);
        }

        $parcels = isset($shipmentResult['parcels']) && is_array($shipmentResult['parcels'])
            ? $shipmentResult['parcels']
            : [];
        $packageNumber = '';

        if (!empty($parcels[0]['parcelno'])) {
            $packageNumber = sanitize_text_field((string) $parcels[0]['parcelno']);
        }

        $mpsId = !empty($shipmentResult['mpsId'])
            ? sanitize_text_field((string) $shipmentResult['mpsId'])
            : sanitize_text_field((string) ($shipmentResult['mpsid'] ?? ''));

        if ($packageNumber === '' && $mpsId !== '') {
            $packageNumber = $mpsId;
        }

        return [
            Order::EXPORT_STATUS_META_KEY => self::RESPONSE_SUCCESS_STATUS,
            Order::EXPORT_LABEL_URL_META_KEY => esc_url_raw((string) ($shipmentResult['label'] ?? '')),
            Order::EXPORT_MPSID_META_KEY => $mpsId ?: $packageNumber,
            Order::EXPORT_PACKAGE_NUMBER_META_KEY => $packageNumber,
            Order::EXPORT_SHIPMENT_ID_META_KEY => $mpsId,
            self::RESPONSE_WARNING_MESSAGE_KEY => $warningMessage,
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
        return $this->bulkDownloadLabelsViaShipper($package_numbers);
    }

    private function bulkDownloadLabelsViaShipper($package_numbers = [])
    {
        $package_numbers = array_values(array_filter(array_map('sanitize_text_field', (array) $package_numbers)));
        if (empty($package_numbers)) {
            return false;
        }

        $payload = [
            'jsonrpc' => '2.0',
            'method' => 'printLabels',
            'params' => $this->buildShipperSecurityParams() + [
                'label' => [
                    'parcels' => [
                        'parcel' => array_map(static function ($packageNumber) {
                            return ['parcelno' => $packageNumber];
                        }, $package_numbers),
                    ],
                ],
            ],
            'id' => 'printLabels',
        ];

        $response = wp_remote_post(self::SHIPPER_URL, [
            'timeout' => 45,
            'headers' => [
                'Accept' => 'application/pdf',
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $body = (string) wp_remote_retrieve_body($response);
        $contentType = (string) wp_remote_retrieve_header($response, 'content-type');

        if (str_contains(strtolower($contentType), 'application/pdf') && $body !== '') {
            return $body;
        }

        return false;
    }

    private function callShipper(array $data = [], bool $logRequest = false): array
    {
        $response = wp_remote_post(self::SHIPPER_URL, [
            'timeout' => 45,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($data),
        ]);

        if ($logRequest) {
            ard_dpd_log('Shipper request log', [
                'url' => self::SHIPPER_URL,
                'data' => $data,
                'response' => is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response),
            ]);
        }

        if (is_wp_error($response)) {
            throw new Exception(sprintf(__('Something went wrong: %s', 'ar-design-dpd'), $response->get_error_message()));
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        $rawBody = (string) wp_remote_retrieve_body($response);
        $decodedBody = json_decode($rawBody, true);

        if (!is_array($decodedBody)) {
            throw new Exception(__('DPD shipper API returned an invalid response.', 'ar-design-dpd'));
        }

        if ($statusCode >= 400) {
            throw new Exception($this->extractShipperErrorMessage($decodedBody, $rawBody), $statusCode ?: 400);
        }

        return $decodedBody;
    }

    private function buildShipperSecurityParams(): array
    {
        $settings = DpdExportSettings::getDefaultSettings();
        $apiKey = isset($settings[DpdExportSettings::API_KEY_OPTION_KEY]) ? sanitize_text_field((string) $settings[DpdExportSettings::API_KEY_OPTION_KEY]) : '';
        $email = isset($settings[DpdExportSettings::EMAIL_OPTION_KEY]) ? sanitize_email((string) $settings[DpdExportSettings::EMAIL_OPTION_KEY]) : '';

        if ($apiKey === '' || $email === '') {
            throw new Exception(__('DPD SK shipper API is not fully configured. Please set login email and API key.', 'ar-design-dpd'));
        }

        return [
            'DPDSecurity' => [
                'SecurityToken' => [
                    'ClientKey' => $apiKey,
                    'Email' => $email,
                ],
            ],
        ];
    }

    private function throwOnShipperErrors(array $response, array $shipmentResult = []): void
    {
        if (!empty($response['error'])) {
            throw new Exception($this->extractShipperErrorMessage($response, __('DPD shipper API returned an error.', 'ar-design-dpd')));
        }

        if (isset($shipmentResult['success']) && !$shipmentResult['success']) {
            throw new Exception($this->extractShipperErrorMessage($shipmentResult, __('DPD shipper API rejected the shipment.', 'ar-design-dpd')));
        }
    }

    private function extractShipperErrorMessage(array $payload, string $fallback = ''): string
    {
        if (!empty($payload['error']) && is_array($payload['error'])) {
            $message = sanitize_text_field((string) ($payload['error']['message'] ?? ''));
            if ($message !== '') {
                return $message;
            }
        }

        if (!empty($payload['messages']) && is_array($payload['messages'])) {
            $messages = array_filter(array_map(function ($message) {
                return $this->normalizeShipperMessage($message);
            }, $payload['messages']));

            if ($messages !== []) {
                return implode(' ', $messages);
            }
        }

        if (!empty($payload['result']['result']) && is_array($payload['result']['result'])) {
            foreach ($payload['result']['result'] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $message = $this->extractShipperErrorMessage($item);
                if ($message !== '') {
                    return $message;
                }
            }
        }

        return $fallback !== '' ? sanitize_text_field($fallback) : '';
    }

    private function extractShipperWarningMessage(array $payload): string
    {
        if (isset($payload['success']) && !$payload['success']) {
            return '';
        }

        if (!empty($payload['messages']) && is_array($payload['messages'])) {
            $messages = array_filter(array_map(function ($message) {
                return $this->normalizeShipperMessage($message);
            }, $payload['messages']));

            if ($messages !== []) {
                return implode(' ', $messages);
            }
        }

        return '';
    }

    /**
     * @param mixed $message
     */
    private function normalizeShipperMessage($message): string
    {
        if (is_string($message) || is_numeric($message)) {
            return sanitize_text_field((string) $message);
        }

        if (is_array($message)) {
            $parts = [];

            if (!empty($message['value'])) {
                $parts[] = sanitize_text_field((string) $message['value']);
            }

            if (!empty($message['element'])) {
                $parts[] = sprintf(
                    /* translators: %s: invalid payload element */
                    __('Element: %s', 'ar-design-dpd'),
                    sanitize_text_field((string) $message['element'])
                );
            }

            if (!empty($message['envelope'])) {
                $parts[] = sprintf(
                    /* translators: %s: payload envelope */
                    __('Envelope: %s', 'ar-design-dpd'),
                    sanitize_text_field((string) $message['envelope'])
                );
            }

            if ($parts !== []) {
                return implode(' | ', $parts);
            }
        }

        return '';
    }

    private function persistLabelFile(string $labelContent, string $identifier = '', string $preferredExtension = 'pdf'): string
    {
        $binary = $this->decodeLabelContent($labelContent);
        if ($binary === '') {
            return '';
        }

        $uploadDir = wp_upload_dir();
        if (!empty($uploadDir['error'])) {
            return '';
        }

        $directory = trailingslashit($uploadDir['basedir']) . 'ar-design-dpd-labels';
        if (!wp_mkdir_p($directory)) {
            return '';
        }

        $safeIdentifier = sanitize_file_name($identifier ?: 'shipment');
        $extension = strtolower($preferredExtension) === 'zpl' ? 'zpl' : 'pdf';
        if (strpos($binary, '^XA') === 0) {
            $extension = 'txt';
        }

        $filename = sprintf('%s-%s.%s', $safeIdentifier ?: 'shipment', gmdate('YmdHis'), $extension);
        $filepath = trailingslashit($directory) . $filename;

        if (file_put_contents($filepath, $binary) === false) {
            return '';
        }

        return trailingslashit($uploadDir['baseurl']) . 'ar-design-dpd-labels/' . rawurlencode($filename);
    }

    private function decodeLabelContent(string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        if (strpos($content, 'data:') === 0) {
            $parts = explode(',', $content, 2);
            if (count($parts) === 2) {
                $decoded = base64_decode($parts[1], true);
                if ($decoded !== false) {
                    return $decoded;
                }
            }
        }

        $decoded = base64_decode($content, true);
        if ($decoded !== false && $decoded !== '') {
            return $decoded;
        }

        return $content;
    }
}
