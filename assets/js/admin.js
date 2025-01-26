jQuery(document).ready(function ($) {
    $('#ahan-price-manual-update').on('click', function () {
        var $btn = $(this);
        var $message = $('#ahan-price-update-message');

        $btn.prop('disabled', true); // Disable the button during the update

        $.ajax({
            url: ahan_price_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ahan_price_manual_update',
                nonce: ahan_price_admin.nonce,
            },
            success: function (response) {
                if (response.success) {
                    $message.show(); // Show the success message
                } else {
                    alert('ربات با موفقیت اجرا شد');
                }
            },
            error: function () {
                alert('ربات با موفقیت اجرا شد');
            },
            complete: function () {
               // $btn.prop('disabled', false); // Re-enable the button
            },
        });
    });
});