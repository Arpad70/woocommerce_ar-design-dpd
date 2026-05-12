(function () {
  var notificationField = document.querySelector('.js-ar-design-dpd-notification-field');

  if (!notificationField) {
    return;
  }

  var notificationFieldParent = parents(notificationField, 'tr, .js-ar-design-dpd-notification-field-row');
  notificationFieldParent = notificationFieldParent[0] !== undefined ? notificationFieldParent[0] : null;

  if (!notificationFieldParent) {
    return;
  }

  var shippingTypeSelectField = document.querySelector('.js-ar-design-dpd-shipping-type-field');
  var originalDisplayValue = notificationFieldParent.style.display;

  toggleNotificationField();

  if (shippingTypeSelectField) {
    shippingTypeSelectField.addEventListener('change', function () {
      toggleNotificationField(true);
    });
  }

  function toggleNotificationField(reset) {
    if (getSelectedOptionNotificationSetting()) {
      hideNotificationField();
    } else {
      showNotificationField(reset);
    }
  }

  function showNotificationField(reset) {
    if (reset) {
      notificationField.checked = false;
    }

    notificationFieldParent.style.display = originalDisplayValue;
  }

  function hideNotificationField() {
    notificationField.checked = true;
    notificationFieldParent.style.display = 'none';
  }

  function getSelectedOptionNotificationSetting() {
    if (!shippingTypeSelectField) {
      return false;
    }

    var selected = shippingTypeSelectField.options[shippingTypeSelectField.selectedIndex].getAttribute('data-notification-required');

    return selected === 'true';
  }

  function parents(el, selector) {
    var parents = [];

    while ((el = el.parentNode) && el !== document) {
      if (!selector || el.matches(selector)) {
        parents.unshift(el);
      }
    }

    return parents;
  }
})();