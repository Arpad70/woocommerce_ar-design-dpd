<?php

defined('ABSPATH') || exit;

$type = isset($type) ? (string) $type : 'customer';
$view = isset($view) && is_array($view) ? $view : [];

$carrierLabel = isset($view['carrier_label']) ? (string) $view['carrier_label'] : 'DPD';
$labelUrl = isset($view['label_url']) ? (string) $view['label_url'] : '';
$trackingLinks = isset($view['tracking_links']) && is_array($view['tracking_links']) ? $view['tracking_links'] : [];
$carrierCostValue = isset($view['carrier_cost_value']) ? (string) $view['carrier_cost_value'] : '';
$carrierCostPlaceholder = isset($view['carrier_cost_placeholder']) ? (string) $view['carrier_cost_placeholder'] : '';
$statusLabel = isset($view['status_label']) ? (string) $view['status_label'] : '';
$statusCode = isset($view['status']) ? (string) $view['status'] : '';
$statusDescription = isset($view['status_description']) ? (string) $view['status_description'] : '';
$statusDate = isset($view['status_date']) ? (string) $view['status_date'] : '';
$statusLocation = isset($view['status_location']) ? (string) $view['status_location'] : '';
$lastSyncAt = isset($view['last_sync_at']) ? (string) $view['last_sync_at'] : '';
$lastError = isset($view['last_error']) ? (string) $view['last_error'] : '';

if ($labelUrl === '' && $trackingLinks === [] && $statusLabel === '' && $statusCode === '' && $lastError === '') {
    return;
}

$containerStyle = $type === 'admin'
    ? 'style="width: 100%; display: block; margin-top: 12px; padding-top: 8px; border-top: 1px solid #dcdcde;"'
    : 'style="margin-top: 16px;"';

?>

<div class="ar-design-dpd-shipment-summary" <?php echo $containerStyle; ?>>
    <p>
        <strong><?php echo esc_html(sprintf(
            /* translators: %s: carrier label shown in the shipment summary, for example DPD. */
            __('%s Shipment', 'ar-design-dpd'),
            $carrierLabel
        )); ?></strong><br>

        <?php if ($labelUrl !== '') : ?>
            <a class="button" href="<?php echo esc_url($labelUrl); ?>" target="_blank" rel="noopener noreferrer" style="margin: 6px 0 8px;">
                <?php echo esc_html(sprintf(
                    /* translators: %s: carrier label shown in the shipment summary, for example DPD. */
                    __('Download %s label', 'ar-design-dpd'),
                    $carrierLabel
                )); ?>
            </a>
            <br>
        <?php endif; ?>

        <?php if ($trackingLinks !== []) : ?>
            <strong><?php echo esc_html__('Tracking', 'ar-design-dpd'); ?></strong>:<br>
            <?php foreach ($trackingLinks as $trackingLink) : ?>
                <?php
                $trackingNumber = isset($trackingLink['number']) ? (string) $trackingLink['number'] : '';
                $trackingUrl = isset($trackingLink['url']) ? (string) $trackingLink['url'] : '';
                if ($trackingNumber === '') {
                    continue;
                }
                ?>
                <?php if ($trackingUrl !== '') : ?>
                    <a href="<?php echo esc_url($trackingUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($trackingNumber); ?></a><br>
                <?php else : ?>
                    <?php echo esc_html($trackingNumber); ?><br>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($carrierCostValue !== '' || $carrierCostPlaceholder !== '') : ?>
            <strong><?php echo esc_html__('Carrier cost', 'ar-design-dpd'); ?></strong>:
            <?php if ($carrierCostValue !== '') : ?>
                <?php echo esc_html($carrierCostValue); ?><br>
            <?php else : ?>
                <em><?php echo esc_html($carrierCostPlaceholder); ?></em><br>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($statusLabel !== '' || $statusCode !== '') : ?>
            <strong><?php echo esc_html__('Current status', 'ar-design-dpd'); ?></strong>:
            <?php echo esc_html($statusLabel !== '' ? $statusLabel : $statusCode); ?><br>
        <?php endif; ?>

        <?php if ($statusDescription !== '') : ?>
            <span><?php echo esc_html($statusDescription); ?></span><br>
        <?php endif; ?>

        <?php if ($statusDate !== '') : ?>
            <strong><?php echo esc_html__('Status date', 'ar-design-dpd'); ?></strong>: <?php echo esc_html($statusDate); ?><br>
        <?php endif; ?>

        <?php if ($statusLocation !== '') : ?>
            <strong><?php echo esc_html__('Location', 'ar-design-dpd'); ?></strong>: <?php echo esc_html($statusLocation); ?><br>
        <?php endif; ?>

        <?php if ($lastSyncAt !== '') : ?>
            <strong><?php echo esc_html__('Last sync', 'ar-design-dpd'); ?></strong>: <?php echo esc_html($lastSyncAt); ?><br>
        <?php endif; ?>

        <?php if ($lastError !== '') : ?>
            <strong><?php echo esc_html__('Last tracking error', 'ar-design-dpd'); ?></strong>: <?php echo esc_html($lastError); ?><br>
        <?php endif; ?>
    </p>
</div>