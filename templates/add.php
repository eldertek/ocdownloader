<?php
/**
 * @var array $_
 * @var \OCP\IL10N $l
 */

use OCP\IURLGenerator;
use OCP\Server;
use OCP\Util;

Util::addStyle('ocdownloader', 'styles');
Util::addScript('ocdownloader', 'badger');
Util::addScript('ocdownloader', 'ocdownloader');
Util::addScript('ocdownloader', 'add');

/** @var IURLGenerator $urlGen */
$urlGen = Server::get(IURLGenerator::class);
$isAria2 = ($_['WD'] ?? 'ARIA2') === 'ARIA2';
$httpSupportsMagnet = !empty($_['AllowProtocolBT']) && $isAria2;
?>
<div id="app" class="app-ocdownloader">
	<div id="app-navigation">
		<?php print_unescaped($this->inc('part.navigation')); ?>
	</div>
	<div id="app-content">
		<div id="app-content-wrapper">
			<div id="controls">
				<div class="actions">
					<div id="NewDL">
						<?php if (!empty($_['AllowProtocolHTTP'])): ?>
						<div class="button" data-rel="OCDHTTP">
							<p data-rel="OCDHTTP"><?php p($httpSupportsMagnet ? $l->t('Magnet/HTTP') : $l->t('HTTP')); ?></p>
						</div>
						<?php endif; ?>
						<?php if (!empty($_['AllowProtocolFTP'])): ?>
						<div class="button" data-rel="OCDFTP">
							<p data-rel="OCDFTP">FTP</p>
						</div>
						<?php endif; ?>
						<?php if (!empty($_['AllowProtocolYT'])): ?>
						<div class="button" data-rel="OCDYT">
							<p data-rel="OCDYT">YT-DLP</p>
						</div>
						<?php endif; ?>
						<?php if (!empty($_['AllowProtocolBT']) && $isAria2): ?>
						<div class="button" data-rel="OCDBT">
							<p data-rel="OCDBT">BITTORRENT</p>
						</div>
						<?php endif; ?>
					</div>
				</div>
				<div class="righttitle"><?php p($l->t('Add Download')); ?></div>
				<div class="ocd-engine-badge"><p class="lead"><?php p($l->t('Engine')); ?>: <strong><?php p($_['WD'] ?? 'ARIA2'); ?></strong></p></div>
			</div>

			<?php if (!empty($_['AllowProtocolHTTP'])): ?>
			<div class="content-page" rel="OCDHTTP">
				<h3>
					<?php p($httpSupportsMagnet ? $l->t('New Magnet/HTTP download') : $l->t('New HTTP download')); ?>
					<span class="muted OCDLRMsg"></span>
					<div class="button launch">
						<a><?php p($httpSupportsMagnet ? $l->t('Launch Magnet/HTTP download') : $l->t('Launch HTTP download')); ?></a>
					</div>
				</h3>
				<input type="text" placeholder="<?php p($httpSupportsMagnet ? $l->t('Magnet/HTTP link to download') : $l->t('HTTP link to download')); ?>" class="form-control url" />
				<div class="jumbotron">
					<h5><?php p($l->t('Options')); ?></h5>
					<div class="group-option">
						<ul>
							<label for="option-http-user"><?php p($l->t('Basic Auth User')); ?></label>
							<input type="text" id="option-http-user" placeholder="<?php p($l->t('Username')); ?>" />
						</ul>
						<ul>
							<label for="option-http-pwd"><?php p($l->t('Basic Auth Password')); ?></label>
							<input type="password" id="option-http-pwd" placeholder="<?php p($l->t('Password')); ?>" />
						</ul>
						<ul>
							<label for="option-http-referer"><?php p($l->t('HTTP Referer')); ?></label>
							<input type="text" id="option-http-referer" placeholder="<?php p($l->t('Referer')); ?>" />
						</ul>
						<ul>
							<label for="option-http-useragent"><?php p($l->t('HTTP User Agent')); ?></label>
							<input type="text" id="option-http-useragent" placeholder="<?php p($l->t('Useragent')); ?>" />
						</ul>
						<ul>
							<label for="option-http-outfilename"><?php p($l->t('HTTP Output Filename')); ?></label>
							<input type="text" id="option-http-outfilename" placeholder="<?php p($l->t('Filename')); ?>" />
						</ul>
					</div>
				</div>
			</div>
			<?php endif; ?>

			<?php if (!empty($_['AllowProtocolFTP'])): ?>
			<div class="content-page ocd-hidden" rel="OCDFTP">
				<h3>
					<?php p($l->t('New FTP download')); ?>
					<span class="muted OCDLRMsg"></span>
					<div class="button launch">
						<a><?php p($l->t('Launch FTP Download')); ?></a>
					</div>
				</h3>
				<input type="text" placeholder="<?php p($l->t('FTP URL to download')); ?>" class="form-control url" />
				<div class="jumbotron">
					<h5><?php p($l->t('Options')); ?></h5>
					<div class="group-option">
						<ul>
							<label for="option-ftp-user"><?php p($l->t('FTP User')); ?></label>
							<input type="text" id="option-ftp-user" placeholder="<?php p($l->t('Username')); ?>" />
						</ul>
						<ul>
							<label for="option-ftp-pwd"><?php p($l->t('FTP Password')); ?></label>
							<input type="password" id="option-ftp-pwd" placeholder="<?php p($l->t('Password')); ?>" />
						</ul>
						<ul>
							<label for="option-ftp-referer"><?php p($l->t('FTP Referer')); ?></label>
							<input type="text" id="option-ftp-referer" placeholder="<?php p($l->t('Referer')); ?>" />
						</ul>
						<ul>
							<label for="option-ftp-useragent"><?php p($l->t('FTP User Agent')); ?></label>
							<input type="text" id="option-ftp-useragent" placeholder="<?php p($l->t('Useragent')); ?>" />
						</ul>
						<ul>
							<label for="option-ftp-outfilename"><?php p($l->t('FTP Output Filename')); ?></label>
							<input type="text" id="option-ftp-outfilename" placeholder="<?php p($l->t('Filename')); ?>" />
						</ul>
					</div>
					<div class="group-option">
						<label for="option-ftp-pasv"><?php p($l->t('Passive Mode')); ?></label>
						<input type="checkbox" id="option-ftp-pasv" checked />
					</div>
				</div>
			</div>
			<?php endif; ?>

			<?php if (!empty($_['AllowProtocolYT'])): ?>
			<div class="content-page ocd-hidden" rel="OCDYT">
				<h3>
					<?php p($l->t('New video download (yt-dlp)')); ?>
					<span class="muted OCDLRMsg"></span>
					<div class="button launch">
						<a><?php p($l->t('Launch Video Download')); ?></a>
					</div>
				</h3>
				<input type="text" placeholder="<?php p($l->t('Video URL to download')); ?>" class="form-control url" />
				<div class="jumbotron">
					<h5><?php p($l->t('Options')); ?></h5>
					<div class="group-option">
						<label for="option-yt-extractaudio"><?php p($l->t('Only extract audio')); ?></label>
						<input type="checkbox" id="option-yt-extractaudio" />
						<i><?php p($l->t('(Extract best audio stream without transcoding)')); ?></i>
					</div>
					<div class="group-option">
						<label for="option-yt-forceipv4"><?php p($l->t('Force IPv4')); ?></label>
						<input type="checkbox" id="option-yt-forceipv4" checked />
					</div>
				</div>
			</div>
			<?php endif; ?>

			<?php if (!empty($_['AllowProtocolBT']) && $isAria2): ?>
			<div class="content-page ocd-hidden" rel="OCDBT">
				<h3>
					<?php p($l->t('New BitTorrent download')); ?>
					<span class="muted OCDLRMsg"></span>
					<div class="button launch">
						<a><?php p($l->t('Launch BitTorrent Download')); ?></a>
					</div>
					<div class="button uploadfile">
						<input type="file" name="files[]" id="uploadfile" accept=".torrent" multiple="multiple" data-url="<?php p($urlGen->linkToRoute('ocdownloader.btdownloader.uploadfiles')); ?>">
						<label class="svg icon-upload" for="uploadfile">
							<span class="hidden-visually"><?php p($l->t('Upload')); ?></span>
						</label>
					</div>
				</h3>
				<div class="actions">
					<div class="button" id="TorrentsList">
						<a><?php p($l->t('Select a file.torrent')); ?><?php if (trim((string)($_['TTSFOLD'] ?? '')) === ''): ?> <i><?php p($l->t('(Default folder: /Downloads/Files/Torrents)')); ?></i><?php endif; ?><div class="icon-caret-dark svg"></div></a>
						<ul>
							<li><p class="loader"><span class="icon-loading-small"></span></p></li>
						</ul>
					</div>
				</div>
				<div class="jumbotron">
					<h5><?php p($l->t('Options')); ?></h5>
					<div class="group-option">
						<label for="option-bt-rmtorrent"><?php p($l->t('Remove torrent file after start')); ?></label>
						<input type="checkbox" id="option-bt-rmtorrent" checked />
					</div>
				</div>
			</div>
			<?php endif; ?>

			<div class="content-queue">
				<table id="Queue" border="0" cellspacing="0" cellpadding="0">
					<thead>
						<tr>
							<th width="20%" data-rel="FILENAME"><?php p($l->t('FILENAME')); ?></th>
							<th width="10%" data-rel="PROTO" class="border"><?php p($l->t('PROTOCOL')); ?></th>
							<th width="35%" data-rel="MESSAGE" class="border"><?php p($l->t('INFORMATION')); ?></th>
							<th width="10%" data-rel="SPEED" class="border"><?php p($l->t('SPEED')); ?></th>
							<th width="15%" data-rel="STATUS" class="border"><?php p($l->t('STATUS')); ?></th>
							<th width="10%" data-rel="ACTION"></th>
						</tr>
					</thead>
					<tbody>
						<tr data-rel="LOADER">
							<td colspan="6"><div class="icon-loading-small"></div><?php p($l->t('Loading')); ?> ...</td>
						</tr>
					</tbody>
				</table>
			</div>
			<div class="loadingtext loadinginline ocd-hidden"><?php p($l->t('Loading')); ?> ...</div>
		</div>
	</div>
</div>
