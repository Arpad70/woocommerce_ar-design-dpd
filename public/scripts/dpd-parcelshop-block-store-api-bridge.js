/*
 * @deprecated 2026-05-23 This standalone Store API bridge is no longer enqueued.
 * DPD Checkout Blocks now sync parcelshop data through the WooCommerce Blocks
 * IntegrationRegistry script and checkout store extension data.
 *
 * Kept temporarily in the repository as a deprecated artifact until the next
 * asset cleanup/rebuild wave.
 */

if (false) {
(function () {
	'use strict';

	var DEFAULT_SETTINGS = {
		ready: false,
		templateClass: 'dpd-parcelshop-container',
		templateSourceId: 'dpd-template-source',
		shippingMethodId: 'wc_dpd_parcelshop',
		extensionNamespace: 'ar-design-dpd',
		storageKey: 'ard_dpd_chosen_parcelshop',
		shippingMethodStorageKey: 'ard_dpd_chosen_parcelshop_shipping_method',
		fieldKeys: [],
		requiredFieldKeys: [],
		radioSelectors: [
			'input[type="radio"][id*="wc_dpd_parcelshop"]',
			'input[type="radio"][id*="ard_dpd_parcelshop"]',
			'input[type="radio"][value*="dpd_parcelshop"]',
			'input[type="radio"][name*="dpd_parcelshop"]'
		],
		optionSelector: '.wc-block-components-radio-control__option',
		chosenWrapSelector: '.js-dpd-chosen-parcelshop-content',
		chosenTextSelector: '.js-dpd-chosen-parcelshop-chosen-parcelshop-text'
	};

	var settings = mergeSettings(DEFAULT_SETTINGS, window.ard_dpd_parcelshop_block_settings || {});
	var bodyObserver = null;
	var refreshTimer = null;
	var syncIntervalId = null;

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

		rawData = storage.getItem(settings.storageKey);
		if (!rawData) {
			return null;
		}

		try {
			return JSON.parse(rawData);
		} catch (error) {
			return null;
		}
	}

	function readStoredShippingMethodBaseId() {
		var storage = getStorage();

		if (!storage) {
			return '';
		}

		return storage.getItem(settings.shippingMethodStorageKey) || '';
	}

	function persistParcelshopData(parcelshopData, shippingMethodBaseId) {
		var storage = getStorage();

		if (!storage || !hasMeaningfulParcelshopData(parcelshopData)) {
			return;
		}

		storage.setItem(settings.storageKey, JSON.stringify(parcelshopData));
		storage.setItem(settings.shippingMethodStorageKey, shippingMethodBaseId || '');
	}

	function getShippingMethodBaseId(value) {
		if (!value) {
			return '';
		}

		return String(value).split(':')[0];
	}

	function isDpdShippingMethodValue(value) {
		return getShippingMethodBaseId(value) === settings.shippingMethodId;
	}

	function findDpdRadios() {
		var allRadios = [];

		settings.radioSelectors.forEach(function (selector) {
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

	function escapeAttributeValue(value) {
		if (window.CSS && typeof window.CSS.escape === 'function') {
			return window.CSS.escape(value);
		}

		return String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
	}

	function getTemplateContainers() {
		return Array.prototype.slice.call(document.querySelectorAll('.' + settings.templateClass));
	}

	function getFieldInput(container, fieldKey) {
		if (!container) {
			return null;
		}

		return container.querySelector('input[name="' + escapeAttributeValue(fieldKey) + '"]');
	}

	function extractParcelshopDataFromContainer(container) {
		var parcelshopData = {};

		if (!container) {
			return parcelshopData;
		}

		settings.fieldKeys.forEach(function (fieldKey) {
			var input = getFieldInput(container, fieldKey);
			var value = input && typeof input.value !== 'undefined' ? String(input.value).trim() : '';

			if (value !== '') {
				parcelshopData[fieldKey] = value;
			}
		});

		return parcelshopData;
	}

	function hasMeaningfulParcelshopData(parcelshopData) {
		var meaningfulKey = settings.requiredFieldKeys[0] || settings.fieldKeys[0];

		if (!parcelshopData || typeof parcelshopData !== 'object') {
			return false;
		}

		if (meaningfulKey && parcelshopData[meaningfulKey]) {
			return true;
		}

		return settings.requiredFieldKeys.some(function (fieldKey) {
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

		settings.fieldKeys.forEach(function (fieldKey) {
			var input = getFieldInput(container, fieldKey);
			if (!input) {
				return;
			}

			input.value = parcelshopData[fieldKey] || '';
		});

		chosenWrap = container.querySelector(settings.chosenWrapSelector);
		chosenText = container.querySelector(settings.chosenTextSelector);

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

	function getCheckedDpdRadio() {
		var radios = findDpdRadios();
		var index;

		for (index = 0; index < radios.length; index += 1) {
			if (radios[index].checked) {
				return radios[index];
			}
		}

		return radios.length > 0 ? radios[0] : null;
	}

	function getActiveTemplateContainer() {
		var radio = getCheckedDpdRadio();
		var optionContainer = radio ? radio.closest(settings.optionSelector) : null;

		if (!optionContainer) {
			return getTemplateContainers()[0] || null;
		}

		return optionContainer.querySelector('.' + settings.templateClass);
	}

	function syncParcelshopDataFromDom() {
		var activeContainer = getActiveTemplateContainer();
		var activeRadio = getCheckedDpdRadio();
		var parcelshopData = extractParcelshopDataFromContainer(activeContainer);

		if (!hasMeaningfulParcelshopData(parcelshopData)) {
			getTemplateContainers().some(function (container) {
				parcelshopData = extractParcelshopDataFromContainer(container);
				return hasMeaningfulParcelshopData(parcelshopData);
			});
		}

		if (!hasMeaningfulParcelshopData(parcelshopData)) {
			return;
		}

		persistParcelshopData(parcelshopData, getShippingMethodBaseId(activeRadio ? activeRadio.value : ''));
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

	function isStoreApiCheckoutUrl(url) {
		return /\/wc\/store\/(?:v\d+\/)?checkout(?:\/|$|\?)/.test(url);
	}

	function shouldInjectIntoPayload(payload) {
		var shippingMethod = '';

		if (!payload || typeof payload !== 'object') {
			return false;
		}

		if (Array.isArray(payload.shipping_method) && payload.shipping_method[0]) {
			shippingMethod = payload.shipping_method[0];
		} else if (typeof payload.shipping_method === 'string') {
			shippingMethod = payload.shipping_method;
		} else {
			var radio = getCheckedDpdRadio();
			shippingMethod = radio ? radio.value : readStoredShippingMethodBaseId();
		}

		return isDpdShippingMethodValue(shippingMethod);
	}

	function getCurrentParcelshopData() {
		var activeData = extractParcelshopDataFromContainer(getActiveTemplateContainer());

		if (hasMeaningfulParcelshopData(activeData)) {
			return activeData;
		}

		return readStoredParcelshopData();
	}

	function injectParcelshopIntoRequestBody(bodyText) {
		var parcelshopData;
		var payload;

		if (typeof bodyText !== 'string' || !bodyText.trim()) {
			return bodyText;
		}

		parcelshopData = getCurrentParcelshopData();
		if (!hasMeaningfulParcelshopData(parcelshopData)) {
			return bodyText;
		}

		try {
			payload = JSON.parse(bodyText);
		} catch (error) {
			return bodyText;
		}

		if (!shouldInjectIntoPayload(payload)) {
			return bodyText;
		}

		settings.fieldKeys.forEach(function (fieldKey) {
			if (parcelshopData[fieldKey]) {
				payload[fieldKey] = parcelshopData[fieldKey];
			}
		});

		payload.extensions = payload.extensions || {};
		payload.extensions[settings.extensionNamespace] = payload.extensions[settings.extensionNamespace] || {};

		settings.fieldKeys.forEach(function (fieldKey) {
			if (parcelshopData[fieldKey]) {
				payload.extensions[settings.extensionNamespace][fieldKey] = parcelshopData[fieldKey];
			}
		});

		return JSON.stringify(payload);
	}

	function installStoreApiCheckoutBridge() {
		if (typeof window.fetch !== 'function' || window.fetch.__ardDpdParcelshopBridgeInstalled) {
			return;
		}

		var originalFetch = window.fetch.bind(window);

		window.fetch = async function (resource, options) {
			var requestUrl = typeof resource === 'string' ? resource : resource && resource.url ? resource.url : '';

			if (!isStoreApiCheckoutUrl(requestUrl)) {
				return originalFetch(resource, options);
			}

			if (resource instanceof Request && (!options || typeof options.body === 'undefined')) {
				var requestBody = await resource.clone().text();
				var patchedRequestBody = injectParcelshopIntoRequestBody(requestBody);

				if (patchedRequestBody !== requestBody) {
					var requestHeaders = new Headers(resource.headers || {});
					if (!requestHeaders.has('Content-Type')) {
						requestHeaders.set('Content-Type', 'application/json');
					}

					resource = new Request(resource, {
						body: patchedRequestBody,
						headers: requestHeaders
					});
				}

				return originalFetch(resource, options);
			}

			if (options && typeof options.body === 'string') {
				var patchedBody = injectParcelshopIntoRequestBody(options.body);

				if (patchedBody !== options.body) {
					options = Object.assign({}, options, {
						body: patchedBody
					});
				}
			}

			return originalFetch(resource, options);
		};

		window.fetch.__ardDpdParcelshopBridgeInstalled = true;
	}

	function scheduleRefresh() {
		if (refreshTimer) {
			return;
		}

		refreshTimer = window.setTimeout(function () {
			refreshTimer = null;
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
			characterData: true,
			attributes: true,
			attributeFilter: ['class']
		});
	}

	function installInteractionHandlers() {
		document.body.addEventListener('change', function () {
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

	function installSyncFallback() {
		if (syncIntervalId) {
			return;
		}

		syncIntervalId = window.setInterval(function () {
			syncParcelshopDataFromDom();
		}, 1000);
	}

	function initialize() {
		if (!settings.ready) {
			return;
		}

		hydrateTemplatesFromStorage();
		syncParcelshopDataFromDom();
		installStoreApiCheckoutBridge();
		installDomObserver();
		installInteractionHandlers();
		installSyncFallback();
	}

	domReady(initialize);
})();
}
