/**
 * ocDownloader Admin Settings controller - pure vanilla ES6 (Nextcloud 28-35).
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

	function updateProtocolDetails() {
		var wdSelect = document.getElementById('OCDWhichDownloader');
		var detailsStrong = document.querySelector('#OCDWhichDownloaderDetails > strong');
		if (!wdSelect || !detailsStrong) {
			return;
		}
		var selected = wdSelect.options[wdSelect.selectedIndex];
		detailsStrong.textContent = selected ? (selected.getAttribute('data-protocols') || '') : '';
	}

	function initAdminSettings() {
		var form = document.getElementById('ocdownloader');
		if (!form) {
			return;
		}

		var wdSelect = document.getElementById('OCDWhichDownloader');
		var ariaBox = document.getElementById('OCDAriaSettings');
		var btBox = document.getElementById('OCDBTSettings');
		var allowBtSelect = document.getElementById('OCDAllowProtocolBT');
		var allowBtDetails = document.getElementById('OCDAllowProtocolBTDetails');

		function syncVisibility() {
			var isAria2 = wdSelect && wdSelect.value === 'ARIA2';
			var isBtAllowed = allowBtSelect && allowBtSelect.value === 'Y';
			if (ariaBox) {
				ariaBox.classList.toggle('ocd-hidden', !isAria2);
			}
			if (btBox) {
				btBox.classList.toggle('ocd-hidden', !(isAria2 && isBtAllowed));
			}
			if (allowBtSelect) {
				allowBtSelect.classList.toggle('ocd-hidden', !isAria2);
			}
			if (allowBtDetails) {
				allowBtDetails.classList.toggle('ocd-hidden', isAria2);
			}
			updateProtocolDetails();
		}

		if (wdSelect) {
			wdSelect.addEventListener('change', function () {
				if (wdSelect.value !== 'ARIA2' && allowBtSelect && allowBtSelect.value !== 'N') {
					allowBtSelect.value = 'N';
					allowBtSelect.dispatchEvent(new Event('change'));
				}
				syncVisibility();
			});
		}

		if (allowBtSelect) {
			allowBtSelect.addEventListener('change', syncVisibility);
		}

		form.querySelectorAll('input.ToUse, select.ToUse').forEach(function (field) {
			field.addEventListener('change', function () {
				var loaderId = field.getAttribute('data-loader') || '';
				var loaderEl = loaderId ? document.getElementById(loaderId) : null;
				var msgEl = loaderId ? document.getElementById(loaderId + 'Msg') : null;

				if (loaderEl) {
					loaderEl.style.display = 'inline-block';
				}
				if (msgEl) {
					msgEl.style.display = 'none';
					msgEl.classList.remove('error', 'success');
				}

				var key = (field.id || '').replace(/^OCD/, '');
				fetch(generateUrl('/apps/ocdownloader/adminsettings/save'), {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'Accept': 'application/json',
						'requesttoken': getRequestToken()
					},
					body: JSON.stringify({
						KEY: key,
						VAL: field.value
					})
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
						updateProtocolDetails();
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

		syncVisibility();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAdminSettings);
	} else {
		initAdminSettings();
	}
})();
