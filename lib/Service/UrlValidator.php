<?php

declare(strict_types=1);

namespace OCA\Ocdownloader\Service;

use InvalidArgumentException;

class UrlValidator {
	private const BLOCKED_HOSTS = [
		'localhost',
		'localhost.localdomain',
		'ip6-localhost',
		'ip6-loopback',
		'metadata.google.internal',
		'metadata',
	];

	/**
	 * Validate a user-supplied download URL and block SSRF targets (loopback, private, link-local, reserved IPs)
	 * as well as CLI option injection.
	 *
	 * @param string $url
	 * @param string[] $allowedSchemes
	 * @return string Normalized URL
	 */
	public static function validateDownloadUrl(string $url, array $allowedSchemes = ['http', 'https', 'ftp', 'ftps']): string {
		$url = trim($url);
		if ($url === '' || strlen($url) > 4096) {
			throw new InvalidArgumentException('Invalid URL');
		}
		if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1 || str_starts_with($url, '-')) {
			throw new InvalidArgumentException('Invalid characters in URL');
		}

		$parts = parse_url($url);
		if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
			throw new InvalidArgumentException('Invalid URL format');
		}

		$scheme = strtolower((string)$parts['scheme']);
		if (!in_array($scheme, $allowedSchemes, true)) {
			throw new InvalidArgumentException('Unsupported URL protocol');
		}

		$host = strtolower(trim((string)$parts['host'], '[]'));
		if ($host === '') {
			throw new InvalidArgumentException('Missing URL host');
		}

		if (
			in_array($host, self::BLOCKED_HOSTS, true)
			|| str_ends_with($host, '.localhost')
			|| str_ends_with($host, '.local')
			|| str_ends_with($host, '.internal')
		) {
			throw new InvalidArgumentException('Local or internal hosts are not allowed');
		}

		if (isset($parts['port'])) {
			$port = (int)$parts['port'];
			if ($port < 1 || $port > 65535) {
				throw new InvalidArgumentException('Invalid URL port');
			}
		}

		// Direct IPv4 / IPv6 literal check (including octal/hex IPv4 forms via ip2long)
		if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
			self::assertPublicIp($host);
			return $url;
		}
		if (preg_match('/^[0-9.]+$/', $host) === 1 || str_starts_with($host, '0x')) {
			$long = ip2long($host);
			if ($long === false) {
				throw new InvalidArgumentException('Invalid IP address in URL');
			}
			self::assertPublicIp(long2ip($long));
			return $url;
		}

		// Resolve DNS records and block DNS rebinding / internal hostnames pointing to private IPs
		$resolvedIps = [];
		$records = @dns_get_record($host, DNS_A + DNS_AAAA);
		if (is_array($records)) {
			foreach ($records as $record) {
				if (!empty($record['ip'])) {
					$resolvedIps[] = (string)$record['ip'];
				}
				if (!empty($record['ipv6'])) {
					$resolvedIps[] = (string)$record['ipv6'];
				}
			}
		}
		if ($resolvedIps === []) {
			$v4List = @gethostbynamel($host);
			if (is_array($v4List)) {
				foreach ($v4List as $ip) {
					$resolvedIps[] = (string)$ip;
				}
			}
		}

		if ($resolvedIps === []) {
			throw new InvalidArgumentException('Unable to resolve URL host');
		}

		foreach ($resolvedIps as $ip) {
			self::assertPublicIp($ip);
		}

		return $url;
	}

	public static function validateMagnetUri(string $uri): string {
		$uri = trim($uri);
		if ($uri === '' || strlen($uri) > 8192 || preg_match('/[\x00-\x1F\x7F]/', $uri) === 1 || str_starts_with($uri, '-')) {
			throw new InvalidArgumentException('Invalid magnet URI');
		}
		if (preg_match('/^magnet:\?xt=urn:[a-z0-9]+:[a-z0-9]{32,64}/i', $uri) !== 1) {
			throw new InvalidArgumentException('Invalid magnet URI');
		}
		return $uri;
	}

	public static function isValidAdminEndpointUrl(string $value): bool {
		$value = trim($value);
		if ($value === '' || preg_match('/[\x00-\x1F\x7F\s]/', $value) === 1) {
			return false;
		}
		if (!str_contains($value, '://')) {
			$value = 'http://' . $value;
		}
		$parts = parse_url($value);
		if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
			return false;
		}
		return in_array(strtolower((string)$parts['scheme']), ['http', 'https', 'socks5', 'socks5h'], true);
	}

	private static function assertPublicIp(string $ip): void {
		$normalized = strtolower(trim($ip, '[]'));
		if (str_starts_with($normalized, '::ffff:')) {
			$mapped = substr($normalized, 7);
			if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
				$normalized = $mapped;
			}
		}

		if (
			filter_var(
				$normalized,
				FILTER_VALIDATE_IP,
				FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
			) === false
		) {
			throw new InvalidArgumentException('Private or reserved IP addresses are not allowed');
		}
	}
}
