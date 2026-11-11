<?php

declare(strict_types=1);

namespace OCA\Ocdownloader\Controller;

use OCA\Ocdownloader\AppInfo\Application;
use OCA\Ocdownloader\Service\SettingsService;
use OCA\Ocdownloader\Service\StorageService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Throwable;

class PersonalSettingsController extends Controller {
	public function __construct(
		IRequest $request,
		private IUserSession $userSession,
		private IL10N $l10n,
		private SettingsService $settings,
		private StorageService $storage,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function save(): JSONResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		if ($uid === '') {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('Authentication required')]);
		}

		$rawKey = trim((string)($this->request->getParam('KEY') ?? ''));
		$rawVal = trim((string)($this->request->getParam('VAL') ?? ''));
		if ($rawKey === '') {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('Undefined field')]);
		}

		$key = str_starts_with($rawKey, 'OCD') ? substr($rawKey, 3) : $rawKey;
		if (!in_array($key, SettingsService::PERSONAL_KEYS, true)) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('Unknown field')]);
		}

		try {
			$message = '';
			$valueToSave = $rawVal !== '' ? ltrim($rawVal, '/') : null;

			if (($key === 'DownloadsFolder' || $key === 'TorrentsFolder') && $valueToSave !== null) {
				$cleanFolder = $this->storage->normalizeRelativePath($valueToSave);
				if (!$this->storage->nodeExists($uid, $cleanFolder)) {
					$this->storage->ensureUserFolder($uid, $cleanFolder);
					$message = (string)$this->l10n->t('The folder did not exist and has been created.');
				}
				$valueToSave = $cleanFolder;
			}

			$this->settings->setPersonalValue($uid, $key, $valueToSave);

			return new JSONResponse([
				'ERROR' => false,
				'MESSAGE' => $message !== '' ? $message : (string)$this->l10n->t('Saved'),
			]);
		} catch (Throwable $e) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => $e->getMessage()]);
		}
	}

	#[NoAdminRequired]
	public function get(): JSONResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		$personalSettings = [];
		foreach (SettingsService::PERSONAL_KEYS as $key) {
			$val = $this->settings->getPersonalValue($uid, $key);
			if ($val === null) {
				$val = match ($key) {
					'DownloadsFolder' => 'Downloads',
					'TorrentsFolder' => 'Downloads/Files/Torrents',
					'BTRatioToReach' => '0.0',
					'BTSeedTimeToReach_BTSeedTimeToReachUnit' => '1_w',
					default => null,
				};
			}
			$personalSettings[$key] = $val;
		}

		return new JSONResponse(['ERROR' => false, 'VALS' => $personalSettings]);
	}
}
