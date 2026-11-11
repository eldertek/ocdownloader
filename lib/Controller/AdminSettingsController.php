<?php

declare(strict_types=1);

namespace OCA\Ocdownloader\Controller;

use OCA\Ocdownloader\AppInfo\Application;
use OCA\Ocdownloader\Service\Aria2Client;
use OCA\Ocdownloader\Service\QueueRepository;
use OCA\Ocdownloader\Service\SettingsService;
use OCA\Ocdownloader\Service\UrlValidator;
use OCA\Ocdownloader\Service\YtDlpService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

class AdminSettingsController extends Controller {
	public function __construct(
		IRequest $request,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private IL10N $l10n,
		private SettingsService $settings,
		private QueueRepository $queueRepo,
		private Aria2Client $aria2,
		private YtDlpService $ytDlp,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	public function save(): JSONResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		if ($uid === '' || !$this->groupManager->isAdmin($uid)) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('Access denied')], 403);
		}

		$postKey = trim((string)($this->request->getParam('KEY') ?? ''));
		$rawVal = $this->request->getParam('VAL');
		$postVal = $rawVal !== null ? trim((string)$rawVal) : '';

		if ($postKey === '' || !in_array($postKey, SettingsService::ADMIN_KEYS, true)) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('Unknown field')]);
		}

		$error = false;
		$message = null;
		$valueToSave = $postVal !== '' ? $postVal : null;

		if ($valueToSave !== null) {
			switch ($postKey) {
				case 'YTDLBinary':
					if (!is_file($valueToSave) || !is_executable($valueToSave)) {
						$valueToSave = null;
						$error = true;
						$message = (string)$this->l10n->t('Unable to find executable yt-dlp or youtube-dl binary at this path');
					}
					break;

				case 'ProxyAddress':
					if (!UrlValidator::isValidAdminEndpointUrl($valueToSave)) {
						$valueToSave = null;
						$error = true;
						$message = (string)$this->l10n->t('Invalid proxy address URL');
					}
					break;

				case 'AriaAddress':
					if (!UrlValidator::isValidAdminEndpointUrl($valueToSave)) {
						$valueToSave = null;
						$error = true;
						$message = (string)$this->l10n->t('Invalid Aria2 address URL');
					}
					break;

				case 'ProxyPort':
				case 'AriaPort':
					if (!ctype_digit($valueToSave)) {
						$valueToSave = null;
						$error = true;
						$message = (string)$this->l10n->t('Port should be a numeric value');
					} else {
						$port = (int)$valueToSave;
						if ($port < 1 || $port > 65535) {
							$valueToSave = null;
							$error = true;
							$message = (string)$this->l10n->t('Port should be a value from 1 to 65535');
						}
					}
					break;

				case 'WhichDownloader':
					$valueToSave = in_array($valueToSave, ['ARIA2', 'CURL'], true) ? $valueToSave : 'ARIA2';
					if ($valueToSave !== 'ARIA2') {
						$this->aria2->resetAllDownloads($this->queueRepo);
					}
					break;

				case 'ProxyOnlyWithYTDL':
				case 'AllowProtocolHTTP':
				case 'AllowProtocolFTP':
				case 'AllowProtocolYT':
				case 'AllowProtocolBT':
					$valueToSave = in_array($valueToSave, ['Y', 'N'], true) ? $valueToSave : 'N';
					break;

				case 'MaxDownloadSpeed':
				case 'BTMaxUploadSpeed':
					if (!ctype_digit($valueToSave)) {
						$valueToSave = null;
						$error = true;
						$message = (string)$this->l10n->t('Speed limit setting should be a numeric value');
					}
					break;
			}
		}

		if (!$error) {
			$this->settings->setAdminValue($postKey, $valueToSave);
		}

		return new JSONResponse([
			'ERROR' => $error,
			'MESSAGE' => $message ?? (string)$this->l10n->t('Saved'),
		]);
	}

	#[NoAdminRequired]
	public function get(): JSONResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		$isAdmin = $uid !== '' && $this->groupManager->isAdmin($uid);

		$requestedKeys = $this->request->getParam('KEYS');
		if (!is_array($requestedKeys)) {
			$requestedKeys = SettingsService::PUBLIC_ADMIN_KEYS;
		}

		$allowedKeys = $isAdmin
			? array_diff(SettingsService::ADMIN_KEYS, ['ProxyPasswd', 'AriaToken'])
			: SettingsService::PUBLIC_ADMIN_KEYS;

		$result = [];
		foreach ($requestedKeys as $key) {
			$keyStr = (string)$key;
			if (!in_array($keyStr, $allowedKeys, true)) {
				continue;
			}
			$val = $this->settings->getAdminValue($keyStr);
			if ($val === null) {
				$val = match ($keyStr) {
					'YTDLBinary' => $this->ytDlpServiceDefault(),
					'YTDLAudioFormat' => 'bestaudio',
					'YTDLVideoFormat' => 'best[width<=1280]/best',
					'WhichDownloader' => 'ARIA2',
					'AllowProtocolHTTP', 'AllowProtocolFTP', 'AllowProtocolYT', 'AllowProtocolBT' => 'Y',
					default => null,
				};
			}
			$result[$keyStr] = $val;
		}

		return new JSONResponse(['ERROR' => false, 'VALS' => $result]);
	}

	private function ytDlpServiceDefault(): string {
		return $this->ytDlp->getDefaultBinaryDisplayPath();
	}
}
