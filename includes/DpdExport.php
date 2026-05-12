<?php

namespace ArDesign\DPD;

use Exception;
use League\ISO3166\ISO3166;

defined('ABSPATH') || exit;

/**
 * DpdExport class
 */
class DpdExport
{
    public const SHIPMENT_TYPE_KEY = 'dpd_shipment_type';
    public const CUSTOMER_FULL_NAME_KEY = 'dpd_customer_full_name';
    public const CUSTOMER_COMPANY_KEY = 'dpd_customer_company';
    public const CUSTOMER_STREET_KEY = 'dpd_customer_street';
    public const CUSTOMER_HOUSE_NUMBER_KEY = 'dpd_customer_house_number';
    public const CUSTOMER_ZIP_KEY = 'dpd_customer_zip';
    public const CUSTOMER_CITY_KEY = 'dpd_customer_city';
    public const CUSTOMER_COUNTRY_KEY = 'dpd_customer_country';
    public const CUSTOMER_PHONE_KEY = 'dpd_customer_phone';
    public const CUSTOMER_EMAIL_KEY = 'dpd_customer_email';
    public const ORDER_ID_KEY = 'dpd_order_id';
    public const ORDER_PRICE_KEY = 'dpd_order_price';
    public const ORDER_CURRENCY_KEY = 'dpd_order_currency';
    public const ORDER_PAYMENT_METHOD_KEY = 'dpd_order_payment_method';
    public const ORDER_SHIPPING_METHOD_KEY = 'dpd_order_shipping_method';
    public const ORDER_HAS_PARCELSHOP_SHIPPING_KEY = 'dpd_order_has_parcelshop_shipping';
    public const ORDER_PARCELSHOP_ID = 'dpd_order_shipping_parcelshop_id';
    public const ORDER_PARCELSHOP_PUS_ID = 'dpd_order_shipping_parcelshop_pus_id';
    public const ORDER_PARCELSHOP_COD_ALLOWED_KEY = 'dpd_order_shipping_parcelshop_cod_allowed';
    public const ORDER_NOTE_KEY = 'dpd_order_note';
    public const ORDER_PACKAGE_WEIGHT_KEY = 'dpd_package_weight';
    public const ORDER_REFERENCE_1_KEY = 'dpd_reference_1';
    public const ORDER_REFERENCE_2_KEY = 'dpd_reference_2';
    public const ORDER_ADDRESS_ID_KEY = 'dpd_address_id';
    public const ORDER_BANK_ID_KEY = 'dpd_bank_id';
    public const ORDER_PICKUP_DATE_KEY = 'dpd_order_pickup_date';
    public const RESPONSE_SUCCESS_STATUS = 'success';
    public const RESPONSE_ERROR_STATUS = 'error';

    public $dpd_shipment_type = '';
    public $dpd_customer_full_name = '';
    public $dpd_customer_company = '';
    public $dpd_customer_street = '';
    public $dpd_customer_house_number = '';
    public $dpd_customer_zip = '';
    public $dpd_customer_city = '';
    public $dpd_customer_country = '';
    public $dpd_customer_phone = '';
    public $dpd_customer_email = '';
    public $dpd_order_id = '';
    public $dpd_order_payment_method = '';
    public $dpd_order_shipping_method = '';
    public $dpd_order_has_parcelshop_shipping = '';
    public $dpd_order_shipping_parcelshop_id = '';
    public $dpd_order_shipping_parcelshop_pus_id = '';
    public $dpd_order_shipping_parcelshop_cod_allowed = '';
    public $dpd_order_price = '';
    public $dpd_order_currency = '';
    public $dpd_order_note = '';
    public $dpd_reference_1 = '';
    public $dpd_reference_2 = '';
    public $dpd_package_weight = '';
    public $dpd_order_pickup_date = '';
    public ?string $dpd_api_key;
    public ?string $dpd_api_email;
    public ?string $dpd_delis_id;
    public ?string $dpd_address_id;
    public ?string $dpd_bank_id;
    public int|string $dpd_shipping = 0;
    public $dpd_notification = 'no';
    public $dpd_labels_format = 'A4';
    public $dpd_language = 'sk';
    public $dpd_print_format = 'pdf';

