jQuery(function ($) {
    const config = window.ardDpdAdminWorkflow || {};

    function markLabelPrinted(orderId) {
        return $.post(config.ajaxUrl || window.ajaxurl, {
            action: 'ard_dpd_mark_label_printed',
            nonce: config.nonce || '',
            orderId: orderId
        });
    }

    $(document).on('click', '.ard-dpd-download-label', function (event) {
        const $link = $(this);
        const labelUrl = $link.attr('href');
        const orderId = parseInt($link.data('orderId'), 10);

        if (!labelUrl || !orderId) {
            return;
        }

        event.preventDefault();
        window.open(labelUrl, '_blank', 'noopener');

        if (!window.confirm(config.confirmText || '')) {
            return;
        }

        markLabelPrinted(orderId)
            .done(function (response) {
                if (response && response.success) {
                    window.alert((response.data && response.data.message) || config.successText || '');
                    window.location.reload();
                    return;
                }

                window.alert((response && response.data && response.data.message) || config.errorText || '');
            })
            .fail(function () {
                window.alert(config.errorText || '');
            });
    });
});
