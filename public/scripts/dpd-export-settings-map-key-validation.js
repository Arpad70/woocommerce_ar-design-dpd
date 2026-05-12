(function () {
	var mapWidgetField = document.querySelector(
		'.js-ar-design-dpd-map-widget-enabled-field'
	);
	var apiKeyField = document.querySelector('.js-ar-design-dpd-api-key-field');
	var mapApiKeyField = document.querySelector(
		'.js-ar-design-dpd-map-api-key-field'
	);

	if (!mapWidgetField || !mapApiKeyField) {
		return;
	}

	var notice = document.createElement('p');
	notice.className = 'description js-ar-design-dpd-map-key-validation-message';
	notice.style.marginTop = '8px';
	notice.style.fontWeight = '600';
	mapApiKeyField.insertAdjacentElement('afterend', notice);

	updateNotice();

	mapWidgetField.addEventListener('change', updateNotice);
	mapApiKeyField.addEventListener('input', updateNotice);

	if (apiKeyField) {
		apiKeyField.addEventListener('input', updateNotice);
	}

	function updateNotice() {
		var isMapWidgetEnabled = !!mapWidgetField.checked;
		var apiKey = getValue(apiKeyField);
		var mapApiKey = getValue(mapApiKeyField);

		notice.style.color = '';
		notice.hidden = false;

		if (!isMapWidgetEnabled) {
			notice.textContent = '';
			notice.hidden = true;
			return;
		}

		if (!mapApiKey) {
			notice.textContent =
				ard_dpd_map_key_validation_settings.missing_map_key;
			notice.style.color = '#b32d2e';
			return;
		}

		if (apiKey && mapApiKey === apiKey) {
			notice.textContent =
				ard_dpd_map_key_validation_settings.same_key_error;
			notice.style.color = '#b32d2e';
			return;
		}

		notice.textContent = ard_dpd_map_key_validation_settings.helper_text;
		notice.style.color = '#2271b1';
	}

	function getValue(field) {
		return field && typeof field.value === 'string' ? field.value.trim() : '';
	}
})();