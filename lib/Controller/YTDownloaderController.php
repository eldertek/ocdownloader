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
use OCA\Ocdownloader\Service\YtDlpService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Throwable;

class YTDownloaderController extends Controller {
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
		private YtDlpService $ytDlp,
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
			if (!$this->settings->isProtocolAllowed('AllowProtocolYT', $isAdmin)) {
				return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('You are not allowed to use the YouTube protocol')]);
			}

			$extractAudio = isset($postOptions['YTExtractAudio'])
				&& ($postOptions['YTExtractAudio'] === true || $postOptions['YTExtractAudio'] === 'true' || $postOptions['YTExtractAudio'] === '1');
			$forceIPv4 = !isset($postOptions['YTForceIPv4'])
				|| ($postOptions['YTForceIPv4'] !== false && $postOptions['YTForceIPv4'] !== 'false' && $postOptions['YTForceIPv4'] !== '0');

			$dl = $this->ytDlp->resolveMedia($rawUrl, $extractAudio, $forceIPv4);

			$downloadsFolder = $this->settings->getDownloadsFolder($uid);
			$localDir = $this->storage->getLocalFolderPath($uid, $downloadsFolder);

			$target = $dl['FILENAME'];
			if ($this->storage->nodeExists($uid, $downloadsFolder . '/' . $target)) {
				$target = time() . '_' . $target;
			}

			$options = [
				'dir' => $localDir,
				'out' => $target,
			];
			$options = array_merge($options, $this->settings->getProxyOptions(true));
			$maxSpeed = (int)($this->settings->getAdminValue('MaxDownloadSpeed') ?? 0);
			if ($maxSpeed > 0) {
				$options['max-download-limit'] = $maxSpeed . 'K';
			}

			$isAria2 = $this->settings->isAria2();
			$addResult = $isAria2
				? $this->aria2->addUri([$dl['URL']], $options)
				: $this->curl->addUri($dl['URL'], $options);

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

			$this->queueRepo->insert($uid, $gid, $target, $dl['TYPE'], 1, 0, time());

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
				'PROTO' => $dl['TYPE'],
				'ISTORRENT' => false,
			]);
		} catch (Throwable $e) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => $e->getMessage()]);
		}
	}
}
