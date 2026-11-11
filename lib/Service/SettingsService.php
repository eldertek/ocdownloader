<?php

declare(strict_types=1);

namespace OCA\Ocdownloader\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class SettingsService {
	public const ADMIN_KEYS = [
		'YTDLBinary',
		'YTDLAudioFormat',
		'YTDLVideoFormat',
		'ProxyAddress',
		'ProxyPort',
		'ProxyUser',
		'ProxyPasswd',
		'WhichDownloader',
		'ProxyOnlyWithYTDL',
		'AllowProtocolHTTP',
		'AllowProtocolFTP',
		'AllowProtocolYT',
		'AllowProtocolBT',
		'MaxDownloadSpeed',
		'BTMaxUploadSpeed',
		'AriaAddress',
		'AriaPort',
		'AriaToken',
	];

	public const PUBLIC_ADMIN_KEYS = [
		'WhichDownloader',
		'AllowProtocolHTTP',
		'AllowProtocolFTP',
		'AllowProtocolYT',
		'AllowProtocolBT',
	];

	public const PERSONAL_KEYS = [
		'DownloadsFolder',
		'TorrentsFolder',
		'BTRatioToReach',
		'BTSeedTimeToReach_BTSeedTimeToReachUnit',
	];

	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function getAdminValue(string $key, ?string $default = null): ?string {
		$qb = $this->db->getQueryBuilder();
		$qb->select('cfg_val')
			->from('ocdownloader_admin')
			->where($qb->expr()->eq('cfg_key', $qb->createNamedParameter($key)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if (is_array($row)) {
			$row = array_change_key_case($row, CASE_LOWER);
			if (array_key_exists('cfg_val', $row) && $row['cfg_val'] !== null && $row['cfg_val'] !== '') {
				return (string)$row['cfg_val'];
			}
		}

		return $default;
	}

	/**
	 * @return array<string, string|null>
	 */
	public function getAllAdminValues(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('cfg_key', 'cfg_val')
			->from('ocdownloader_admin');

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$values = [];
		foreach ($rows as $row) {
			$normalized = array_change_key_case($row, CASE_LOWER);
			$k = (string)($normalized['cfg_key'] ?? '');
			if ($k !== '') {
				$values[$k] = $normalized['cfg_val'] !== null ? (string)$normalized['cfg_val'] : null;
			}
		}
		return $values;
	}

	public function setAdminValue(string $key, ?string $value): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('ocdownloader_admin')
			->where($qb->expr()->eq('cfg_key', $qb->createNamedParameter($key)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$existing = $result->fetch();
		$result->closeCursor();

		if (is_array($existing)) {
			$update = $this->db->getQueryBuilder();
			$update->update('ocdownloader_admin')
				->set('cfg_val', $update->createNamedParameter($value))
				->where($update->expr()->eq('cfg_key', $update->createNamedParameter($key)));
			$update->executeStatement();
			return;
		}

		$insert = $this->db->getQueryBuilder();
		$insert->insert('ocdownloader_admin')
			->values([
				'cfg_key' => $insert->createNamedParameter($key),
				'cfg_val' => $insert->createNamedParameter($value),
			]);
		$insert->executeStatement();
	}

	public function getPersonalValue(string $uid, string $key, ?string $default = null): ?string {
		if ($uid === '') {
			return $default;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('cfg_val')
			->from('ocdownloader_personal')
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('cfg_key', $qb->createNamedParameter($key)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if (is_array($row)) {
			$row = array_change_key_case($row, CASE_LOWER);
			if (array_key_exists('cfg_val', $row) && $row['cfg_val'] !== null && $row['cfg_val'] !== '') {
				return (string)$row['cfg_val'];
			}
		}

		return $default;
	}

	/**
	 * @return array<string, string|null>
	 */
	public function getAllPersonalValues(string $uid): array {
		if ($uid === '') {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('cfg_key', 'cfg_val')
			->from('ocdownloader_personal')
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));

		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		$values = [];
		foreach ($rows as $row) {
			$normalized = array_change_key_case($row, CASE_LOWER);
			$k = (string)($normalized['cfg_key'] ?? '');
			if ($k !== '') {
				$values[$k] = $normalized['cfg_val'] !== null ? (string)$normalized['cfg_val'] : null;
			}
		}
		return $values;
	}

	public function setPersonalValue(string $uid, string $key, ?string $value): void {
		if ($uid === '') {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('ocdownloader_personal')
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('cfg_key', $qb->createNamedParameter($key)))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$existing = $result->fetch();
		$result->closeCursor();

		if (is_array($existing)) {
			$update = $this->db->getQueryBuilder();
			$update->update('ocdownloader_personal')
				->set('cfg_val', $update->createNamedParameter($value))
				->where($update->expr()->eq('uid', $update->createNamedParameter($uid)))
				->andWhere($update->expr()->eq('cfg_key', $update->createNamedParameter($key)));
			$update->executeStatement();
			return;
		}

		$insert = $this->db->getQueryBuilder();
		$insert->insert('ocdownloader_personal')
			->values([
				'uid' => $insert->createNamedParameter($uid),
				'cfg_key' => $insert->createNamedParameter($key),
				'cfg_val' => $insert->createNamedParameter($value),
			]);
		$insert->executeStatement();
	}

	public function getWhichDownloader(): string {
		$wd = $this->getAdminValue('WhichDownloader', 'ARIA2');
		return $wd === 'CURL' ? 'CURL' : 'ARIA2';
	}

	public function isAria2(): bool {
		return $this->getWhichDownloader() === 'ARIA2';
	}

	public function isProtocolAllowed(string $protocolKey, bool $isAdmin): bool {
		if ($isAdmin) {
			return true;
		}
		$val = $this->getAdminValue($protocolKey, 'Y');
		return $val === null || $val === 'Y';
	}

	public function getDownloadsFolder(string $uid): string {
		$folder = $this->getPersonalValue($uid, 'DownloadsFolder', 'Downloads') ?? 'Downloads';
		$folder = trim(str_replace('\\ ', ' ', $folder), '/');
		return $folder !== '' ? $folder : 'Downloads';
	}

	public function getTorrentsFolder(string $uid): string {
		$folder = $this->getPersonalValue($uid, 'TorrentsFolder', 'Downloads/Files/Torrents') ?? 'Downloads/Files/Torrents';
		$folder = trim(str_replace('\\ ', ' ', $folder), '/');
		return $folder !== '' ? $folder : 'Downloads/Files/Torrents';
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getProxyOptions(bool $forYtdl = false): array {
		$onlyYtdl = $this->getAdminValue('ProxyOnlyWithYTDL', 'N') === 'Y';
		if (!$forYtdl && $onlyYtdl) {
			return [];
		}

		$addr = $this->getAdminValue('ProxyAddress');
		$port = (int)($this->getAdminValue('ProxyPort') ?? 0);
		if ($addr === null || trim($addr) === '' || $port < 1 || $port > 65535) {
			return [];
		}

		$proxy = rtrim(trim($addr), '/') . ':' . $port;
		$options = ['all-proxy' => $proxy];

		$user = $this->getAdminValue('ProxyUser');
		$passwd = $this->getAdminValue('ProxyPasswd');
		if ($user !== null && $user !== '' && $passwd !== null && $passwd !== '') {
			$options['all-proxy-user'] = $user;
			$options['all-proxy-passwd'] = $passwd;
		}

		return $options;
	}
}
