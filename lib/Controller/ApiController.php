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
use OCA\Ocdownloader\Service\YtDlpService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use Throwable;

class ApiController extends Controller {
	public function __construct(
		IRequest $request,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private IConfig $config,
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
	#[NoCSRFRequired]
	public function checkAddonVersion(?string $Version = null): JSONResponse {
		$version = trim((string)($Version ?? $this->request->getParam('Version') ?? '0.0.0'));
		$appVersion = $this->config->getAppValue(Application::APP_ID, 'installed_version', '2.0.0');
		return new JSONResponse([
			'RESULT' => version_compare($version, $appVersion, '<='),
			'VERSION' => $appVersion,
		]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function add(?string $URL = null): JSONResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		if ($uid === '') {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => 'Authenticationrequired'], 401);
		}

		$rawUrl = $URL
			?? $this->request->getParam('URL')
			?? $this->request->getParam('url')
			?? $this->request->getParam('FILE')
			?? '';
		$rawUrl = trim((string)$rawUrl);
		if ($rawUrl !== '' && !str_contains($rawUrl, '://') && !str_starts_with(strtolower($rawUrl), 'magnet:')) {
			$rawUrl = rawurldecode($rawUrl);
		}

		if ($rawUrl === '') {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => 'InvalidURL']);
		}

		try {
			$isAdmin = $this->groupManager->isAdmin($uid);
			$isMagnet = Tools::isMagnet($rawUrl);
			$isAria2 = $this->settings->isAria2();

			if ($isMagnet) {
				if (!$this->settings->isProtocolAllowed('AllowProtocolHTTP', $isAdmin) || !$isAria2) {
					return new JSONResponse(['ERROR' => true, 'MESSAGE' => 'Notallowedtouseprotocolhttp']);
				}
				$validatedUrl = UrlValidator::validateMagnetUri($rawUrl);
				$dl = [
					'URL' => $validatedUrl,
					'FILENAME' => Tools::extractFilenameFromUrl($validatedUrl, true),
					'PROTO' => 'MAGNET',
				];
			} elseif ($this->isVideoSiteUrl($rawUrl)) {
				if (!$this->settings->isProtocolAllowed('AllowProtocolYT', $isAdmin)) {
					return new JSONResponse(['ERROR' => true, 'MESSAGE' => 'Notallowedtouseprotocolyt']);
				}
				$media = $this->ytDlp->resolveMedia($rawUrl, false, true);
				$dl = [
					'URL' => $media['URL'],
					'FILENAME' => $media['FILENAME'],
					'PROTO' => 'YT ' . (string)$this->l10n->t('Video'),
				];
			} else {
				$validatedUrl = UrlValidator::validateDownloadUrl($rawUrl, ['http', 'https', 'ftp', 'ftps']);
				$scheme = strtolower((string)parse_url($validatedUrl, PHP_URL_SCHEME));
				if (str_starts_with($scheme, 'http') && !$this->settings->isProtocolAllowed('AllowProtocolHTTP', $isAdmin)) {
					return new JSONResponse(['ERROR' => true, 'MESSAGE' => 'Notallowedtouseprotocolhttp']);
				}
				if (str_starts_with($scheme, 'ftp') && !$this->settings->isProtocolAllowed('AllowProtocolFTP', $isAdmin)) {
					return new JSONResponse(['ERROR' => true, 'MESSAGE' => 'Notallowedtouseprotocolftp']);
				}
				$dl = [
					'URL' => $validatedUrl,
					'FILENAME' => Tools::extractFilenameFromUrl($validatedUrl, false),
					'PROTO' => strtoupper($scheme),
				];
			}

			$downloadsFolder = $this->settings->getDownloadsFolder($uid);
			$localDir = $this->storage->getLocalFolderPath($uid, $downloadsFolder);
			if ($this->storage->nodeExists($uid, $downloadsFolder . '/' . $dl['FILENAME'])) {
				$dl['FILENAME'] = time() . '_' . $dl['FILENAME'];
			}

			$options = [
				'dir' => $localDir,
				'out' => $dl['FILENAME'],
				'follow-torrent' => $isMagnet,
			];
			$options = array_merge($options, $this->settings->getProxyOptions(false));
			$maxSpeed = (int)($this->settings->getAdminValue('MaxDownloadSpeed') ?? 0);
			if ($maxSpeed > 0) {
				$options['max-download-limit'] = $maxSpeed . 'K';
			}

			$addResult = $isAria2
				? $this->aria2->addUri([$dl['URL']], $options)
				: $this->curl->addUri($dl['URL'], $options);

			$gid = is_array($addResult) && isset($addResult['result']) ? (string)$addResult['result'] : '';
			if ($gid === '') {
				return new JSONResponse(['ERROR' => true, 'MESSAGE' => 'ReturnedGIDisnullIsAria2crunningasadaemon']);
			}

			$this->queueRepo->insert($uid, $gid, $dl['FILENAME'], $dl['PROTO'], 1, 1, time());

			usleep(500000);
			$statusResp = $isAria2 ? $this->aria2->tellStatus($gid) : $this->curl->tellStatus($gid);
			$rawStatus = (string)($statusResp['result']['status'] ?? '');
			if (Tools::getDownloadStatusID($rawStatus) === 0) {
				$this->queueRepo->updateStatus($uid, $gid, 0);
				$this->storage->scanDownloadedTarget($uid, $downloadsFolder, $dl['FILENAME']);
			}

			return new JSONResponse([
				'ERROR' => false,
				'FILENAME' => $dl['FILENAME'],
				'GID' => $gid,
			]);
		} catch (Throwable $e) {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => $e->getMessage()]);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function getQueue(): JSONResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		if ($uid === '') {
			return new JSONResponse(['ERROR' => true, 'MESSAGE' => 'Authenticationrequired', 'QUEUE' => null, 'COUNTER' => null], 401);
		}

		try {
			$rows = $this->queueRepo->findByUserAndFilters($uid, [0, 1, 2, 3, 4], [0, 1]);
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

						$progressStr = Tools::getProgressString($completedLength, $totalLength, $progress);
						$isTorrent = isset($res['bittorrent']);
						$upLen = (float)($res['uploadLength'] ?? 0);

						$queue[] = [
							'GID' => $gid,
							'PROGRESSVAL' => round($progress * 100, 2),
							'PROGRESS' => [
								'Message' => null,
								'ProgressString' => $progressStr ?? 'N_A',
								'NumSeeders' => ($isTorrent && $progress < 1.0) ? ($res['numSeeders'] ?? null) : null,
								'UploadLength' => ($isTorrent && $progress >= 1.0) ? Tools::formatSizeUnits($upLen) : null,
								'Ratio' => ($isTorrent && $completedLength > 0) ? round($upLen / $completedLength, 2) : null,
							],
							'STATUS' => [
								'Value' => $rawStatus !== '' ? ($row['status'] === 4 ? 'Removed' : ucfirst($rawStatus)) : 'N_A',
								'Seeding' => $isTorrent && $progress >= 1.0 && $dlStatus !== 3,
							],
							'STATUSID' => $row['status'] === 4 ? 4 : $dlStatus,
							'SPEED' => isset($res['downloadSpeed'])
								? ($progress >= 1.0
									? ($isTorrent
										? ((float)($res['uploadSpeed'] ?? 0) <= 0 ? '--' : (Tools::formatSizeUnits($res['uploadSpeed']) . '/s'))
										: '--')
									: ($dlStatus === 4 ? '--' : (Tools::formatSizeUnits($res['downloadSpeed']) . '/s')))
								: 'N_A',
							'FILENAME' => $row['filename'],
							'PROTO' => $row['protocol'],
							'ISTORRENT' => $isTorrent,
						];

						if ($row['status'] !== $dlStatus) {
							$this->queueRepo->updateStatusUnlessRemoved($uid, $gid, $dlStatus);
							if ($dlStatus === 0) {
								$completedFilesToScan[] = $row['filename'];
							}
						}
					} else {
						$queue[] = [
							'GID' => $gid,
							'PROGRESSVAL' => $row['status'] === 0 ? 100 : 0,
							'PROGRESS' => [
								'Message' => $row['status'] === 0 ? null : 'ErrorGIDnotfound',
								'ProgressString' => $row['status'] === 0 ? '100%' : null,
								'NumSeeders' => null,
								'UploadLength' => null,
								'Ratio' => null,
							],
							'STATUS' => [
								'Value' => $row['status'] === 0 ? 'Complete' : ($row['status'] === 4 ? 'Removed' : 'N_A'),
								'Seeding' => false,
							],
							'STATUSID' => $row['status'] === 0 || $row['status'] === 4 ? $row['status'] : $dlStatus,
							'SPEED' => '--',
							'FILENAME' => $row['filename'],
							'PROTO' => $row['protocol'],
							'ISTORRENT' => false,
						];
					}
				} else {
					$queue[] = [
						'GID' => $gid,
						'PROGRESSVAL' => 0,
						'PROGRESS' => [
							'Message' => $isAria2 ? 'ReturnedstatusisnullIsAria2crunningasadaemon' : 'Unabletofinddownloadstatusfile',
							'ProgressString' => null,
							'NumSeeders' => null,
							'UploadLength' => null,
							'Ratio' => null,
						],
						'STATUS' => [
							'Value' => 'N_A',
							'Seeding' => null,
						],
						'STATUSID' => $dlStatus,
						'SPEED' => 'N_A',
						'FILENAME' => $row['filename'],
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
				'MESSAGE' => null,
				'QUEUE' => $queue,
				'COUNTER' => $this->queueRepo->getCounters($uid),
			]);
		} catch (Throwable $e) {
			return new JSONResponse([
				'ERROR' => true,
				'MESSAGE' => $e->getMessage(),
				'QUEUE' => null,
				'COUNTER' => null,
			]);
		}
	}

	private function isVideoSiteUrl(string $url): bool {
		return preg_match('#^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?v=|shorts/)|youtu\.be/)#i', $url) === 1;
	}
}
