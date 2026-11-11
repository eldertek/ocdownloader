/**
 * Vanilla DOM badge helper compatible with Nextcloud 28-35 (no jQuery dependency).
 */
(function () {
	'use strict';

	window.OCDLR = window.OCDLR || {};

	window.OCDLR.setBadge = function (target, badgeValue) {
		var el = typeof target === 'string' ? document.querySelector(target) : target;
		if (!el) {
			return;
		}

		var outer = el.querySelector('.badger-outter');
		if (badgeValue === null || badgeValue === undefined || badgeValue === '') {
			if (outer) {
				outer.remove();
			}
			return;
		}

		var badgeStr = String(badgeValue);
		var badgeEl = el.querySelector('.badger-badge');
		var oldText = badgeEl ? badgeEl.textContent : '0';

		if (badgeStr.charAt(0) === '+') {
			var inc = Number(badgeStr.slice(1));
			badgeStr = isNaN(inc) ? (oldText + badgeStr.slice(1)) : String(Math.max(0, Math.round((Number(oldText) || 0) + inc)));
		} else if (badgeStr.charAt(0) === '-') {
			var dec = Number(badgeStr.slice(1));
			badgeStr = isNaN(dec) ? oldText : String(Math.max(0, Math.round((Number(oldText) || 0) - dec)));
		}

		if (!outer) {
			outer = document.createElement('div');
			outer.className = 'badger-outter';
			var inner = document.createElement('div');
			inner.className = 'badger-inner';
			badgeEl = document.createElement('p');
			badgeEl.className = 'badger-badge badger-number';
			inner.appendChild(badgeEl);
			outer.appendChild(inner);
			el.appendChild(outer);
		}

		badgeEl.textContent = badgeStr;
		if (isNaN(Number(badgeStr))) {
			badgeEl.classList.remove('badger-number');
			badgeEl.classList.add('badger-text');
		} else {
			badgeEl.classList.remove('badger-text');
			badgeEl.classList.add('badger-number');
		}
	};
})();
