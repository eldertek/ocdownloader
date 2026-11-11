/**
 * ocDownloader Personal Settings controller - pure vanilla ES6 (Nextcloud 28-35).
 */
(function () {
	'use strict';

	function getRequestToken() {
		if (document.head && document.head.dataset && document.head.dataset.requesttoken) {
			return document.head.dataset.requesttoken;
		}
		if (window.OC && window.OC.requestToken) {
			return window.OC.requestToken;
		}
		return '';
	}

	function generateUrl(path) {
		if (window.OC && typeof window.OC.generateUrl === 'function') {
			return window.OC.generateUrl(path);
		}
		var scripts = document.querySelectorAll('script[src*="/apps/ocdownloader/"]');
		var root = '';
		if (scripts.length > 0) {
			var src = scripts[0].getAttribute('src') || '';
			var idx = src.indexOf('/apps/ocdownloader/');
			if (idx >= 0) {
				try {
					root = new URL(src.slice(0, idx), window.location.origin).pathname.replace(/\/$/, '');
				} catch (e) {
					root = src.slice(0, idx).replace(/\/$/, '');
				}
			}
		}
		var needsIndex = window.location.pathname.indexOf('/index.php/') !== -1;
		return root + (needsIndex ? '/index.php' : '') + path;
	}

	function initPersonalSettings() {
		var form = document.getElementById('ocdownloader');
		if (!form) {
			return;
		}

		var loaderEl = document.getElementById('OCDSLoader');
		var msgEl = document.getElementById('OCDSMsg');

		form.querySelectorAll('input[type="button"][data-rel]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var key = btn.getAttribute('data-rel') || '';
				var val = '';

				if (key === 'OCDBTSeedTimeToReach_OCDBTSeedTimeToReachUnit') {
					key = 'BTSeedTimeToReach_BTSeedTimeToReachUnit';
					var tEl = document.getElementById('OCDBTSeedTimeToReach');
					var uEl = document.getElementById('OCDBTSeedTimeToReachUnit');
					val = (tEl ? tEl.value : '1') + '_' + (uEl ? uEl.value : 'w');
				} else {
					var inputEl = document.getElementById(key);
					val = inputEl ? inputEl.value : '';
				}

				if (loaderEl) {
					loaderEl.style.display = 'inline-block';
				}
				if (msgEl) {
					msgEl.style.display = 'none';
					msgEl.classList.remove('error', 'success');
				}

				fetch(generateUrl('/apps/ocdownloader/personalsettings/save'), {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'Accept': 'application/json',
						'requesttoken': getRequestToken()
					},
					body: JSON.stringify({ KEY: key, VAL: val })
				})
					.then(function (resp) {
						return resp.json();
					})
					.then(function (data) {
						if (msgEl) {
							msgEl.textContent = (data && data.MESSAGE) || '';
							msgEl.classList.add(data && data.ERROR ? 'error' : 'success');
							msgEl.style.display = 'inline-block';
						}
					})
					.catch(function () {
						if (msgEl) {
							msgEl.textContent = 'Error saving setting';
							msgEl.classList.add('error');
							msgEl.style.display = 'inline-block';
						}
					})
					.finally(function () {
						if (loaderEl) {
							loaderEl.style.display = 'none';
						}
					});
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initPersonalSettings);
	} else {
		initPersonalSettings();
	}
})();
