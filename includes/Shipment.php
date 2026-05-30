<?php

namespace ArDesign\DPD;

defined('ABSPATH') || exit;

/**
 * Normalized shipment data layer for DPD exports.
 */
class Shipment
{
    private const SHIPMENT_CREATED_EVENT = 'ard_shipping_shipment_created';
    private const SHIPMENT_UPDATED_EVENT = 'ard_shipping_shipment_updated';
    private const SHIPMENT_DELIVERED_EVENT = 'ard_shipping_shipment_delivered';

    public const CARRIER = 'dpd';
    public const DPD_TRACKING_BASE_URL = 'https://www.dpd.com/sk/sk/cakam-balik/sledovanie-balikov/';
    public const CARRIER_META_KEY = '_ard_shipping_carrier';
    public const REFERENCE_META_KEY = '_ard_shipping_reference';
    public const PRIMARY_TRACKING_NUMBER_META_KEY = '_ard_shipping_tracking_number';
    public const TRACKING_NUMBERS_META_KEY = '_ard_shipping_tracking_numbers';
    public const TRACKING_URL_META_KEY = '_ard_shipping_tracking_url';
    public const LABEL_URL_META_KEY = '_ard_shipping_label_url';
    public const STATUS_META_KEY = '_ard_shipping_status';
    public const STATUS_LABEL_META_KEY = '_ard_shipping_status_label';
    public const CREATED_AT_META_KEY = '_ard_shipping_created_at';
    public const CREATED_AT_GMT_META_KEY = '_ard_shipping_created_at_gmt';
    public const UPDATED_AT_META_KEY = '_ard_shipping_updated_at';
    public const UPDATED_AT_GMT_META_KEY = '_ard_shipping_updated_at_gmt';
    public const PAYLOAD_META_KEY = '_ard_shipping_payload';
    public const HANDOVER_EMAIL_SENT_AT_META_KEY = '_ard_shipping_handover_email_sent_at';
    public const HANDOVER_EMAIL_SENT_AT_GMT_META_KEY = '_ard_shipping_handover_email_sent_at_gmt';
    public const DELIVERED_AT_META_KEY = '_ard_shipping_delivered_at';
    public const DELIVERED_AT_GMT_META_KEY = '_ard_shipping_delivered_at_gmt';
    public const DELIVERY_EMAIL_SENT_AT_META_KEY = '_ard_shipping_delivery_email_sent_at';
    public const DELIVERY_EMAIL_SENT_AT_GMT_META_KEY = '_ard_shipping_delivery_email_sent_at_gmt';
    public const DELIVERY_WORKFLOW_PROCESSED_AT_META_KEY = '_ard_shipping_delivery_workflow_processed_at';
    public const DELIVERY_WORKFLOW_PROCESSED_AT_GMT_META_KEY = '_ard_shipping_delivery_workflow_processed_at_gmt';
    public const INVOICE_FILE_META_KEY = '_ard_shipping_invoice_file';

    public static function init(): void
    {
        add_action('ard_dpd_after_order_export', [__CLASS__, 'syncExportedShipment'], 10, 2);
    }

    public static function syncExportedShipment(mixed $order, array $response = []): void
    {
        if (!$order instanceof \WC_Order) {
            return;
        }

        $shipmentData = self::buildShipmentDataFromExport($order, is_array($response) ? $response : []);

        if (empty($shipmentData['tracking_number']) && empty($shipmentData['label_url'])) {
            return;
        }

        self::storeShipmentData($order, $shipmentData);
        $order->save_meta_data();

        /**
         * Fires when a normalized shipment payload is created for an order.
         *
         * @param int       $order_id
         * @param array     $shipmentData
         * @param \WC_Order $order
         */
        do_action(self::getShipmentCreatedEventName(), $order->get_id(), $shipmentData, $order);

        /**
         * Fires when normalized shipment data is updated for an order.
         *
         * @param int       $order_id
         * @param array     $shipmentData
         * @param \WC_Order $order
         */
        do_action(self::getShipmentUpdatedEventName(), $order->get_id(), $shipmentData, $order);
    }

