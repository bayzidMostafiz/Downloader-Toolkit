/**
 * WP Downloader Toolkit Admin Script
 */

jQuery(document).ready(function ($) {
	'use strict';

	// Select All Checkbox for File Manager Explorer
	$('#wpdt-select-all-fm, #wpdt-cb-select-all').on('change', function () {
		var isChecked = $(this).is(':checked');
		$('.wpdt-fm-cb, #wpdt-select-all-fm, #wpdt-cb-select-all').prop('checked', isChecked);
	});

	// Select All Checkbox for Media Tab
	$('#wpdt-select-all-media').on('change', function () {
		var isChecked = $(this).is(':checked');
		$('.wpdt-media-cb').prop('checked', isChecked);
	});

	// Support Tab Key Indentation in Code Editor
	$('#wpdt_file_content').on('keydown', function (e) {
		if (e.key === 'Tab') {
			e.preventDefault();
			var start = this.selectionStart;
			var end = this.selectionEnd;

			$(this).val($(this).val().substring(0, start) + "\t" + $(this).val().substring(end));
			this.selectionStart = this.selectionEnd = start + 1;
		}
	});
});
