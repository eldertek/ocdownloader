<?php

declare(strict_types=1);

namespace OCA\Ocdownloader\Settings;

use OCA\Ocdownloader\Service\SettingsService;
use OCA\Ocdownloader\Service\YtDlpService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

class Admin implements ISettings {
	public function __construct(
		private SettingsService $settings,
		private YtDlpService $ytDlpService,
	) {
	}

	public function getForm(): TemplateResponse {
		$all = $this->settings->getAllAdminValues();
		$parameters = [
			'OCDS_YTDLBinary' => $all['YTDLBinary'] ?? $this->ytDlpService->getDefaultBinaryDisplayPath(),
			'OCDS_YTDLAudioFormat' => $all['YTDLAudioFormat'] ?? 'bestaudio',
			'OCDS_YTDLVideoFormat' => $all['YTDLVideoFormat'] ?? 'best[width<=1280]/best',
			'OCDS_WhichDownloader' => $this->settings->getWhichDownloader(),
			'OCDS_AllowProtocolHTTP' => $all['AllowProtocolHTTP'] ?? 'Y',
			'OCDS_AllowProtocolFTP' => $all['AllowProtocolFTP'] ?? 'Y',
			'OCDS_AllowProtocolYT' => $all['AllowProtocolYT'] ?? 'Y',
			'OCDS_AllowProtocolBT' => $all['AllowProtocolBT'] ?? 'Y',
			'OCDS_ProxyOnlyWithYTDL' => $all['ProxyOnlyWithYTDL'] ?? 'N',
			'OCDS_ProxyAddress' => $all['ProxyAddress'] ?? '',
			'OCDS_ProxyPort' => $all['ProxyPort'] ?? '',
			'OCDS_ProxyUser' => $all['ProxyUser'] ?? '',
			'OCDS_AriaAddress' => $all['AriaAddress'] ?? '127.0.0.1',
			'OCDS_AriaPort' => $all['AriaPort'] ?? '6800',
			'OCDS_MaxDownloadSpeed' => $all['MaxDownloadSpeed'] ?? '',
			'OCDS_BTMaxUploadSpeed' => $all['BTMaxUploadSpeed'] ?? '',
		];

		return new TemplateResponse('ocdownloader', 'settings/admin', $parameters, '');
	}

	public function getSection(): string {
		return 'ocdownloader';
	}

	public function getPriority(): int {
		return 50;
	}
}
