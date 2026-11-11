<?php

declare(strict_types=1);

return [
	'routes' => [
		// Index views
		['name' => 'Index#add', 'url' => '/add', 'verb' => 'GET'],
		['name' => 'Index#all', 'url' => '/all', 'verb' => 'GET'],
		['name' => 'Index#completes', 'url' => '/completes', 'verb' => 'GET'],
		['name' => 'Index#actives', 'url' => '/actives', 'verb' => 'GET'],
		['name' => 'Index#waitings', 'url' => '/waitings', 'verb' => 'GET'],
		['name' => 'Index#stopped', 'url' => '/stopped', 'verb' => 'GET'],
		['name' => 'Index#removed', 'url' => '/removed', 'verb' => 'GET'],

		// Protocol downloaders
		['name' => 'HttpDownloader#add', 'url' => '/httpdownloader/add', 'verb' => 'POST'],
		['name' => 'FtpDownloader#add', 'url' => '/ftpdownloader/add', 'verb' => 'POST'],
		['name' => 'YTDownloader#add', 'url' => '/ytdownloader/add', 'verb' => 'POST'],
		['name' => 'BTDownloader#add', 'url' => '/btdownloader/add', 'verb' => 'POST'],
		['name' => 'BTDownloader#listTorrentFiles', 'url' => '/btdownloader/listtorrentfiles', 'verb' => 'POST'],
		['name' => 'BTDownloader#uploadFiles', 'url' => '/btdownloader/uploadfiles', 'verb' => 'POST'],

		// Queue actions
		['name' => 'Queue#get', 'url' => '/queue/get', 'verb' => 'POST'],
		['name' => 'Queue#count', 'url' => '/queue/count', 'verb' => 'POST'],
		['name' => 'Queue#hide', 'url' => '/queue/hide', 'verb' => 'POST'],
		['name' => 'Queue#hideAll', 'url' => '/queue/hideall', 'verb' => 'POST'],
		['name' => 'Queue#remove', 'url' => '/queue/remove', 'verb' => 'POST'],
		['name' => 'Queue#completelyRemove', 'url' => '/queue/completelyremove', 'verb' => 'POST'],
		['name' => 'Queue#pause', 'url' => '/queue/pause', 'verb' => 'POST'],
		['name' => 'Queue#unPause', 'url' => '/queue/unpause', 'verb' => 'POST'],
		['name' => 'Queue#removeAll', 'url' => '/queue/removeall', 'verb' => 'POST'],
		['name' => 'Queue#completelyRemoveAll', 'url' => '/queue/completelyremoveall', 'verb' => 'POST'],

		// Settings
		['name' => 'AdminSettings#save', 'url' => '/adminsettings/save', 'verb' => 'POST'],
		['name' => 'AdminSettings#get', 'url' => '/adminsettings/get', 'verb' => 'POST'],
		['name' => 'PersonalSettings#save', 'url' => '/personalsettings/save', 'verb' => 'POST'],
		['name' => 'PersonalSettings#get', 'url' => '/personalsettings/get', 'verb' => 'GET'],

		// External / Extension API
		['name' => 'Api#checkAddonVersion', 'url' => '/api/version', 'verb' => 'POST'],
		['name' => 'Api#getQueue', 'url' => '/api/queue/get', 'verb' => 'GET'],
		['name' => 'Api#add', 'url' => '/api/add', 'verb' => 'POST'],
	],
];
