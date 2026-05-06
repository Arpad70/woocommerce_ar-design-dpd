<?php

namespace WcDPD;

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
    public $dpd_order_price = '';
    public $dpd_order_currency = '';
    public $dpd_order_note = '';
    public $dpd_reference_1 = '';
    public $dpd_reference_2 = '';
    public $dpd_package_weight = '';
    public $dpd_order_pickup_date = '';
    public $dpd_api_key;
    public $dpd_api_email;
    public $dpd_delis_id;
    public $dpd_address_id;
    public $dpd_bank_id;
    public $dpd_shipping = 0;
    public $dpd_notification = 'no';

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
    }

    /**
     * Get request data
     *
     * @return array
     */
    public function getRequestData()
    {
        $notification_data = [
            'notification' => [
                0 => [
                    'destination' => !$this->{self::CUSTOMER_EMAIL_KEY} ? $this->{self::CUSTOMER_PHONE_KEY} : $this->{self::CUSTOMER_EMAIL_KEY},
                    'type'        => '1',
                    'rule'        => '1',
                ]
            ]
        ];

        $add_notification_data = $this->{DpdExportSettings::NOTIFICATION_OPTION_KEY} == 'yes' ? true : false;

        // Check if notification is required for the shipment type
        if (DpdExportSettings::isNotificationRequired($this->{DpdExportSettings::SHIPPING_OPTION_KEY})) {
            $add_notification_data = true;
        }

        $services = [
            'notifications' => $add_notification_data ? $notification_data : '',
        ];

        $cod_data = [
            'bankAccount' => [
                'id' =>  $this->{DpdExportSettings::BANK_ID_OPTION_KEY},
            ],
            'paymentMethod'  => $this->getAllowedPaymentMethods(),
            'variableSymbol' => $this->{self::ORDER_NOTE_KEY},
            'amount'         => round($this->{self::ORDER_PRICE_KEY}, 2),
            'currency'       => $this->{self::ORDER_CURRENCY_KEY},
        ];

        $add_cod_data = $this->orderPaymentIsCod() ? true : false;

        if ($add_cod_data) {
            $services['cod'] = $cod_data;
        }

        $parcelshop_data = [
            'parcelShopId' => $this->{self::ORDER_PARCELSHOP_PUS_ID},
        ];

        $add_parcelshop_data = $this->{self::ORDER_HAS_PARCELSHOP_SHIPPING_KEY} ? true : false;

        if ($add_parcelshop_data) {
            $services['parcelShopDelivery'] = $parcelshop_data;
        }

        // Calculate parcel weight
        $parcel_weight = 3.0; // Default weight
        if (!empty($this->{self::ORDER_PACKAGE_WEIGHT_KEY})) {
            $weight = floatval($this->{self::ORDER_PACKAGE_WEIGHT_KEY});
            if ($weight > 0) {
                $parcel_weight = $weight;
            }
        }

        $data = [
            'jsonrpc' => '2.0',
            'method' => 'create',
            'params' => [
                'DPDSecurity' => [
                    'SecurityToken' => [
                        'ClientKey' => $this->{DpdExportSettings::API_KEY_OPTION_KEY},
                        'Email' =>  $this->{DpdExportSettings::EMAIL_OPTION_KEY},
                    ],
                ],
                'shipment' => [
                    0 => [
                        'delisId' => $this->{DpdExportSettings::DELIS_ID_OPTION_KEY},
                        'reference' => $this->{self::ORDER_ID_KEY},
                        'note' => $this->{self::ORDER_NOTE_KEY},
                        'product' => $this->{DpdExportSettings::SHIPPING_OPTION_KEY},
                        'pickup' => [
                            'date' => $this->{self::ORDER_PICKUP_DATE_KEY},
                        ],
                        'addressSender' => [
                            'id' => $this->{DpdExportSettings::ADDRESS_ID_OPTION_KEY},
                        ],
                        'addressRecipient' => [
                            'type' => $this->{self::SHIPMENT_TYPE_KEY},
                            'name' => $this->{self::CUSTOMER_FULL_NAME_KEY},
                            'nameDetail' => $this->{self::CUSTOMER_COMPANY_KEY},
                            'street' => $this->{self::CUSTOMER_STREET_KEY},
                            'houseNumber' => $this->{self::CUSTOMER_HOUSE_NUMBER_KEY},
                            'zip' => $this->{self::CUSTOMER_ZIP_KEY},
                            'country' => $this->{self::CUSTOMER_COUNTRY_KEY},
                            'city' => $this->{self::CUSTOMER_CITY_KEY},
                            'phone' => $this->{self::CUSTOMER_PHONE_KEY} ,
                            'email' => $this->{self::CUSTOMER_EMAIL_KEY},
                            'note' => $this->{self::ORDER_NOTE_KEY},
                        ],
                        'parcels' => [
                            'parcel' => [
                                0 => [
                                    'weight' => $parcel_weight,
                                    'reference1' => $this->{self::ORDER_REFERENCE_1_KEY},
                                    'reference2' => $this->{self::ORDER_REFERENCE_2_KEY},
                                    'reference4' => 'Woocommerce',
                                ],
                            ],
                        ],
                        'services' => $services,
                    ],
                ],
                'id' => $this->{self::ORDER_ID_KEY},
            ]
        ];

        return $data;
    }

    /**
     * Get allowed payment methods for the order
     *
     * @return int
     */
    public function getAllowedPaymentMethods()
    {
        // Check if country is different than Slovakia, then allow only cash for cod
        if ($this->{self::CUSTOMER_COUNTRY_KEY} != 703 && $this->orderPaymentIsCod()) {
            // Only cash for foreign countries
            return 0;
        }

        // Cash and card
        return 1;
    }

    /**
     * Check if order payment method is COD
     *
     * @return bool
     */
    public function orderPaymentIsCod()
    {
        $cod_payment_ids = (array) apply_filters('wc_dpd_cod_id', ['cod']);

        return in_array($this->{self::ORDER_PAYMENT_METHOD_KEY}, $cod_payment_ids);
    }

    /**
     * Set request recipient data
     *
     * @param array $data
     *
     * @return void
     */
    public function setAddressRecipient($data = [])
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
        $this->{self::ORDER_PICKUP_DATE_KEY} = isset($data[self::ORDER_PICKUP_DATE_KEY]) && !empty($data[self::ORDER_PICKUP_DATE_KEY]) ? $data[self::ORDER_PICKUP_DATE_KEY] : '';

        $this->{DpdExportSettings::SHIPPING_OPTION_KEY} = !empty($data[DpdExportSettings::SHIPPING_OPTION_KEY]) ? $data[DpdExportSettings::SHIPPING_OPTION_KEY] : $this->{DpdExportSettings::SHIPPING_OPTION_KEY};
        $this->{DpdExportSettings::NOTIFICATION_OPTION_KEY} = !empty($data[DpdExportSettings::NOTIFICATION_OPTION_KEY]) ? $data[DpdExportSettings::NOTIFICATION_OPTION_KEY] : $this->{DpdExportSettings::NOTIFICATION_OPTION_KEY};
        $this->{DpdExportSettings::ADDRESS_ID_OPTION_KEY} = !empty($data[DpdExportSettings::ADDRESS_ID_OPTION_KEY]) ? $data[DpdExportSettings::ADDRESS_ID_OPTION_KEY] : $this->{DpdExportSettings::ADDRESS_ID_OPTION_KEY};
        $this->{DpdExportSettings::BANK_ID_OPTION_KEY} = !empty($data[DpdExportSettings::BANK_ID_OPTION_KEY]) ? $data[DpdExportSettings::BANK_ID_OPTION_KEY] : $this->{DpdExportSettings::BANK_ID_OPTION_KEY};

        // Add country ISO code
        $country = isset($data[self::CUSTOMER_COUNTRY_KEY]) && !empty($data[self::CUSTOMER_COUNTRY_KEY]) ? $data[self::CUSTOMER_COUNTRY_KEY] : '';

        // Fix for Czech Republic - WooCommerce uses 'cs' but ISO3166 needs 'CZ'
        if (strtolower($country) === 'cs') {
            $country = 'CZ';
        }

        try {
            // Convert to uppercase for ISO3166 compatibility
            $country_data = (new ISO3166())->alpha2(strtoupper($country));
            $this->{self::CUSTOMER_COUNTRY_KEY} = !empty($country_data['numeric']) ? (int) $country_data['numeric'] : '';
        } catch (Exception $e) {
            // Log error and keep original value if country code is invalid
            error_log('DPD Export: ' . $e->getMessage());
            $this->{self::CUSTOMER_COUNTRY_KEY} = $country;
        }
    }

    /**
     * Submit request to DPD
     *
     * @param array $data
     * @param \WC_Order $data
     *
     * @return array
     *
     * @throws Exception
     */
    public function export($data = [], $order = null)
    {
        $data = $this->setAddressRecipient($data);
        $data = $this->getRequestData();

        $data = apply_filters('wc_dpd_export_data', $data, $order);

        if (empty($data)) {
            throw new Exception('No data', 400);
        }

        if (!apply_filters('wc_dpd_allow_export', true, $data)) {
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
     * @param \WC_Order $order
     *
     * @return array
     *
     * @throws Exception
     */
    public static function doExport($data = [], $order = null)
    {
        $export = new self();
        return $export->export($data, $order);
    }
}
