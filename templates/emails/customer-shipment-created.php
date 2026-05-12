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

do_action('woocommerce_email_header', $email_heading, $email);
?>

<p><?php echo wp_kses_post(wpautop($intro_text)); ?></p>

<p>
    <strong><?php esc_html_e('Carrier', 'ar-design-dpd'); ?>:</strong>
    <?php echo esc_html($carrier); ?><br>
    <strong><?php esc_html_e('Tracking number', 'ar-design-dpd'); ?>:</strong>
    <?php echo esc_html($tracking_number); ?>
</p>

<?php if ($tracking_url) : ?>
    <p>
        <a href="<?php echo esc_url($tracking_url); ?>" style="display:inline-block;padding:12px 20px;background:#dc0032;color:#ffffff;text-decoration:none;border-radius:4px;">
            <?php echo esc_html($tracking_button_text); ?>
        </a>
    </p>
    <p>
        <small>
            <a href="<?php echo esc_url($tracking_url); ?>"><?php echo esc_html($tracking_url); ?></a>
        </small>
    </p>
<?php endif; ?>

<?php if ($order) : ?>
    <?php do_action('woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email); ?>
    <?php do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email); ?>
    <?php do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email); ?>
<?php endif; ?>
<?php do_action('woocommerce_email_footer', $email); ?>