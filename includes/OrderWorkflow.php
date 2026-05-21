<?php

namespace ArDesign\DPD;

use WC_Order;

defined('ABSPATH') || exit;

class OrderWorkflow
{
    public const RETURN_STATUS = ARD_WORKFLOW_STATUS_RETURN;
    public const READY_TO_SHIP_STATUS = ARD_WORKFLOW_STATUS_READY_TO_SHIP;
    public const IN_TRANSIT_STATUS = ARD_WORKFLOW_STATUS_IN_TRANSIT;
    public const MANUAL_REVIEW_STATUS = ARD_WORKFLOW_STATUS_MANUAL_REVIEW;

    private const MANAGED_STATUSES = [
        self::READY_TO_SHIP_STATUS,
        self::IN_TRANSIT_STATUS,
        self::RETURN_STATUS,
    ];

    public static function init(): void
    {
        add_action('init', [__CLASS__, 'registerWorkflowStatuses']);
        add_filter('wc_order_statuses', [__CLASS__, 'registerWorkflowStatusesInLists']);
    }

    public static function registerWorkflowStatuses(): void
    {
        ard_workflow_register_post_statuses(self::MANAGED_STATUSES, 'ar-design-dpd');
    }

    public static function registerWorkflowStatusesInLists(array $statuses): array
    {
        $statuses = ard_workflow_insert_statuses_after(
            $statuses,
            [self::READY_TO_SHIP_STATUS, self::IN_TRANSIT_STATUS],
            'ar-design-dpd',
            'wc-processing'
        );

        return ard_workflow_insert_statuses_after(
            $statuses,
            [self::RETURN_STATUS],
            'ar-design-dpd',
            'wc-v-preprave'
        );
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
        $targetStatus = ard_workflow_normalize_status($targetStatus);
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

        return isset($statuses[ard_workflow_wc_status_key($status)]);
    }
}
