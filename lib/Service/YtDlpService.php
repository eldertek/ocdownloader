<?php

declare(strict_types=1);

namespace OCA\Ocdownloader\Service;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class YtDlpService {
	public function __construct(
		private SettingsService $settings,
	) {
	}

	public function resolveBinary(): ?string {
		$configured = $this->settings->getAdminValue('YTDLBinary');
		if ($configured !== null) {
			$clean = trim(str_replace('\\ ', ' ', $configured));
			if ($clean !== '' && is_file($clean) && is_executable($clean)) {
				return $clean;
			}
		}

		$finder = new ExecutableFinder();
		$searchDirs = ['/usr/local/bin', '/usr/bin', '/bin'];
		$ytdlp = $finder->find('yt-dlp', null, $searchDirs);
		if ($ytdlp !== null) {
			return $ytdlp;
		}
		return $finder->find('youtube-dl', null, $searchDirs);
	}

	public function getDefaultBinaryDisplayPath(): string {
		return $this->resolveBinary() ?? '/usr/local/bin/yt-dlp';
	}

	/**
	 * Resolve direct media stream URL and output filename via yt-dlp (or youtube-dl fallback)
	 * without any shell string interpolation.
	 *
	 * @return array{URL:string, FILENAME:string, TYPE:string}
	 */
	public function resolveMedia(string $url, bool $extractAudio = false, bool $forceIPv4 = true): array {
		$validatedUrl = UrlValidator::validateDownloadUrl($url, ['http', 'https']);

		$binary = $this->resolveBinary();
		if ($binary === null) {
			throw new RuntimeException('yt-dlp (or youtube-dl) binary was not found on the server');
		}

		$audioFormat = $this->settings->getAdminValue('YTDLAudioFormat', 'bestaudio') ?? 'bestaudio';
		$videoFormat = $this->settings->getAdminValue('YTDLVideoFormat', 'best[width<=1280]/best') ?? 'best[width<=1280]/best';
		$selectedFormat = $extractAudio ? $audioFormat : $videoFormat;

		// Sanitise format expression to safe characters
		if (preg_match('/^[\w\[\]<=!>\/+,.*:-]{1,128}$/', $selectedFormat) !== 1) {
			$selectedFormat = $extractAudio ? 'bestaudio' : 'best';
		}

		$playlistIndex = 1;
		if (preg_match('/[?&]index=(\d+)/', $validatedUrl, $matches) === 1) {
			$playlistIndex = max(1, (int)$matches[1]);
		}

		$args = [
			$binary,
			'--no-config',
			'--no-warnings',
			'--ignore-errors',
			'--playlist-items',
			(string)$playlistIndex,
			'--get-url',
			'--get-filename',
			'-f',
			$selectedFormat,
		];

		if ($forceIPv4) {
			$args[] = '-4';
		}

		$proxyOpts = $this->settings->getProxyOptions(true);
		if (!empty($proxyOpts['all-proxy'])) {
			$args[] = '--proxy';
			$args[] = (string)$proxyOpts['all-proxy'];
		}

		$args[] = '--';
		$args[] = $validatedUrl;

		$process = new Process($args, null, ['LC_ALL' => 'C.UTF-8', 'LANG' => 'C.UTF-8']);
		$process->setTimeout(45);
		$process->run();

		$output = trim($process->getOutput());
		if ($output === '') {
			throw new RuntimeException(
				$extractAudio
					? 'Unable to retrieve media audio stream URL'
					: 'Unable to retrieve media video stream URL'
			);
		}

		$lines = array_values(array_filter(
			array_map('trim', preg_split('/\r\n|\r|\n/', $output) ?: []),
			static fn (string $line): bool => $line !== ''
		));

		$streamUrl = null;
		$fileName = null;
		foreach ($lines as $line) {
			if (str_starts_with($line, 'http://') || str_starts_with($line, 'https://')) {
				if ($streamUrl === null) {
					$streamUrl = $line;
				}
				if ($extractAudio && str_contains(rawurldecode($line), 'mime=audio/')) {
					$streamUrl = $line;
				}
			} else {
				$fileName = $line;
			}
		}

		if ($streamUrl === null || $fileName === null) {
			throw new RuntimeException('Unable to parse media URL and filename from yt-dlp output');
		}

		$validatedStreamUrl = UrlValidator::validateDownloadUrl($streamUrl, ['http', 'https']);

		return [
			'URL' => $validatedStreamUrl,
			'FILENAME' => Tools::cleanString($fileName),
			'TYPE' => $extractAudio ? 'YT Audio' : 'YT Video',
		];
	}
}
