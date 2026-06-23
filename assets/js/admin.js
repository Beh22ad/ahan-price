jQuery(document).ready(function ($) {
    // Manual update button
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
                    console.log('Update started successfully.');
                } else {
                    console.error('Update failed.');
                }
            },
            error: function () {
                console.error('AJAX request failed.');
            },
            complete: function () {
                $btn.prop('disabled', false); // Re-enable the button
            },
        });
    });

    // Delete log button
    $('#ahan-delete-log').on('click', function () {
        if (!confirm('آیا از حذف فایل لاگ اطمینان دارید؟')) {
            return;
        }

        var $btn = $(this);
        var $message = $('#ahan-log-message');

        $btn.prop('disabled', true);

        $.ajax({
            url: ahan_price_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ahan_price_delete_log',
                nonce: ahan_price_admin.delete_log_nonce,
            },
            success: function (response) {
                if (response.success) {
                    $message.removeClass('notice-error').addClass('notice-success');
                    $message.html('<p>' + response.data + '</p>').show();
                    
                    // Reload page after 1 second to refresh log status
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    $message.removeClass('notice-success').addClass('notice-error');
                    $message.html('<p>' + response.data + '</p>').show();
                }
            },
            error: function () {
                $message.removeClass('notice-success').addClass('notice-error');
                $message.html('<p>خطا در حذف فایل لاگ</p>').show();
            },
            complete: function () {
                $btn.prop('disabled', false);
            },
        });
    });

    // Retry license check button
    $(document).on('click', '.ahan-retry-license-check', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('در حال بررسی...');
        
        $.ajax({
            url: ahan_price_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ahan_price_retry_license_check',
                nonce: ahan_price_admin.retry_nonce,
            },
            success: function() {
                location.reload();
            },
            error: function() {
                location.reload();
            }
        });
    });
});