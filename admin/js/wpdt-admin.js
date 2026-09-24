/**
 * WP Downloader Toolkit Admin Script
 */

jQuery(document).ready(function ($) {
	'use strict';

	// Select All Checkbox for Media Tab
	$('#wpdt-select-all-media').on('change', function () {
		var isChecked = $(this).is(':checked');
		$('.wpdt-media-cb').prop('checked', isChecked);
	});

	// Relocate Opt-In Modal to <body> to ensure full-screen floating overlay
	if ($('#wpdt-optin-modal').length) {
		$('body').append($('#wpdt-optin-modal'));
	}

	var isSending = false;

	// Opt-In Modal Submission Handler
	function handleOptinSubmit(e) {
		if (e) {
			e.preventDefault();
			e.stopPropagation();
		}

		if (isSending) {
			return false;
		}
		isSending = true;

		var $btn = $('#wpdt-submit-btn');
		$btn.prop('disabled', true).text('Submitting...');

		var data = {
			action: 'wpdt_submit_optin',
			nonce: wpdt_data.nonce,
			name: $('#wpdt_user_name').val(),
			email: $('#wpdt_user_email').val(),
			phone: $('#wpdt_user_phone').val()
		};

		$.post(wpdt_data.ajax_url, data, function (response) {
			$('#wpdt-optin-modal').fadeOut(300, function () {
				$(this).remove();
			});
		}).fail(function () {
			$('#wpdt-optin-modal').fadeOut(300, function () {
				$(this).remove();
			});
		});

		return false;
	}

	$('#wpdt-submit-btn').off('click').on('click', handleOptinSubmit);

	// Opt-In Modal Dismissal (Skip)
	$('#wpdt-skip-btn').off('click').on('click', function (e) {
		if (e) {
			e.preventDefault();
			e.stopPropagation();
		}

		if (isSending) {
			return false;
		}
		isSending = true;

		var $modal = $('#wpdt-optin-modal');
		$modal.fadeOut(300, function () {
			$(this).remove();
		});

		$.post(wpdt_data.ajax_url, {
			action: 'wpdt_dismiss_optin',
			nonce: wpdt_data.nonce
		});

		return false;
	});
});
