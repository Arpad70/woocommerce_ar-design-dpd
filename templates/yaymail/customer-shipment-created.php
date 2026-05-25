<?php

defined('ABSPATH') || exit;

$args = isset($args) && is_array($args) ? $args : [];

$yaymailEmailClass = '\\ArDesign\\DPD\\YayMailShipmentCreatedEmail';
if (!class_exists($yaymailEmailClass)) {
    return;
}

$template = $yaymailEmailClass::get_instance()->template;

if (!empty($template)) {
    $content = $template->get_content($args);
    yaymail_kses_post_e($content);
}