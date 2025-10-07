jQuery(document).ready(function($) {
    
    // YouTube Data Import
    $('#fetch-youtube-data').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const videoUrl = $('#video_url').val();
        const statusDiv = $('#youtube-import-status');
        
        if (!videoUrl) {
            statusDiv.html('<div class="notice notice-error"><p>Please enter a video URL first.</p></div>');
            return;
        }
        
        button.prop('disabled', true).text('Importing...');
        statusDiv.html('<div class="notice notice-info"><p>Fetching data from YouTube...</p></div>');
        
        $.ajax({
            url: videoSEO.ajaxurl,
            type: 'POST',
            data: {
                action: 'fetch_youtube_data',
                nonce: videoSEO.nonce,
                video_url: videoUrl
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    
                    // Populate fields with YouTube data
                    if (!$('#title').val()) {
                        $('#title').val(data.title);
                    }
                    
                    if (!$('#content').val()) {
                        if (typeof wp !== 'undefined' && wp.editor) {
                            wp.editor.setContent('content', data.description);
                        } else {
                            $('#content').val(data.description);
                        }
                    }
                    
                    $('#video_thumbnail').val(data.thumbnail);
                    $('#video_duration').val(data.duration);
                    $('#video_upload_date').val(data.upload_date);
                    $('#youtube_id').val(data.youtube_id);
                    
                    // Auto-generate SEO fields
                    if (!$('#seo_title').val()) {
                        $('#seo_title').val(data.title.substring(0, 60));
                    }
                    
                    if (!$('#seo_description').val()) {
                        const desc = data.description.substring(0, 152) + '...';
                        $('#seo_description').val(desc);
                    }
                    
                    statusDiv.html('<div class="notice notice-success"><p><strong>Success!</strong> Video data imported from YouTube.</p></div>');
                } else {
                    statusDiv.html('<div class="notice notice-error"><p><strong>Error:</strong> ' + response.data + '</p></div>');
                }
            },
            error: function() {
                statusDiv.html('<div class="notice notice-error"><p>Failed to connect to YouTube API.</p></div>');
            },
            complete: function() {
                button.prop('disabled', false).text('Import from YouTube');
            }
        });
    });
    
    // Auto-extract YouTube ID when URL changes
    $('#video_url').on('change blur', function() {
        const url = $(this).val();
        const youtubePattern = /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/ ]{11})/i;
        const match = url.match(youtubePattern);
        
        if (match && match[1]) {
            $('#youtube_id').val(match[1]);
        }
    });
    
    // Generate SEO Title
    $('#generate-seo-title').on('click', function(e) {
        e.preventDefault();
        
        const title = $('#title').val();
        if (title) {
            const seoTitle = title + ' - Watch Now | Video Tutorial';
            $('#seo_title').val(seoTitle.substring(0, 60));
        } else {
            alert('Please enter a video title first.');
        }
    });
    
    // Generate SEO Description
    $('#generate-seo-description').on('click', function(e) {
        e.preventDefault();
        
        let content = '';
        
        // Try to get content from WordPress editor
        if (typeof wp !== 'undefined' && wp.editor) {
            content = wp.editor.getContent('content');
        } else {
            content = $('#content').val();
        }
        
        // Strip HTML tags
        const stripped = $('<div>').html(content).text();
        
        if (stripped) {
            const description = stripped.substring(0, 152) + '...';
            $('#seo_description').val(description);
        } else {
            const title = $('#title').val();
            if (title) {
                $('#seo_description').val('Watch ' + title + '. Learn more in this comprehensive video guide.');
            } else {
                alert('Please enter video content or title first.');
            }
        }
    });
    
    // Generate Blog Post
    $('#generate-blog-post').on('click', function(e) {
        e.preventDefault();
        
        const postId = $('#post_ID').val();
        
        if (!postId) {
            alert('Please save the video post first.');
            return;
        }
        
        const button = $(this);
        button.prop('disabled', true).text('Generating...');
        
        // Check the checkbox to trigger blog post creation on save
        $('#auto_blog_post').prop('checked', true);
        
        // Trigger save
        $('#publish, #save-post').trigger('click');
        
        setTimeout(function() {
            button.prop('disabled', false).text('Generate Now');
        }, 2000);
    });
    
    // Show character count for SEO fields
    function updateCharCount(input, counterId, maxLength) {
        const count = $(input).val().length;
        const color = count > maxLength ? 'red' : (count > maxLength * 0.9 ? 'orange' : 'green');
        
        let counter = $('#' + counterId);
        if (counter.length === 0) {
            counter = $('<span id="' + counterId + '" style="margin-left: 10px;"></span>');
            $(input).after(counter);
        }
        
        counter.html('<span style="color: ' + color + ';">' + count + ' / ' + maxLength + ' characters</span>');
    }
    
    $('#seo_title').on('input', function() {
        updateCharCount(this, 'seo-title-count', 60);
    });
    
    $('#seo_description').on('input', function() {
        updateCharCount(this, 'seo-description-count', 160);
    });
    
    // Initialize character counts
    if ($('#seo_title').length) {
        updateCharCount('#seo_title', 'seo-title-count', 60);
    }
    
    if ($('#seo_description').length) {
        updateCharCount('#seo-description-count', 'seo-description-count', 160);
    }
    
    // Video duration helper
    $('#video_duration').after('<button type="button" class="button" id="convert-duration" style="margin-left: 10px;">Convert from MM:SS</button>');
    
    $('#convert-duration').on('click', function(e) {
        e.preventDefault();
        
        const input = prompt('Enter duration in format MM:SS or HH:MM:SS (e.g., 4:13 or 1:04:13)');
        if (input) {
            const parts = input.split(':').map(p => parseInt(p));
            let seconds = 0;
            
            if (parts.length === 2) {
                // MM:SS
                seconds = (parts[0] * 60) + parts[1];
            } else if (parts.length === 3) {
                // HH:MM:SS
                seconds = (parts[0] * 3600) + (parts[1] * 60) + parts[2];
            }
            
            if (seconds > 0) {
                $('#video_duration').val(seconds);
            }
        }
    });
    
    // Thumbnail preview
    $('#video_thumbnail').on('change blur', function() {
        const url = $(this).val();
        let preview = $('#thumbnail-preview');
        
        if (preview.length === 0) {
            preview = $('<div id="thumbnail-preview" style="margin-top: 10px;"></div>');
            $(this).parent().append(preview);
        }
        
        if (url) {
            preview.html('<img src="' + url + '" style="max-width: 300px; height: auto; border: 1px solid #ddd; padding: 5px;">');
        } else {
            preview.empty();
        }
    });
    
    // Trigger initial thumbnail preview
    if ($('#video_thumbnail').val()) {
        $('#video_thumbnail').trigger('change');
    }
    
    // Video URL format detection
    $('#video_url').on('change blur', function() {
        const url = $(this).val();
        let message = '';
        
        if (url.includes('youtube.com') || url.includes('youtu.be')) {
            message = '✓ YouTube video detected';
        } else if (url.includes('vimeo.com')) {
            message = '✓ Vimeo video detected';
        } else if (url.match(/\.(mp4|webm|ogg)$/i)) {
            message = '✓ Direct video file detected';
        } else if (url) {
            message = '⚠ Unknown video format';
        }
        
        let formatMsg = $('#video-format-message');
        if (formatMsg.length === 0) {
            formatMsg = $('<p id="video-format-message" class="description" style="margin-top: 5px;"></p>');
            $(this).parent().append(formatMsg);
        }
        
        formatMsg.text(message);
    });
    
    // Trigger initial format detection
    if ($('#video_url').val()) {
        $('#video_url').trigger('change');
    }
    
    // Warn before leaving if there are unsaved changes
    let formChanged = false;
    
    $('#post').on('change', 'input, textarea, select', function() {
        formChanged = true;
    });
    
    $('#publish, #save-post').on('click', function() {
        formChanged = false;
    });
    
    $(window).on('beforeunload', function() {
        if (formChanged) {
            return 'You have unsaved changes. Are you sure you want to leave?';
        }
    });
});