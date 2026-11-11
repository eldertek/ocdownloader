<?php

declare(strict_types=1);

namespace OCA\Ocdownloader\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {
	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
	) {
	}

	public function getID(): string {
		return 'ocdownloader';
	}

	public function getName(): string {
		return $this->l10n->t('ocDownloader');
	}

	public function getPriority(): int {
		return 85;
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath('ocdownloader', 'ocdownloader.svg');
	}
}
