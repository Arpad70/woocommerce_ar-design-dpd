window.dpdParcelShopWidget = (function () {
	var mapWidgetPopupSelector,
		popup,
		dpdMapWidget,
		mapWidgetPopupContainerSelector,
		mapWidgetPopupOpenBtnSelector,
		chosenParcelShopContentSelector,
		chosenParcelShopContentTextSelector,
		chosenParcelShopHiddenParcelIdSelector,
		chosenParcelShopHiddenParcelPusIdSelector,
		chosenParcelShopHiddenParcelNameSelector,
		chosenParcelShopHiddenParcelStreetSelector,
		chosenParcelShopHiddenParcelCitySelector,
		chosenParcelShopHiddenParcelZipSelector,
		chosenParcelShopHiddenParcelCountryCodeSelector,
		dpdMapWidgetEl,
		dpdMapOpenWidgetMapPopupEl,
		customerZip,
		countries,
		allowedCountries,
		baseCountryCode,
		isMapInitialized = false;

	function init() {
		mapWidgetPopupOpenBtnSelector =
			'.js-dpd-parcelshop-map-widget-open-popup-btn';

		mapWidgetPopupSelector = '.js-dpd-parcelshop-map-widget-popup';

		mapWidgetPopupContainerSelector =
			'.js-dpd-parcelshop-map-widget-popup-container';

		chosenParcelShopContentSelector = '.js-dpd-chosen-parcelshop-content';
		chosenParcelShopContentTextSelector =
			'.js-dpd-chosen-parcelshop-chosen-parcelshop-text';
		chosenParcelShopHiddenParcelIdSelector =
			'.js-dpd-parcelshop-hidden-parcelshop-id';
		chosenParcelShopHiddenParcelPusIdSelector =
			'.js-dpd-parcelshop-hidden-parcelshop-pus-id';
		chosenParcelShopHiddenParcelNameSelector =
			'.js-dpd-parcelshop-hidden-parcelshop-name';
		chosenParcelShopHiddenParcelStreetSelector =
			'.js-dpd-parcelshop-hidden-parcelshop-street';
		chosenParcelShopHiddenParcelCitySelector =
			'.js-dpd-parcelshop-hidden-parcelshop-city';
		chosenParcelShopHiddenParcelZipSelector =
			'.js-dpd-parcelshop-hidden-parcelshop-zip';
		chosenParcelShopHiddenParcelCountryCodeSelector =
			'.js-dpd-parcelshop-hidden-parcelshop-country-code';

		popup = document.querySelector(mapWidgetPopupSelector);

		document.addEventListener(
			'click',
			function (event) {
				if (!event.target.matches(mapWidgetPopupOpenBtnSelector)) {
					return;
				}

				event.preventDefault();

				dpdMapWidgetEl = document.querySelector(
					'.js-dpd-parcelshop-map-widget-popup-embed'
				);

				if (!dpdMapWidgetEl) {
					return;
				}

				dpdMapOpenWidgetMapPopupEl = event.target.closest(
					mapWidgetPopupOpenBtnSelector
				);

				if (!dpdMapOpenWidgetMapPopupEl) {
					return;
				}

				initMap();
				openPopup();
			},
			false
		);

		document.addEventListener(
			'click',
			function (event) {
				if (!event.target.matches(mapWidgetPopupContainerSelector)) {
					return;
				}

				event.preventDefault();
				closePopup();
			},
			false
		);

		document.addEventListener(
			'keydown',
			function (event) {
				if (event.key !== 'Escape') {
					return;
				}

				closePopup();
			},
			false
		);

		document.addEventListener(
			'change',
			function (event) {
				if (
					event.target &&
					event.target.matches('input[name="payment_method"]')
				) {
					if (isParcelShopChosen()) {
						document.body.dispatchEvent(new Event('update_checkout'));
					}
				}
			},
			false
		);
	}

	function initMap() {
		if (isMapInitialized) {
			return;
		}

		var apiKey = dpdMapWidgetEl.getAttribute('data-api-key');
		var language = dpdMapWidgetEl.getAttribute('data-language') || 'sk';

		if (!apiKey) {
			console.log('Map Api key is missing');
			return;
		}

		countries = JSON.parse(
			dpdMapOpenWidgetMapPopupEl.getAttribute('data-countries')
		);
		allowedCountries = JSON.parse(
			dpdMapOpenWidgetMapPopupEl.getAttribute('data-allowed-countries')
		).map(function (countryCode) {
			return String(countryCode).toUpperCase();
		});
		baseCountryCode = String(
			dpdMapOpenWidgetMapPopupEl.getAttribute(
			'data-base-country-code'
			) || ''
		).toUpperCase();

		dpdMapWidget = new DpdPudo.Widget({
			apiKey: apiKey,
			country: baseCountryCode,
			allowedCountries: allowedCountries,
			language: language,
		});

		isMapInitialized = true;
	}

	function openLegacyPopupFallback(code) {
		if (
			code === 'invalid_api_key' &&
			window.dpdParcelShopPopup &&
			typeof window.dpdParcelShopPopup.openPopup === 'function'
		) {
			alert(
				ard_dpd_parcelshop_map_widget_settings.invalid_api_key_error_message
			);
			window.dpdParcelShopPopup.openPopup();
			return true;
		}

		return false;
	}

	function setSelectedParcelShop(data) {
		if (typeof data === 'undefined') {
			data = {};
		}

		if (!data || Object.keys(data).length === 0) {
			return;
		}

		var parcelShopId = resolveParcelShopId(data);
		var parcelShopPusId = resolveParcelShopPusId(data, parcelShopId);
		var parcelShopName = data.hasOwnProperty('name') ? data.name : null;
		var parcelShopStreet = data.hasOwnProperty('street') ? data.street : null;
		var parcelShopZip = data.hasOwnProperty('zip') ? data.zip : null;
		var parcelShopCity = data.hasOwnProperty('city') ? data.city : null;
		var parcelShopCountryCode = data.hasOwnProperty('countryCode')
			? data.countryCode
			: null;
		var parcelShopMaxWeight = data.hasOwnProperty('maxweight')
			? data.maxweight
			: null;
		var parcelShopCod = data.hasOwnProperty('cod') ? data.cod : null;
		var parcelShopCard = data.hasOwnProperty('card') ? data.card : null;
		var isEligibleForAlzabox = data.hasOwnProperty('isEligibleForAlzabox')
			? data.isEligibleForAlzabox
			: null;
		var isEligibleForSlovenskaPostaBox = data.hasOwnProperty(
			'isEligibleForSlovenskaPostaBox'
		)
			? data.isEligibleForSlovenskaPostaBox
			: null;
		var isEligibleForZbox = data.hasOwnProperty('isEligibleForZbox')
			? data.isEligibleForZbox
			: null;

		setSelectedParcelShopSession(
			parcelShopId,
			parcelShopPusId,
			parcelShopName,
			parcelShopStreet,
			parcelShopZip,
			parcelShopCity,
			parcelShopCountryCode,
			parcelShopMaxWeight,
			parcelShopCod,
			parcelShopCard,
			isEligibleForAlzabox,
			isEligibleForSlovenskaPostaBox,
			isEligibleForZbox
		);

		var parcelShopCountry =
			countries &&
			typeof countries === 'object' &&
			countries.hasOwnProperty(parcelShopCountryCode)
				? countries[parcelShopCountryCode]
				: parcelShopCountryCode;

		document.querySelector(chosenParcelShopHiddenParcelIdSelector).value =
			parcelShopId;
		document.querySelector(chosenParcelShopHiddenParcelPusIdSelector).value =
			parcelShopPusId;
		document.querySelector(chosenParcelShopHiddenParcelNameSelector).value =
			parcelShopName;
		document.querySelector(chosenParcelShopHiddenParcelStreetSelector).value =
			parcelShopStreet;
		document.querySelector(chosenParcelShopHiddenParcelZipSelector).value =
			parcelShopZip;
		document.querySelector(chosenParcelShopHiddenParcelCitySelector).value =
			parcelShopCity;
		document.querySelector(chosenParcelShopHiddenParcelCountryCodeSelector).value =
			parcelShopCountryCode;

		var notEmptyAddressValues = Object.values([
			parcelShopName,
			parcelShopStreet,
			parcelShopZip,
			parcelShopCity,
			parcelShopCountry,
		]).filter(function (value) {
			return !!value;
		});

		document.querySelector(chosenParcelShopContentTextSelector).innerHTML =
			notEmptyAddressValues.join(', ');

		document
			.querySelector(chosenParcelShopContentSelector)
			.classList.add('active');
	}

	function resolveParcelShopId(data) {
		var candidateKeys = ['parcelShopId', 'pudoId', 'id', 'locationId'];

		for (var i = 0; i < candidateKeys.length; i++) {
			var candidateKey = candidateKeys[i];
			if (!data.hasOwnProperty(candidateKey) || data[candidateKey] === null || data[candidateKey] === '') {
				continue;
			}

			return data[candidateKey];
		}

		return null;
	}

	function resolveParcelShopPusId(data, parcelShopId) {
		var candidateKeys = ['pusId', 'publicId', 'parcelShopPusId'];

		for (var i = 0; i < candidateKeys.length; i++) {
			var candidateKey = candidateKeys[i];
			if (!data.hasOwnProperty(candidateKey) || data[candidateKey] === null || data[candidateKey] === '') {
				continue;
			}

			return data[candidateKey];
		}

		return parcelShopId;
	}

	function setSelectedParcelShopSession(
		parcelShopId,
		parcelShopPusId,
		parcelShopName,
		parcelShopStreet,
		parcelShopZip,
		parcelShopCity,
		parcelShopCountryCode,
		parcelShopMaxWeight,
		parcelShopCod,
		parcelShopCard,
		isEligibleForAlzabox,
		isEligibleForSlovenskaPostaBox,
		isEligibleForZbox
	) {
		if (typeof parcelShopId === 'undefined') parcelShopId = '';
		if (typeof parcelShopPusId === 'undefined') parcelShopPusId = '';
		if (typeof parcelShopName === 'undefined') parcelShopName = '';
		if (typeof parcelShopStreet === 'undefined') parcelShopStreet = '';
		if (typeof parcelShopZip === 'undefined') parcelShopZip = '';
		if (typeof parcelShopCity === 'undefined') parcelShopCity = '';
		if (typeof parcelShopCountryCode === 'undefined') parcelShopCountryCode = '';
		if (typeof parcelShopMaxWeight === 'undefined') parcelShopMaxWeight = '';
		if (typeof parcelShopCod === 'undefined') parcelShopCod = '';
		if (typeof parcelShopCard === 'undefined') parcelShopCard = '';
		if (typeof isEligibleForAlzabox === 'undefined') isEligibleForAlzabox = true;
		if (typeof isEligibleForSlovenskaPostaBox === 'undefined') isEligibleForSlovenskaPostaBox = true;
		if (typeof isEligibleForZbox === 'undefined') isEligibleForZbox = true;

		var xhr = new XMLHttpRequest();
		xhr.open('POST', ard_dpd_parcelshop_map_widget_settings.ajax_url, true);
		xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

		xhr.onreadystatechange = function () {
			var DONE = 4;
			var OK = 200;

			if (xhr.readyState === DONE) {
				if (xhr.status === OK) {
					document.body.dispatchEvent(new Event('update_checkout'));
				}
			}
		};

		xhr.send(
			'action=ard_dpd_update_chosen_parcelshop&wp_nonce=' +
				popup.getAttribute('data-nonce') +
				'&ard_dpd_parcelshop_id=' +
				parcelShopId +
				'&ard_dpd_parcelshop_pus_id=' +
				parcelShopPusId +
				'&ard_dpd_parcelshop_name=' +
				parcelShopName +
				'&ard_dpd_parcelshop_street=' +
				parcelShopStreet +
				'&ard_dpd_parcelshop_zip=' +
				parcelShopZip +
				'&ard_dpd_parcelshop_city=' +
				parcelShopCity +
				'&ard_dpd_parcelshop_country_code=' +
				parcelShopCountryCode +
				'&ard_dpd_parcelshop_max_weight=' +
				parcelShopMaxWeight +
				'&ard_dpd_parcelshop_cod=' +
				parcelShopCod +
				'&ard_dpd_parcelshop_card=' +
				parcelShopCard +
				'&ard_dpd_parcelshop_is_alzabox_eligible=' +
				isEligibleForAlzabox +
				'&ard_dpd_parcelshop_is_slovenska_posta_eligible=' +
				isEligibleForSlovenskaPostaBox +
				'&ard_dpd_parcelshop_is_zbox_eligible=' +
				isEligibleForZbox
		);
	}

	function openPopup() {
		popup.classList.add('active');

		if (dpdMapWidgetEl && dpdMapWidget) {
			var chosenParcelShopId = document.querySelector(
				chosenParcelShopHiddenParcelIdSelector
			).value;

			if (chosenParcelShopId) {
				dpdMapWidget.options.selectedPudoId = chosenParcelShopId;
			}

			customerZip = getCustomerZip();

			if (customerZip) {
				dpdMapWidget.options.zip = customerZip;
			}

			var countryCode = dpdMapOpenWidgetMapPopupEl.getAttribute(
				'data-base-country-code'
			);

			if (countryCode) {
				dpdMapWidget.options.country = String(countryCode).toUpperCase();
			}

			var language = dpdMapWidgetEl.getAttribute('data-language') || 'sk';
			dpdMapWidget.options.language = language;

			var minWeightInKg = parseInt(
				dpdMapOpenWidgetMapPopupEl.getAttribute('data-min-weight-in-kg')
			);
			var isEligibleForAlzabox =
				dpdMapOpenWidgetMapPopupEl.getAttribute(
					'data-is-eligible-for-alzabox'
				) === 'true';
			var isEligibleForSlovenskaPostaBox =
				dpdMapOpenWidgetMapPopupEl.getAttribute(
					'data-is-eligible-for-slovenska-posta-box'
				) === 'true';
			var isEligibleForZbox =
				dpdMapOpenWidgetMapPopupEl.getAttribute(
					'data-is-eligible-for-zbox'
				) === 'true';

			dpdMapWidget.options.minWeightInKg = minWeightInKg;

			var disallowShops =
				dpdMapOpenWidgetMapPopupEl.getAttribute('data-disallow-shops') ===
				'true';
			var disallowLockers =
				dpdMapOpenWidgetMapPopupEl.getAttribute('data-disallow-lockers') ===
				'true';
			var disallowDpdPickupStations =
				dpdMapOpenWidgetMapPopupEl.getAttribute(
					'data-disallow-dpd-pickup-stations'
				) === 'true';
			var disallowSkPost =
				dpdMapOpenWidgetMapPopupEl.getAttribute('data-disallow-sk-post') ===
				'true';
			var disallowAlzaBoxes =
				dpdMapOpenWidgetMapPopupEl.getAttribute(
					'data-disallow-alza-boxes'
				) === 'true';
			var disallowZbox =
				dpdMapOpenWidgetMapPopupEl.getAttribute('data-disallow-zbox') ===
				'true';

			var allowedPudoTypes = ['shop', 'locker'];
			if (disallowShops) {
				allowedPudoTypes = allowedPudoTypes.filter(function (type) {
					return type !== 'shop';
				});
			}
			if (disallowLockers) {
				allowedPudoTypes = allowedPudoTypes.filter(function (type) {
					return type !== 'locker';
				});
			}

			dpdMapWidget.options.allowedPudoTypes = allowedPudoTypes;

			var allowedLockerTypes = [
				'dpdSkPickupStations',
				'skPost',
				'alzaSlovakia',
				'zBox',
				'outsideOfSlovakia',
			];

			if (!isEligibleForAlzabox || !isEligibleForSlovenskaPostaBox || !isEligibleForZbox) {
				if (!isEligibleForAlzabox) {
					allowedLockerTypes = allowedLockerTypes.filter(function (type) {
						return type !== 'alzaSlovakia';
					});
				}

				if (!isEligibleForSlovenskaPostaBox) {
					allowedLockerTypes = allowedLockerTypes.filter(function (type) {
						return type !== 'skPost';
					});
				}

				if (!isEligibleForZbox) {
					allowedLockerTypes = allowedLockerTypes.filter(function (type) {
						return type !== 'zBox';
					});
				}
			}

			if (disallowLockers) {
				allowedLockerTypes = [];
			} else {
				if (disallowDpdPickupStations) {
					allowedLockerTypes = allowedLockerTypes.filter(function (type) {
						return type !== 'dpdSkPickupStations';
					});
				}
				if (disallowSkPost) {
					allowedLockerTypes = allowedLockerTypes.filter(function (type) {
						return type !== 'skPost';
					});
				}
				if (disallowAlzaBoxes) {
					allowedLockerTypes = allowedLockerTypes.filter(function (type) {
						return type !== 'alzaSlovakia';
					});
				}
				if (disallowZbox) {
					allowedLockerTypes = allowedLockerTypes.filter(function (type) {
						return type !== 'zBox';
					});
				}
			}

			dpdMapWidget.options.allowedLockerTypes = allowedLockerTypes;

			if (allowedPudoTypes.length === 0 && allowedLockerTypes.length === 0) {
				alert(ard_dpd_parcelshop_map_widget_settings.no_pickup_types_error_message);
				closePopup();
				return;
			}

			var requiredServices = [];
			var isCodRequired =
				dpdMapOpenWidgetMapPopupEl.getAttribute('data-is-cod-required') ===
				'true';
			var isCardPaymentRequired =
				dpdMapOpenWidgetMapPopupEl.getAttribute(
					'data-is-card-payment-required'
				) === 'true';

			if (isCodRequired) {
				requiredServices.push('cod');
			}

			if (isCardPaymentRequired) {
				requiredServices.push('cardPayment');
			}

			dpdMapWidget.options.requiredServices = requiredServices;

			dpdMapWidget
				.attach(dpdMapWidgetEl)
				.then(function (pudo) {
					setSelectedParcelShop({
						id: pudo.id,
						pusId: pudo.pusId,
						parcelShopId: pudo.parcelShopId,
						pudoId: pudo.pudoId,
						name: pudo.name,
						street: pudo.street,
						houseno: pudo.houseno,
						zip: pudo.zip,
						city: pudo.city,
						countryCode: pudo.countryCode,
						maxweight: pudo.maxWeightInKg,
						cod:
							pudo.services && pudo.services.includes('cod')
								? true
								: false,
						card:
							pudo.services && pudo.services.includes('cardPayment')
								? true
								: false,
						isEligibleForAlzabox: isEligibleForAlzabox,
						isEligibleForSlovenskaPostaBox: isEligibleForSlovenskaPostaBox,
						isEligibleForZbox: isEligibleForZbox,
					});

					closePopup();
				})
				.catch(function (code) {
					console.error('DPD map widget attach failed', {
						code: code,
						baseCountryCode: baseCountryCode,
						allowedCountries: allowedCountries,
						requiredServices: requiredServices,
					});

					if (openLegacyPopupFallback(code)) {
						closePopup();
						return;
					}

					alert(
						ard_dpd_parcelshop_map_widget_settings.widget_error_message +
							(code ? ' (' + code + ')' : '')
					);
					closePopup();
				});
		}
	}

	function closePopup() {
		popup.classList.remove('active');

		if (dpdMapWidget && dpdMapWidget.close) {
			dpdMapWidget.close();
		}
	}

	function isParcelShopChosen() {
		var chosenParcelShopId = document.querySelector(
			chosenParcelShopHiddenParcelIdSelector
		).value;
		return chosenParcelShopId !== '';
	}

	function getCustomerZip() {
		var shipToDifferentAddress = document.querySelector(
			'input[name="ship_to_different_address"]'
		);

		if (shipToDifferentAddress && shipToDifferentAddress.checked) {
			var shippingPostcodeField = document.querySelector(
				'input[name="shipping_postcode"]'
			);

			if (shippingPostcodeField) {
				customerZip = shippingPostcodeField.value;
			}

			if (customerZip) {
				return customerZip;
			}
		}

		var billingPostcodeField = document.querySelector(
			'input[name="billing_postcode"]'
		);

		if (billingPostcodeField) {
			customerZip = billingPostcodeField.value;
		}

		if (customerZip) {
			return customerZip;
		}

		customerZip = dpdMapOpenWidgetMapPopupEl.getAttribute('data-customer-zip');

		if (customerZip) {
			return customerZip;
		}

		return '';
	}

	function docReady(fn) {
		if (
			document.readyState === 'complete' ||
			document.readyState === 'interactive'
		) {
			setTimeout(fn, 1);
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	docReady(function () {
		init();
	});

	return {
		openPopup: openPopup,
	};
})();
