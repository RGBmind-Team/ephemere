/**
 * RGBmind Ephemere - Administrative JavaScript
 *
 * Handles One-Click Magic Link AJAX generation, clipboard copying,
 * and welcome guide dismissal without inline scripts.
 *
 * @package RGBmind_Ephemere
 */

document.addEventListener('DOMContentLoaded', function() {
	'use strict';

	// 1. One-Click Magic Link: Generation & Copy (User Profile)
	var genBtn     = document.getElementById('rgbmind-ajax-gen-magic-btn');
	var copyBtn    = document.getElementById('rgbmind-copy-magic-btn');
	var resultBox  = document.getElementById('rgbmind-magic-link-result');
	var inputField = document.getElementById('rgbmind_magic_url_field');
	var nonceField = document.getElementById('rgbmind_eph_nonce');

	if (genBtn) {
		genBtn.addEventListener('click', function() {
			var originalText   = genBtn.textContent;
			var generatingText = (window.rgbmindEphData && window.rgbmindEphData.generatingText) ? window.rgbmindEphData.generatingText : 'Generating...';
			genBtn.textContent = generatingText;
			genBtn.disabled    = true;

			var data = new URLSearchParams();
			data.append('action', 'rgbmind_eph_generate_magic_link');
			data.append('user_id', genBtn.getAttribute('data-userid'));
			if (nonceField) {
				data.append('nonce', nonceField.value);
			}

			var ajaxEndpoint = (window.rgbmindEphData && window.rgbmindEphData.ajaxUrl) ? window.rgbmindEphData.ajaxUrl : (window.ajaxurl || '/wp-admin/admin-ajax.php');

			fetch(ajaxEndpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: data.toString()
			})
			.then(function(res) { return res.json(); })
			.then(function(response) {
				genBtn.textContent = originalText;
				genBtn.disabled    = false;
				if (response.success && response.data.url) {
					if (inputField) {
						inputField.value = response.data.url;
						inputField.select();
					}
					if (resultBox) {
						resultBox.style.display = 'block';
					}
				} else {
					var errMsg = (response.data && response.data.message) ? response.data.message : 'Error generating magic link.';
					alert(errMsg);
				}
			})
			.catch(function() {
				genBtn.textContent = originalText;
				genBtn.disabled    = false;
				alert('Connection error while generating magic link.');
			});
		});
	}

	if (copyBtn && inputField) {
		copyBtn.addEventListener('click', function() {
			inputField.select();
			navigator.clipboard.writeText(inputField.value).then(function() {
				var copiedText = (window.rgbmindEphData && window.rgbmindEphData.copiedText) ? window.rgbmindEphData.copiedText : 'Copied!';
				var copyText   = (window.rgbmindEphData && window.rgbmindEphData.copyText) ? window.rgbmindEphData.copyText : 'Copy';
				copyBtn.textContent = copiedText;
				setTimeout(function() {
					copyBtn.textContent = copyText;
				}, 2000);
			});
		});
	}

	// 2. Interactive Welcome Guide Dismissal
	var guideContainer = document.getElementById('rgbmind-ephemere-welcome-guide');
	if (guideContainer) {
		var dismissNotice = function() {
			var dismissNonce = (window.rgbmindEphData && window.rgbmindEphData.dismissNonce) ? window.rgbmindEphData.dismissNonce : (guideContainer.getAttribute('data-nonce') || '');
			var ajaxEndpoint = (window.rgbmindEphData && window.rgbmindEphData.ajaxUrl) ? window.rgbmindEphData.ajaxUrl : (window.ajaxurl || '/wp-admin/admin-ajax.php');

			var data = new URLSearchParams();
			data.append('action', 'rgbmind_eph_dismiss_welcome');
			data.append('nonce', dismissNonce);

			fetch(ajaxEndpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: data.toString()
			});
		};

		var dismissBtn = document.getElementById('rgbmind-dismiss-guide-btn');
		if (dismissBtn) {
			dismissBtn.addEventListener('click', function() {
				dismissNotice();
			});
		}

		guideContainer.addEventListener('click', function(e) {
			if (e.target && e.target.classList.contains('notice-dismiss')) {
				dismissNotice();
			}
		});
	}
});
