<?php

use WcDPD\Shipping;

$countries = isset($countries) ? (array) $countries : [];
$base_country_code = isset($base_country_code) ? (string) $base_country_code : '';

?>

<div class="js-dpd-parcelshop-popup dpd-parcelshop-popup">
	<button class="js-dpd-parcelshop-popup-close-btn dpd-parcelshop-popup__close-btn">x</button>

	<div class="js-dpd-parcelshop-popup-container dpd-parcelshop-popup__container">
		<div class="dpd-parcelshop-popup__content">
			<form class="js-dpd-parcelshop-popup-form dpd-parcelshop-popup__form" data-nonce="<?php echo wp_create_nonce('wc-dpd-parcelshop'); ?>">
				<div class="dpd-parcelshop-popup__row">
					<div class="js-dpd-parcelshop-popup-input-wrap dpd-parcelshop-popup__input-wrap">
						<label class="dpd-parcelshop-popup__label" for="dpd-parcelshop-popup-city"><?php echo __('City', 'wc-dpd'); ?>*:</label>
						<input type="text" id="dpd-parcelshop-popup-city" name="dpd-parcelshop-popup-city" class="js-dpd-parcelshop-popup-input-city dpd-parcelshop-popup__input dpd-parcelshop-popup__input--city">
					</div>

					<div class="js-dpd-parcelshop-popup-input-wrap dpd-parcelshop-popup__input-wrap">
						<label class="dpd-parcelshop-popup__label" for="dpd-parcelshop-popup-zip"><?php echo __('Zip', 'wc-dpd'); ?>*:</label>
						<input type="text" id="dpd-parcelshop-popup-zip" name="dpd-parcelshop-popup-zip" class="js-dpd-parcelshop-popup-input-zip dpd-parcelshop-popup__input dpd-parcelshop-popup__input--zip">
					</div>

					<div class="js-dpd-parcelshop-popup-input-wrap dpd-parcelshop-popup__input-wrap">
						<label class="dpd-parcelshop-popup__label" for="dpd-parcelshop-popup-country"><?php echo __('Country', 'wc-dpd'); ?>*:</label>
						<select id="dpd-parcelshop-popup-country" name="dpd-parcelshop-popup-country" class="js-dpd-parcelshop-popup-input-country dpd-parcelshop-popup__input dpd-parcelshop-popup__input--country">
							<?php foreach ($countries as $country_code => $country) :
							    $selected = $country_code == $base_country_code ? 'selected' : ''; ?>
								<option value="<?php echo $country_code; ?>" <?php echo $selected; ?>><?php echo $country; ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="dpd-parcelshop-popup__row">
					<button type="submit" class="js-dpd-parcelshop-popup-search-btn dpd-parcelshop-popup__search-btn"><?php echo __('Search', 'wc-dpd'); ?></button>
				</div>

				<div class="js-dpd-parcelshop-popup-results dpd-parcelshop-popup__results">
					<div class="dpd-parcelshop-popup__row">
						<ul class="js-dpd-parcelshop-popup-parcels-list dpd-parcelshop-popup__parcels-list">
						</ul>
					</div>

					<div class="dpd-parcelshop-popup__row">
						<button type="button" class="js-dpd-parcelshop-popup-choose-parcelshop-btn dpd-parcelshop-popup__choose-btn"><?php echo __('Choose parcelshop', 'wc-dpd'); ?></button>
					</div>
				</div>

				<div class="js-dpd-parcelshop-popup-response dpd-parcelshop-popup__response"></div>
			</form>
		</div>
	</div>

	<script id="<?php echo Shipping::FRAGMENTS_ELEMENT_ID; ?>"></script>
</div>