    public function __construct()
    {
        $default_settings = DpdExportSettings::getDefaultSettings();

        $this->dpd_api_key = isset($default_settings[DpdExportSettings::API_KEY_OPTION_KEY]) ? $default_settings[DpdExportSettings::API_KEY_OPTION_KEY] : null;
        $this->dpd_api_email = isset($default_settings[DpdExportSettings::EMAIL_OPTION_KEY]) ? $default_settings[DpdExportSettings::EMAIL_OPTION_KEY] : null;
        $this->dpd_delis_id = isset($default_settings[DpdExportSettings::DELIS_ID_OPTION_KEY]) ? $default_settings[DpdExportSettings::DELIS_ID_OPTION_KEY] : null;
        $this->dpd_address_id = isset($default_settings[DpdExportSettings::ADDRESS_ID_OPTION_KEY]) ? $default_settings[DpdExportSettings::ADDRESS_ID_OPTION_KEY] : null;
        $this->dpd_bank_id = isset($default_settings[DpdExportSettings::BANK_ID_OPTION_KEY]) ? $default_settings[DpdExportSettings::BANK_ID_OPTION_KEY] : null;
        $this->dpd_shipping = isset($default_settings[DpdExportSettings::SHIPPING_OPTION_KEY]) ? $default_settings[DpdExportSettings::SHIPPING_OPTION_KEY] : 0;
        $this->dpd_notification = isset($default_settings[DpdExportSettings::NOTIFICATION_OPTION_KEY]) ? $default_settings[DpdExportSettings::NOTIFICATION_OPTION_KEY] : 'no';
        $this->dpd_labels_format = isset($default_settings[DpdExportSettings::LABELS_FORMAT_OPTION_KEY]) ? $default_settings[DpdExportSettings::LABELS_FORMAT_OPTION_KEY] : 'A4';
        $this->dpd_language = isset($default_settings[DpdExportSettings::LANGUAGE_OPTION_KEY]) ? $default_settings[DpdExportSettings::LANGUAGE_OPTION_KEY] : 'sk';
        $this->dpd_print_format = isset($default_settings[DpdExportSettings::PRINT_FORMAT_OPTION_KEY]) ? $default_settings[DpdExportSettings::PRINT_FORMAT_OPTION_KEY] : 'pdf';
    }

    /**
     * Get request data
     *
     * @return array
     */
    public function getRequestData()
    {
        return $this->getShipperRequestData();
    }

    private function getShipperRequestData(): array
    {
        $apiKey = sanitize_text_field((string) $this->dpd_api_key);
        $email = sanitize_email((string) $this->dpd_api_email);
        $delisId = sanitize_text_field((string) $this->dpd_delis_id);
        $senderAddressId = $this->normalizeIntegerValue($this->{DpdExportSettings::ADDRESS_ID_OPTION_KEY});
        $product = $this->resolveShipperProductCode();
        $countryNumeric = $this->resolveCountryNumericCode((string) $this->{self::CUSTOMER_COUNTRY_KEY});
        $shipmentType = sanitize_text_field((string) $this->{self::SHIPMENT_TYPE_KEY});

        if (!$apiKey || !$email || !$delisId) {
            throw new Exception(__('DPD SK shipper API is not fully configured. Please set DELIS ID, login email and API key in DPD Export settings.', 'ar-design-dpd'));
        }

        if (!$senderAddressId) {
            throw new Exception(__('DPD shipper sender address ID is missing. Please select a pickup address for this export.', 'ar-design-dpd'));
        }

        if (!$product) {
            throw new Exception(__('DPD product code is missing. Please configure the default DPD shipping product.', 'ar-design-dpd'));
        }

        if (!$countryNumeric) {
            throw new Exception(sprintf(
                /* translators: %s: customer country ISO alpha-2 code. */
                __('Country %s could not be converted to the numeric ISO code required by DPD shipper API.', 'ar-design-dpd'),
                (string) $this->{self::CUSTOMER_COUNTRY_KEY}
            ));
        }

        $shipment = array_filter([
            'reference' => (string) $this->{self::ORDER_NOTE_KEY},
            'delisId' => $delisId,
            'reference1' => (string) $this->{self::ORDER_REFERENCE_1_KEY},
            'reference2' => (string) $this->{self::ORDER_REFERENCE_2_KEY},
            'note' => (string) $this->{self::ORDER_NOTE_KEY},
            'product' => $product,
            'pickup' => $this->getShipperPickupData(),
            'addressSender' => [
                'id' => (string) $senderAddressId,
            ],
            'addressRecipient' => array_filter([
                'type' => $shipmentType ?: 'b2c',
                'name' => (string) $this->{self::CUSTOMER_FULL_NAME_KEY},
                'nameDetail' => (string) $this->{self::CUSTOMER_COMPANY_KEY},
                'street' => (string) $this->{self::CUSTOMER_STREET_KEY},
                'houseNumber' => (string) $this->{self::CUSTOMER_HOUSE_NUMBER_KEY},
                'zip' => (string) $this->{self::CUSTOMER_ZIP_KEY},
                'country' => $countryNumeric,
                'city' => (string) $this->{self::CUSTOMER_CITY_KEY},
                'phone' => $this->normalizePhoneNumber((string) $this->{self::CUSTOMER_PHONE_KEY}),
                'email' => sanitize_email((string) $this->{self::CUSTOMER_EMAIL_KEY}),
                'reference' => (string) $this->{self::ORDER_REFERENCE_1_KEY},
                'note' => (string) $this->{self::ORDER_NOTE_KEY},
            ], static function ($value) {
                return $value !== '' && $value !== null;
            }),
            'parcels' => [
                'parcel' => [$this->getShipperParcelData()],
            ],
            'services' => $this->getShipperServicesData($product),
        ], static function ($value) {
            return $value !== '' && $value !== null && $value !== [];
        });

        return [
            'jsonrpc' => '2.0',
            'method' => $this->resolveShipperMethod($product),
            'params' => [
                'DPDSecurity' => [
                    'SecurityToken' => [
                        'ClientKey' => $apiKey,
                        'Email' => $email,
                    ],
                ],
                'shipment' => [$shipment],
            ],
            'id' => (string) ($this->{self::ORDER_ID_KEY} ?: 'null'),
        ];
    }

