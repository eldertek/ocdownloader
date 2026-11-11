<?php

declare(strict_types=1);

namespace OCA\Ocdownloader\Controller;

use OCA\Ocdownloader\AppInfo\Application;
use OCA\Ocdownloader\Service\Aria2Client;
use OCA\Ocdownloader\Service\CurlDownloader;
use OCA\Ocdownloader\Service\QueueRepository;
use OCA\Ocdownloader\Service\SettingsService;
use OCA\Ocdownloader\Service\StorageService;
use OCA\Ocdownloader\Service\Tools;
use OCA\Ocdownloader\Service\UrlValidator;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Throwable;

class FtpDownloaderController extends Controller {
	public function __construct(
		IRequest $request,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private IL10N $l10n,
		private SettingsService $settings,
		private StorageService $storage,
		private QueueRepository $queueRepo,
		private Aria2Client $aria2,
		private CurlDownloader $curl,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	public function add(): JSONResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		if ($uid === '') {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('Authentication required')]);
		}

		$rawUrl = trim((string)($this->request->getParam('FILE') ?? ''));
		$postOptions = $this->request->getParam('OPTIONS');
		$postOptions = is_array($postOptions) ? $postOptions : [];

		if ($rawUrl === '') {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('Please check the URL you have provided')]);
		}

		try {
			$isAdmin = $this->groupManager->isAdmin($uid);
			if (!$this->settings->isProtocolAllowed('AllowProtocolFTP', $isAdmin)) {
				return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('You are not allowed to use the FTP protocol')]);
			}

			$validatedUrl = UrlValidator::validateDownloadUrl($rawUrl, ['ftp', 'ftps']);
			$downloadsFolder = $this->settings->getDownloadsFolder($uid);
			$localDir = $this->storage->getLocalFolderPath($uid, $downloadsFolder);

			$target = Tools::extractFilenameFromUrl($validatedUrl, false);
			if (!empty($postOptions['FTPOutfilename']) && is_string($postOptions['FTPOutfilename']) && trim($postOptions['FTPOutfilename']) !== '') {
				$target = Tools::sanitiseFileName($postOptions['FTPOutfilename']);
			}
			if ($this->storage->nodeExists($uid, $downloadsFolder . '/' . $target)) {
				$target = time() . '_' . $target;
			}

			$options = [
				'dir' => $localDir,
				'out' => $target,
				'follow-torrent' => false,
			];

			if (!empty($postOptions['FTPUser']) && isset($postOptions['FTPPasswd']) && trim((string)$postOptions['FTPUser']) !== '') {
				$options['ftp-user'] = trim((string)$postOptions['FTPUser']);
				$options['ftp-passwd'] = (string)$postOptions['FTPPasswd'];
			}
			if (!empty($postOptions['FTPReferer']) && trim((string)$postOptions['FTPReferer']) !== '') {
				$options['referer'] = trim((string)$postOptions['FTPReferer']);
			}
			if (!empty($postOptions['FTPUseragent']) && trim((string)$postOptions['FTPUseragent']) !== '') {
				$options['user-agent'] = trim((string)$postOptions['FTPUseragent']);
			}
			if (isset($postOptions['FTPPasv'])) {
				$pasvVal = $postOptions['FTPPasv'];
				$options['ftp-pasv'] = ($pasvVal === true || $pasvVal === 'true' || $pasvVal === '1' || $pasvVal === 1);
			}

			$options = array_merge($options, $this->settings->getProxyOptions(false));
			$maxSpeed = (int)($this->settings->getAdminValue('MaxDownloadSpeed') ?? 0);
			if ($maxSpeed > 0) {
				$options['max-download-limit'] = $maxSpeed . 'K';
			}

			$isAria2 = $this->settings->isAria2();
			$addResult = $isAria2
				? $this->aria2->addUri([$validatedUrl], $options)
				: $this->curl->addUri($validatedUrl, $options);

			$gid = is_array($addResult) && isset($addResult['result']) ? (string)$addResult['result'] : '';
			if ($gid === '') {
				return new JSONResponse([
					'ERROR' => true,
					'MESSAGE' => (string)$this->l10n->t(
						$isAria2
							? 'Returned GID is null. Verify Aria2c RPC configuration.'
							: 'An error occurred while starting the cURL download'
					),
				]);
			}

			$proto = strtoupper((string)substr($validatedUrl, 0, (int)strpos($validatedUrl, ':')));
			$this->queueRepo->insert($uid, $gid, $target, $proto, 1, 0, time());

			usleep(500000);
			$statusResp = $isAria2 ? $this->aria2->tellStatus($gid) : $this->curl->tellStatus($gid);
			$resultData = is_array($statusResp['result'] ?? null) ? $statusResp['result'] : [];

			$completedLength = (float)($resultData['completedLength'] ?? 0);
			$totalLength = (float)($resultData['totalLength'] ?? 0);
			$progress = $totalLength > 0 ? ($completedLength / $totalLength) : 0.0;
			$rawStatus = (string)($resultData['status'] ?? 'active');
			$dlStatus = Tools::getDownloadStatusID($rawStatus);

			if ($dlStatus === 0) {
				$this->queueRepo->updateStatus($uid, $gid, 0);
				$this->storage->scanDownloadedTarget($uid, $downloadsFolder, $target);
			}

			$progressString = Tools::getProgressString($completedLength, $totalLength, $progress);

			return new JSONResponse([
				'ERROR' => false,
				'MESSAGE' => (string)$this->l10n->t('Download started'),
				'GID' => $gid,
				'PROGRESSVAL' => round($progress * 100, 2) . '%',
				'PROGRESS' => $progressString ?? (string)$this->l10n->t('N/A'),
				'STATUS' => $rawStatus !== '' ? (string)$this->l10n->t(ucfirst($rawStatus)) : (string)$this->l10n->t('N/A'),
				'STATUSID' => $dlStatus,
				'SPEED' => isset($resultData['downloadSpeed'])
					? Tools::formatSizeUnits($resultData['downloadSpeed']) . '/s'
					: (string)$this->l10n->t('N/A'),
				'FILENAME' => $target,
				'FILENAME_SHORT' => Tools::getShortFilename($target),
				'PROTO' => $proto,
				'ISTORRENT' => false,
			]);
		} catch (Throwable $e) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => $e->getMessage()]);
		}
	}
}
