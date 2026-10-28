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
	var ttlSelect  = document.getElementById('rgbmind_magic_link_ttl');
	var customWrap = document.getElementById('rgbmind_magic_custom_wrap');
	var customVal  = document.getElementById('rgbmind_magic_custom_val');
	var customUnit = document.getElementById('rgbmind_magic_custom_unit');
	var expNotice  = document.getElementById('rgbmind-magic-link-expiration-notice');

	if (ttlSelect && customWrap) {
		ttlSelect.addEventListener('change', function() {
			if (ttlSelect.value === 'custom') {
				customWrap.style.display = 'inline-flex';
			} else {
				customWrap.style.display = 'none';
			}
		});
	}

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

			if (ttlSelect) {
				var ttlVal = ttlSelect.value;
				if (ttlVal === 'custom' && customVal && customUnit) {
					var num = parseInt(customVal.value, 10) || 1;
					var unit = customUnit.value;
					var mult = 3600;
					if (unit === 'minutes') {
						mult = 60;
					} else if (unit === 'days') {
						mult = 86400;
					}
					data.append('ttl', num * mult);
				} else {
					data.append('ttl', ttlVal);
				}
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
					var badge = document.getElementById('rgbmind-magic-expires-badge');
					if (badge && response.data.expires_at) {
						badge.textContent = 'Expires: ' + response.data.expires_at;
						badge.style.display = 'inline-block';
					}
					var expNotice = document.getElementById('rgbmind-magic-link-expiration-notice');
					if (expNotice && response.data.expires_at) {
						var expiresPrefix = (window.rgbmindEphData && window.rgbmindEphData.linkExpiresPrefix) ? window.rgbmindEphData.linkExpiresPrefix : 'Active temporary link generated (expires on ';
						expNotice.textContent = expiresPrefix + response.data.expires_at + ').';
						expNotice.style.display = 'block';
					}
					var btnLabel = document.getElementById('rgbmind-gen-btn-label');
					if (btnLabel) {
						btnLabel.textContent = (window.rgbmindEphData && window.rgbmindEphData.regenerateText) ? window.rgbmindEphData.regenerateText : 'Regenerate Link';
					}
					if (genBtn) {
						genBtn.classList.remove('button-primary');
						genBtn.classList.add('button-secondary');
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

	// 3. Live Countdown Timers for Magic Link and User Account Expiration
	var countdownElements = document.querySelectorAll('.rgbmind-live-countdown');
	if (countdownElements.length > 0) {
		function updateCountdowns() {
			var currentEpoch = Math.floor(Date.now() / 1000);
			countdownElements.forEach(function(el) {
				var targetEpoch = parseInt(el.getAttribute('data-timestamp'), 10);
				if (!targetEpoch || isNaN(targetEpoch)) return;
				var remaining = targetEpoch - currentEpoch;

				if (remaining <= 0) {
					el.textContent = 'Expired';
					el.style.color = '#d63638';
					el.style.background = '#fcf0f1';
					el.style.borderColor = '#f5c2c7';
					return;
				}

				var days = Math.floor(remaining / 86400);
				var hours = Math.floor((remaining % 86400) / 3600);
				var minutes = Math.floor((remaining % 3600) / 60);
				var seconds = remaining % 60;

				var str = '';
				if (days > 0) {
					str = days + 'd ' + (hours < 10 ? '0' : '') + hours + 'h ' + (minutes < 10 ? '0' : '') + minutes + 'm';
				} else if (hours > 0) {
					str = (hours < 10 ? '0' : '') + hours + 'h ' + (minutes < 10 ? '0' : '') + minutes + 'm ' + (seconds < 10 ? '0' : '') + seconds + 's';
				} else {
					str = (minutes < 10 ? '0' : '') + minutes + 'm ' + (seconds < 10 ? '0' : '') + seconds + 's';
				}

				el.textContent = str;
			});
		}

		updateCountdowns();
		setInterval(updateCountdowns, 1000);
	}
});