    /**
     * Get allowed payment methods for the order
     *
     * @return int
     */
    public function getAllowedPaymentMethods()
    {
        return $this->{self::CUSTOMER_COUNTRY_KEY} !== 'SK' && $this->orderPaymentIsCod()
            ? 'Cash'
            : 'Cash';
    }

    /**
     * Check if order payment method is COD
     *
     * @return bool
     */
    public function orderPaymentIsCod()
    {
        $cod_payment_ids = (array) ard_dpd_apply_filters('wc_dpd_cod_id', 'ard_dpd_cod_payment_ids', ['cod']);

        return in_array($this->{self::ORDER_PAYMENT_METHOD_KEY}, $cod_payment_ids);
    }

    /**
     * Set request recipient data
     *
     * @param array $data
     *
        * @return self
     */
    public function setAddressRecipient(array $data = []): self
    {
        $this->{self::SHIPMENT_TYPE_KEY} = isset($data[self::SHIPMENT_TYPE_KEY]) && !empty($data[self::SHIPMENT_TYPE_KEY]) ? $data[self::SHIPMENT_TYPE_KEY] : 'b2c';
        $this->{self::CUSTOMER_FULL_NAME_KEY} = isset($data[self::CUSTOMER_FULL_NAME_KEY]) && !empty($data[self::CUSTOMER_FULL_NAME_KEY]) ? $data[self::CUSTOMER_FULL_NAME_KEY] : '';
        $this->{self::CUSTOMER_COMPANY_KEY} = isset($data[self::CUSTOMER_COMPANY_KEY]) && !empty($data[self::CUSTOMER_COMPANY_KEY]) ? $data[self::CUSTOMER_COMPANY_KEY] : '';
        $this->{self::CUSTOMER_STREET_KEY} = isset($data[self::CUSTOMER_STREET_KEY]) && !empty($data[self::CUSTOMER_STREET_KEY]) ? $data[self::CUSTOMER_STREET_KEY] : '';
        $this->{self::CUSTOMER_HOUSE_NUMBER_KEY} = isset($data[self::CUSTOMER_HOUSE_NUMBER_KEY]) && !empty($data[self::CUSTOMER_HOUSE_NUMBER_KEY]) ? $data[self::CUSTOMER_HOUSE_NUMBER_KEY] : '';
        $this->{self::CUSTOMER_ZIP_KEY} = isset($data[self::CUSTOMER_ZIP_KEY]) && !empty($data[self::CUSTOMER_ZIP_KEY]) ? $data[self::CUSTOMER_ZIP_KEY] : '';
        $this->{self::CUSTOMER_CITY_KEY} = isset($data[self::CUSTOMER_CITY_KEY]) && !empty($data[self::CUSTOMER_CITY_KEY]) ? $data[self::CUSTOMER_CITY_KEY] : '';
        $this->{self::CUSTOMER_PHONE_KEY} = isset($data[self::CUSTOMER_PHONE_KEY]) && !empty($data[self::CUSTOMER_PHONE_KEY]) ? $data[self::CUSTOMER_PHONE_KEY] : '';
        $this->{self::CUSTOMER_EMAIL_KEY} = isset($data[self::CUSTOMER_EMAIL_KEY]) && !empty($data[self::CUSTOMER_EMAIL_KEY]) ? $data[self::CUSTOMER_EMAIL_KEY] : '';
        $this->{self::ORDER_NOTE_KEY} = isset($data[self::ORDER_NOTE_KEY]) && !empty($data[self::ORDER_NOTE_KEY]) ? $data[self::ORDER_NOTE_KEY] : '';
        $this->{self::ORDER_REFERENCE_1_KEY} = isset($data[self::ORDER_REFERENCE_1_KEY]) && !empty($data[self::ORDER_REFERENCE_1_KEY]) ? $data[self::ORDER_REFERENCE_1_KEY] : '';
        $this->{self::ORDER_REFERENCE_2_KEY} = isset($data[self::ORDER_REFERENCE_2_KEY]) && !empty($data[self::ORDER_REFERENCE_2_KEY]) ? $data[self::ORDER_REFERENCE_2_KEY] : '';
        $this->{self::ORDER_PACKAGE_WEIGHT_KEY} = isset($data[self::ORDER_PACKAGE_WEIGHT_KEY]) && !empty($data[self::ORDER_PACKAGE_WEIGHT_KEY]) ? $data[self::ORDER_PACKAGE_WEIGHT_KEY] : '';
        $this->{self::ORDER_ID_KEY} = isset($data[self::ORDER_ID_KEY]) && !empty($data[self::ORDER_ID_KEY]) ? $data[self::ORDER_ID_KEY] : '';
        $this->{self::ORDER_PRICE_KEY} = isset($data[self::ORDER_PRICE_KEY]) && !empty($data[self::ORDER_PRICE_KEY]) ? $data[self::ORDER_PRICE_KEY] : '';
        $this->{self::ORDER_CURRENCY_KEY} = isset($data[self::ORDER_CURRENCY_KEY]) && !empty($data[self::ORDER_CURRENCY_KEY]) ? $data[self::ORDER_CURRENCY_KEY] : 'EUR';
        $this->{self::ORDER_PAYMENT_METHOD_KEY} = isset($data[self::ORDER_PAYMENT_METHOD_KEY]) && !empty($data[self::ORDER_PAYMENT_METHOD_KEY]) ? $data[self::ORDER_PAYMENT_METHOD_KEY] : '';
        $this->{self::ORDER_SHIPPING_METHOD_KEY} = isset($data[self::ORDER_SHIPPING_METHOD_KEY]) && !empty($data[self::ORDER_SHIPPING_METHOD_KEY]) ? $data[self::ORDER_SHIPPING_METHOD_KEY] : '';
        $this->{self::ORDER_HAS_PARCELSHOP_SHIPPING_KEY} = isset($data[self::ORDER_HAS_PARCELSHOP_SHIPPING_KEY]) && !empty($data[self::ORDER_HAS_PARCELSHOP_SHIPPING_KEY]) ? $data[self::ORDER_HAS_PARCELSHOP_SHIPPING_KEY] : '';
        $this->{self::ORDER_PARCELSHOP_ID} = isset($data[self::ORDER_PARCELSHOP_ID]) && !empty($data[self::ORDER_PARCELSHOP_ID]) ? $data[self::ORDER_PARCELSHOP_ID] : '';
        $this->{self::ORDER_PARCELSHOP_PUS_ID} = isset($data[self::ORDER_PARCELSHOP_PUS_ID]) && !empty($data[self::ORDER_PARCELSHOP_PUS_ID]) ? $data[self::ORDER_PARCELSHOP_PUS_ID] : '';
        $this->{self::ORDER_PARCELSHOP_COD_ALLOWED_KEY} = isset($data[self::ORDER_PARCELSHOP_COD_ALLOWED_KEY]) ? $data[self::ORDER_PARCELSHOP_COD_ALLOWED_KEY] : '';
        $this->{self::ORDER_PICKUP_DATE_KEY} = isset($data[self::ORDER_PICKUP_DATE_KEY]) && !empty($data[self::ORDER_PICKUP_DATE_KEY]) ? $data[self::ORDER_PICKUP_DATE_KEY] : '';

        $this->{DpdExportSettings::NOTIFICATION_OPTION_KEY} = !empty($data[DpdExportSettings::NOTIFICATION_OPTION_KEY]) ? $data[DpdExportSettings::NOTIFICATION_OPTION_KEY] : $this->{DpdExportSettings::NOTIFICATION_OPTION_KEY};
        $this->{DpdExportSettings::ADDRESS_ID_OPTION_KEY} = !empty($data[DpdExportSettings::ADDRESS_ID_OPTION_KEY]) ? $data[DpdExportSettings::ADDRESS_ID_OPTION_KEY] : $this->{DpdExportSettings::ADDRESS_ID_OPTION_KEY};
        $this->{DpdExportSettings::BANK_ID_OPTION_KEY} = !empty($data[DpdExportSettings::BANK_ID_OPTION_KEY]) ? $data[DpdExportSettings::BANK_ID_OPTION_KEY] : $this->{DpdExportSettings::BANK_ID_OPTION_KEY};
        $this->{DpdExportSettings::PRINT_FORMAT_OPTION_KEY} = !empty($this->dpd_print_format) ? $this->dpd_print_format : 'pdf';

        $country = isset($data[self::CUSTOMER_COUNTRY_KEY]) && !empty($data[self::CUSTOMER_COUNTRY_KEY]) ? $data[self::CUSTOMER_COUNTRY_KEY] : '';
        if (strtolower($country) === 'cs') {
            $country = 'CZ';
        }

        $this->{self::CUSTOMER_COUNTRY_KEY} = strtoupper((string) $country);

        return $this;
    }

