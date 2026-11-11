<?php

declare(strict_types=1);

namespace OCA\Ocdownloader\Controller;

use OCA\Ocdownloader\AppInfo\Application;
use OCA\Ocdownloader\Service\Aria2Client;
use OCA\Ocdownloader\Service\QueueRepository;
use OCA\Ocdownloader\Service\SettingsService;
use OCA\Ocdownloader\Service\StorageService;
use OCA\Ocdownloader\Service\Tools;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Throwable;

class BTDownloaderController extends Controller {
	public function __construct(
		IRequest $request,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private IL10N $l10n,
		private SettingsService $settings,
		private StorageService $storage,
		private QueueRepository $queueRepo,
		private Aria2Client $aria2,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function add(): JSONResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		if ($uid === '') {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('Authentication required')]);
		}

		$rawFile = trim((string)($this->request->getParam('FILE') ?? ''));
		$postOptions = $this->request->getParam('OPTIONS');
		$postOptions = is_array($postOptions) ? $postOptions : [];

		if ($rawFile === '') {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('Please select a torrent file')]);
		}

		try {
			$isAdmin = $this->groupManager->isAdmin($uid);
			if (!$this->settings->isProtocolAllowed('AllowProtocolBT', $isAdmin) || !$this->settings->isAria2()) {
				return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('You are not allowed to use the BitTorrent protocol')]);
			}

			$torrentsFolder = $this->settings->getTorrentsFolder($uid);
			$downloadsFolder = $this->settings->getDownloadsFolder($uid);
			$safeTorrentName = Tools::sanitiseFileName($rawFile);
			$torrentBytes = $this->storage->readTorrentContent($uid, $torrentsFolder, $safeTorrentName);

			$targetFolder = Tools::cleanString((string)preg_replace('/\.torrent$/i', '', $safeTorrentName));
			$relativeTargetDir = $downloadsFolder . '/' . $targetFolder;
			$alreadyExists = $this->storage->nodeExists($uid, $relativeTargetDir);
			$localTargetDir = $this->storage->getLocalFolderPath($uid, $relativeTargetDir);

			$seedRatio = $this->settings->getPersonalValue($uid, 'BTRatioToReach', '0.0') ?? '0.0';
			$seedTimeRaw = $this->settings->getPersonalValue($uid, 'BTSeedTimeToReach_BTSeedTimeToReachUnit', '1_w') ?? '1_w';
			$seedTimeMinutes = 10080.0;
			$parts = explode('_', $seedTimeRaw);
			if (count($parts) === 2) {
				$seedTimeMinutes = Tools::getMinutes($parts[0], $parts[1]);
			}

			$options = [
				'dir' => $localTargetDir,
				'seed-ratio' => $seedRatio,
				'seed-time' => (string)(int)round($seedTimeMinutes),
			];
			if ($alreadyExists) {
				$options['bt-hash-check-seed'] = true;
				$options['check-integrity'] = true;
			}

			$maxDown = (int)($this->settings->getAdminValue('MaxDownloadSpeed') ?? 0);
			if ($maxDown > 0) {
				$options['max-download-limit'] = $maxDown . 'K';
			}
			$maxUp = (int)($this->settings->getAdminValue('BTMaxUploadSpeed') ?? 0);
			if ($maxUp > 0) {
				$options['max-upload-limit'] = $maxUp . 'K';
			}
			$options = array_merge($options, $this->settings->getProxyOptions(false));

			$addResult = $this->aria2->addTorrent(base64_encode($torrentBytes), $options);
			$gid = is_array($addResult) && isset($addResult['result']) ? (string)$addResult['result'] : '';
			if ($gid === '') {
				return new JSONResponse([
					'ERROR' => true,
					'MESSAGE' => (string)$this->l10n->t('Returned GID is null. Verify Aria2c RPC configuration.'),
				]);
			}

			$this->queueRepo->insert($uid, $gid, $targetFolder, 'BitTorrent', 1, 0, time());

			$rmTorrent = isset($postOptions['BTRMTorrent'])
				&& ($postOptions['BTRMTorrent'] === true || $postOptions['BTRMTorrent'] === 'true' || $postOptions['BTRMTorrent'] === '1');
			if ($rmTorrent) {
				$this->storage->deleteNodeIfExists($uid, $torrentsFolder . '/' . $safeTorrentName);
			}

			usleep(500000);
			$statusResp = $this->aria2->tellStatus($gid);
			$resultData = is_array($statusResp['result'] ?? null) ? $statusResp['result'] : [];

			$completedLength = (float)($resultData['completedLength'] ?? 0);
			$totalLength = (float)($resultData['totalLength'] ?? 0);
			$progress = $totalLength > 0 ? ($completedLength / $totalLength) : 0.0;
			$rawStatus = (string)($resultData['status'] ?? 'active');
			$dlStatus = Tools::getDownloadStatusID($rawStatus);
			$numSeeders = (int)($resultData['numSeeders'] ?? 0);
			$progressStr = Tools::getProgressString($completedLength, $totalLength, $progress) ?? (string)$this->l10n->t('N/A');

			return new JSONResponse([
				'ERROR' => false,
				'MESSAGE' => (string)$this->l10n->t('Download started'),
				'GID' => $gid,
				'PROGRESSVAL' => round($progress * 100, 2) . '%',
				'PROGRESS' => $progressStr . ' - ' . (string)$this->l10n->t('Seeders') . ': ' . $numSeeders,
				'STATUS' => $rawStatus !== '' ? (string)$this->l10n->t(ucfirst($rawStatus)) : (string)$this->l10n->t('N/A'),
				'STATUSID' => $dlStatus,
				'SPEED' => isset($resultData['downloadSpeed'])
					? Tools::formatSizeUnits($resultData['downloadSpeed']) . '/s'
					: (string)$this->l10n->t('N/A'),
				'FILENAME' => $targetFolder,
				'FILENAME_SHORT' => Tools::getShortFilename($targetFolder),
				'PROTO' => 'BitTorrent',
				'ISTORRENT' => true,
			]);
		} catch (Throwable $e) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => $e->getMessage()]);
		}
	}

	#[NoAdminRequired]
	public function listTorrentFiles(): JSONResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		if ($uid === '') {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('Authentication required')]);
		}

		try {
			$isAdmin = $this->groupManager->isAdmin($uid);
			if (!$this->settings->isProtocolAllowed('AllowProtocolBT', $isAdmin)) {
				return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('You are not allowed to use the BitTorrent protocol')]);
			}

			$torrentsFolder = $this->settings->getTorrentsFolder($uid);
			$files = $this->storage->listTorrentFiles($uid, $torrentsFolder);
			return new JSONResponse(['ERROR' => false, 'FILES' => $files]);
		} catch (Throwable $e) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => $e->getMessage()]);
		}
	}

	#[NoAdminRequired]
	public function uploadFiles(): JSONResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		if ($uid === '') {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('Authentication required')]);
		}

		try {
			$isAdmin = $this->groupManager->isAdmin($uid);
			if (!$this->settings->isProtocolAllowed('AllowProtocolBT', $isAdmin)) {
				return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('You are not allowed to use the BitTorrent protocol')]);
			}

			if (!isset($_FILES['files'])) {
				return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('Error while uploading torrent file')]);
			}

			$torrentsFolder = $this->settings->getTorrentsFolder($uid);
			$files = $_FILES['files'];
			$names = is_array($files['name']) ? $files['name'] : [$files['name']];
			$tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
			$errors = is_array($files['error']) ? $files['error'] : [$files['error']];

			$savedCount = 0;
			foreach ($names as $idx => $originalName) {
				$err = (int)($errors[$idx] ?? UPLOAD_ERR_NO_FILE);
				$tmpPath = (string)($tmpNames[$idx] ?? '');
				if ($err !== UPLOAD_ERR_OK || $tmpPath === '' || !is_uploaded_file($tmpPath)) {
					continue;
				}
				$this->storage->saveUploadedTorrent($uid, $torrentsFolder, (string)$originalName, $tmpPath);
				$savedCount++;
			}

			if ($savedCount === 0) {
				return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('Error while uploading torrent file')]);
			}

			return new JSONResponse(['ERROR' => false, 'MESSAGE' => (string)$this->l10n->t('Upload OK')]);
		} catch (Throwable $e) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => $e->getMessage()]);
		}
	}
}
