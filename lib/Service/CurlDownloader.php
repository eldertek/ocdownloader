<?php

declare(strict_types=1);

namespace OCA\Ocdownloader\Service;

use RuntimeException;

class CurlDownloader {
	/**
	 * @param array<string, mixed> $options
	 * @return array{result:string}
	 */
	public function addUri(string $uri, array $options): array {
		$gid = 'ocd_' . bin2hex(random_bytes(8));
		$this->spawnWorker($gid, $uri, $options);
		return ['result' => $gid];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function tellStatus(string $gid): array {
		$file = $this->getStatusFilePath($gid);
		if ($file === null || !is_file($file)) {
			return ['error' => true];
		}

		$statusArray = [
			'status' => 'waiting',
			'completedLength' => '0',
			'totalLength' => '0',
			'downloadSpeed' => '0',
			'PID' => 0,
			'GID' => $gid,
		];

		$lastLine = $this->readLastNonEmptyLine($file);
		if ($lastLine !== null) {
			$parts = explode(';', $lastLine);
			if (count($parts) === 5) {
				$statusArray['status'] = $parts[0];
				$statusArray['completedLength'] = (string)(int)$parts[1];
				$statusArray['totalLength'] = (string)(int)$parts[2];
				$statusArray['downloadSpeed'] = (string)(int)$parts[3];
				$statusArray['PID'] = (int)$parts[4];
			}
		}

		return ['result' => $statusArray];
	}

	/**
	 * @param array<string, mixed> $status
	 * @return array{result:string}|null
	 */
	public function remove(array $status): ?array {
		$gid = (string)($status['GID'] ?? '');
		$file = $this->getStatusFilePath($gid);
		if ($file === null) {
			return null;
		}

		$pid = isset($status['PID']) ? (int)$status['PID'] : 0;
		if ($pid > 0 && function_exists('posix_kill')) {
			@posix_kill($pid, 15);
		}

		if (is_file($file)) {
			$line = sprintf(
				"removed;%d;%d;%d;%d\n",
				(int)($status['completedLength'] ?? 0),
				(int)($status['totalLength'] ?? 0),
				(int)($status['downloadSpeed'] ?? 0),
				$pid
			);
			@file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
			return ['result' => $gid];
		}

		return ['result' => $gid];
	}

	/**
	 * @return array{result:string}
	 */
	public function removeDownloadResult(string $gid): array {
		$file = $this->getStatusFilePath($gid);
		if ($file !== null && is_file($file)) {
			@unlink($file);
		}
		return ['result' => 'OK'];
	}

	private function getStatusFilePath(string $gid): ?string {
		if (preg_match('/^[a-zA-Z0-9_-]{4,64}$/', $gid) !== 1) {
			return null;
		}
		return rtrim(sys_get_temp_dir(), '/\\') . '/ocdownloader_' . $gid . '.curl';
	}

	private function readLastNonEmptyLine(string $file): ?string {
		$handle = @fopen($file, 'rb');
		if (!is_resource($handle)) {
			return null;
		}

		try {
			if (fseek($handle, 0, SEEK_END) !== 0) {
				return null;
			}
			$pos = ftell($handle);
			if ($pos === false || $pos === 0) {
				return null;
			}

			$readBytes = min(4096, $pos);
			fseek($handle, -$readBytes, SEEK_END);
			$chunk = fread($handle, $readBytes);
			if (!is_string($chunk) || trim($chunk) === '') {
				return null;
			}
			$lines = preg_split('/\r\n|\r|\n/', trim($chunk));
			if (!is_array($lines) || $lines === []) {
				return null;
			}
			$last = end($lines);
			return is_string($last) && $last !== '' ? $last : null;
		} finally {
			fclose($handle);
		}
	}

	/**
	 * @param array<string, mixed> $options
	 */
	private function spawnWorker(string $gid, string $uri, array $options): void {
		$workerScript = dirname(__DIR__, 2) . '/SERVER/fallback.php';
		if (!is_file($workerScript)) {
			throw new RuntimeException('cURL background worker script is missing');
		}

		$statusFile = $this->getStatusFilePath($gid);
		if ($statusFile !== null) {
			@file_put_contents($statusFile, "waiting;0;0;0;0\n");
		}

		$phpBinary = PHP_BINARY !== '' && is_executable(PHP_BINARY) ? PHP_BINARY : '/usr/local/bin/php';
		if (!is_executable($phpBinary)) {
			$phpBinary = '/usr/bin/php';
		}

		$encodedOptions = json_encode($options, JSON_UNESCAPED_SLASHES);
		if ($encodedOptions === false) {
			throw new RuntimeException('Unable to encode download options');
		}

		$descriptors = [
			0 => ['file', '/dev/null', 'r'],
			1 => ['file', '/dev/null', 'w'],
			2 => ['file', '/dev/null', 'w'],
		];

		$process = @proc_open([$phpBinary, $workerScript, $gid, $uri, $encodedOptions], $descriptors, $pipes);
		if (!is_resource($process)) {
			throw new RuntimeException('Unable to start cURL background download worker');
		}
		proc_close($process);
	}
}
