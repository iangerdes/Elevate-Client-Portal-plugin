// File: elevate-client-portal/assets/js/admin/uploader.js
/**
 * Handles all logic for the file uploader component, including drag-and-drop,
 * progress bars, and queue management.
 *
 * @package Elevate_Client_Portal
 * @version 1.0.0
 */

jQuery(function ($) {
    const mainContentArea = $('#ecp-dashboard-main-content');
    let fileQueue = [];
    let isUploading = false;

    // This script assumes the global ECP_Admin object has been loaded by dashboard.js

    function setupUploaderEvents(context) {
        const uploaderForms = $(context).find('.ecp-upload-form');
        
        // Unbind previous events to prevent duplicates
        uploaderForms.off('.uploader');
        mainContentArea.off('.uploaderDropzone');

        // Handle file selection via the input field
        uploaderForms.on('change.uploader', '.ecp-file-upload-input', function(e) {
            const form = $(this).closest('form');
            form.find('#ecp-upload-progress-container').show();
            $.each(e.target.files, (i, file) => {
                file.queueId = new Date().getTime() + i;
                addToQueue(file, form);
            });
            if (!isUploading) {
                processQueue(form);
            }
        });

        // Toggle encryption password field
        uploaderForms.on('change.uploader', '.ecp-encrypt-toggle', function() {
            $(this).closest('.ecp-encryption-section').find('.ecp-password-fields').slideToggle($(this).is(':checked'));
        });
        
        // --- Drag and Drop Handlers ---
        mainContentArea.on('dragover.uploaderDropzone', '.ecp-dropzone-area', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
        });

        mainContentArea.on('dragleave.uploaderDropzone', '.ecp-dropzone-area', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
        });

        mainContentArea.on('drop.uploaderDropzone', '.ecp-dropzone-area', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
            const form = $(this).closest('form');
            form.find('#ecp-upload-progress-container').show();
            const files = e.originalEvent.dataTransfer.files;
            $.each(files, (i, file) => {
                file.queueId = new Date().getTime() + i;
                addToQueue(file, form);
            });
            if (!isUploading) {
                processQueue(form);
            }
        });
        
        // Allow clicking the dropzone to trigger the file input
        mainContentArea.on('click.uploaderDropzone', '.ecp-dropzone-area', function() {
            $(this).closest('form').find('.ecp-file-upload-input').trigger('click');
        });
    }

    function addToQueue(file, form) {
        fileQueue.push(file);
        const progressContainer = form.find('#ecp-upload-progress-container');
        // Add a new progress bar item for the file
        progressContainer.append(`
            <div class="ecp-upload-item" id="file-${file.queueId}">
                <div class="ecp-upload-filename">${file.name}</div>
                <div class="ecp-progress-bar-outer">
                    <div class="ecp-progress-bar-inner" style="width: 0%;"></div>
                </div>
                 <div class="ecp-upload-status">Waiting...</div>
            </div>
        `);
    }

    function processQueue(form) {
        if (isUploading) return;
        
        // If queue is empty, finish up.
        if (fileQueue.length === 0) {
            // Wait a moment before refreshing to let the user see the "Complete!" status.
            setTimeout(() => {
                form.find('#ecp-upload-progress-container').fadeOut(500, function() { $(this).html('').show(); });
                // Use the global helper to refresh the file list
                ECP_Admin.refreshFileManager(form.find('input[name="user_id"]').val());
            }, 1500);
            return;
        }

        isUploading = true;
        const file = fileQueue.shift();
        const progressItem = $(`#file-${file.queueId}`);
        progressItem.find('.ecp-upload-status').text('Uploading...');
        
        // Create form data from the main form to include all fields (folder, encryption, etc.)
        const formData = new FormData(form[0]);
        formData.append('ecp_file_upload', file);
        formData.append('original_filename', file.name);
        // Ensure the correct nonce is included for the AJAX request
        formData.set('nonce', ecp_ajax.nonces.fileManagerNonce);

        $.ajax({
            url: ecp_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                // Add a progress event listener to the XHR object
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = (evt.loaded / evt.total) * 100;
                        progressItem.find('.ecp-progress-bar-inner').css('width', percentComplete + '%');
                    }
                }, false);
                return xhr;
            }
        }).done(response => {
            const statusIndicator = progressItem.find('.ecp-upload-status');
            if (response.success) {
                statusIndicator.text('Complete!').addClass('success');
            } else {
                statusIndicator.text(response.data.message || 'Failed.').addClass('error');
            }
        }).fail(() => {
            progressItem.find('.ecp-upload-status').text('Server Error.').addClass('error');
        }).always(() => {
            isUploading = false;
            // Process the next file in the queue
            processQueue(form);
        });
    }

    // Use a MutationObserver to detect when a file manager view is loaded into the DOM.
    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            if (mutation.addedNodes.length) {
                // If the added content contains a file manager, set up the uploader events.
                if ($(mutation.target).find('.ecp-file-manager').length > 0) {
                    setupUploaderEvents(mutation.target);
                    break;
                }
            }
        }
    });

    if (mainContentArea.length) {
         observer.observe(mainContentArea[0], { childList: true, subtree: true });
    }
    
    // Initial setup for any uploader that might be present on page load.
    setupUploaderEvents(mainContentArea);
});

