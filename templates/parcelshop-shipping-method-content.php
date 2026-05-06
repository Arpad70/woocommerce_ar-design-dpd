
<?php
use WcDPD\DpdParcelShopShippingMethod;

use function WcDPD\is_map_widget_enabled;

$chosen_parcelshop_id = isset($chosen_parcelshop_id) ? $chosen_parcelshop_id : '';
$chosen_parcelshop_pus_id = isset($chosen_parcelshop_pus_id) ? $chosen_parcelshop_pus_id : '';
$chosen_parcelshop_id = $chosen_parcelshop_pus_id ? $chosen_parcelshop_id : '';
$chosen_parcelshop_name = isset($chosen_parcelshop_name) ? $chosen_parcelshop_name : '';
$chosen_parcelshop_street = isset($chosen_parcelshop_street) ? $chosen_parcelshop_street : '';
$chosen_parcelshop_city = isset($chosen_parcelshop_city) ? $chosen_parcelshop_city : '';
$chosen_parcelshop_zip = isset($chosen_parcelshop_zip) ? $chosen_parcelshop_zip : '';
$chosen_parcelshop_country_code = isset($chosen_parcelshop_country_code) ? $chosen_parcelshop_country_code : '';
$chosen_parcelshop_text = isset($chosen_parcelshop_text) ? $chosen_parcelshop_text : '';
$customer_zip = isset($customer_zip) ? $customer_zip : '';
$countries = isset($countries) ? (array) $countries : [];
$allowed_countries = isset($allowed_countries) ? (array) $allowed_countries : [];
$base_country_code = isset($base_country_code) ? (string) $base_country_code : '';
$min_weight = isset($min_weight) ? (float) $min_weight : 0;
$is_eligible_for_alzabox = isset($is_eligible_for_alzabox) ? (bool) $is_eligible_for_alzabox : true;
$is_eligible_for_slovenska_posta_box = isset($is_eligible_for_slovenska_posta_box) ? (bool) $is_eligible_for_slovenska_posta_box : true;
$is_eligible_for_zbox = isset($is_eligible_for_zbox) ? (bool) $is_eligible_for_zbox : true;
$is_cod_required = isset($is_cod_required) ? (bool) $is_cod_required : false;
$is_card_required = isset($is_card_required) ? (bool) $is_card_required : false;
$disallow_shops = isset($disallow_shops) ? (bool) $disallow_shops : false;
$disallow_lockers = isset($disallow_lockers) ? (bool) $disallow_lockers : false;
$disallow_dpd_pickup_stations = isset($disallow_dpd_pickup_stations) ? (bool) $disallow_dpd_pickup_stations : false;
$disallow_sk_post = isset($disallow_sk_post) ? (bool) $disallow_sk_post : false;
$disallow_alza_boxes = isset($disallow_alza_boxes) ? (bool) $disallow_alza_boxes : false;
$disallow_zbox = isset($disallow_zbox) ? (bool) $disallow_zbox : false;
?>
<div class="dpd-parcelshop-shipping-method-content">
	<div class="dpd-parcelshop-shipping-method-content__open-btn-wrap">
		<div class="dpd-parcelshop-shipping-method-content__logo">
			<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" x="0px" y="0px" viewBox="0 0 297.5 126.6" style="enable-background:new 0 0 297.5 126.6;" xml:space="preserve">
				<path fill="#414042" d="M191.6,93.1c-5.5,1.4-12.6,2.2-18.8,2.2c-15.9,0-26.4-8.4-26.4-23.9c0-14.6,9.8-24.1,24.1-24.1 c3.2,0,6.6,0.4,8.7,1.4V27.5h12.4V93.1z M179.2,59.6c-2-0.9-4.5-1.4-7.6-1.4c-7.5,0-12.6,4.6-12.6,12.8c0,8.8,5.5,13.7,14.2,13.7 c1.5,0,3.9-0.1,6-0.5L179.2,59.6L179.2,59.6z M293,93.1c-5.5,1.4-12.6,2.2-18.8,2.2c-15.9,0-26.4-8.4-26.4-23.9 c0-14.6,9.8-24.1,24.1-24.1c3.2,0,6.6,0.4,8.7,1.4V27.5H293V93.1z M280.7,59.6c-2-0.9-4.5-1.4-7.6-1.4c-7.5,0-12.6,4.6-12.6,12.8 c0,8.8,5.5,13.7,14.2,13.7c1.5,0,3.9-0.1,6-0.5L280.7,59.6L280.7,59.6z M211,59.5c2.1-0.8,4.9-1.1,7.4-1.1 c7.6,0,12.9,4.4,12.9,12.4c0,9.4-5.8,13.6-13.6,13.7v10.8c0.2,0,0.4,0,0.6,0c16,0,25.6-9,25.6-24.9c0-14.5-10.2-23.1-25.2-23.1 c-7.6,0-15.1,1.8-20.1,3.8v61.7H211L211,59.5L211,59.5z"/>
				<path fill="#dc0032" d="M79.3,56.5c-0.5,0.3-1.3,0.3-1.8,0l-2.9-1.7c-0.2-0.1-0.5-0.4-0.6-0.7c0,0,0,0,0,0c-0.2-0.3-0.3-0.6-0.3-0.9 l-0.1-3.4c0-0.6,0.4-1.3,0.9-1.6l35.3-20.6l-49.6-27c-0.5-0.3-1.3-0.4-2-0.5c-0.7,0-1.4,0.1-2,0.5l-49.6,27l55.6,32.3 c0.5,0.3,0.9,1,0.9,1.6v47.2c0,0.6-0.4,1.3-0.9,1.5l-3,1.7c-0.2,0.1-0.6,0.2-0.9,0.2c0,0,0,0-0.1,0c-0.4,0-0.7-0.1-1-0.2l-3-1.7 c-0.5-0.3-0.9-1-0.9-1.5V66.5c0-0.3-0.3-0.7-0.5-0.8L3.8,37.2V93c0,1.2,0.9,2.8,2,3.4l50.4,29.7c0.5,0.3,1.2,0.5,2,0.5 c0.7,0,1.4-0.2,2-0.5l50.4-29.7c1.1-0.6,2-2.2,2-3.4V37.2L79.3,56.5z"/>
			</svg>
		</div>
		<?php if (is_map_widget_enabled()): ?>
			<button type="button" class="js-dpd-parcelshop-map-widget-open-popup-btn dpd-parcelshop-shipping-method-content__open-btn"
				data-countries="<?php echo htmlspecialchars(json_encode($countries), ENT_QUOTES, 'UTF-8'); ?>"
				data-allowed-countries="<?php echo htmlspecialchars(json_encode($allowed_countries), ENT_QUOTES, 'UTF-8'); ?>"
				data-base-country-code="<?php echo $base_country_code; ?>"
				data-customer-zip="<?php echo $customer_zip; ?>"
				data-min-weight-in-kg="<?php echo $min_weight; ?>"
				data-is-eligible-for-alzabox="<?php echo $is_eligible_for_alzabox ? 'true' : 'false'; ?>"
				data-is-eligible-for-slovenska-posta-box="<?php echo $is_eligible_for_slovenska_posta_box ? 'true' : 'false'; ?>"
				data-is-eligible-for-zbox="<?php echo $is_eligible_for_zbox ? 'true' : 'false'; ?>"
				data-is-cod-required="<?php echo $is_cod_required ? 'true' : 'false'; ?>"
				data-is-card-payment-required="<?php echo $is_card_required ? 'true' : 'false'; ?>"
				data-disallow-shops="<?php echo $disallow_shops ? 'true' : 'false'; ?>"
				data-disallow-lockers="<?php echo $disallow_lockers ? 'true' : 'false'; ?>"
				data-disallow-dpd-pickup-stations="<?php echo $disallow_dpd_pickup_stations ? 'true' : 'false'; ?>"
				data-disallow-sk-post="<?php echo $disallow_sk_post ? 'true' : 'false'; ?>"
				data-disallow-alza-boxes="<?php echo $disallow_alza_boxes ? 'true' : 'false'; ?>"
				data-disallow-zbox="<?php echo $disallow_zbox ? 'true' : 'false'; ?>"
			><?php echo __('Choose parcelshop', 'wc-dpd'); ?></button>
		<?php else: ?>
			<button type="button" class="js-dpd-parcelshop-open-popup-btn dpd-parcelshop-shipping-method-content__open-btn"><?php echo __('Choose parcelshop', 'wc-dpd'); ?></button>
		<?php endif; ?>
	</div>
	<div class="js-dpd-chosen-parcelshop-content dpd-parcelshop-shipping-method-content__chosen-parcelshop-wrap <?php echo $chosen_parcelshop_text ? 'active' : ''; ?>">
		<p class="dpd-parcelshop-shipping-method-content__chosen-parcelshop"><?php echo __('Selected parcelshop', 'wc-dpd'); ?>: <strong class="js-dpd-chosen-parcelshop-chosen-parcelshop-text"><?php echo $chosen_parcelshop_text; ?></strong></p>
	</div>
	<input type="hidden" name="<?php echo DpdParcelShopShippingMethod::PARCELSHOP_ID_META_KEY; ?>" class="js-dpd-parcelshop-hidden-parcelshop-id" value="<?php echo esc_attr($chosen_parcelshop_id); ?>">
	<input type="hidden" name="<?php echo DpdParcelShopShippingMethod::PARCELSHOP_PUS_ID_META_KEY; ?>" class="js-dpd-parcelshop-hidden-parcelshop-pus-id" value="<?php echo esc_attr($chosen_parcelshop_pus_id); ?>">
	<input type="hidden" name="<?php echo DpdParcelShopShippingMethod::PARCELSHOP_NAME_META_KEY; ?>" class="js-dpd-parcelshop-hidden-parcelshop-name" value="<?php echo esc_attr($chosen_parcelshop_name); ?>">
	<input type="hidden" name="<?php echo DpdParcelShopShippingMethod::PARCELSHOP_STREET_META_KEY; ?>" class="js-dpd-parcelshop-hidden-parcelshop-street" value="<?php echo esc_attr($chosen_parcelshop_street); ?>">
	<input type="hidden" name="<?php echo DpdParcelShopShippingMethod::PARCELSHOP_CITY_META_KEY; ?>" class="js-dpd-parcelshop-hidden-parcelshop-city" value="<?php echo esc_attr($chosen_parcelshop_city); ?>">
	<input type="hidden" name="<?php echo DpdParcelShopShippingMethod::PARCELSHOP_ZIP_META_KEY; ?>" class="js-dpd-parcelshop-hidden-parcelshop-zip" value="<?php echo esc_attr($chosen_parcelshop_zip); ?>">
	<input type="hidden" name="<?php echo DpdParcelShopShippingMethod::PARCELSHOP_COUNTRY_CODE_META_KEY; ?>" class="js-dpd-parcelshop-hidden-parcelshop-country-code" value="<?php echo esc_attr($chosen_parcelshop_country_code); ?>">
</div>
