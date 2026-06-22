<?php

defined('ABSPATH') || exit;

$args = isset($args) && is_array($args) ? $args : [];

$yaymailEmailClass = '\\ArDesign\\DPD\\YayMailShipmentDeliveredEmail';
if (!class_exists($yaymailEmailClass)) {
	return;
}

$template = class_exists('\\YayMail\\YayMailTemplate')
	? new \YayMail\YayMailTemplate('ard_dpd_shipment_delivered')
	: null;

if (!$template || !method_exists($template, 'is_enabled') || !$template->is_enabled()) {
	return;
}

$content = $template->get_content($args);
yaymail_kses_post_e($content);
