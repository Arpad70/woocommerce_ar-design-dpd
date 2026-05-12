<?php

namespace ArDesign\DPD;

use WC_Order;

defined('ABSPATH') || exit;

class Automation
{
    public static function init(): void
    {
        add_action('ard_shipping_shipment_delivered', [__CLASS__, 'handleDeliveredShipment'], 5, 3);
    }

    public static function handleDeliveredShipment($order_id, $shipmentData = [], $order = null): void
    {
        $order = $order instanceof WC_Order ? $order : wc_get_order($order_id);

        if (!$order instanceof WC_Order) {
            return;
        }

        if ($order->get_meta(Shipment::DELIVERY_WORKFLOW_PROCESSED_AT_META_KEY, true)) {
            return;
        }

        $status = (string) apply_filters('ard_shipping_delivered_order_status', 'completed', $order, $shipmentData);
        if ($status && !$order->has_status([$status, 'cancelled', 'refunded', 'failed'])) {
            $order->update_status($status, __('Shipment delivery confirmed by carrier.', 'ar-design-dpd'));
        } else {
            $order->add_order_note(__('Shipment delivery confirmed by carrier.', 'ar-design-dpd'));
        }

        if (self::shouldSendInvoiceAfterDelivery($order)) {
            $invoiceFile = self::ensureInvoiceFile($order);

            if ($invoiceFile) {
                $order->update_meta_data(Shipment::INVOICE_FILE_META_KEY, $invoiceFile);
                $order->add_order_note(__('Invoice was prepared for cash on delivery follow-up email.', 'ar-design-dpd'));
            }
        }

        $order->update_meta_data(Shipment::DELIVERY_WORKFLOW_PROCESSED_AT_META_KEY, current_time('mysql'));
        $order->save_meta_data();
    }

    public static function shouldSendInvoiceAfterDelivery(WC_Order $order): bool
    {
        $codPaymentMethods = (array) apply_filters('ard_shipping_cod_payment_method_ids', ['cod'], $order);

        return in_array($order->get_payment_method(), $codPaymentMethods, true);
    }

    public static function ensureInvoiceFile(WC_Order $order): ?string
    {
        if (!function_exists('wcpdf_get_document') || !function_exists('wcpdf_get_document_file')) {
            return null;
        }

        $document = wcpdf_get_document('invoice', $order, true);
        if (!$document) {
            return null;
        }

        $file = wcpdf_get_document_file($document, 'pdf');

        return $file && file_exists($file) ? $file : null;
    }
}