<?php

declare(strict_types=1);

namespace OCA\Ocdownloader\Service;

class Tools {
	public static function sanitiseFileName(string $filename): string {
		$filename = trim(str_replace(["\0", "\r", "\n"], '', $filename));
		$filename = str_replace(['..', '/', '\\', DIRECTORY_SEPARATOR], '_', $filename);
		$filename = ltrim($filename, '.');
		return $filename !== '' ? $filename : ('download_' . time());
	}

	public static function isMagnet(string $url): bool {
		return preg_match('/^magnet:\?xt=urn:[a-z0-9]+:[a-z0-9]{32,64}/i', trim($url)) === 1;
	}

	public static function checkURL(string $url, array $allowedSchemes = ['http', 'https', 'ftp', 'ftps']): bool {
		try {
			UrlValidator::validateDownloadUrl($url, $allowedSchemes);
			return true;
		} catch (\Throwable) {
			return false;
		}
	}

	public static function getProgressString(float|int|string $completed, float|int|string $total, float $progress): ?string {
		$completedNum = (float)$completed;
		$totalNum = (float)$total;
		if ($totalNum <= 0) {
			return null;
		}

		$completedStr = self::formatSizeUnits($completedNum);
		$totalStr = self::formatSizeUnits($totalNum);
		$pct = round(($completedNum / $totalNum) * 100, 2);

		if ($progress > 0 && $progress < 1) {
			return $completedStr . ' / ' . $totalStr . ' (' . $pct . '%)';
		}
		if ($progress >= 1) {
			return $totalStr . ' (' . $pct . '%)';
		}
		return null;
	}

	public static function formatSizeUnits(float|int|string $bytes): string {
		$value = (float)$bytes;
		if ($value >= 1073741824) {
			return number_format($value / 1073741824, 2) . ' GB';
		}
		if ($value >= 1048576) {
			return number_format($value / 1048576, 2) . ' MB';
		}
		if ($value >= 1024) {
			return number_format($value / 1024, 2) . ' KB';
		}
		return (int)$value . ' B';
	}

	public static function cleanString(string $text): string {
		$replacements = [
			'/[áàâãªä]/u' => 'a',
			'/[ÁÀÂÃÄ]/u' => 'A',
			'/[ÍÌÎÏ]/u' => 'I',
			'/[íìîï]/u' => 'i',
			'/[éèêë]/u' => 'e',
			'/[ÉÈÊË]/u' => 'E',
			'/[óòôõºö]/u' => 'o',
			'/[ÓÒÔÕÖ]/u' => 'O',
			'/[úùûü]/u' => 'u',
			'/[ÚÙÛÜ]/u' => 'U',
			'/ç/u' => 'c',
			'/Ç/u' => 'C',
			'/ñ/u' => 'n',
			'/Ñ/u' => 'N',
			'/[\x{2013}\x{2014}]/u' => '-',
			'/[\x{2018}\x{2019}\x{2039}\x{203A}\x{201A}]/u' => '',
			'/[\x{201C}\x{201D}\x{00AB}\x{00BB}\x{201E}]/u' => '',
			'/[\s\x{00A0}]+/u' => '_',
		];
		$cleaned = preg_replace(array_keys($replacements), array_values($replacements), $text) ?? $text;
		return self::sanitiseFileName($cleaned);
	}

	public static function getDownloadStatusID(?string $status): int {
		return match (strtolower((string)$status)) {
			'complete' => 0,
			'active' => 1,
			'waiting' => 2,
			'paused' => 3,
			'removed' => 4,
			default => 5,
		};
	}

	public static function getMinutes(int|string $number, string $unitLetter): float {
		$num = max(1, (int)$number);
		if ($unitLetter === 'i') {
			return (float)$num;
		}

		$units = [
			'h' => 'hour',
			'd' => 'day',
			'w' => 'week',
			'm' => 'month',
			'y' => 'year',
		];
		if (!isset($units[$unitLetter])) {
			return 10080.0;
		}

		$to = strtotime('+' . $num . ' ' . $units[$unitLetter] . ($num > 1 ? 's' : ''));
		$from = time();
		if ($to === false) {
			return 10080.0;
		}
		return round(abs($to - $from) / 60, 2);
	}

	public static function getShortFilename(string $filename): string {
		return mb_strlen($filename, 'UTF-8') > 40
			? mb_substr($filename, 0, 40, 'UTF-8') . '...'
			: $filename;
	}

	public static function extractFilenameFromUrl(string $url, bool $isMagnet = false): string {
		if ($isMagnet) {
			$queryStr = (string)parse_url($url, PHP_URL_QUERY);
			parse_str(str_replace('tr=', 'tr[]=', $queryStr), $query);
			$dn = isset($query['dn']) && is_string($query['dn']) && trim($query['dn']) !== ''
				? $query['dn']
				: ('magnet_' . substr(md5($url), 0, 10));
			return self::cleanString($dn);
		}

		$path = (string)parse_url($url, PHP_URL_PATH);
		$base = basename(rawurldecode($path));
		if ($base === '' || $base === '/' || $base === '.') {
			$host = (string)parse_url($url, PHP_URL_HOST);
			$base = ($host !== '' ? $host : 'download') . '_' . time();
		}
		return self::cleanString($base);
	}
}