    private function getShipperPickupData(): array
    {
        return array_filter([
            'date' => (string) $this->{self::ORDER_PICKUP_DATE_KEY},
        ], static function ($value) {
            return $value !== '' && $value !== null;
        });
    }

    private function getShipperParcelData(): array
    {
        $parcel = [
            'reference1' => (string) $this->{self::ORDER_REFERENCE_1_KEY},
            'reference2' => (string) $this->{self::ORDER_REFERENCE_2_KEY},
            'weight' => $this->normalizeParcelWeight(),
        ];

        return array_filter($parcel, static function ($value) {
            return $value !== '' && $value !== null;
        });
    }

    private function getShipperServicesData(int $product): array
    {
        $services = [];

        if ($this->orderPaymentIsCod()) {
            if (!empty($this->{self::ORDER_HAS_PARCELSHOP_SHIPPING_KEY}) && !$this->parcelshopSupportsCod()) {
                throw new Exception(__('Selected DPD Pickup / Pickup Station does not support dobírka (COD). Change the payment method or choose another pickup point.', 'ar-design-dpd'));
            }

            $codAmount = round((float) $this->{self::ORDER_PRICE_KEY}, 2);
            $bankId = $this->normalizeIntegerValue($this->{DpdExportSettings::BANK_ID_OPTION_KEY});

            if ($codAmount > 0) {
                $services['cod'] = array_filter([
                    'amount' => number_format($codAmount, 2, '.', ''),
                    'currency' => (string) ($this->{self::ORDER_CURRENCY_KEY} ?: 'EUR'),
                    'bankAccount' => $bankId ? ['id' => $bankId] : [],
                    'variableSymbol' => preg_replace('/\D+/', '', (string) ($this->{self::ORDER_ID_KEY} ?: $this->{self::ORDER_NOTE_KEY})),
                    'paymentMethod' => (int) apply_filters('ard_dpd_shipper_cod_payment_method', 0, $this),
                ], static function ($value) {
                    return $value !== '' && $value !== null && $value !== [];
                });
            }
        }

        $notifications = $this->getShipperNotificationsData($product);
        if ($notifications !== []) {
            $services['notifications'] = [
                'notification' => $notifications,
            ];
        }

        if (!empty($this->{self::ORDER_HAS_PARCELSHOP_SHIPPING_KEY})) {
            $parcelShopId = $this->{self::ORDER_PARCELSHOP_PUS_ID} ?: $this->{self::ORDER_PARCELSHOP_ID};
            if ($parcelShopId) {
                $services['parcelShopDelivery'] = [
                    'parcelShopId' => (int) $parcelShopId,
                ];
            }
        }

        return $services;
    }

