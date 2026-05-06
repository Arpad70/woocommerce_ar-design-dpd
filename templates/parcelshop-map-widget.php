<?php

$api_key = isset($api_key) ? (string) $api_key : '';
$language = isset($language) ? (string) $language : 'sk';

?>

<div class="js-dpd-parcelshop-map-widget-popup dpd-parcelshop-map-widget" data-nonce="<?php echo wp_create_nonce('wc-dpd-parcelshop'); ?>">
	<div class="js-dpd-parcelshop-map-widget-popup-container dpd-parcelshop-map-widget__container">
		<div class="dpd-parcelshop-map-widget__embed js-dpd-parcelshop-map-widget-popup-embed" data-api-key="<?php echo $api_key; ?>" data-language="<?php echo $language; ?>"></div>
	</div>
</div>
