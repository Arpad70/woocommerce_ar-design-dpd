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

do_action('woocommerce_email_header', $email_heading, $email);
?>

<p><?php echo wp_kses_post(wpautop($thank_you_message)); ?></p>

<?php if ($tracking_number) : ?>
    <p>
        <strong><?php esc_html_e('Tracking number', 'ar-design-dpd'); ?>:</strong>
        <?php echo esc_html($tracking_number); ?>
    </p>
<?php endif; ?>

<?php if ($invoice_attached) : ?>
    <p><?php esc_html_e('Your invoice is attached to this email.', 'ar-design-dpd'); ?></p>
<?php endif; ?>

<?php if ($promo_message) : ?>
    <p><?php echo wp_kses_post(wpautop($promo_message)); ?></p>
<?php endif; ?>

<?php if ($complaint_information) : ?>
    <p><?php echo wp_kses_post(wpautop($complaint_information)); ?></p>
<?php endif; ?>

<?php if ($claim_form_url) : ?>
    <p>
        <a href="<?php echo esc_url($claim_form_url); ?>"><?php esc_html_e('Complaint procedure and claim page', 'ar-design-dpd'); ?></a>
    </p>
<?php endif; ?>

<?php if ($order) : ?>
    <?php do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email); ?>
    <?php do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email); ?>
    <?php do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email); ?>
<?php endif; ?>

<?php do_action('woocommerce_email_footer', $email); ?>