/**
 * ocDownloader Add page controller - pure vanilla ES6 (Nextcloud 28-35).
 */
(function () {
	'use strict';

	function initAddPage() {
		var Utils = window.OCDLR && window.OCDLR.Utils;
		if (!Utils) {
			return;
		}

		Utils.startView('add');

		// Switch protocol tabs
		document.querySelectorAll('#NewDL > div[data-rel]').forEach(function (tabBtn) {
			tabBtn.addEventListener('click', function () {
				var targetRel = tabBtn.getAttribute('data-rel');
				document.querySelectorAll('#app-content-wrapper .content-page').forEach(function (page) {
					if (page.getAttribute('rel') === targetRel) {
						page.classList.remove('ocd-hidden');
					} else {
						page.classList.add('ocd-hidden');
					}
				});
			});
		});

		// Torrents dropdown
		var torrentsListBtn = document.getElementById('TorrentsList');
		if (torrentsListBtn) {
			torrentsListBtn.addEventListener('click', function () {
				Utils.GetTorrentsList(torrentsListBtn.querySelector('ul'));
			});
		}

		// Launch HTTP / Magnet download
		var httpLaunch = document.querySelector('#app-content-wrapper .content-page[rel="OCDHTTP"] div.launch');
		if (httpLaunch) {
			httpLaunch.addEventListener('click', function () {
				var page = httpLaunch.closest('.content-page');
				var inputUrl = page ? page.querySelector('input.url') : null;
				if (!inputUrl) {
					return;
				}
				var raw = inputUrl.value.trim().replace(/\s+/g, '%20');
				if (Utils.ValidURL(raw)) {
					Utils.AddDownload(httpLaunch, 'http', raw, {
						HTTPUser: (document.getElementById('option-http-user') || {}).value || '',
						HTTPPasswd: (document.getElementById('option-http-pwd') || {}).value || '',
						HTTPReferer: (document.getElementById('option-http-referer') || {}).value || '',
						HTTPUseragent: (document.getElementById('option-http-useragent') || {}).value || '',
						HTTPOutfilename: (document.getElementById('option-http-outfilename') || {}).value || ''
					});
					inputUrl.value = '';
					['option-http-user', 'option-http-pwd', 'option-http-referer', 'option-http-useragent', 'option-http-outfilename'].forEach(function (id) {
						var el = document.getElementById(id);
						if (el) {
							el.value = '';
						}
					});
				} else {
					Utils.PrintError(Utils.tr('Invalid URL. Please check the address of the file.'));
				}
			});
		}

		// Launch FTP download
		var ftpLaunch = document.querySelector('#app-content-wrapper .content-page[rel="OCDFTP"] div.launch');
		if (ftpLaunch) {
			ftpLaunch.addEventListener('click', function () {
				var page = ftpLaunch.closest('.content-page');
				var inputUrl = page ? page.querySelector('input.url') : null;
				if (!inputUrl) {
					return;
				}
				var raw = inputUrl.value.trim();
				if (Utils.ValidURL(raw)) {
					var pasvEl = document.getElementById('option-ftp-pasv');
					Utils.AddDownload(ftpLaunch, 'ftp', raw, {
						FTPUser: (document.getElementById('option-ftp-user') || {}).value || '',
						FTPPasswd: (document.getElementById('option-ftp-pwd') || {}).value || '',
						FTPPasv: pasvEl ? Boolean(pasvEl.checked) : true,
						FTPReferer: (document.getElementById('option-ftp-referer') || {}).value || '',
						FTPUseragent: (document.getElementById('option-ftp-useragent') || {}).value || '',
						FTPOutfilename: (document.getElementById('option-ftp-outfilename') || {}).value || ''
					});
					inputUrl.value = '';
				} else {
					Utils.PrintError(Utils.tr('Invalid URL. Please check the address of the file.'));
				}
			});
		}

		// Launch yt-dlp video download
		var ytLaunch = document.querySelector('#app-content-wrapper .content-page[rel="OCDYT"] div.launch');
		if (ytLaunch) {
			ytLaunch.addEventListener('click', function () {
				var page = ytLaunch.closest('.content-page');
				var inputUrl = page ? page.querySelector('input.url') : null;
				if (!inputUrl) {
					return;
				}
				var raw = inputUrl.value.trim();
				if (Utils.ValidURL(raw)) {
					var audioEl = document.getElementById('option-yt-extractaudio');
					var ipv4El = document.getElementById('option-yt-forceipv4');
					Utils.AddDownload(ytLaunch, 'yt', raw, {
						YTExtractAudio: audioEl ? Boolean(audioEl.checked) : false,
						YTForceIPv4: ipv4El ? Boolean(ipv4El.checked) : true
					});
					inputUrl.value = '';
				} else {
					Utils.PrintError(Utils.tr('Invalid URL. Please check the address of the file.'));
				}
			});
		}

		// Launch BitTorrent download
		var btLaunch = document.querySelector('#app-content-wrapper .content-page[rel="OCDBT"] div.launch');
		if (btLaunch) {
			btLaunch.addEventListener('click', function () {
				var anchor = document.querySelector('#TorrentsList > a');
				var selectedFile = anchor ? (anchor.getAttribute('data-filename') || '') : '';
				if (selectedFile && anchor.getAttribute('data-rel') === 'File') {
					var rmEl = document.getElementById('option-bt-rmtorrent');
					Utils.AddDownload(btLaunch, 'bt', selectedFile, {
						BTRMTorrent: rmEl ? Boolean(rmEl.checked) : true
					});
					anchor.removeAttribute('data-rel');
					anchor.removeAttribute('data-filename');
					anchor.textContent = Utils.tr('Select a file.torrent');
				} else {
					Utils.PrintError(Utils.tr('Please select a torrent file'));
				}
			});
		}

		// Native FormData torrent file upload (replaces removed jquery.fileupload)
		var uploadInput = document.getElementById('uploadfile');
		if (uploadInput) {
			uploadInput.addEventListener('change', function () {
				if (!uploadInput.files || uploadInput.files.length === 0) {
					return;
				}
				var label = uploadInput.parentNode.querySelector('label');
				var formData = new FormData();
				Array.prototype.forEach.call(uploadInput.files, function (file) {
					formData.append('files[]', file, file.name);
				});

				uploadInput.disabled = true;
				if (label) {
					label.classList.remove('icon-upload');
					label.classList.add('icon-loading-small');
				}

				Utils.apiRequest(Utils.BaseURL + 'btdownloader/uploadfiles', 'POST', formData)
					.then(function (data) {
						if (data && !data.ERROR) {
							Utils.PrintInfo(data.MESSAGE);
						} else {
							Utils.PrintError((data && data.MESSAGE) || Utils.tr('Error while uploading torrent file'));
						}
					})
					.catch(function () {
						Utils.PrintError(Utils.tr('Error while uploading torrent file'));
					})
					.finally(function () {
						uploadInput.disabled = false;
						uploadInput.value = '';
						if (label) {
							label.classList.remove('icon-loading-small');
							label.classList.add('icon-upload');
						}
					});
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAddPage);
	} else {
		initAddPage();
	}
})();
