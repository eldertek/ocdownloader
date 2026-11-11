<?php
/**
 * @var array $_
 * @var \OCP\IL10N $l
 */

use OCP\Util;

Util::addScript('ocdownloader', 'settings/personal');
Util::addStyle('ocdownloader', 'settings/personal');

$btSeedTime = 1;
$btSeedUnit = 'w';
if (!empty($_['OCDS_BTSeedTimeToReach_BTSeedTimeToReachUnit'])) {
	$seedParts = explode('_', (string)$_['OCDS_BTSeedTimeToReach_BTSeedTimeToReachUnit']);
	if (count($seedParts) === 2) {
		$btSeedTime = (int)$seedParts[0];
		$btSeedUnit = (string)$seedParts[1];
	}
}
$dlFolder = '/' . ltrim((string)($_['OCDS_DownloadsFolder'] ?? 'Downloads'), '/');
$torFolder = '/' . ltrim((string)($_['OCDS_TorrentsFolder'] ?? 'Downloads/Files/Torrents'), '/');
$ratio = (string)($_['OCDS_BTRatioToReach'] ?? '0.0');
?>
<form id="ocdownloader" class="section">
	<h2>ocDownloader</h2>
	<p>
		<span class="info"><?php p($l->t('Personal download folder and BitTorrent seeding preferences')); ?></span>
		<span id="OCDSLoader" class="OCDSLoader icon-loading-small"></span>
		<span class="msg" id="OCDSMsg"></span>
	</p>
	<p id="DownloadsFolder">
		<label for="OCDDownloadsFolder"><?php p($l->t('Default Downloads Folder')); ?></label>
		<input type="text" class="OCDDownloadsFolder" id="OCDDownloadsFolder" value="<?php p($dlFolder); ?>" placeholder="/Downloads" />
		<input type="button" value="<?php p($l->t('Save')); ?>" data-rel="OCDDownloadsFolder" />
	</p>
	<?php if (!empty($_['AllowProtocolBT'])): ?>
	<hr />
	<div class="ocd-clear"></div>
	<p id="TorrentsFolder">
		<label for="OCDTorrentsFolder"><?php p($l->t('Default Torrents Folder')); ?></label>
		<input type="text" class="OCDTorrentsFolder" id="OCDTorrentsFolder" value="<?php p($torFolder); ?>" placeholder="/Downloads/Files/Torrents" />
		<input type="button" value="<?php p($l->t('Save')); ?>" data-rel="OCDTorrentsFolder" />
	</p>
	<hr />
	<div class="ocd-clear"></div>
	<p><span class="info"><?php p($l->t('BitTorrent protocol settings - Ratio')); ?></span></p>
	<p id="RatioValue">
		<label for="OCDBTRatioToReach"><?php p($l->t('Target seed ratio')); ?></label>
		<select id="OCDBTRatioToReach">
			<option value="0.0"<?php if ($ratio === '0.0'): ?> selected="selected"<?php endif; ?>>0 (<?php p($l->t('unlimited')); ?>)</option>
			<?php for ($r = 1; $r <= 10; $r++): $val = $r . '.0'; ?>
			<option value="<?php p($val); ?>"<?php if ($ratio === $val): ?> selected="selected"<?php endif; ?>><?php p($val); ?></option>
			<?php endfor; ?>
		</select>
		<input type="button" value="<?php p($l->t('Save')); ?>" data-rel="OCDBTRatioToReach" />
	</p>
	<p><span class="info"><?php p($l->t('BitTorrent protocol settings - Seed time')); ?></span></p>
	<p id="SeedTimeValue">
		<label for="OCDBTSeedTimeToReach"><?php p($l->t('Target seed time')); ?></label>
		<select id="OCDBTSeedTimeToReach">
			<?php for ($n = 1; $n < 60; $n++): ?>
			<option value="<?php p((string)$n); ?>"<?php if ($btSeedTime === $n): ?> selected="selected"<?php endif; ?>><?php p((string)$n); ?></option>
			<?php endfor; ?>
		</select>
		<select id="OCDBTSeedTimeToReachUnit">
			<option value="i"<?php if ($btSeedUnit === 'i'): ?> selected="selected"<?php endif; ?>><?php p($l->t('minutes')); ?></option>
			<option value="h"<?php if ($btSeedUnit === 'h'): ?> selected="selected"<?php endif; ?>><?php p($l->t('hours')); ?></option>
			<option value="d"<?php if ($btSeedUnit === 'd'): ?> selected="selected"<?php endif; ?>><?php p($l->t('days')); ?></option>
			<option value="w"<?php if ($btSeedUnit === 'w'): ?> selected="selected"<?php endif; ?>><?php p($l->t('weeks')); ?></option>
			<option value="m"<?php if ($btSeedUnit === 'm'): ?> selected="selected"<?php endif; ?>><?php p($l->t('months')); ?></option>
			<option value="y"<?php if ($btSeedUnit === 'y'): ?> selected="selected"<?php endif; ?>><?php p($l->t('years')); ?></option>
		</select>
		<input type="button" value="<?php p($l->t('Save')); ?>" data-rel="OCDBTSeedTimeToReach_OCDBTSeedTimeToReachUnit" />
	</p>
	<?php endif; ?>
</form>