    private function getShipperNotificationsData(int $product): array
    {
        $shouldForceNotifications = in_array($product, [9, 17], true);
        $notificationEnabled = $this->{DpdExportSettings::NOTIFICATION_OPTION_KEY} === 'yes';

        if (!$notificationEnabled && !$shouldForceNotifications) {
            return [];
        }

        $rule = $product === 17 ? 902 : 904;
        $language = strtoupper((string) ($this->dpd_language ?: 'SK'));
        $notifications = [];

        $email = sanitize_email((string) $this->{self::CUSTOMER_EMAIL_KEY});
        if ($email) {
            $notifications[] = [
                'destination' => $email,
                'type' => 1,
                'rule' => $rule,
                'language' => $language,
            ];
        }

        $phone = $this->normalizePhoneNumber((string) $this->{self::CUSTOMER_PHONE_KEY});
        if ($phone) {
            $notifications[] = [
                'destination' => $phone,
                'type' => 3,
                'rule' => $rule,
                'language' => $language,
            ];
        }

        if ($notifications === [] && $shouldForceNotifications) {
            throw new Exception(__('DPD Home / ParcelShop export requires a customer email or phone number for notifications.', 'ar-design-dpd'));
        }

        return $notifications;
    }

    private function resolveShipperMethod(int $product): string
    {
        return (string) apply_filters('ard_dpd_shipper_create_method', 'createV3', $product, $this);
    }

