<?php

namespace ArDesign\DPD;

use ArDesign\Shared\Workflow\OrderStatusTransitionService;
use WC_Order;

defined('ABSPATH') || exit;

require_once WP_PLUGIN_DIR . '/ar-design-shared-support/includes/workflow/OrderStatusTransitionService.php';

class OrderWorkflow
{
    public const READY_TO_SHIP_STATUS = 'zabalena';
    public const IN_TRANSIT_STATUS = 'v-preprave';
    public const MANUAL_REVIEW_STATUS = 'manual-review';

    private const MANAGED_STATUSES = [
        self::IN_TRANSIT_STATUS,
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
        return ard_workflow_insert_statuses_after(
            $statuses,
            [self::IN_TRANSIT_STATUS],
            'ar-design-dpd',
            'wc-zabalena'
        );
    }

    public static function markLabelPrinted(WC_Order $order): bool
    {
        $order->add_order_note(__('Shipping label was downloaded and confirmed as printed.', 'ar-design-dpd'));
        return true;
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
                ['cancelled', 'refunded', 'failed', 'completed', self::MANUAL_REVIEW_STATUS]
            );

            return;
        }

        if (in_array($status, ['returning_to_sender', 'courier_returned'], true)) {
            $order->add_order_note(__('Carrier reported a return event. WooCommerce status was left unchanged.', 'ar-design-dpd'));
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
        return OrderStatusTransitionService::transition(
            $order,
            $targetStatus,
            $internalNote,
            'ar-design-dpd',
            $customerNote,
            $notifyCustomer,
            $blockedStatuses
        );
    }
}
