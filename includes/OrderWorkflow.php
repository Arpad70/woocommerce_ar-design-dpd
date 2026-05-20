<?php

namespace ArDesign\DPD;

use WC_Order;

defined('ABSPATH') || exit;

class OrderWorkflow
{
    public const RETURN_STATUS = 'vratka';
    public const READY_TO_SHIP_STATUS = 'na-odoslanie';
    public const IN_TRANSIT_STATUS = 'v-preprave';
    public const MANUAL_REVIEW_STATUS = 'manual-review';

    public static function init(): void
    {
        add_action('init', [__CLASS__, 'registerReturnStatus']);
        add_filter('wc_order_statuses', [__CLASS__, 'registerReturnStatusInLists']);
    }

    public static function registerReturnStatus(): void
    {
        $statusKey = 'wc-' . self::RETURN_STATUS;

        if (\function_exists('post_status_exists') && \post_status_exists($statusKey)) {
            return;
        }

        register_post_status(
            $statusKey,
            [
                'label' => _x('Vratka', 'Order status', 'ar-design-dpd'),
                'public' => true,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                'label_count' => _n_noop('Vratka <span class="count">(%s)</span>', 'Vratka <span class="count">(%s)</span>', 'ar-design-dpd'),
            ]
        );
    }

    public static function registerReturnStatusInLists(array $statuses): array
    {
        $statusKey = 'wc-' . self::RETURN_STATUS;
        if (isset($statuses[$statusKey])) {
            return $statuses;
        }

        $result = [];
        $inserted = false;

        foreach ($statuses as $key => $label) {
            $result[$key] = $label;

            if (in_array($key, ['wc-v-preprave', 'wc-na-odoslanie', 'wc-completed'], true)) {
                $result[$statusKey] = __('Vratka', 'ar-design-dpd');
                $inserted = true;
            }
        }

        if (!$inserted) {
            $result[$statusKey] = __('Vratka', 'ar-design-dpd');
        }

        return $result;
    }

    public static function markLabelPrinted(WC_Order $order): bool
    {
        return self::transitionOrderStatus(
            $order,
            self::READY_TO_SHIP_STATUS,
            __('Shipping label was downloaded and confirmed as printed. Order moved to Na odoslanie.', 'ar-design-dpd'),
            '',
            false,
            ['cancelled', 'refunded', 'failed', 'completed', self::RETURN_STATUS, self::MANUAL_REVIEW_STATUS, self::IN_TRANSIT_STATUS]
        );
    }

    public static function handleTrackingUpdate(WC_Order $order, array $trackingData): void
    {
        $status = sanitize_key((string) ($trackingData['current_status'] ?? ''));

        if ($status === '') {
            return;
        }

        if (in_array($status, ['picked_up', 'in_transit', 'out_for_delivery'], true)) {
            self::transitionOrderStatus(
                $order,
                self::IN_TRANSIT_STATUS,
                __('Carrier confirmed that the shipment is in transit.', 'ar-design-dpd'),
                __('Your shipment has been handed over to the carrier and is now in transit.', 'ar-design-dpd'),
                true,
                ['cancelled', 'refunded', 'failed', 'completed', self::RETURN_STATUS, self::MANUAL_REVIEW_STATUS]
            );

            return;
        }

        if ($status === 'returning_to_sender') {
            self::transitionOrderStatus(
                $order,
                self::RETURN_STATUS,
                __('Carrier reported that the shipment is returning to the sender.', 'ar-design-dpd'),
                '',
                false,
                ['cancelled', 'refunded', 'failed', 'completed', self::MANUAL_REVIEW_STATUS]
            );

            return;
        }

        if ($status === 'courier_returned') {
            self::transitionOrderStatus(
                $order,
                self::MANUAL_REVIEW_STATUS,
                __('Returned shipment was received back and now requires manual review.', 'ar-design-dpd'),
                '',
                false,
                ['cancelled', 'refunded', 'failed', 'completed']
            );
        }
    }

    private static function transitionOrderStatus(
        WC_Order $order,
        string $targetStatus,
        string $internalNote,
        string $customerNote = '',
        bool $notifyCustomer = false,
        array $blockedStatuses = []
    ): bool {
        $targetStatus = sanitize_key($targetStatus);
        if ($targetStatus === '') {
            return false;
        }

        if (!self::isStatusAvailable($targetStatus)) {
            $order->add_order_note(sprintf(
                /* translators: %s: requested WooCommerce order status slug. */
                __('Requested workflow status "%s" is not available, so the order status was not changed.', 'ar-design-dpd'),
                $targetStatus
            ));

            return false;
        }

        if ($order->has_status([$targetStatus])) {
            return false;
        }

        if ($blockedStatuses !== [] && $order->has_status($blockedStatuses)) {
            return false;
        }

        $order->update_status($targetStatus, $internalNote);

        if ($notifyCustomer && $customerNote !== '') {
            $order->add_order_note($customerNote, true, false);
        }

        return true;
    }

    private static function isStatusAvailable(string $status): bool
    {
        $statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [];

        return isset($statuses['wc-' . $status]);
    }
}
