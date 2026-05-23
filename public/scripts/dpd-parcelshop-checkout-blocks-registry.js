(function () {
	'use strict';

	var DEFAULT_SETTINGS = {
		ready: false,
		extension_namespace: 'ar-design-dpd',
		storage_key: 'ard_dpd_chosen_parcelshop',
		template_html: '',
		template_class: 'dpd-parcelshop-container',
		template_selected_class: 'is-selected',
		field_keys: [],
		required_field_keys: [],
		radio_selectors: [
			'input[type="radio"][id*="wc_dpd_parcelshop"]',
			'input[type="radio"][id*="ard_dpd_parcelshop"]',
			'input[type="radio"][value*="dpd_parcelshop"]',
			'input[type="radio"][name*="dpd_parcelshop"]'
		],
		option_selector: '.wc-block-components-radio-control__option',
		chosen_wrap_selector: '.js-dpd-chosen-parcelshop-content',
		chosen_text_selector: '.js-dpd-chosen-parcelshop-chosen-parcelshop-text'
	};

	var settings = mergeSettings(
		DEFAULT_SETTINGS,
		mergeSettings(getRegistrySettings(), window.ard_dpd_parcelshop_block_settings || {})
	);
	var bodyObserver = null;
	var refreshTimer = null;

	function mergeSettings(defaults, localized) {
		var merged = {};
		var key;

		for (key in defaults) {
			if (Object.prototype.hasOwnProperty.call(defaults, key)) {
				merged[key] = defaults[key];
			}
		}

		for (key in localized) {
			if (Object.prototype.hasOwnProperty.call(localized, key)) {
				merged[key] = localized[key];
			}
		}

		return merged;
	}

	function getRegistrySettings() {
		try {
			if (
				window.wc &&
				window.wc.wcSettings &&
				typeof window.wc.wcSettings.getSetting === 'function'
			) {
				return window.wc.wcSettings.getSetting('ard_dpd_checkout_blocks_data', {});
			}
		} catch (error) {
			return {};
		}

		return {};
	}

	function domReady(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback);
			return;
		}

		callback();
	}

	function getStorage() {
		try {
			return window.sessionStorage;
		} catch (error) {
			return null;
		}
	}

	function readStoredParcelshopData() {
		var storage = getStorage();
		var rawData;

		if (!storage) {
			return null;
		}

		rawData = storage.getItem(settings.storage_key);
		if (!rawData) {
			return null;
		}

		try {
			return JSON.parse(rawData);
		} catch (error) {
			return null;
		}
	}

	function persistParcelshopData(parcelshopData) {
		var storage = getStorage();

		if (!storage || !hasMeaningfulParcelshopData(parcelshopData)) {
			return;
		}

		storage.setItem(settings.storage_key, JSON.stringify(parcelshopData));
	}

	function findDpdRadios() {
		var allRadios = [];

		settings.radio_selectors.forEach(function (selector) {
			var radios = document.querySelectorAll(selector);
			var index;

			for (index = 0; index < radios.length; index += 1) {
				if (allRadios.indexOf(radios[index]) === -1) {
					allRadios.push(radios[index]);
				}
			}
		});

		return allRadios;
	}

	function getCheckedDpdRadio() {
		var radios = findDpdRadios();
		var index;

		for (index = 0; index < radios.length; index += 1) {
			if (radios[index].checked) {
				return radios[index];
			}
		}

		return null;
	}

	function getFirstDpdRadio() {
		var radios = findDpdRadios();
		return radios[0] || null;
	}

	function getTemplateHtml() {
		if (!settings.template_html || typeof settings.template_html !== 'string') {
			return '';
		}

		return settings.template_html;
	}

	function addTemplates() {
		var templateContent = getTemplateHtml();

		if (!templateContent) {
			return;
		}

		findDpdRadios().forEach(function (radio) {
			ensureTemplateForRadio(radio, templateContent);
		});

		syncTemplateVisibility();
	}

	function getOptionContainerFromRadio(radio) {
		if (!radio || typeof radio.closest !== 'function') {
			return null;
		}

		return radio.closest(settings.option_selector);
	}

	function getTemplateContainerFromOption(optionContainer) {
		if (!optionContainer) {
			return null;
		}

		return optionContainer.querySelector('.' + settings.template_class);
	}

	function createTemplateContainer(templateContent) {
		var template = document.createElement('div');

		template.className = settings.template_class;
		template.setAttribute('data-ard-dpd-block-mount', 'parcelshop');
		template.setAttribute('data-ard-dpd-selected', 'false');
		template.setAttribute('aria-hidden', 'true');
		template.innerHTML = templateContent;

		return template;
	}

	function ensureTemplateForRadio(radio, templateContent) {
		var optionContainer = getOptionContainerFromRadio(radio);
		var template;

		if (!optionContainer) {
			return null;
		}

		template = getTemplateContainerFromOption(optionContainer);
		if (template) {
			return template;
		}

		template = createTemplateContainer(templateContent);
		optionContainer.appendChild(template);

		return template;
	}

	function escapeAttributeValue(value) {
		if (window.CSS && typeof window.CSS.escape === 'function') {
			return window.CSS.escape(value);
		}

		return String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
	}

	function getTemplateContainers() {
		return Array.prototype.slice.call(document.querySelectorAll('.' + settings.template_class));
	}

	function getFieldInput(container, fieldKey) {
		if (!container) {
			return null;
		}

		return container.querySelector(
			'[data-ard-dpd-field="' + escapeAttributeValue(fieldKey) + '"], input[name="' + escapeAttributeValue(fieldKey) + '"]'
		);
	}

	function getChosenWrap(container) {
		if (!container) {
			return null;
		}

		return container.querySelector('[data-ard-dpd-ui="chosen-parcelshop"]') || container.querySelector(settings.chosen_wrap_selector);
	}

	function getChosenText(container) {
		if (!container) {
			return null;
		}

		return container.querySelector('[data-ard-dpd-ui="chosen-parcelshop-text"]') || container.querySelector(settings.chosen_text_selector);
	}

	function extractParcelshopDataFromContainer(container) {
		var parcelshopData = {};

		if (!container) {
			return parcelshopData;
		}

		settings.field_keys.forEach(function (fieldKey) {
			var input = getFieldInput(container, fieldKey);
			var value = input && typeof input.value !== 'undefined' ? String(input.value).trim() : '';

			if (value !== '') {
				parcelshopData[fieldKey] = value;
			}
		});

		return parcelshopData;
	}

	function hasMeaningfulParcelshopData(parcelshopData) {
		var meaningfulKey = settings.required_field_keys[0] || settings.field_keys[0];

		if (!parcelshopData || typeof parcelshopData !== 'object') {
			return false;
		}

		if (meaningfulKey && parcelshopData[meaningfulKey]) {
			return true;
		}

		return settings.required_field_keys.some(function (fieldKey) {
			return Boolean(parcelshopData[fieldKey]);
		});
	}

	function buildChosenParcelshopText(parcelshopData) {
		var parts = [];

		if (!parcelshopData || typeof parcelshopData !== 'object') {
			return '';
		}

		[
			parcelshopData.wc_dpd_parcelshop_name,
			parcelshopData.wc_dpd_parcelshop_street,
			parcelshopData.wc_dpd_parcelshop_zip,
			parcelshopData.wc_dpd_parcelshop_city,
			parcelshopData.wc_dpd_parcelshop_country_code
		].forEach(function (value) {
			if (value) {
				parts.push(value);
			}
		});

		return parts.join(', ');
	}

	function applyParcelshopDataToContainer(container, parcelshopData) {
		var chosenWrap;
		var chosenText;

		if (!container || !parcelshopData || typeof parcelshopData !== 'object') {
			return;
		}

		settings.field_keys.forEach(function (fieldKey) {
			var input = getFieldInput(container, fieldKey);

			if (!input) {
				return;
			}

			input.value = parcelshopData[fieldKey] || '';
		});

		chosenWrap = getChosenWrap(container);
		chosenText = getChosenText(container);

		if (chosenText) {
			chosenText.textContent = buildChosenParcelshopText(parcelshopData);
		}

		if (chosenWrap) {
			if (hasMeaningfulParcelshopData(parcelshopData)) {
				chosenWrap.classList.add('active');
			} else {
				chosenWrap.classList.remove('active');
			}
		}
	}

	function getActiveTemplateContainer() {
		var radio = getCheckedDpdRadio();
		var optionContainer = radio ? getOptionContainerFromRadio(radio) : null;

		if (!optionContainer) {
			optionContainer = getFirstDpdRadio();
			optionContainer = optionContainer ? getOptionContainerFromRadio(optionContainer) : null;
		}

		if (!optionContainer) {
			return getTemplateContainers()[0] || null;
		}

		return getTemplateContainerFromOption(optionContainer);
	}

	function syncTemplateVisibility() {
		var activeRadio = getCheckedDpdRadio();
		var fallbackRadio = getFirstDpdRadio();
		var selectedRadio = activeRadio || fallbackRadio;

		findDpdRadios().forEach(function (radio) {
			var optionContainer = getOptionContainerFromRadio(radio);
			var template = getTemplateContainerFromOption(optionContainer);
			var isSelected = Boolean(template && selectedRadio && radio === selectedRadio);

			if (!template) {
				return;
			}

			template.classList.toggle(settings.template_selected_class, isSelected);
			template.classList.toggle('active', isSelected);
			template.setAttribute('data-ard-dpd-selected', isSelected ? 'true' : 'false');
			template.setAttribute('aria-hidden', isSelected ? 'false' : 'true');
		});
	}

	function getCheckoutStoreDispatcher() {
		try {
			if (
				window.wp &&
				window.wp.data &&
				typeof window.wp.data.dispatch === 'function' &&
				window.wc &&
				window.wc.wcBlocksData &&
				window.wc.wcBlocksData.checkoutStore
			) {
				return window.wp.data.dispatch(window.wc.wcBlocksData.checkoutStore);
			}
		} catch (error) {
			return null;
		}

		return null;
	}

	function buildExtensionData(parcelshopData) {
		var extensionData = {};

		settings.field_keys.forEach(function (fieldKey) {
			extensionData[fieldKey] = parcelshopData && parcelshopData[fieldKey] ? parcelshopData[fieldKey] : '';
		});

		return extensionData;
	}

	function syncCheckoutStoreExtensionData(parcelshopData) {
		var dispatcher = getCheckoutStoreDispatcher();
		var activeRadio = getCheckedDpdRadio();

		if (!dispatcher || typeof dispatcher.setExtensionData !== 'function') {
			return false;
		}

		if (!activeRadio) {
			dispatcher.setExtensionData(settings.extension_namespace, buildExtensionData(null), true);
			return true;
		}

		dispatcher.setExtensionData(
			settings.extension_namespace,
			buildExtensionData(hasMeaningfulParcelshopData(parcelshopData) ? parcelshopData : null),
			true
		);

		return true;
	}

	function syncParcelshopDataFromDom() {
		var activeContainer = getActiveTemplateContainer();
		var parcelshopData = extractParcelshopDataFromContainer(activeContainer);

		syncTemplateVisibility();

		if (!hasMeaningfulParcelshopData(parcelshopData)) {
			getTemplateContainers().some(function (container) {
				parcelshopData = extractParcelshopDataFromContainer(container);
				return hasMeaningfulParcelshopData(parcelshopData);
			});
		}

		if (hasMeaningfulParcelshopData(parcelshopData)) {
			persistParcelshopData(parcelshopData);
		}

		syncCheckoutStoreExtensionData(parcelshopData);
	}

	function hydrateTemplatesFromStorage() {
		var storedParcelshopData = readStoredParcelshopData();

		if (!hasMeaningfulParcelshopData(storedParcelshopData)) {
			return;
		}

		getTemplateContainers().forEach(function (container) {
			applyParcelshopDataToContainer(container, storedParcelshopData);
		});
	}

	function scheduleRefresh() {
		if (refreshTimer) {
			return;
		}

		refreshTimer = window.setTimeout(function () {
			refreshTimer = null;
			addTemplates();
			hydrateTemplatesFromStorage();
			syncParcelshopDataFromDom();
		}, 120);
	}

	function installDomObserver() {
		if (bodyObserver || !document.body) {
			return;
		}

		bodyObserver = new MutationObserver(function () {
			scheduleRefresh();
		});

		bodyObserver.observe(document.body, {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: ['class', 'checked']
		});
	}

	function installInteractionHandlers() {
		document.body.addEventListener('change', function () {
			window.setTimeout(function () {
				scheduleRefresh();
			}, 0);
		});

		document.body.addEventListener('input', function () {
			window.setTimeout(function () {
				scheduleRefresh();
			}, 0);
		});

		document.body.addEventListener('click', function () {
			window.setTimeout(function () {
				scheduleRefresh();
			}, 0);
		});

		if (window.jQuery) {
			window.jQuery(document.body).on('updated_checkout', function () {
				scheduleRefresh();
			});
		}
	}

	function initialize() {
		if (!settings.ready) {
			return;
		}

		addTemplates();
		hydrateTemplatesFromStorage();
		syncParcelshopDataFromDom();
		installDomObserver();
		installInteractionHandlers();
	}

	domReady(initialize);
})();