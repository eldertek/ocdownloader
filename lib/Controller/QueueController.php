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
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Throwable;

class QueueController extends Controller {
	public function __construct(
		IRequest $request,
		private IUserSession $userSession,
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
	public function get(): JSONResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		if ($uid === '') {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('Authentication required')]);
		}

		try {
			$view = trim((string)($this->request->getParam('VIEW') ?? 'add'));
			[$statuses, $cleaned] = match ($view) {
				'completes' => [[0], [0, 1]],
				'removed' => [[4], [0, 1]],
				'actives' => [[1], [0, 1]],
				'stopped' => [[3], [0, 1]],
				'waitings' => [[2], [0, 1]],
				'all' => [[0, 1, 2, 3, 4], [0, 1]],
				default => [[0, 1, 2, 3], [0]],
			};

			$rows = $this->queueRepo->findByUserAndFilters($uid, $statuses, $cleaned);
			$isAria2 = $this->settings->isAria2();
			$downloadsFolder = $this->settings->getDownloadsFolder($uid);

			$queue = [];
			$completedFilesToScan = [];

			foreach ($rows as $row) {
				$gid = $row['gid'];
				$statusResp = $isAria2 ? $this->aria2->tellStatus($gid) : $this->curl->tellStatus($gid);
				$dlStatus = 5;

				if (is_array($statusResp)) {
					if (!isset($statusResp['error']) && is_array($statusResp['result'] ?? null)) {
						$res = $statusResp['result'];
						$completedLength = (float)($res['completedLength'] ?? 0);
						$totalLength = (float)($res['totalLength'] ?? 0);
						$progress = $totalLength > 0 ? ($completedLength / $totalLength) : 0.0;

						$rawStatus = (string)($res['status'] ?? '');
						$dlStatus = Tools::getDownloadStatusID($rawStatus);
						if ($rawStatus === 'complete' && $progress <= 0.0) {
							$progress = 1.0;
						}

						$progressStr = Tools::getProgressString($completedLength, $totalLength, $progress)
							?? (string)$this->l10n->t('N/A');

						$isTorrent = isset($res['bittorrent']);
						if ($isTorrent && $progress < 1.0) {
							$seeders = (int)($res['numSeeders'] ?? 0);
							$progressStr .= ' - <strong>' . htmlspecialchars((string)$this->l10n->t('Seeders'), ENT_QUOTES, 'UTF-8') . '</strong>: ' . $seeders;
						} elseif ($isTorrent && $progress >= 1.0) {
							$uploadLen = (float)($res['uploadLength'] ?? 0);
							$ratio = $completedLength > 0 ? round($uploadLen / $completedLength, 2) : 0.0;
							$progressStr .= ' - <strong>' . htmlspecialchars((string)$this->l10n->t('Uploaded'), ENT_QUOTES, 'UTF-8') . '</strong>: '
								. Tools::formatSizeUnits($uploadLen)
								. ' - <strong>' . htmlspecialchars((string)$this->l10n->t('Ratio'), ENT_QUOTES, 'UTF-8') . '</strong>: ' . $ratio;
						}

						$statusLabel = $rawStatus !== ''
							? (string)$this->l10n->t($row['status'] === 4 ? 'Removed' : ucfirst($rawStatus))
							: (string)$this->l10n->t('N/A');
						if ($isTorrent && $progress >= 1.0 && $dlStatus !== 3 && $row['status'] !== 4) {
							$statusLabel .= ' - ' . (string)$this->l10n->t('Seeding');
						}

						$speedLabel = (string)$this->l10n->t('N/A');
						if (isset($res['downloadSpeed'])) {
							if ($progress >= 1.0) {
								$upSpeed = (float)($res['uploadSpeed'] ?? 0);
								$speedLabel = ($isTorrent && $upSpeed > 0) ? (Tools::formatSizeUnits($upSpeed) . '/s') : '--';
							} elseif ($dlStatus === 4 || $row['status'] === 4) {
								$speedLabel = '--';
							} else {
								$speedLabel = Tools::formatSizeUnits($res['downloadSpeed']) . '/s';
							}
						}

						$queue[] = [
							'GID' => $gid,
							'PROGRESSVAL' => round($progress * 100, 2) . '%',
							'PROGRESS' => $progressStr,
							'STATUS' => $statusLabel,
							'STATUSID' => $row['status'] === 4 ? 4 : $dlStatus,
							'SPEED' => $speedLabel,
							'FILENAME' => $row['filename'],
							'FILENAME_SHORT' => Tools::getShortFilename($row['filename']),
							'PROTO' => $row['protocol'],
							'ISTORRENT' => $isTorrent,
						];

						if ($row['protocol'] === 'MAGNET' && $dlStatus === 0 && !empty($res['followedBy']) && is_array($res['followedBy'])) {
							foreach ($res['followedBy'] as $followedGid) {
								$followedGidStr = (string)$followedGid;
								if ($followedGidStr === '' || $this->queueRepo->exists($uid, $followedGidStr)) {
									continue;
								}
								$followedStatus = $isAria2 ? $this->aria2->tellStatus($followedGidStr) : $this->curl->tellStatus($followedGidStr);
								if (is_array($followedStatus) && !isset($followedStatus['error'])) {
									$torrentName = Tools::cleanString((string)($followedStatus['result']['bittorrent']['info']['name'] ?? ('torrent_' . $followedGidStr)));
									$this->queueRepo->insert($uid, $followedGidStr, $torrentName, 'TORRENT', 1, 0, time());
								}
							}
						}

						if ($row['status'] !== $dlStatus) {
							$this->queueRepo->updateStatusUnlessRemoved($uid, $gid, $dlStatus);
							if ($dlStatus === 0) {
								$completedFilesToScan[] = $row['filename'];
							}
						}
					} else {
						// If GID vanished from Aria2/cURL while already marked Complete or Removed in DB, preserve DB entry!
						if ($row['status'] === 0 || $row['status'] === 4) {
							$isComplete = $row['status'] === 0;
							$queue[] = [
								'GID' => $gid,
								'PROGRESSVAL' => $isComplete ? '100%' : '0%',
								'PROGRESS' => $isComplete ? (string)$this->l10n->t('Complete') : (string)$this->l10n->t('Removed'),
								'STATUS' => (string)$this->l10n->t($isComplete ? 'Complete' : 'Removed'),
								'STATUSID' => $row['status'],
								'SPEED' => '--',
								'FILENAME' => $row['filename'],
								'FILENAME_SHORT' => Tools::getShortFilename($row['filename']),
								'PROTO' => $row['protocol'],
								'ISTORRENT' => false,
							];
						} else {
							$this->queueRepo->deleteByUserAndGid($uid, $gid);
						}
					}
				} else {
					$queue[] = [
						'GID' => $gid,
						'PROGRESSVAL' => '0%',
						'PROGRESS' => $isAria2
							? (string)$this->l10n->t('Returned status is null. Verify Aria2c RPC daemon.')
							: (string)$this->l10n->t('Unable to read cURL status file'),
						'STATUS' => (string)$this->l10n->t('N/A'),
						'STATUSID' => $dlStatus,
						'SPEED' => (string)$this->l10n->t('N/A'),
						'FILENAME' => $row['filename'],
						'FILENAME_SHORT' => Tools::getShortFilename($row['filename']),
						'PROTO' => $row['protocol'],
						'ISTORRENT' => false,
					];
				}
			}

			foreach (array_unique($completedFilesToScan) as $fileName) {
				$this->storage->scanDownloadedTarget($uid, $downloadsFolder, $fileName);
			}

			return new JSONResponse([
				'ERROR' => false,
				'QUEUE' => $queue,
				'COUNTER' => $this->queueRepo->getCounters($uid),
			]);
		} catch (Throwable $e) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => $e->getMessage()]);
		}
	}

	#[NoAdminRequired]
	public function count(): JSONResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		try {
			return new JSONResponse([
				'ERROR' => false,
				'COUNTER' => $this->queueRepo->getCounters($uid),
			]);
		} catch (Throwable $e) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => $e->getMessage()]);
		}
	}

	#[NoAdminRequired]
	public function pause(): JSONResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		$gid = trim((string)($this->request->getParam('GID') ?? ''));
		if ($uid === '' || $gid === '') {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('Bad GID')]);
		}

		try {
			if (!$this->settings->isAria2()) {
				return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('Pause is only supported with ARIA2')]);
			}

			$status = $this->aria2->tellStatus($gid);
			$state = (string)($status['result']['status'] ?? '');
			$resGid = $gid;
			if (!isset($status['error']) && $state === 'active') {
				$pauseResp = $this->aria2->pause($gid);
				$resGid = (string)($pauseResp['result'] ?? '');
			}

			if ($resGid === $gid) {
				$this->queueRepo->updateStatus($uid, $gid, 3);
				return new JSONResponse(['ERROR' => false, 'MESSAGE' => (string)$this->l10n->t('The download has been paused')]);
			}
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('An error occurred while pausing the download')]);
		} catch (Throwable $e) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => $e->getMessage()]);
		}
	}

	#[NoAdminRequired]
	public function unPause(): JSONResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		$gid = trim((string)($this->request->getParam('GID') ?? ''));
		if ($uid === '' || $gid === '') {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('Bad GID')]);
		}

		try {
			if (!$this->settings->isAria2()) {
				return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('Resume is only supported with ARIA2')]);
			}

			$status = $this->aria2->tellStatus($gid);
			$state = (string)($status['result']['status'] ?? '');
			$resGid = $gid;
			if (!isset($status['error']) && $state === 'paused') {
				$unpauseResp = $this->aria2->unpause($gid);
				$resGid = (string)($unpauseResp['result'] ?? '');
			}

			if ($resGid === $gid) {
				$this->queueRepo->updateStatus($uid, $gid, 1);
				return new JSONResponse(['ERROR' => false, 'MESSAGE' => (string)$this->l10n->t('The download has been unpaused')]);
			}
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('An error occurred while unpausing the download')]);
		} catch (Throwable $e) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => $e->getMessage()]);
		}
	}

	#[NoAdminRequired]
	public function hide(): JSONResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		$gid = trim((string)($this->request->getParam('GID') ?? ''));
		if ($uid === '' || $gid === '') {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('Bad GID')]);
		}

		try {
			$this->queueRepo->markCleaned($uid, $gid);
			return new JSONResponse(['ERROR' => false, 'MESSAGE' => (string)$this->l10n->t('The download has been cleaned')]);
		} catch (Throwable $e) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => $e->getMessage()]);
		}
	}

	#[NoAdminRequired]
	public function hideAll(): JSONResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		$gids = $this->request->getParam('GIDS');
		if ($uid === '' || !is_array($gids) || $gids === []) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('No GIDS in the download queue')]);
		}

		try {
			$cleaned = [];
			foreach ($gids as $gid) {
				$gidStr = trim((string)$gid);
				if ($gidStr === '') {
					continue;
				}
				$this->queueRepo->markCleaned($uid, $gidStr);
				$cleaned[] = ['GID' => $gidStr];
			}

			return new JSONResponse([
				'ERROR' => false,
				'MESSAGE' => (string)$this->l10n->t('All downloads have been cleaned'),
				'QUEUE' => $cleaned,
			]);
		} catch (Throwable $e) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => $e->getMessage()]);
		}
	}

	#[NoAdminRequired]
	public function remove(): JSONResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		$gid = trim((string)($this->request->getParam('GID') ?? ''));
		if ($uid === '' || $gid === '') {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('Bad GID')]);
		}

		try {
			if ($this->stopEngineDownload($gid)) {
				$this->queueRepo->markRemoved($uid, $gid);
				return new JSONResponse(['ERROR' => false, 'MESSAGE' => (string)$this->l10n->t('The download has been removed')]);
			}
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('An error occurred while removing the download')]);
		} catch (Throwable $e) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => $e->getMessage()]);
		}
	}

	#[NoAdminRequired]
	public function removeAll(): JSONResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		$gids = $this->request->getParam('GIDS');
		if ($uid === '' || !is_array($gids) || $gids === []) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('No GIDS in the download queue')]);
		}

		try {
			$removed = [];
			foreach ($gids as $gid) {
				$gidStr = trim((string)$gid);
				if ($gidStr !== '' && $this->stopEngineDownload($gidStr)) {
					$this->queueRepo->markRemoved($uid, $gidStr);
					$removed[] = $gidStr;
				}
			}

			return new JSONResponse([
				'ERROR' => false,
				'MESSAGE' => (string)$this->l10n->t('All downloads have been removed'),
				'GIDS' => $removed,
			]);
		} catch (Throwable $e) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => $e->getMessage()]);
		}
	}

	#[NoAdminRequired]
	public function completelyRemove(): JSONResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		$gid = trim((string)($this->request->getParam('GID') ?? ''));
		if ($uid === '' || $gid === '') {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('Bad GID')]);
		}

		try {
			$this->purgeEngineResult($gid);
			$this->queueRepo->deleteByUserAndGid($uid, $gid);
			return new JSONResponse(['ERROR' => false, 'MESSAGE' => (string)$this->l10n->t('The download has been totally removed')]);
		} catch (Throwable $e) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => $e->getMessage()]);
		}
	}

	#[NoAdminRequired]
	public function completelyRemoveAll(): JSONResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		$gids = $this->request->getParam('GIDS');
		if ($uid === '' || !is_array($gids) || $gids === []) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => (string)$this->l10n->t('Bad GID')]);
		}

		try {
			$deleted = [];
			foreach ($gids as $gid) {
				$gidStr = trim((string)$gid);
				if ($gidStr === '') {
					continue;
				}
				$this->purgeEngineResult($gidStr);
				$this->queueRepo->deleteByUserAndGid($uid, $gidStr);
				$deleted[] = $gidStr;
			}

			return new JSONResponse([
				'ERROR' => false,
				'MESSAGE' => (string)$this->l10n->t('The download has been totally removed'),
				'GIDS' => $deleted,
			]);
		} catch (Throwable $e) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => $e->getMessage()]);
		}
	}

	private function stopEngineDownload(string $gid): bool {
		$isAria2 = $this->settings->isAria2();
		$status = $isAria2 ? $this->aria2->tellStatus($gid) : $this->curl->tellStatus($gid);
		$state = (string)($status['result']['status'] ?? '');

		if (is_array($status) && !isset($status['error']) && $state !== '' && $state !== 'error' && $state !== 'complete') {
			$resp = $isAria2
				? $this->aria2->forceRemove($gid)
				: $this->curl->remove($status['result']);
			return is_array($resp) && (string)($resp['result'] ?? '') === $gid;
		}
		if (!$isAria2 && is_array($status['result'] ?? null)) {
			$this->curl->remove($status['result']);
		}
		return true;
	}

	private function purgeEngineResult(string $gid): void {
		if ($this->settings->isAria2()) {
			$this->aria2->removeDownloadResult($gid);
		} else {
			$this->curl->removeDownloadResult($gid);
		}
	}
}
