<?php

declare(strict_types=1);

namespace OCA\Ocdownloader\Settings;

use OCA\Ocdownloader\Service\SettingsService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\Settings\ISettings;

class Personal implements ISettings {
	public function __construct(
		private SettingsService $settings,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
	) {
	}

	public function getForm(): TemplateResponse {
		$user = $this->userSession->getUser();
		$uid = $user !== null ? $user->getUID() : '';
		$isAdmin = $uid !== '' && $this->groupManager->isAdmin($uid);

		$personalValues = $this->settings->getAllPersonalValues($uid);
		$parameters = [
			'AllowProtocolBT' => $this->settings->isProtocolAllowed('AllowProtocolBT', $isAdmin) && $this->settings->isAria2(),
			'OCDS_DownloadsFolder' => $personalValues['DownloadsFolder'] ?? 'Downloads',
			'OCDS_TorrentsFolder' => $personalValues['TorrentsFolder'] ?? 'Downloads/Files/Torrents',
			'OCDS_BTRatioToReach' => $personalValues['BTRatioToReach'] ?? '0.0',
			'OCDS_BTSeedTimeToReach_BTSeedTimeToReachUnit' => $personalValues['BTSeedTimeToReach_BTSeedTimeToReachUnit'] ?? '1_w',
		];

		return new TemplateResponse('ocdownloader', 'settings/personal', $parameters, '');
	}

	public function getSection(): string {
		return 'ocdownloader';
	}

	public function getPriority(): int {
		return 50;
	}
}
