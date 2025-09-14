$(document).ready(function() {

    // --- Profile Editing Modal ---
    $('.edit-profile-pic-btn, #editProfileBtn').on('click', function() {
        $('#profileEditModal').show();
    });

    $('.close').on('click', function() {
        $('#profileEditModal').hide();
    });

    $(window).on('click', function(e) {
        if ($(e.target).hasClass('modal')) {
            $('.modal').hide();
        }
    });

    // --- Profile Update Form ---
    $('#profileEditForm').on('submit', function(e) {
        e.preventDefault();
        let formData = new FormData(this);
        formData.append('action', 'update_profile');

        $.ajax({
            url: 'ajax_handler.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                if (response.success) {
                    alert('Profile updated successfully!');
                    location.reload(); // Refresh to show updated data
                } else {
                    alert('Error: ' + response.error);
                }
            },
            error: function() {
                alert('An unexpected error occurred.');
            }
        });
    });

    // --- Client-Side Validation ---
    $('#signupForm').on('submit', function(e) {
        let password = $('#password').val();
        let password_re = $('#password_re').val();
        if (password !== password_re) {
            alert("Passwords do not match!");
            e.preventDefault(); // Stop form submission
        }
    });

    // --- AJAX for Adding a Post ---
    $('#addPostForm').on('submit', function(e) {
        e.preventDefault();
        let formData = new FormData(this);
        formData.append('action', 'add_post');

        $.ajax({
            url: 'ajax_handler.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            success: function(response) {
                // Try to parse response if not already an object
                if (typeof response === 'string') {
                    try {
                        response = JSON.parse(response);
                    } catch (e) {
                        alert('Invalid server response.');
                        console.error('Invalid JSON:', response);
                        return;
                    }
                }
                if (response.success) {
                    // Prepend the new post to the feed
                    let post = response.post;
                    let postHtml = `
                        <div class="post-card" data-post-id="${post.id}">
                            <div class="post-header">
                                <img src="${post.profile_picture}" alt="User" class="post-author-pic">
                                <div>
                                    <strong>${post.full_name}</strong>
                                    <small>Posted on ${new Date(post.created_at).toLocaleDateString()}</small>
                                </div>
                                <button class="delete-post-btn">×</button>
                            </div>
                            <p class="post-description">${post.description}</p>
                            <img src="${post.image}" alt="Post Image" class="post-image">
                            <div class="post-actions">
                                <button class="like-btn">Like <span class="like-count">${post.likes}</span></button>
                                <button class="dislike-btn">Dislike <span class="dislike-count">${post.dislikes}</span></button>
                            </div>
                        </div>`;
                    $('#postsContainer').prepend(postHtml);
                    $('#addPostForm')[0].reset(); // Clear the form
                } else {
                    alert('Error: ' + response.error);
                    console.error('Post creation error:', response);
                }
            },
            error: function(xhr, status, error) {
                alert('An unexpected error occurred: ' + error);
                console.error('AJAX error:', xhr.responseText);
            }
        });
    });

    // --- AJAX for Deleting a Post ---
    $('#postsContainer').on('click', '.delete-post-btn', function() {
        if (!confirm('Are you sure you want to delete this post?')) {
            return;
        }
        let postCard = $(this).closest('.post-card');
        let postId = postCard.data('post-id');

        $.ajax({
            url: 'ajax_handler.php',
            type: 'POST',
            data: {
                action: 'delete_post',
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    postCard.fadeOut(500, function() {
                        $(this).remove();
                    });
                } else {
                    alert('Error: ' + response.error);
                }
            }
        });
    });

    // --- AJAX for Liking/Disliking a Post ---
    $('#postsContainer').on('click', '.like-btn, .dislike-btn', function() {
        let button = $(this);
        let postCard = button.closest('.post-card');
        let postId = postCard.data('post-id');
        let type = button.hasClass('like-btn') ? 'like' : 'dislike';
        let likeBtn = postCard.find('.like-btn');
        let dislikeBtn = postCard.find('.dislike-btn');

        // Prevent double click until AJAX completes
        if (button.prop('disabled')) return;
        button.prop('disabled', true);
        likeBtn.prop('disabled', true);
        dislikeBtn.prop('disabled', true);

        $.ajax({
            url: 'ajax_handler.php',
            type: 'POST',
            data: {
                action: 'like_dislike',
                post_id: postId,
                type: type
            },
            success: function(response) {
                if (response.success) {
                    // Update counts
                    postCard.find('.like-count').text(response.likes);
                    postCard.find('.dislike-count').text(response.dislikes);
                    // Toggle button states
                    if (button.hasClass('active')) {
                        button.removeClass('active');
                    } else {
                        button.addClass('active');
                        if (type === 'like') {
                            dislikeBtn.removeClass('active');
                        } else {
                            likeBtn.removeClass('active');
                        }
                    }
                } else {
                    alert('Error: ' + response.error);
                }
            },
            complete: function() {
                likeBtn.prop('disabled', false);
                dislikeBtn.prop('disabled', false);
            }
        });
    });

});
