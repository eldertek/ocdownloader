<?php
/**
 * @var array $_
 * @var \OCP\IL10N $l
 */
?>
<ul>
	<li data-id="add"<?php if (($_['PAGE'] ?? -1) === 0): ?> class="active"<?php endif; ?>>
		<a href="add"><?php p($l->t('Add Download')); ?></a>
	</li>
	<li data-id="all"<?php if (($_['PAGE'] ?? -1) === 1): ?> class="active"<?php endif; ?>>
		<div class="badge" id="ball">
			<a href="all"><?php p($l->t('All Downloads')); ?></a>
		</div>
	</li>
	<li data-id="completes"<?php if (($_['PAGE'] ?? -1) === 2): ?> class="active"<?php endif; ?>>
		<div class="badge" id="bcompletes">
			<a href="completes"><?php p($l->t('Complete Downloads')); ?></a>
		</div>
	</li>
	<li data-id="actives"<?php if (($_['PAGE'] ?? -1) === 3): ?> class="active"<?php endif; ?>>
		<div class="badge" id="bactives">
			<a href="actives"><?php p($l->t('Active Downloads')); ?></a>
		</div>
	</li>
	<li data-id="waiting"<?php if (($_['PAGE'] ?? -1) === 4): ?> class="active"<?php endif; ?>>
		<div class="badge" id="bwaitings">
			<a href="waitings"><?php p($l->t('Waiting Downloads')); ?></a>
		</div>
	</li>
	<li data-id="stopped"<?php if (($_['PAGE'] ?? -1) === 5): ?> class="active"<?php endif; ?>>
		<div class="badge" id="bstopped">
			<a href="stopped"><?php p($l->t('Stopped Downloads')); ?></a>
		</div>
	</li>
	<li data-id="removed"<?php if (($_['PAGE'] ?? -1) === 6): ?> class="active"<?php endif; ?>>
		<div class="badge" id="bremoved">
			<a href="removed"><?php p($l->t('Removed Downloads')); ?></a>
		</div>
	</li>
</ul>