    public static function buildShipmentDataFromExport(\WC_Order $order, array $response = []): array
    {
        $trackingNumber = self::getTrackingNumber($order, $response);
        $labelUrl = self::getLabelUrl($order, $response);
        $reference = self::getReference($order, $response);
        $createdAt = self::currentGmtMysql();

        return [
            'carrier' => self::CARRIER,
            'reference' => $reference,
            'tracking_number' => $trackingNumber,
            'tracking_numbers' => $trackingNumber ? [$trackingNumber] : [],
            'tracking_url' => self::buildTrackingUrl($trackingNumber, $order),
            'label_url' => $labelUrl,
            'status' => 'created',
            'status_label' => __('Shipment exported to DPD', 'ar-design-dpd'),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'payload' => $response,
        ];
    }

    public static function storeShipmentData(\WC_Order $order, array $shipmentData): void
    {
        $carrier = (string) ($shipmentData['carrier'] ?? $order->get_meta(self::CARRIER_META_KEY, true) ?: self::CARRIER);

        if (!self::canUpdateSharedShipmentData($order, $carrier)) {
            return;
        }

        $order->update_meta_data(self::CARRIER_META_KEY, $carrier);
        $order->update_meta_data(self::REFERENCE_META_KEY, (string) ($shipmentData['reference'] ?? ''));
        $order->update_meta_data(self::PRIMARY_TRACKING_NUMBER_META_KEY, (string) ($shipmentData['tracking_number'] ?? ''));
        $order->update_meta_data(self::TRACKING_NUMBERS_META_KEY, array_values((array) ($shipmentData['tracking_numbers'] ?? [])));
        $order->update_meta_data(self::TRACKING_URL_META_KEY, (string) ($shipmentData['tracking_url'] ?? ''));
        $order->update_meta_data(self::LABEL_URL_META_KEY, (string) ($shipmentData['label_url'] ?? ''));
        $order->update_meta_data(self::STATUS_META_KEY, (string) ($shipmentData['status'] ?? ''));
        $order->update_meta_data(self::STATUS_LABEL_META_KEY, (string) ($shipmentData['status_label'] ?? ''));
        self::storeTimestampMeta(
            $order,
            self::UPDATED_AT_META_KEY,
            self::UPDATED_AT_GMT_META_KEY,
            (string) ($shipmentData['updated_at'] ?? self::currentGmtMysql())
        );
        $order->update_meta_data(self::PAYLOAD_META_KEY, isset($shipmentData['payload']) && is_array($shipmentData['payload']) ? $shipmentData['payload'] : []);

        if (!self::hasStoredTimestamp($order, self::CREATED_AT_META_KEY, self::CREATED_AT_GMT_META_KEY)) {
            self::storeTimestampMeta(
                $order,
                self::CREATED_AT_META_KEY,
                self::CREATED_AT_GMT_META_KEY,
                (string) ($shipmentData['created_at'] ?? self::currentGmtMysql())
            );
        }

        if ($carrier === self::CARRIER && !empty($shipmentData['tracking_number'])) {
            $order->update_meta_data(Order::TRACKING_NUMBER_META_KEY, (string) $shipmentData['tracking_number']);
        }
    }

