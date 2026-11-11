<?php
/**
 * @var array $_
 * @var \OCP\IL10N $l
 */

use OCP\Util;

Util::addScript('ocdownloader', 'settings/admin');
Util::addStyle('ocdownloader', 'settings/admin');

$wd = $_['OCDS_WhichDownloader'] ?? 'ARIA2';
$isAria2 = $wd === 'ARIA2';
$allowBT = ($_['OCDS_AllowProtocolBT'] ?? 'Y') === 'Y';
?>
<form id="ocdownloader" class="section">
	<h2>ocDownloader</h2>
	<p>
		<span class="info"><?php p($l->t('yt-dlp / youtube-dl settings (leave blank to reset to defaults)')); ?></span>
		<span id="OCDSLoaderYTDLBinary" class="OCDSLoader icon-loading-small"></span>
		<span id="OCDSLoaderYTDLBinaryMsg" class="msg"></span>
		<span id="OCDSLoaderYTDLAudioFormat" class="OCDSLoader icon-loading-small"></span>
		<span id="OCDSLoaderYTDLAudioFormatMsg" class="msg"></span>
		<span id="OCDSLoaderYTDLVideoFormat" class="OCDSLoader icon-loading-small"></span>
		<span id="OCDSLoaderYTDLVideoFormatMsg" class="msg"></span>
	</p>
	<p>
		<label for="OCDYTDLBinary"><?php p($l->t('yt-dlp / youtube-dl Binary Path')); ?></label>
		<input type="text" class="OCDYTDLBinary ToUse" id="OCDYTDLBinary" data-loader="OCDSLoaderYTDLBinary" value="<?php p($_['OCDS_YTDLBinary'] ?? '/usr/local/bin/yt-dlp'); ?>" placeholder="/usr/local/bin/yt-dlp" />
	</p>
	<p>
		<label for="OCDYTDLAudioFormat"><?php p($l->t('Audio Format')); ?></label>
		<input type="text" class="OCDYTDLAudioFormat ToUse" id="OCDYTDLAudioFormat" data-loader="OCDSLoaderYTDLAudioFormat" value="<?php p($_['OCDS_YTDLAudioFormat'] ?? 'bestaudio'); ?>" />
	</p>
	<p>
		<label for="OCDYTDLVideoFormat"><?php p($l->t('Video Format')); ?></label>
		<input type="text" class="OCDYTDLVideoFormat ToUse" id="OCDYTDLVideoFormat" data-loader="OCDSLoaderYTDLVideoFormat" value="<?php p($_['OCDS_YTDLVideoFormat'] ?? 'best[width<=1280]/best'); ?>" />
	</p>
	<hr />
	<div class="ocd-clear"></div>
	<p>
		<span class="info"><?php p($l->t('Proxy settings')); ?></span>
		<span id="OCDSLoaderProxySettings" class="OCDSLoader icon-loading-small"></span>
		<span id="OCDSLoaderProxySettingsMsg" class="msg"></span>
	</p>
	<p>
		<label for="OCDProxyAddress"><?php p($l->t('Proxy Address')); ?></label>
		<input type="text" class="OCDProxyAddress ToUse" id="OCDProxyAddress" data-loader="OCDSLoaderProxySettings" value="<?php p($_['OCDS_ProxyAddress'] ?? ''); ?>" placeholder="http://proxy.example.com" />
		<label for="OCDProxyPort"><?php p($l->t('Proxy Port')); ?></label>
		<input type="text" class="OCDProxyPort ToUse" id="OCDProxyPort" data-loader="OCDSLoaderProxySettings" value="<?php p($_['OCDS_ProxyPort'] ?? ''); ?>" placeholder="8080" />
	</p>
	<p>
		<span class="info"><?php p($l->t('If no authentication is required by your proxy, leave the following fields blank')); ?></span>
	</p>
	<p>
		<label for="OCDProxyUser"><?php p($l->t('Proxy User')); ?></label>
		<input type="text" class="OCDProxyUser ToUse" id="OCDProxyUser" data-loader="OCDSLoaderProxySettings" value="<?php p($_['OCDS_ProxyUser'] ?? ''); ?>" placeholder="<?php p($l->t('Username')); ?>" autocomplete="off" />
		<label for="OCDProxyPasswd"><?php p($l->t('Proxy Password')); ?></label>
		<input type="password" class="OCDProxyPasswd ToUse" id="OCDProxyPasswd" data-loader="OCDSLoaderProxySettings" placeholder="<?php p($l->t('Password')); ?>" autocomplete="new-password" />
	</p>
	<p>
		<label for="OCDProxyOnlyWithYTDL"><?php p($l->t('Only use proxy settings with yt-dlp')); ?></label>
		<select id="OCDProxyOnlyWithYTDL" class="ToUse" data-loader="OCDSLoaderProxySettings">
			<option value="N"><?php p($l->t('No')); ?></option>
			<option value="Y"<?php if (($_['OCDS_ProxyOnlyWithYTDL'] ?? 'N') === 'Y'): ?> selected="selected"<?php endif; ?>><?php p($l->t('Yes')); ?></option>
		</select>
	</p>
	<hr />
	<div class="ocd-clear"></div>
	<p>
		<span class="info"><?php p($l->t('General settings')); ?></span>
		<span id="OCDSLoaderGeneralSettings" class="OCDSLoader icon-loading-small"></span>
		<span id="OCDSLoaderGeneralSettingsMsg" class="msg"></span>
	</p>
	<p>
		<span class="info"><?php p($l->t('Switching from ARIA2 to cURL clears active ARIA2 queue entries')); ?></span>
	</p>
	<p>
		<label for="OCDWhichDownloader"><?php p($l->t('Download engine')); ?></label>
		<select id="OCDWhichDownloader" class="ToUse" data-loader="OCDSLoaderGeneralSettings">
			<option value="ARIA2" data-protocols="HTTP(S) / Magnet / FTP(S) / yt-dlp / BitTorrent"<?php if ($isAria2): ?> selected="selected"<?php endif; ?>>ARIA2</option>
			<option value="CURL" data-protocols="HTTP(S) / FTP(S) / yt-dlp"<?php if (!$isAria2): ?> selected="selected"<?php endif; ?>>cURL</option>
		</select>
		<span id="OCDWhichDownloaderDetails" class="details"><?php p($l->t('Available protocols')); ?>: <strong></strong></span>
	</p>
	<div id="OCDAriaSettings"<?php if (!$isAria2): ?> class="ocd-hidden"<?php endif; ?>>
		<p>
			<label for="OCDAriaAddress"><?php p($l->t('ARIA2 RPC Address')); ?></label>
			<input type="text" class="OCDAriaAddress ToUse" id="OCDAriaAddress" data-loader="OCDSLoaderGeneralSettings" value="<?php p($_['OCDS_AriaAddress'] ?? '127.0.0.1'); ?>" placeholder="127.0.0.1" />
			<label for="OCDAriaPort"><?php p($l->t('ARIA2 RPC Port')); ?></label>
			<input type="text" class="OCDAriaPort ToUse" id="OCDAriaPort" data-loader="OCDSLoaderGeneralSettings" value="<?php p($_['OCDS_AriaPort'] ?? '6800'); ?>" placeholder="6800" />
		</p>
		<p>
			<label for="OCDAriaToken"><?php p($l->t('ARIA2 Secret Token')); ?></label>
			<input type="password" class="OCDAriaToken ToUse" id="OCDAriaToken" data-loader="OCDSLoaderGeneralSettings" placeholder="<?php p($l->t('Secret Token')); ?>" autocomplete="new-password" />
		</p>
	</div>
	<p>
		<label for="OCDMaxDownloadSpeed"><?php p($l->t('Max download speed')); ?></label>
		<input type="text" id="OCDMaxDownloadSpeed" data-loader="OCDSLoaderGeneralSettings" class="ToUse" value="<?php p($_['OCDS_MaxDownloadSpeed'] ?? ''); ?>" placeholder="10000" />
		<span class="details"><?php p($l->t('KB/s (0 or empty for unlimited)')); ?></span>
	</p>
	<div id="OCDBTSettings"<?php if (!($isAria2 && $allowBT)): ?> class="ocd-hidden"<?php endif; ?>>
		<hr />
		<div class="ocd-clear"></div>
		<p>
			<span class="info"><?php p($l->t('BitTorrent protocol settings - Max upload speed')); ?></span>
			<span id="OCDSLoaderBTGeneralSettings" class="OCDSLoader icon-loading-small"></span>
			<span id="OCDSLoaderBTGeneralSettingsMsg" class="msg"></span>
		</p>
		<p>
			<label for="OCDBTMaxUploadSpeed"><?php p($l->t('BitTorrent max upload speed')); ?></label>
			<input type="text" id="OCDBTMaxUploadSpeed" data-loader="OCDSLoaderBTGeneralSettings" class="ToUse" value="<?php p($_['OCDS_BTMaxUploadSpeed'] ?? ''); ?>" placeholder="5000" />
			<span class="details"><?php p($l->t('KB/s (0 or empty for unlimited)')); ?></span>
		</p>
	</div>
	<hr />
	<div class="ocd-clear"></div>
	<p>
		<span class="info"><?php p($l->t('Allowed protocols for standard users (administrators can use all protocols)')); ?></span>
		<span id="OCDSLoaderPermissionsSettings" class="OCDSLoader icon-loading-small"></span>
		<span id="OCDSLoaderPermissionsSettingsMsg" class="msg"></span>
	</p>
	<p>
		<label for="OCDAllowProtocolHTTP"><?php p($l->t('Allow HTTP/Magnet')); ?></label>
		<select id="OCDAllowProtocolHTTP" class="ToUse" data-loader="OCDSLoaderPermissionsSettings">
			<option value="Y"><?php p($l->t('Yes')); ?></option>
			<option value="N"<?php if (($_['OCDS_AllowProtocolHTTP'] ?? 'Y') === 'N'): ?> selected="selected"<?php endif; ?>><?php p($l->t('No')); ?></option>
		</select>
	</p>
	<p>
		<label for="OCDAllowProtocolFTP"><?php p($l->t('Allow FTP')); ?></label>
		<select id="OCDAllowProtocolFTP" class="ToUse" data-loader="OCDSLoaderPermissionsSettings">
			<option value="Y"><?php p($l->t('Yes')); ?></option>
			<option value="N"<?php if (($_['OCDS_AllowProtocolFTP'] ?? 'Y') === 'N'): ?> selected="selected"<?php endif; ?>><?php p($l->t('No')); ?></option>
		</select>
	</p>
	<p>
		<label for="OCDAllowProtocolYT"><?php p($l->t('Allow Video (yt-dlp)')); ?></label>
		<select id="OCDAllowProtocolYT" class="ToUse" data-loader="OCDSLoaderPermissionsSettings">
			<option value="Y"><?php p($l->t('Yes')); ?></option>
			<option value="N"<?php if (($_['OCDS_AllowProtocolYT'] ?? 'Y') === 'N'): ?> selected="selected"<?php endif; ?>><?php p($l->t('No')); ?></option>
		</select>
	</p>
	<p>
		<label for="OCDAllowProtocolBT"><?php p($l->t('Allow BitTorrent')); ?></label>
		<select id="OCDAllowProtocolBT" class="ToUse<?php if (!$isAria2): ?> ocd-hidden<?php endif; ?>" data-loader="OCDSLoaderPermissionsSettings">
			<option value="Y"><?php p($l->t('Yes')); ?></option>
			<option value="N"<?php if (!$allowBT): ?> selected="selected"<?php endif; ?>><?php p($l->t('No')); ?></option>
		</select>
		<span id="OCDAllowProtocolBTDetails" class="details<?php if ($isAria2): ?> ocd-hidden<?php endif; ?>"><strong><?php p($l->t('No')); ?></strong></span>
	</p>
</form>
