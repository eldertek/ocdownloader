/**
 * ocDownloader frontend core - pure vanilla ES6 (Nextcloud 28-35, zero jQuery dependency).
 */
(function () {
	'use strict';

	window.OCDLR = window.OCDLR || {};

	function tr(text) {
		if (typeof window.t === 'function') {
			return window.t('ocdownloader', text);
		}
		return text;
	}

	function getRequestToken() {
		if (document.head && document.head.dataset && document.head.dataset.requesttoken) {
			return document.head.dataset.requesttoken;
		}
		if (window.OC && window.OC.requestToken) {
			return window.OC.requestToken;
		}
		return '';
	}

	function detectWebRoot() {
		if (window.OC && typeof window.OC.webroot === 'string') {
			return window.OC.webroot;
		}
		var scripts = document.querySelectorAll('script[src*="/apps/ocdownloader/"]');
		if (scripts.length > 0) {
			var src = scripts[0].getAttribute('src') || '';
			var idx = src.indexOf('/apps/ocdownloader/');
			if (idx >= 0) {
				var prefix = src.slice(0, idx);
				try {
					return new URL(prefix, window.location.origin).pathname.replace(/\/$/, '');
				} catch (e) {
					return prefix.replace(/\/$/, '');
				}
			}
		}
		return '';
	}

	function generateUrl(path) {
		if (window.OC && typeof window.OC.generateUrl === 'function') {
			return window.OC.generateUrl(path);
		}
		var root = detectWebRoot();
		var needsIndex = window.location.pathname.indexOf('/index.php/') !== -1;
		return root + (needsIndex ? '/index.php' : '') + (path.charAt(0) === '/' ? path : ('/' + path));
	}

	function filesFolderUrl(folderPath) {
		var clean = '/' + String(folderPath || 'Downloads').replace(/^\/+/, '');
		return generateUrl('/apps/files/?dir=' + encodeURIComponent(clean).replace(/%2F/g, '/'));
	}

	function apiRequest(endpoint, method, payload) {
		var opts = {
			method: method || 'POST',
			credentials: 'same-origin',
			headers: {
				'requesttoken': getRequestToken(),
				'Accept': 'application/json'
			}
		};
		if (method !== 'GET' && payload !== undefined) {
			if (payload instanceof FormData) {
				opts.body = payload;
			} else {
				opts.headers['Content-Type'] = 'application/json';
				opts.body = JSON.stringify(payload);
			}
		}
		return fetch(generateUrl(endpoint), opts).then(function (resp) {
			return resp.json();
		});
	}

	function cssEscapeAttr(val) {
		return String(val).replace(/["\\]/g, '\\$&');
	}

	var Utils = {
		BaseURL: '/apps/ocdownloader/',
		DownloadsFolder: 'Downloads',
		TorrentsFolder: 'Downloads/Files/Torrents',
		WhichDownloader: 'ARIA2',
		BadgerStatus: ['completes', 'actives', 'waitings', 'stopped'],
		pollTimer: null,

		tr: tr,
		generateUrl: generateUrl,
		filesFolderUrl: filesFolderUrl,
		apiRequest: apiRequest,

		initBadgesLoading: function () {
			['#ball', '#bcompletes', '#bactives', '#bwaitings', '#bstopped', '#bremoved'].forEach(function (sel) {
				window.OCDLR.setBadge(sel, '...');
			});
		},

		applyCounters: function (counters) {
			if (!counters) {
				return;
			}
			window.OCDLR.setBadge('#ball', counters.ALL);
			window.OCDLR.setBadge('#bcompletes', counters.COMPLETES);
			window.OCDLR.setBadge('#bactives', counters.ACTIVES);
			window.OCDLR.setBadge('#bwaitings', counters.WAITINGS);
			window.OCDLR.setBadge('#bstopped', counters.STOPPED);
			window.OCDLR.setBadge('#bremoved', counters.REMOVED);
		},

		ValidURL: function (urlString) {
			if (!urlString || typeof urlString !== 'string') {
				return false;
			}
			var trimmed = urlString.trim();
			if (/^magnet:\?xt=urn:[a-z0-9]+:[a-z0-9]{32,64}/i.test(trimmed)) {
				return true;
			}
			try {
				var parsed = new URL(trimmed);
				return ['http:', 'https:', 'ftp:', 'ftps:'].indexOf(parsed.protocol.toLowerCase()) !== -1 && Boolean(parsed.hostname);
			} catch (e) {
				return false;
			}
		},

		PrintError: function (msg) {
			document.querySelectorAll('#app-content-wrapper span.muted').forEach(function (el) {
				el.classList.remove('info', 'alert');
				el.classList.add('alert');
				el.textContent = msg || '';
			});
		},

		PrintInfo: function (msg) {
			document.querySelectorAll('#app-content-wrapper span.muted').forEach(function (el) {
				el.classList.remove('info', 'alert');
				el.classList.add('info');
				el.textContent = msg || '';
			});
		},

		showLoading: function (visible) {
			var el = document.querySelector('#app-content-wrapper .loadingtext');
			if (!el) {
				return;
			}
			if (visible) {
				el.classList.remove('ocd-hidden');
			} else {
				el.classList.add('ocd-hidden');
			}
		},

		loadInitialSettings: function () {
			return Promise.all([
				apiRequest(Utils.BaseURL + 'personalsettings/get', 'GET').then(function (data) {
					if (data && !data.ERROR && data.VALS) {
						Object.keys(data.VALS).forEach(function (k) {
							Utils[k] = data.VALS[k];
						});
					}
				}).catch(function () {}),
				apiRequest(Utils.BaseURL + 'adminsettings/get', 'POST', { KEYS: ['WhichDownloader'] }).then(function (data) {
					if (data && !data.ERROR && data.VALS) {
						Object.keys(data.VALS).forEach(function (k) {
							Utils[k] = data.VALS[k];
						});
					}
				}).catch(function () {})
			]);
		},

		startView: function (view) {
			Utils.initBadgesLoading();
			Utils.loadInitialSettings().finally(function () {
				Utils.GetCounters();
				Utils.pollQueueOnce(true, view);
			});
		},

		scheduleNextPoll: function (dynMode, view) {
			if (Utils.pollTimer) {
				clearTimeout(Utils.pollTimer);
			}
			Utils.pollTimer = setTimeout(function () {
				Utils.pollQueueOnce(dynMode, view);
			}, 3000);
		},

		pollQueueOnce: function (dynMode, view) {
			var tbody = document.querySelector('#Queue tbody');
			if (!tbody) {
				return;
			}

			var rows = tbody.querySelectorAll('tr');
			if (rows.length === 0 && !dynMode) {
				Utils.scheduleNextPoll(view !== 'add', view);
				return;
			}

			Utils.showLoading(true);
			apiRequest(Utils.BaseURL + 'queue/get', 'POST', { VIEW: view })
				.then(function (data) {
					if (data.ERROR) {
						Utils.PrintError(data.MESSAGE);
						return;
					}

					var loaderRow = tbody.querySelector('tr[data-rel="LOADER"]');
					if (loaderRow) {
						loaderRow.remove();
					}

					Utils.applyCounters(data.COUNTER);

					var seenGids = [];
					(data.QUEUE || []).forEach(function (item) {
						seenGids.push(item.GID);
						var row = tbody.querySelector('tr[data-rel="' + cssEscapeAttr(item.GID) + '"]');
						if (!row && (dynMode || view === 'add')) {
							row = Utils.PrependToQueue(item, view);
						}
						if (row) {
							Utils.updateRowElement(row, item, view);
						}
					});

					Utils.RemoveQueueItems(seenGids);
				})
				.catch(function () {})
				.finally(function () {
					Utils.showLoading(false);
					Utils.scheduleNextPoll(view !== 'add', view);
				});
		},

		updateRowElement: function (row, item, view) {
			var actionTd = row.querySelector('td[data-rel="ACTION"]');
			if (actionTd) {
				if (item.STATUSID === 1 && !actionTd.querySelector('.icon-pause')) {
					var existingPlay = actionTd.querySelector('.icon-play');
					if (existingPlay) {
						existingPlay.remove();
					}
					Utils.ActionPause(actionTd, view);
				}
				if (item.STATUSID === 3 && !actionTd.querySelector('.icon-play')) {
					var existingPause = actionTd.querySelector('.icon-pause');
					if (existingPause) {
						existingPause.remove();
					}
					Utils.ActionPlay(actionTd, view);
				}
			}

			var filenameTd = row.querySelector('td[data-rel="FILENAME"]');
			var speedTd = row.querySelector('td[data-rel="SPEED"]');

			if (item.PROGRESSVAL === '100%' || item.STATUSID === 0) {
				if (filenameTd) {
					filenameTd.textContent = '';
					var link = document.createElement('a');
					link.title = item.FILENAME || '';
					link.href = filesFolderUrl(Utils.DownloadsFolder);
					link.textContent = item.FILENAME_SHORT || item.FILENAME || '';
					filenameTd.appendChild(link);
				}
				if (speedTd) {
					speedTd.textContent = '';
					if (item.ISTORRENT && item.SPEED && item.SPEED !== '--') {
						var upIcon = document.createElement('div');
						upIcon.className = 'icon-upload svg';
						speedTd.appendChild(upIcon);
					} else if (!item.ISTORRENT && actionTd) {
						actionTd.querySelectorAll('.icon-pause, .icon-play').forEach(function (b) {
							b.remove();
						});
					}
					speedTd.appendChild(document.createTextNode(item.SPEED || '--'));
				}
			} else if (speedTd) {
				speedTd.textContent = '';
				if (item.SPEED && item.SPEED !== '--' && item.SPEED !== tr('N/A')) {
					var dlIcon = document.createElement('div');
					dlIcon.className = 'icon-download svg';
					speedTd.appendChild(dlIcon);
				}
				speedTd.appendChild(document.createTextNode(item.SPEED || '--'));
			}

			var pbText = row.querySelector('td[data-rel="MESSAGE"] .pb-text');
			if (pbText) {
				pbText.innerHTML = item.PROGRESS || '';
			}
			var pbVal = row.querySelector('td[data-rel="MESSAGE"] .pb-value');
			if (pbVal) {
				pbVal.style.width = item.PROGRESSVAL || '0%';
			}
			var statusTd = row.querySelector('td[data-rel="STATUS"]');
			if (statusTd) {
				statusTd.setAttribute('data-statusid', String(item.STATUSID));
				statusTd.textContent = item.STATUS || '';
			}
		},

		PrependToQueue: function (data, view) {
			var tbody = document.querySelector('#Queue tbody');
			if (!tbody) {
				return null;
			}
			var loaderRow = tbody.querySelector('tr[data-rel="LOADER"]');
			if (loaderRow) {
				loaderRow.remove();
			}

			var trEl = document.createElement('tr');
			trEl.setAttribute('data-rel', data.GID);

			var fnTd = document.createElement('td');
			fnTd.setAttribute('data-rel', 'FILENAME');
			fnTd.className = 'padding';
			fnTd.textContent = data.FILENAME_SHORT || data.FILENAME || '';
			trEl.appendChild(fnTd);

			var protoTd = document.createElement('td');
			protoTd.setAttribute('data-rel', 'PROTO');
			protoTd.className = 'border padding';
			protoTd.textContent = tr(data.PROTO || '');
			trEl.appendChild(protoTd);

			var msgTd = document.createElement('td');
			msgTd.setAttribute('data-rel', 'MESSAGE');
			msgTd.className = 'border';
			var pbWrap = document.createElement('div');
			pbWrap.className = 'pb-wrap';
			var pbVal = document.createElement('div');
			pbVal.className = 'pb-value';
			pbVal.style.width = data.PROGRESSVAL || '0%';
			var pbTxt = document.createElement('div');
			pbTxt.className = 'pb-text';
			pbTxt.innerHTML = data.PROGRESS || '';
			pbVal.appendChild(pbTxt);
			pbWrap.appendChild(pbVal);
			msgTd.appendChild(pbWrap);
			trEl.appendChild(msgTd);

			if (['add', 'actives', 'all'].indexOf(view) !== -1) {
				var spTd = document.createElement('td');
				spTd.setAttribute('data-rel', 'SPEED');
				spTd.className = 'border padding';
				spTd.textContent = data.SPEED || '--';
				trEl.appendChild(spTd);
			}

			if (['add', 'all'].indexOf(view) !== -1) {
				var stTd = document.createElement('td');
				stTd.setAttribute('data-rel', 'STATUS');
				stTd.setAttribute('data-statusid', String(data.STATUSID));
				stTd.className = 'border padding';
				stTd.textContent = data.STATUS || '';
				trEl.appendChild(stTd);
			}

			var actionTd = document.createElement('td');
			actionTd.setAttribute('data-rel', 'ACTION');
			actionTd.className = 'padding';
			trEl.appendChild(actionTd);

			tbody.insertBefore(trEl, tbody.firstChild);

			var headerActionTh = document.querySelector('#Queue thead th[data-rel="ACTION"]');

			if (view === 'add') {
				Utils.ActionHide(actionTd);
				if (data.STATUSID === 1) {
					Utils.ActionPause(actionTd, view);
				} else if (data.STATUSID === 3) {
					Utils.ActionPlay(actionTd, view);
				}
				if (headerActionTh && !headerActionTh.querySelector('.icon-close')) {
					var hideAllBtn = document.createElement('div');
					hideAllBtn.className = 'icon-close svg';
					hideAllBtn.title = tr('Clean all');
					hideAllBtn.addEventListener('click', function () {
						Utils.HideAllFromQueue(hideAllBtn);
					});
					headerActionTh.appendChild(hideAllBtn);
				}
			}

			if (['completes', 'waitings', 'stopped', 'actives'].indexOf(view) !== -1) {
				Utils.ActionDelete(actionTd, Utils.BadgerStatus[data.STATUSID], view);
				if (headerActionTh && !headerActionTh.querySelector('.icon-delete')) {
					var rmAllBtn = document.createElement('div');
					rmAllBtn.className = 'icon-delete svg';
					rmAllBtn.title = tr('Remove all');
					rmAllBtn.addEventListener('click', function () {
						Utils.RemoveAllFromQueue(rmAllBtn, '', view);
					});
					headerActionTh.appendChild(rmAllBtn);
				}
			}

			if (view === 'all') {
				if (data.STATUSID === 4) {
					Utils.ActionDeleteCompletely(actionTd);
				} else {
					Utils.ActionDelete(actionTd, Utils.BadgerStatus[data.STATUSID], view);
					if (data.STATUSID === 1) {
						Utils.ActionPause(actionTd, view);
					} else if (data.STATUSID === 3) {
						Utils.ActionPlay(actionTd, view);
					}
				}
			}

			if (view === 'actives') {
				Utils.ActionPause(actionTd, view);
			}
			if (view === 'stopped') {
				Utils.ActionPlay(actionTd, view);
			}
			if (view === 'removed') {
				Utils.ActionDeleteCompletely(actionTd);
				if (headerActionTh && !headerActionTh.querySelector('.icon-delete')) {
					var purgeAllBtn = document.createElement('div');
					purgeAllBtn.className = 'icon-delete svg';
					purgeAllBtn.title = tr('Delete all');
					purgeAllBtn.addEventListener('click', function () {
						Utils.RemoveAllFromQueue(purgeAllBtn, 'completely', view);
					});
					headerActionTh.appendChild(purgeAllBtn);
				}
			}

			return trEl;
		},

		RemoveQueueItem: function (rowEl) {
			if (!rowEl || !rowEl.parentNode) {
				return;
			}
			var tbody = rowEl.parentNode;
			rowEl.remove();
			if (tbody.children.length === 0) {
				var th = document.querySelector('#Queue thead th[data-rel="ACTION"]');
				if (th) {
					th.textContent = '';
				}
			}
		},

		RemoveQueueItems: function (keepGids) {
			var tbody = document.querySelector('#Queue tbody');
			if (!tbody) {
				return;
			}
			tbody.querySelectorAll('tr').forEach(function (row) {
				var rel = row.getAttribute('data-rel');
				if (rel && rel !== 'LOADER' && keepGids.indexOf(rel) === -1) {
					row.remove();
				}
			});
			if (tbody.children.length === 0) {
				var th = document.querySelector('#Queue thead th[data-rel="ACTION"]');
				if (th) {
					th.textContent = '';
				}
			}
		},

		HideFromQueue: function (btn) {
			var row = btn.closest('tr');
			var gid = row ? row.getAttribute('data-rel') : '';
			if (!gid) {
				Utils.PrintError(tr('Unable to find the GID for this download'));
				return;
			}
			btn.classList.remove('icon-close');
			btn.classList.add('icon-loading-small');

			apiRequest(Utils.BaseURL + 'queue/hide', 'POST', { GID: gid }).then(function (data) {
				if (data.ERROR) {
					Utils.PrintError(data.MESSAGE);
					btn.classList.remove('icon-loading-small');
					btn.classList.add('icon-close');
				} else {
					Utils.PrintInfo(data.MESSAGE + ' (' + gid + ')');
					Utils.RemoveQueueItem(row);
				}
			});
		},

		HideAllFromQueue: function (btn) {
			var gids = [];
			document.querySelectorAll('#Queue tbody tr td[data-rel="ACTION"] > div.icon-close').forEach(function (b) {
				var r = b.closest('tr');
				if (r && r.getAttribute('data-rel')) {
					b.classList.remove('icon-close');
					b.classList.add('icon-loading-small');
					gids.push(r.getAttribute('data-rel'));
				}
			});
			if (gids.length === 0) {
				Utils.PrintError(tr('No downloads in the queue'));
				return;
			}
			btn.classList.remove('icon-close');
			btn.classList.add('icon-loading-small');

			apiRequest(Utils.BaseURL + 'queue/hideall', 'POST', { GIDS: gids }).then(function (data) {
				if (data.ERROR) {
					Utils.PrintError(data.MESSAGE);
				} else {
					Utils.PrintInfo(data.MESSAGE);
					(data.QUEUE || []).forEach(function (entry) {
						var r = document.querySelector('#Queue tbody tr[data-rel="' + cssEscapeAttr(entry.GID) + '"]');
						if (r) {
							r.remove();
						}
					});
					btn.remove();
				}
			});
		},

		PauseDownload: function (btn, view) {
			var row = btn.closest('tr');
			var gid = row ? row.getAttribute('data-rel') : '';
			if (!gid) {
				return;
			}
			btn.classList.remove('icon-pause');
			btn.classList.add('icon-loading-small');

			apiRequest(Utils.BaseURL + 'queue/pause', 'POST', { GID: gid }).then(function (data) {
				btn.classList.remove('icon-loading-small');
				if (data.ERROR) {
					Utils.PrintError(data.MESSAGE);
					btn.classList.add('icon-pause');
				} else {
					window.OCDLR.setBadge('#bactives', '-1');
					window.OCDLR.setBadge('#bstopped', '+1');
					Utils.PrintInfo(data.MESSAGE + ' (' + gid + ')');
					if (view === 'add' || view === 'all') {
						var td = btn.parentNode;
						btn.remove();
						Utils.ActionPlay(td, view);
						var st = row.querySelector('td[data-rel="STATUS"]');
						if (st) {
							st.setAttribute('data-statusid', '3');
							st.textContent = tr('Paused');
						}
					} else {
						Utils.RemoveQueueItem(row);
					}
				}
			});
		},

		UnPauseDownload: function (btn, view) {
			var row = btn.closest('tr');
			var gid = row ? row.getAttribute('data-rel') : '';
			if (!gid) {
				return;
			}
			btn.classList.remove('icon-play');
			btn.classList.add('icon-loading-small');

			apiRequest(Utils.BaseURL + 'queue/unpause', 'POST', { GID: gid }).then(function (data) {
				btn.classList.remove('icon-loading-small');
				if (data.ERROR) {
					Utils.PrintError(data.MESSAGE);
					btn.classList.add('icon-play');
				} else {
					window.OCDLR.setBadge('#bactives', '+1');
					window.OCDLR.setBadge('#bstopped', '-1');
					Utils.PrintInfo(data.MESSAGE + ' (' + gid + ')');
					if (view === 'add' || view === 'all') {
						var td = btn.parentNode;
						btn.remove();
						Utils.ActionPause(td, view);
						var st = row.querySelector('td[data-rel="STATUS"]');
						if (st) {
							st.setAttribute('data-statusid', '1');
							st.textContent = tr('Active');
						}
					} else {
						Utils.RemoveQueueItem(row);
					}
				}
			});
		},

		RemoveFromQueue: function (btn, completely, view) {
			var row = btn.closest('tr');
			var gid = row ? row.getAttribute('data-rel') : '';
			if (!gid) {
				return;
			}
			btn.classList.remove('icon-delete');
			btn.classList.add('icon-loading-small');

			apiRequest(Utils.BaseURL + 'queue/' + completely + 'remove', 'POST', { GID: gid }).then(function (data) {
				if (data.ERROR) {
					Utils.PrintError(data.MESSAGE);
					btn.classList.remove('icon-loading-small');
					btn.classList.add('icon-delete');
				} else {
					Utils.PrintInfo(data.MESSAGE + ' (' + gid + ')');
					if (view !== 'all' || completely.length > 0) {
						Utils.RemoveQueueItem(row);
					} else {
						var td = btn.parentNode;
						td.textContent = '';
						var st = row.querySelector('td[data-rel="STATUS"]');
						if (st) {
							st.textContent = tr('Removed');
						}
						Utils.ActionDeleteCompletely(td);
					}
					Utils.GetCounters();
				}
			});
		},

		RemoveAllFromQueue: function (btn, completely, view) {
			var gids = [];
			document.querySelectorAll('#Queue tbody tr td[data-rel="ACTION"] > div.icon-delete').forEach(function (b) {
				var r = b.closest('tr');
				if (r && r.getAttribute('data-rel')) {
					b.classList.remove('icon-delete');
					b.classList.add('icon-loading-small');
					gids.push(r.getAttribute('data-rel'));
				}
			});
			if (gids.length === 0) {
				return;
			}
			btn.classList.remove('icon-delete');
			btn.classList.add('icon-loading-small');

			apiRequest(Utils.BaseURL + 'queue/' + completely + 'removeall', 'POST', { GIDS: gids }).then(function (data) {
				if (data.ERROR) {
					Utils.PrintError(data.MESSAGE);
				} else {
					Utils.PrintInfo(data.MESSAGE);
					(data.GIDS || []).forEach(function (gid) {
						var r = document.querySelector('#Queue tbody tr[data-rel="' + cssEscapeAttr(gid) + '"]');
						if (r) {
							Utils.RemoveQueueItem(r);
						}
					});
					Utils.GetCounters();
				}
			});
		},

		GetTorrentsList: function (listEl) {
			if (!listEl) {
				return;
			}
			if (listEl.style.display === 'block') {
				listEl.style.display = 'none';
				return;
			}

			listEl.textContent = '';
			var loadingLi = document.createElement('li');
			loadingLi.innerHTML = '<p class="loader"><span class="icon-loading-small"></span></p>';
			listEl.appendChild(loadingLi);
			listEl.style.display = 'block';

			apiRequest(Utils.BaseURL + 'btdownloader/listtorrentfiles', 'POST', {}).then(function (data) {
				if (data.ERROR) {
					Utils.PrintError(data.MESSAGE);
					return;
				}
				listEl.textContent = '';
				var files = (data.FILES || []).filter(function (f) {
					return f && typeof f.name === 'string' && /\.torrent$/i.test(f.name);
				});

				if (files.length === 0) {
					var emptyLi = document.createElement('li');
					var p = document.createElement('p');
					p.appendChild(document.createTextNode(tr('No Torrent Files') + ', '));
					var a = document.createElement('a');
					a.href = filesFolderUrl(Utils.TorrentsFolder);
					a.textContent = tr('Upload');
					p.appendChild(a);
					emptyLi.appendChild(p);
					listEl.appendChild(emptyLi);
					return;
				}

				files.forEach(function (f) {
					var li = document.createElement('li');
					var p = document.createElement('p');
					p.className = 'clickable';
					p.textContent = f.name;
					p.addEventListener('click', function (ev) {
						ev.stopPropagation();
						var anchor = listEl.parentNode.querySelector('a');
						if (anchor) {
							anchor.setAttribute('data-rel', 'File');
							anchor.setAttribute('data-filename', f.name);
							anchor.textContent = f.name;
							var caret = document.createElement('div');
							caret.className = 'icon-caret-dark svg';
							anchor.appendChild(caret);
						}
						listEl.style.display = 'none';
					});
					li.appendChild(p);
					listEl.appendChild(li);
				});
			});
		},

		GetCounters: function () {
			apiRequest(Utils.BaseURL + 'queue/count', 'POST', {}).then(function (data) {
				if (!data.ERROR && data.COUNTER) {
					Utils.applyCounters(data.COUNTER);
				}
			}).catch(function () {});
		},

		AddDownload: function (btn, type, url, options) {
			if (!btn || btn.classList.contains('icon-loading-small')) {
				return;
			}
			var labelAnchor = btn.querySelector('a');
			if (labelAnchor) {
				labelAnchor.style.display = 'none';
			}
			btn.classList.add('icon-loading-small');

			apiRequest(Utils.BaseURL + type + 'downloader/add', 'POST', { FILE: url, OPTIONS: options || {} })
				.then(function (data) {
					if (data.ERROR) {
						Utils.PrintError(data.MESSAGE);
						return;
					}
					Utils.PrintInfo(data.MESSAGE + ' (' + data.GID + ')');
					var row = Utils.PrependToQueue(data, 'add');
					if (row) {
						Utils.updateRowElement(row, data, 'add');
					}
					Utils.GetCounters();
				})
				.catch(function (err) {
					Utils.PrintError(err && err.message ? err.message : tr('Unable to launch download'));
				})
				.finally(function () {
					if (labelAnchor) {
						labelAnchor.style.display = 'block';
					}
					btn.classList.remove('icon-loading-small');
				});
		},

		ActionHide: function (td) {
			var d = document.createElement('div');
			d.className = 'icon-close svg';
			d.title = tr('Hide from add queue');
			d.addEventListener('click', function () {
				Utils.HideFromQueue(d);
			});
			td.appendChild(d);
		},

		ActionPause: function (td, view) {
			if (Utils.WhichDownloader !== 'ARIA2') {
				return;
			}
			var d = document.createElement('div');
			d.className = 'icon-pause svg';
			d.title = tr('Pause');
			d.addEventListener('click', function () {
				Utils.PauseDownload(d, view);
			});
			td.appendChild(d);
		},

		ActionPlay: function (td, view) {
			if (Utils.WhichDownloader !== 'ARIA2') {
				return;
			}
			var d = document.createElement('div');
			d.className = 'icon-play svg';
			d.title = tr('Resume');
			d.addEventListener('click', function () {
				Utils.UnPauseDownload(d, view);
			});
			td.appendChild(d);
		},

		ActionDelete: function (td, status, view) {
			var d = document.createElement('div');
			d.className = 'icon-delete svg';
			d.title = tr('Remove');
			d.addEventListener('click', function () {
				Utils.RemoveFromQueue(d, '', view);
			});
			td.appendChild(d);
		},

		ActionDeleteCompletely: function (td) {
			var d = document.createElement('div');
			d.className = 'icon-delete svg';
			d.title = tr('Delete permanently');
			d.addEventListener('click', function () {
				Utils.RemoveFromQueue(d, 'completely', 'removed');
			});
			td.appendChild(d);
		}
	};

	window.OCDLR.Utils = Utils;
})();