    public static function getShipmentData(\WC_Order $order): array
    {
        $trackingNumber = (string) $order->get_meta(self::PRIMARY_TRACKING_NUMBER_META_KEY, true);
        $trackingUrl = (string) $order->get_meta(self::TRACKING_URL_META_KEY, true);

        if ($trackingNumber !== '' && self::shouldRegenerateTrackingUrl($trackingUrl)) {
            $trackingUrl = self::buildTrackingUrl($trackingNumber, $order);
        }

        return [
            'carrier' => (string) $order->get_meta(self::CARRIER_META_KEY, true),
            'reference' => (string) $order->get_meta(self::REFERENCE_META_KEY, true),
            'tracking_number' => $trackingNumber,
            'tracking_numbers' => array_values((array) $order->get_meta(self::TRACKING_NUMBERS_META_KEY, true)),
            'tracking_url' => $trackingUrl,
            'label_url' => (string) $order->get_meta(self::LABEL_URL_META_KEY, true),
            'status' => (string) $order->get_meta(self::STATUS_META_KEY, true),
            'status_label' => (string) $order->get_meta(self::STATUS_LABEL_META_KEY, true),
            'created_at' => self::getPreferredTimestamp($order, self::CREATED_AT_GMT_META_KEY, self::CREATED_AT_META_KEY),
            'updated_at' => self::getPreferredTimestamp($order, self::UPDATED_AT_GMT_META_KEY, self::UPDATED_AT_META_KEY),
            'payload' => (array) $order->get_meta(self::PAYLOAD_META_KEY, true),
            'handover_email_sent_at' => self::getPreferredTimestamp($order, self::HANDOVER_EMAIL_SENT_AT_GMT_META_KEY, self::HANDOVER_EMAIL_SENT_AT_META_KEY),
            'delivered_at' => self::getPreferredTimestamp($order, self::DELIVERED_AT_GMT_META_KEY, self::DELIVERED_AT_META_KEY),
            'delivery_email_sent_at' => self::getPreferredTimestamp($order, self::DELIVERY_EMAIL_SENT_AT_GMT_META_KEY, self::DELIVERY_EMAIL_SENT_AT_META_KEY),
            'delivery_workflow_processed_at' => self::getPreferredTimestamp($order, self::DELIVERY_WORKFLOW_PROCESSED_AT_GMT_META_KEY, self::DELIVERY_WORKFLOW_PROCESSED_AT_META_KEY),
            'invoice_file' => (string) $order->get_meta(self::INVOICE_FILE_META_KEY, true),
        ];
    }

    public static function markDelivered(\WC_Order $order, array $shipmentData = []): array
    {
        $existingShipmentData = self::getShipmentData($order);
        $deliveredAt = self::currentGmtMysql();
        $shipmentData = array_merge($existingShipmentData, $shipmentData, [
            'carrier' => $existingShipmentData['carrier'] ?: self::CARRIER,
            'status' => 'delivered',
            'status_label' => __('Shipment delivered', 'ar-design-dpd'),
            'updated_at' => $deliveredAt,
            'delivered_at' => $deliveredAt,
        ]);

        self::storeShipmentData($order, $shipmentData);
        self::storeTimestampMeta($order, self::DELIVERED_AT_META_KEY, self::DELIVERED_AT_GMT_META_KEY, $deliveredAt);
        $order->save_meta_data();

        do_action(self::getShipmentDeliveredEventName(), $order->get_id(), $shipmentData, $order);

        return $shipmentData;
    }

    public static function getTrackingNumber(\WC_Order $order, array $response = []): string
    {
        if (!empty($response[Order::EXPORT_PACKAGE_NUMBER_META_KEY])) {
            return sanitize_text_field((string) $response[Order::EXPORT_PACKAGE_NUMBER_META_KEY]);
        }

        return sanitize_text_field((string) $order->get_meta(Order::EXPORT_PACKAGE_NUMBER_META_KEY, true));
    }

    public static function getReference(\WC_Order $order, array $response = []): string
    {
        if (!empty($response[Order::EXPORT_MPSID_META_KEY])) {
            return sanitize_text_field((string) $response[Order::EXPORT_MPSID_META_KEY]);
        }

        return sanitize_text_field((string) $order->get_meta(Order::EXPORT_MPSID_META_KEY, true));
    }

    public static function getLabelUrl(\WC_Order $order, array $response = []): string
    {
        if (!empty($response[Order::EXPORT_LABEL_URL_META_KEY])) {
            return esc_url_raw((string) $response[Order::EXPORT_LABEL_URL_META_KEY]);
        }

        return esc_url_raw((string) $order->get_meta(Order::EXPORT_LABEL_URL_META_KEY, true));
    }

