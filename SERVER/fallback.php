#!/usr/bin/env php
<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit(1);
}

final class OcdCurlWorker {
	public static function run(string $gid, string $uri, array $options): int {
		if (preg_match('/^[a-zA-Z0-9_-]{4,64}$/', $gid) !== 1) {
			return 1;
		}

		$dir = isset($options['dir']) ? rtrim((string)$options['dir'], '/\\') : '';
		$out = isset($options['out']) ? basename((string)$options['out']) : '';
		if ($dir === '' || $out === '' || !is_dir($dir)) {
			return 1;
		}

		$progressFile = rtrim(sys_get_temp_dir(), '/\\') . '/ocdownloader_' . $gid . '.curl';
		$pid = (int)(getmypid() ?: 0);
		@file_put_contents($progressFile, "active;0;0;0;{$pid}\n");

		$destPath = $dir . '/' . $out;
		$destHandle = @fopen($destPath, 'wb');
		if (!is_resource($destHandle)) {
			@file_put_contents($progressFile, "error;0;0;0;{$pid}\n", FILE_APPEND);
			return 1;
		}

		$ch = curl_init($uri);
		if ($ch === false) {
			fclose($destHandle);
			@file_put_contents($progressFile, "error;0;0;0;{$pid}\n", FILE_APPEND);
			return 1;
		}

		$lastWrite = 0.0;
		$allowedProtocols = CURLPROTO_HTTP | CURLPROTO_HTTPS | CURLPROTO_FTP | CURLPROTO_FTPS;

		curl_setopt_array($ch, [
			CURLOPT_FILE => $destHandle,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 5,
			CURLOPT_CONNECTTIMEOUT => 15,
			CURLOPT_PROTOCOLS => $allowedProtocols,
			CURLOPT_REDIR_PROTOCOLS => $allowedProtocols,
			CURLOPT_NOPROGRESS => false,
			CURLOPT_PROGRESSFUNCTION => static function ($resource, int $dlTotal, int $dlNow) use (&$ch, &$lastWrite, $progressFile, $pid): int {
				$now = microtime(true);
				if (($now - $lastWrite) < 0.4) {
					return 0;
				}
				$lastWrite = $now;
				$info = curl_getinfo($ch);
				$speed = isset($info['speed_download']) ? (int)$info['speed_download'] : 0;
				$total = $dlTotal > 0 ? $dlTotal : $dlNow;
				@file_put_contents($progressFile, "active;{$dlNow};{$total};{$speed};{$pid}\n", FILE_APPEND);
				return 0;
			},
		]);

		if (!empty($options['http-user']) && isset($options['http-passwd'])) {
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			curl_setopt($ch, CURLOPT_USERPWD, $options['http-user'] . ':' . $options['http-passwd']);
		}
		if (!empty($options['ftp-user']) && isset($options['ftp-passwd'])) {
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			curl_setopt($ch, CURLOPT_USERPWD, $options['ftp-user'] . ':' . $options['ftp-passwd']);
		}
		if (isset($options['ftp-pasv'])) {
			curl_setopt($ch, CURLOPT_FTP_USE_EPSV, (bool)$options['ftp-pasv']);
		}
		if (!empty($options['referer'])) {
			curl_setopt($ch, CURLOPT_REFERER, (string)$options['referer']);
		}
		if (!empty($options['user-agent'])) {
			curl_setopt($ch, CURLOPT_USERAGENT, (string)$options['user-agent']);
		}
		if (!empty($options['all-proxy'])) {
			curl_setopt($ch, CURLOPT_PROXY, (string)$options['all-proxy']);
			if (!empty($options['all-proxy-user']) && isset($options['all-proxy-passwd'])) {
				curl_setopt($ch, CURLOPT_PROXYUSERPWD, $options['all-proxy-user'] . ':' . $options['all-proxy-passwd']);
			}
		}
		if (!empty($options['max-download-limit'])) {
			$limitKb = (int)rtrim((string)$options['max-download-limit'], 'Kk');
			if ($limitKb > 0) {
				curl_setopt($ch, CURLOPT_MAX_RECV_SPEED_LARGE, $limitKb * 1024);
			}
		}

		$ok = curl_exec($ch);
		$info = curl_getinfo($ch);
		$errno = curl_errno($ch);
		curl_close($ch);
		fclose($destHandle);

		$httpCode = isset($info['http_code']) ? (int)$info['http_code'] : 0;
		$downloaded = is_file($destPath) ? (int)filesize($destPath) : 0;
		$total = isset($info['download_content_length']) && (int)$info['download_content_length'] > 0
			? (int)$info['download_content_length']
			: $downloaded;

		if ($ok !== false && $errno === 0 && ($httpCode === 0 || ($httpCode >= 200 && $httpCode < 400))) {
			@file_put_contents($progressFile, "complete;{$downloaded};{$total};0;{$pid}\n", FILE_APPEND);
			return 0;
		}

		@unlink($destPath);
		@file_put_contents($progressFile, "error;0;0;0;{$pid}\n", FILE_APPEND);
		return 1;
	}
}

set_time_limit(0);
if ($argc < 4) {
	exit(1);
}
$gid = (string)$argv[1];
$uri = str_contains($argv[2], '://') ? (string)$argv[2] : rawurldecode((string)$argv[2]);
$rawOptions = (string)$argv[3];
$decoded = json_decode($rawOptions, true);
if (!is_array($decoded)) {
	$decoded = json_decode(rawurldecode($rawOptions), true);
}
exit(OcdCurlWorker::run($gid, $uri, is_array($decoded) ? $decoded : []));
