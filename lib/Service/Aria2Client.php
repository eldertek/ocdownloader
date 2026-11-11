<?php

declare(strict_types=1);

namespace OCA\Ocdownloader\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class Aria2Client {
	public function __construct(
		private SettingsService $settings,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @param string[] $uris
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>|null
	 */
	public function addUri(array $uris, array $options = []): ?array {
		return $this->call('addUri', [$uris, $this->normalizeAriaOptions($options)]);
	}

	/**
	 * @param string $base64Torrent
	 * @param array<string, mixed> $options
	 * @return array<string, mixed>|null
	 */
	public function addTorrent(string $base64Torrent, array $options = []): ?array {
		return $this->call('addTorrent', [$base64Torrent, [], $this->normalizeAriaOptions($options)]);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function tellStatus(string $gid): ?array {
		return $this->call('tellStatus', [$gid]);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function pause(string $gid): ?array {
		return $this->call('pause', [$gid]);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function unpause(string $gid): ?array {
		return $this->call('unpause', [$gid]);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function remove(string $gid): ?array {
		return $this->call('remove', [$gid]);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function forceRemove(string $gid): ?array {
		return $this->call('forceRemove', [$gid]);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function removeDownloadResult(string $gid): ?array {
		return $this->call('removeDownloadResult', [$gid]);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function purgeDownloadResult(): ?array {
		return $this->call('purgeDownloadResult', []);
	}

	public function resetAllDownloads(QueueRepository $queueRepo): void {
		foreach ($queueRepo->findAllRows() as $row) {
			$gid = $row['gid'];
			if ($gid === '') {
				continue;
			}
			$status = $this->tellStatus($gid);
			$state = is_array($status) ? (string)($status['result']['status'] ?? '') : '';
			if ($state !== '' && $state !== 'error' && $state !== 'complete') {
				$this->forceRemove($gid);
			}
		}
		$purge = $this->purgeDownloadResult();
		if (is_array($purge) && ($purge['result'] ?? '') === 'OK') {
			$queueRepo->deleteAll();
		}
	}

	/**
	 * @param array<int, mixed> $params
	 * @return array<string, mixed>|null
	 */
	private function call(string $method, array $params): ?array {
		[$endpoint, $host, $port, $token] = $this->getRpcConfig();
		if ($token !== null && $token !== '') {
			array_unshift($params, 'token:' . $token);
		}

		$payload = json_encode([
			'jsonrpc' => '2.0',
			'id' => 'ocdownloader',
			'method' => 'aria2.' . $method,
			'params' => array_values($params),
		]);
		if ($payload === false) {
			return null;
		}

		[$raw, $errno] = $this->sendPost($endpoint, $payload);
		if ($raw === false && $errno === CURLE_COULDNT_CONNECT && $this->isLoopbackHost($host)) {
			if ($this->startLocalAria2Daemon($port, $token)) {
				[$raw, ] = $this->sendPost($endpoint, $payload);
			}
		}

		if (is_string($raw) && $raw !== '') {
			$decoded = json_decode($raw, true);
			if (
				is_array($decoded)
				&& isset($decoded['error']['message'])
				&& stripos((string)$decoded['error']['message'], 'Unauthorized') !== false
				&& $this->isLoopbackHost($host)
				&& $this->settings->getAdminValue('AriaPort') === null
				&& $token === null
			) {
				$fallbackPort = 6801;
				$fallbackEndpoint = 'http://127.0.0.1:' . $fallbackPort . '/jsonrpc';
				[$fbRaw, $fbErrno] = $this->sendPost($fallbackEndpoint, $payload);
				if ($fbRaw === false && $fbErrno === CURLE_COULDNT_CONNECT) {
					if ($this->startLocalAria2Daemon($fallbackPort, null)) {
						[$fbRaw, ] = $this->sendPost($fallbackEndpoint, $payload);
					}
				}
				if (is_string($fbRaw) && $fbRaw !== '') {
					$fbDecoded = json_decode($fbRaw, true);
					if (is_array($fbDecoded)) {
						return $fbDecoded;
					}
				}
			}
			return is_array($decoded) ? $decoded : null;
		}

		return null;
	}

	/**
	 * @return array{0:string, 1:string, 2:int, 3:?string}
	 */
	private function getRpcConfig(): array {
		$rawAddr = trim((string)($this->settings->getAdminValue('AriaAddress', '127.0.0.1') ?? '127.0.0.1'));
		if ($rawAddr === '') {
			$rawAddr = '127.0.0.1';
		}
		if (!str_contains($rawAddr, '://')) {
			$rawAddr = 'http://' . $rawAddr;
		}

		$parts = parse_url($rawAddr);
		$scheme = isset($parts['scheme']) && in_array(strtolower($parts['scheme']), ['http', 'https'], true)
			? strtolower($parts['scheme'])
			: 'http';
		$host = isset($parts['host']) && $parts['host'] !== '' ? $parts['host'] : '127.0.0.1';
		$port = (int)($this->settings->getAdminValue('AriaPort', '6800') ?? 6800);
		if ($port < 1 || $port > 65535) {
			$port = 6800;
		}

		$token = $this->settings->getAdminValue('AriaToken');
		$endpoint = $scheme . '://' . $host . ':' . $port . '/jsonrpc';
		return [$endpoint, $host, $port, $token];
	}

	/**
	 * @return array{0:string|false, 1:int}
	 */
	private function sendPost(string $endpoint, string $payload): array {
		$ch = curl_init($endpoint);
		if ($ch === false) {
			return [false, -1];
		}

		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $payload,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => false,
			CURLOPT_CONNECTTIMEOUT => 3,
			CURLOPT_TIMEOUT => 15,
			CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
		]);

		$raw = curl_exec($ch);
		$errno = curl_errno($ch);
		curl_close($ch);
		return [$raw, $errno];
	}

	private function isLoopbackHost(string $host): bool {
		$clean = strtolower(trim($host, '[]'));
		return in_array($clean, ['127.0.0.1', 'localhost', '::1'], true);
	}

	private function startLocalAria2Daemon(int $port, ?string $token): bool {
		$finder = new ExecutableFinder();
		$binary = $finder->find('aria2c', null, ['/usr/local/bin', '/usr/bin', '/bin']);
		if ($binary === null) {
			return false;
		}

		$args = [
			$binary,
			'--enable-rpc=true',
			'--rpc-listen-all=false',
			'--rpc-listen-port=' . $port,
			'--daemon=true',
			'--continue=true',
			'--auto-file-renaming=false',
			'--allow-overwrite=true',
			'--max-connection-per-server=4',
			'--check-certificate=true',
		];
		if ($token !== null && $token !== '') {
			$args[] = '--rpc-secret=' . $token;
		}

		try {
			$process = new Process($args);
			$process->setTimeout(5);
			$process->run();
			usleep(300000);
			return $process->isSuccessful();
		} catch (\Throwable $e) {
			$this->logger->warning('Unable to auto-start local aria2c daemon: ' . $e->getMessage(), ['app' => 'ocdownloader']);
			return false;
		}
	}

	/**
	 * Aria2 JSON-RPC expects option values as strings.
	 *
	 * @param array<string, mixed> $options
	 * @return array<string, string>
	 */
	private function normalizeAriaOptions(array $options): array {
		$normalized = [];
		foreach ($options as $key => $val) {
			if ($val === null) {
				continue;
			}
			if (is_bool($val)) {
				$normalized[(string)$key] = $val ? 'true' : 'false';
			} else {
				$normalized[(string)$key] = (string)$val;
			}
		}
		return $normalized;
	}
}
