<?php

declare(strict_types=1);

namespace OCA\Ocdownloader\Controller;

use OCA\Ocdownloader\AppInfo\Application;
use OCA\Ocdownloader\Service\SettingsService;
use OCA\Ocdownloader\Service\StorageService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

class IndexController extends Controller {
	public function __construct(
		IRequest $request,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private SettingsService $settings,
		private StorageService $storage,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function add(): TemplateResponse {
		$uid = $this->getUid();
		$isAdmin = $uid !== '' && $this->groupManager->isAdmin($uid);
		$this->syncDownloadsFolder($uid);

		return $this->renderPage('add', [
			'PAGE' => 0,
			'TTSFOLD' => $this->settings->getPersonalValue($uid, 'TorrentsFolder') ?? '',
			'WD' => $this->settings->getWhichDownloader(),
			'AllowProtocolHTTP' => $this->settings->isProtocolAllowed('AllowProtocolHTTP', $isAdmin),
			'AllowProtocolFTP' => $this->settings->isProtocolAllowed('AllowProtocolFTP', $isAdmin),
			'AllowProtocolYT' => $this->settings->isProtocolAllowed('AllowProtocolYT', $isAdmin),
			'AllowProtocolBT' => $this->settings->isProtocolAllowed('AllowProtocolBT', $isAdmin),
		]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function all(): TemplateResponse {
		$this->syncDownloadsFolder($this->getUid());
		return $this->renderPage('all', [
			'PAGE' => 1,
			'WD' => $this->settings->getWhichDownloader(),
		]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function completes(): TemplateResponse {
		$this->syncDownloadsFolder($this->getUid());
		return $this->renderPage('completes', [
			'PAGE' => 2,
			'WD' => $this->settings->getWhichDownloader(),
		]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function actives(): TemplateResponse {
		$this->syncDownloadsFolder($this->getUid());
		return $this->renderPage('actives', [
			'PAGE' => 3,
			'WD' => $this->settings->getWhichDownloader(),
		]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function waitings(): TemplateResponse {
		$this->syncDownloadsFolder($this->getUid());
		return $this->renderPage('waitings', [
			'PAGE' => 4,
			'WD' => $this->settings->getWhichDownloader(),
		]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function stopped(): TemplateResponse {
		$this->syncDownloadsFolder($this->getUid());
		return $this->renderPage('stopped', [
			'PAGE' => 5,
			'WD' => $this->settings->getWhichDownloader(),
		]);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function removed(): TemplateResponse {
		$this->syncDownloadsFolder($this->getUid());
		return $this->renderPage('removed', [
			'PAGE' => 6,
			'WD' => $this->settings->getWhichDownloader(),
		]);
	}

	/**
	 * @param array<string, mixed> $params
	 */
	private function renderPage(string $template, array $params): TemplateResponse {
		$response = new TemplateResponse(Application::APP_ID, $template, $params);
		$csp = new ContentSecurityPolicy();
		$csp->allowInlineStyle(true);
		$response->setContentSecurityPolicy($csp);
		return $response;
	}

	private function getUid(): string {
		$user = $this->userSession->getUser();
		return $user !== null ? $user->getUID() : '';
	}

	private function syncDownloadsFolder(string $uid): void {
		if ($uid === '') {
			return;
		}
		$downloadsFolder = $this->settings->getDownloadsFolder($uid);
		$this->storage->scanDownloadedTarget($uid, $downloadsFolder);
	}
}
