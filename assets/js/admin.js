jQuery(document).ready(function ($) {
    var updateInterval = null;
    var isUpdating = false;

    // Manual update button - start batch process
    $('#ahan-price-manual-update').on('click', function () {
        if (isUpdating) {
            return;
        }

        var $btn = $(this);
        var $message = $('#ahan-price-update-message');

        $btn.prop('disabled', true).text('در حال اجرا...');
        $message.hide();

        $.ajax({
            url: ahan_price_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ahan_price_manual_update',
                nonce: ahan_price_admin.nonce,
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    isUpdating = true;
                    showProgressBar();
                    $message.html('ربات در حال بروزرسانی قیمت‌ها است...').show();
                    // Start processing after the progress bar is shown
                    setTimeout(function() {
                        startBatchProcessing();
                    }, 500);
                } else {
                    $message.html('خطا: ' + (response.data || 'Unknown error')).show();
                    $btn.prop('disabled', false).text('اجرای ربات');
                }
            },
            error: function (xhr, status, error) {
                console.error('AJAX Error:', status, error);
                $message.html('خطا در ارتباط با سرور: ' + error).show();
                $btn.prop('disabled', false).text('اجرای ربات');
            }
        });
    });

    // Cancel update button
    $(document).on('click', '#ahan-cancel-update', function () {
        if (!confirm('آیا از لغو بروزرسانی اطمینان دارید؟')) {
            return;
        }

        $.ajax({
            url: ahan_price_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ahan_price_cancel_update',
                nonce: ahan_price_admin.batch_nonce,
            },
            success: function () {
                stopProgressTracking();
                $('#ahan-price-update-message').html('بروزرسانی لغو شد').show();
                $('#ahan-price-manual-update').prop('disabled', false).text('اجرای ربات');
                $('#ahan-progress-container').remove();
                isUpdating = false;
            }
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

    function showProgressBar() {
        if ($('#ahan-progress-container').length > 0) {
            return;
        }

        var progressHtml = `
            <div id="ahan-progress-container" style="margin-top: 20px; padding: 15px; background: #f0f8ff; border: 2px solid #2271b1; border-radius: 8px; display: block;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <span style="font-weight: bold; font-size: 15px; color: #1a1a1a;">پیشرفت بروزرسانی:</span>
                    <span id="ahan-progress-text" style="font-weight: bold; font-size: 16px; color: #2271b1;">۰%</span>
                </div>
                <div style="width: 100%; height: 28px; background: #e8e8e8; border-radius: 14px; overflow: hidden; position: relative; border: 1px solid #ccc;">
                    <div id="ahan-progress-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #4CAF50, #45a049); transition: width 0.5s ease; border-radius: 14px;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 10px; font-size: 13px; color: #555;">
                    <span id="ahan-progress-detail">در حال آماده‌سازی...</span>
                    <span id="ahan-progress-count" style="font-weight: bold;">۰ از ۰</span>
                </div>
                <div style="margin-top: 12px; text-align: right;">
                    <button id="ahan-cancel-update" class="button button-secondary" style="color: #d63638; border-color: #d63638;">لغو بروزرسانی</button>
                </div>
            </div>
        `;

        // Insert after the message
        $('#ahan-price-update-message').after(progressHtml);
    }

    function startBatchProcessing() {
        processBatch();
        // Start polling for progress updates
        if (updateInterval) {
            clearInterval(updateInterval);
        }
        updateInterval = setInterval(getProgress, 1500);
    }

    function processBatch() {
        $.ajax({
            url: ahan_price_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ahan_price_update_batch',
                nonce: ahan_price_admin.batch_nonce,
            },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
                    var data = response.data;
                    updateProgressUI(data);

                    if (data.completed) {
                        completeUpdate(data);
                    } else {
                        // Continue processing next batch
                        setTimeout(processBatch, 1500);
                    }
                } else {
                    handleError(response);
                }
            },
            error: function (xhr, status, error) {
                console.error('Batch error:', status, error);
                handleError();
            }
        });
    }

    function getProgress() {
        $.ajax({
            url: ahan_price_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'ahan_price_get_progress',
                nonce: ahan_price_admin.batch_nonce,
            },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
                    updateProgressUI(response.data);
                }
            },
            error: function() {
                // Silent fail for progress polling
            }
        });
    }

    function updateProgressUI(data) {
        var percentage = data.percentage || 0;
        var total = data.total || 0;
        var processed = data.processed || 0;
        var successCount = data.success_count || 0;
        var errorCount = data.error_count || 0;

        // Update progress bar
        $('#ahan-progress-bar').css('width', percentage + '%');
        $('#ahan-progress-text').text(percentage + '%');
        $('#ahan-progress-count').text(processed + ' از ' + total);

        // Show Persian formatted message with RTL
        var progressDetail = '';
        if (total > 0 && processed > 0) {
            // Convert numbers to Persian
            var persianProcessed = processed.toString().replace(/\d/g, function(d) {
                return ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'][d];
            });
            var persianTotal = total.toString().replace(/\d/g, function(d) {
                return ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'][d];
            });
            progressDetail = persianProcessed + ' محصول از ' + persianTotal + ' بروز رسانی شد';
        } else if (total > 0 && processed === 0) {
            progressDetail = 'در حال شروع...';
        } else {
            progressDetail = 'آماده شروع';
        }
        
        // Apply RTL and proper styling
        $('#ahan-progress-detail')
            .text(progressDetail)
            .css({
                'direction': 'rtl',
                'display': 'block',
                'unicode-bidi': 'embed'
            });
    }
    function completeUpdate(data) {
        stopProgressTracking();
        isUpdating = false;

        var total = data.total || 0;
        var successCount = data.success_count || 0;
        var errorCount = data.error_count || 0;
        
        var message = '✅ بروزرسانی با موفقیت انجام شد!';
        var detail = ' (مجموع: ' + total + '، موفق: ' + successCount + '، ناموفق: ' + errorCount + ')';

        $('#ahan-price-update-message')
            .html(message + ' ' + detail)
            .show()
            .css('color', 'green')
            .css('font-weight', 'bold')
            .css('font-size', '14px');

        $('#ahan-price-manual-update')
            .prop('disabled', false)
            .text('اجرای ربات');

        // Keep progress bar visible then remove
        setTimeout(function() {
            $('#ahan-progress-container').fadeOut(500, function() {
                $(this).remove();
            });
        }, 5000);
    }

    function handleError(response) {
        stopProgressTracking();
        isUpdating = false;

        var errorMsg = 'خطا در بروزرسانی محصولات';
        if (response && response.data) {
            errorMsg = response.data;
        }

        $('#ahan-price-update-message')
            .html('❌ ' + errorMsg)
            .show()
            .css('color', 'red')
            .css('font-weight', 'bold');

        $('#ahan-price-manual-update')
            .prop('disabled', false)
            .text('اجرای ربات');

        setTimeout(function() {
            $('#ahan-progress-container').fadeOut(500, function() {
                $(this).remove();
            });
        }, 4000);
    }

    function stopProgressTracking() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
    }

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
            }
        });
    });
});