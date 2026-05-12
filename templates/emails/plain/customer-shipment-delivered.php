<?php

defined('ABSPATH') || exit;

$order = isset($order) && $order instanceof \WC_Order ? $order : null;
$email = isset($email) ? $email : null;
$email_heading = isset($email_heading) ? (string) $email_heading : '';
$sent_to_admin = isset($sent_to_admin) ? (bool) $sent_to_admin : false;
$plain_text = isset($plain_text) ? (bool) $plain_text : false;
$thank_you_message = isset($thank_you_message) ? (string) $thank_you_message : '';
$promo_message = isset($promo_message) ? (string) $promo_message : '';
$complaint_information = isset($complaint_information) ? (string) $complaint_information : '';
$claim_form_url = isset($claim_form_url) ? (string) $claim_form_url : '';
$tracking_number = isset($shipment_data['tracking_number']) ? (string) $shipment_data['tracking_number'] : '';
$invoice_attached = $order ? (bool) $order->get_meta('_ard_shipping_invoice_file', true) : false;

echo '= ' . wp_strip_all_tags($email_heading) . " =\n\n";
echo wp_strip_all_tags($thank_you_message) . "\n\n";

if ($tracking_number) {
    echo wp_strip_all_tags(__('Tracking number', 'ar-design-dpd')) . ': ' . $tracking_number . "\n";
}

if ($invoice_attached) {
    echo wp_strip_all_tags(__('Your invoice is attached to this email.', 'ar-design-dpd')) . "\n";
}

if ($promo_message) {
    echo "\n" . wp_strip_all_tags($promo_message) . "\n";
}

if ($complaint_information) {
    echo "\n" . wp_strip_all_tags($complaint_information) . "\n";
}

if ($claim_form_url) {
    echo wp_strip_all_tags(__('Complaint procedure and claim page', 'ar-design-dpd')) . ': ' . $claim_form_url . "\n";
}

echo "\n";
if ($order) {
    do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email);
}
echo "\n";
do_action('woocommerce_email_footer', $email);