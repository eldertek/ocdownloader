(function () {
	'use strict';
	function init() {
		if (window.OCDLR && window.OCDLR.Utils) {
			window.OCDLR.Utils.startView('waitings');
		}
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
