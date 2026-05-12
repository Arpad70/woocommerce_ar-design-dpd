<?php

defined('ABSPATH') || exit;

$order = isset($order) && $order instanceof \WC_Order ? $order : null;
$email = isset($email) ? $email : null;
$email_heading = isset($email_heading) ? (string) $email_heading : '';
$sent_to_admin = isset($sent_to_admin) ? (bool) $sent_to_admin : false;
$plain_text = isset($plain_text) ? (bool) $plain_text : false;
$intro_text = isset($intro_text) ? (string) $intro_text : '';
$tracking_button_text = isset($tracking_button_text) ? (string) $tracking_button_text : '';
$tracking_number = isset($shipment_data['tracking_number']) ? (string) $shipment_data['tracking_number'] : '';
$tracking_url = isset($shipment_data['tracking_url']) ? (string) $shipment_data['tracking_url'] : '';
$carrier = isset($shipment_data['carrier']) ? strtoupper((string) $shipment_data['carrier']) : 'DPD';

echo '= ' . wp_strip_all_tags($email_heading) . " =\n\n";
echo wp_strip_all_tags($intro_text) . "\n\n";
echo wp_strip_all_tags(__('Carrier', 'ar-design-dpd')) . ': ' . $carrier . "\n";
echo wp_strip_all_tags(__('Tracking number', 'ar-design-dpd')) . ': ' . $tracking_number . "\n";

if ($tracking_url) {
    echo wp_strip_all_tags($tracking_button_text) . ': ' . $tracking_url . "\n";
}

echo "\n";
if ($order) {
    do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);
}
echo "\n";
do_action('woocommerce_email_footer', $email);