<?php
/**
 * @var array $_
 * @var \OCP\IL10N $l
 */

use OCP\Util;

Util::addStyle('ocdownloader', 'styles');
Util::addScript('ocdownloader', 'badger');
Util::addScript('ocdownloader', 'ocdownloader');
Util::addScript('ocdownloader', 'removed');
?>
<div id="app" class="app-ocdownloader">
	<div id="app-navigation">
		<?php print_unescaped($this->inc('part.navigation')); ?>
	</div>
	<div id="app-content">
		<div id="app-content-wrapper">
			<div id="controls">
				<div class="righttitle"><?php p($l->t('Removed Downloads')); ?></div>
				<div class="ocd-engine-badge"><p class="lead"><?php p($l->t('Engine')); ?>: <strong><?php p($_['WD'] ?? 'ARIA2'); ?></strong></p></div>
			</div>
			<div class="content-queue">
				<table id="Queue" border="0" cellspacing="0" cellpadding="0">
					<thead>
						<tr>
							<th width="20%" data-rel="FILENAME"><?php p($l->t('FILENAME')); ?></th>
							<th width="10%" data-rel="PROTO" class="border"><?php p($l->t('PROTOCOL')); ?></th>
							<th width="60%" data-rel="MESSAGE" class="border"><?php p($l->t('INFORMATION')); ?></th>
							<th width="10%" data-rel="ACTION"></th>
						</tr>
					</thead>
					<tbody>
						<tr data-rel="LOADER">
							<td colspan="4"><div class="icon-loading-small"></div><?php p($l->t('Loading')); ?> ...</td>
						</tr>
					</tbody>
				</table>
			</div>
			<div class="loadingtext loadingblock ocd-hidden"><?php p($l->t('Loading')); ?> ...</div>
		</div>
	</div>
</div>