    public static function buildTrackingUrl(string $trackingNumber, \WC_Order $order): string
    {
        if (!$trackingNumber) {
            return '';
        }

        $defaultUrl = self::DPD_TRACKING_BASE_URL;

        return (string) apply_filters('ard_dpd_shipment_tracking_url', $defaultUrl, $trackingNumber, $order);
    }

    private static function shouldRegenerateTrackingUrl(string $trackingUrl): bool
    {
        $trackingUrl = trim($trackingUrl);

        if ($trackingUrl === '') {
            return true;
        }

        return str_contains($trackingUrl, 'tracking.dpd.sk');
    }

    private static function canUpdateSharedShipmentData(\WC_Order $order, string $carrier): bool
    {
        $existingCarrier = (string) $order->get_meta(self::CARRIER_META_KEY, true);

        if ($existingCarrier === '' || $existingCarrier === $carrier) {
            return true;
        }

        return self::orderUsesCarrier($order, $carrier);
    }

    private static function orderUsesCarrier(\WC_Order $order, string $carrier): bool
    {
        if ($carrier !== self::CARRIER) {
            return false;
        }

        foreach ($order->get_shipping_methods() as $shippingMethod) {
            if (!is_object($shippingMethod) || !method_exists($shippingMethod, 'get_method_id')) {
                continue;
            }

            $methodId = sanitize_key((string) $shippingMethod->get_method_id());
            if (0 === strpos($methodId, 'wc_dpd_') || false !== strpos($methodId, 'dpd')) {
                return true;
            }

            if (in_array($methodId, ['slovakparcelservice_address', 'slovakparcelservice_pickupplace'], true)) {
                return true;
            }
        }

        return false;
    }

    public static function currentGmtMysql(): string
    {
        return current_time('mysql', true);
    }

    public static function storeTimestampMeta(\WC_Order $order, string $legacyKey, string $gmtKey, ?string $gmtValue = null): string
    {
        $gmtValue = trim((string) ($gmtValue ?? self::currentGmtMysql()));

        if ($gmtValue === '') {
            $gmtValue = self::currentGmtMysql();
        }

        $legacyValue = get_date_from_gmt($gmtValue, 'Y-m-d H:i:s');
        if ($legacyValue === '') {
            $legacyValue = current_time('mysql');
        }

        $order->update_meta_data($legacyKey, $legacyValue);
        $order->update_meta_data($gmtKey, $gmtValue);

        return $gmtValue;
    }

    public static function getPreferredTimestamp(\WC_Order $order, string $gmtKey, string $legacyKey): string
    {
        $gmtValue = trim((string) $order->get_meta($gmtKey, true));

        if ($gmtValue !== '') {
            return $gmtValue;
        }

        return trim((string) $order->get_meta($legacyKey, true));
    }

    private static function hasStoredTimestamp(\WC_Order $order, string $legacyKey, string $gmtKey): bool
    {
        return self::getPreferredTimestamp($order, $gmtKey, $legacyKey) !== '';
    }

    private static function getShipmentCreatedEventName(): string
    {
        return defined('ARD_WORKFLOW_EVENT_SHIPMENT_CREATED')
            ? (string) ARD_WORKFLOW_EVENT_SHIPMENT_CREATED
            : self::SHIPMENT_CREATED_EVENT;
    }

    private static function getShipmentUpdatedEventName(): string
    {
        return defined('ARD_WORKFLOW_EVENT_SHIPMENT_UPDATED')
            ? (string) ARD_WORKFLOW_EVENT_SHIPMENT_UPDATED
            : self::SHIPMENT_UPDATED_EVENT;
    }

    private static function getShipmentDeliveredEventName(): string
    {
        return defined('ARD_WORKFLOW_EVENT_SHIPMENT_DELIVERED')
            ? (string) ARD_WORKFLOW_EVENT_SHIPMENT_DELIVERED
            : self::SHIPMENT_DELIVERED_EVENT;
    }
}
