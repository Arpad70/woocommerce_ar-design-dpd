<?php

defined('ABSPATH') || exit;

$args = isset($args) && is_array($args) ? $args : [];

$yaymailEmailClass = '\\ArDesign\\DPD\\YayMailShipmentCreatedEmail';
if (!class_exists($yaymailEmailClass)) {
    return;
}

$template = class_exists('\\YayMail\\YayMailTemplate')
    ? new \YayMail\YayMailTemplate('ard_shipping_shipment_created')
    : null;

if (!$template || !method_exists($template, 'is_enabled') || !$template->is_enabled()) {
    return;
}

$content = $template->get_content($args);
yaymail_kses_post_e($content);