    private function resolveShipperProductCode(): int
    {
        if (!empty($this->{self::ORDER_HAS_PARCELSHOP_SHIPPING_KEY})) {
            return 17;
        }

        $shipping_method_product = Shipping::getDpdProductCodeForMethod((string) $this->{self::ORDER_SHIPPING_METHOD_KEY});
        if ($shipping_method_product > 0) {
            return $shipping_method_product;
        }

        return $this->normalizeIntegerValue($this->dpd_shipping);
    }

    private function resolveCountryNumericCode(string $countryCode): int
    {
        $countryCode = strtoupper(trim($countryCode));
        if ($countryCode === 'CS') {
            $countryCode = 'CZ';
        }

        if ($countryCode === '') {
            return 0;
        }

        try {
            $country = (new ISO3166())->alpha2($countryCode);

            return isset($country['numeric']) ? (int) $country['numeric'] : 0;
        } catch (\Throwable $exception) {
            return 0;
        }
    }

    private function normalizePhoneNumber(string $phone): string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return '';
        }

        $normalized = preg_replace('/(?!^)\+|[^\d+]+/', '', $phone);

        return sanitize_text_field((string) $normalized);
    }

    private function normalizeParcelWeight(): string
    {
        $weight = 3.0;

        if (!empty($this->{self::ORDER_PACKAGE_WEIGHT_KEY})) {
            $customWeight = (float) $this->{self::ORDER_PACKAGE_WEIGHT_KEY};
            if ($customWeight > 0) {
                $weight = $customWeight;
            }
        }

        return number_format($weight, 2, '.', '');
    }

    private function normalizeIntegerValue(mixed $value): int
    {
        if ($value === '' || $value === null) {
            return 0;
        }

        return (int) $value;
    }

    private function parcelshopSupportsCod(): bool
    {
        $value = $this->{self::ORDER_PARCELSHOP_COD_ALLOWED_KEY};

        if ($value === '' || $value === null) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    /**
     * Submit request to DPD
     *
     * @param array $data
      * @param \WC_Order|null $order
     *
     * @return array
     *
     * @throws Exception
     */
    public function export(array $data = [], $order = null): array
    {
        $data = $this->setAddressRecipient($data);
        $data = $this->getRequestData();

        $data = ard_dpd_apply_filters('wc_dpd_export_data', 'ard_dpd_export_data', $data, $order);

        if (empty($data)) {
            throw new Exception('No data', 400);
        }

        if (!ard_dpd_apply_filters('wc_dpd_allow_export', 'ard_dpd_allow_export', true, $data)) {
            throw new Exception('The export of the order was disabled', 400);
        }

        try {
            $response = (new Client())->export($data);
        } catch (Exception $e) {
            throw $e;
        }

        return $response;
    }

    /**
     * Call export statically
     *
     * @param array $data
      * @param \WC_Order|null $order
     *
     * @return array
     *
     * @throws Exception
     */
    public static function doExport(array $data = [], $order = null): array
    {
        $export = new self();
        return $export->export($data, $order);
    }
}
