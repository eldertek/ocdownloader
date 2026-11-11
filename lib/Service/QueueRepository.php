<?php

declare(strict_types=1);

namespace OCA\Ocdownloader\Service;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class QueueRepository {
	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function insert(
		string $uid,
		string $gid,
		string $filename,
		string $protocol,
		int $status = 1,
		int $isCleaned = 0,
		?int $timestamp = null,
	): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('ocdownloader_queue')
			->values([
				'uid' => $qb->createNamedParameter($uid),
				'gid' => $qb->createNamedParameter($gid),
				'filename' => $qb->createNamedParameter(mb_substr($filename, 0, 512, 'UTF-8')),
				'protocol' => $qb->createNamedParameter(mb_substr($protocol, 0, 64, 'UTF-8')),
				'status' => $qb->createNamedParameter($status, IQueryBuilder::PARAM_INT),
				'is_cleaned' => $qb->createNamedParameter($isCleaned, IQueryBuilder::PARAM_INT),
				'timestamp' => $qb->createNamedParameter($timestamp ?? time(), IQueryBuilder::PARAM_INT),
			]);
		$qb->executeStatement();
	}

	public function exists(string $uid, string $gid): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('ocdownloader_queue')
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('gid', $qb->createNamedParameter($gid)))
			->setMaxResults(1);
		$res = $qb->executeQuery();
		$row = $res->fetch();
		$res->closeCursor();
		return is_array($row);
	}

	/**
	 * @param int[] $statuses
	 * @param int[] $isCleanedValues
	 * @return array<int, array{id:int, uid:string, gid:string, filename:string, protocol:string, status:int, is_cleaned:int, timestamp:int}>
	 */
	public function findByUserAndFilters(string $uid, array $statuses, array $isCleanedValues): array {
		if ($uid === '' || $statuses === [] || $isCleanedValues === []) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'uid', 'gid', 'filename', 'protocol', 'status', 'is_cleaned', 'timestamp')
			->from('ocdownloader_queue')
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->in('status', $qb->createNamedParameter($statuses, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->in('is_cleaned', $qb->createNamedParameter($isCleanedValues, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('timestamp', 'ASC');

		$res = $qb->executeQuery();
		$rows = $res->fetchAll();
		$res->closeCursor();

		$items = [];
		foreach ($rows as $row) {
			$r = array_change_key_case($row, CASE_LOWER);
			$items[] = [
				'id' => (int)($r['id'] ?? 0),
				'uid' => (string)($r['uid'] ?? ''),
				'gid' => (string)($r['gid'] ?? ''),
				'filename' => (string)($r['filename'] ?? ''),
				'protocol' => (string)($r['protocol'] ?? ''),
				'status' => (int)($r['status'] ?? 1),
				'is_cleaned' => (int)($r['is_cleaned'] ?? 0),
				'timestamp' => (int)($r['timestamp'] ?? 0),
			];
		}

		return $items;
	}

	/**
	 * @return array<int, array{gid:string, status:int}>
	 */
	public function findAllRows(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('gid', 'status')
			->from('ocdownloader_queue');
		$res = $qb->executeQuery();
		$rows = $res->fetchAll();
		$res->closeCursor();

		$items = [];
		foreach ($rows as $row) {
			$r = array_change_key_case($row, CASE_LOWER);
			$items[] = [
				'gid' => (string)($r['gid'] ?? ''),
				'status' => (int)($r['status'] ?? 1),
			];
		}
		return $items;
	}

	public function updateStatusUnlessRemoved(string $uid, string $gid, int $status): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('ocdownloader_queue')
			->set('status', $qb->createNamedParameter($status, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('gid', $qb->createNamedParameter($gid)))
			->andWhere($qb->expr()->neq('status', $qb->createNamedParameter(4, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function updateStatus(string $uid, string $gid, int $status): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('ocdownloader_queue')
			->set('status', $qb->createNamedParameter($status, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('gid', $qb->createNamedParameter($gid)));
		$qb->executeStatement();
	}

	public function markCleaned(string $uid, string $gid): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('ocdownloader_queue')
			->set('is_cleaned', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('gid', $qb->createNamedParameter($gid)));
		$qb->executeStatement();
	}

	public function markRemoved(string $uid, string $gid): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('ocdownloader_queue')
			->set('status', $qb->createNamedParameter(4, IQueryBuilder::PARAM_INT))
			->set('is_cleaned', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('gid', $qb->createNamedParameter($gid)));
		$qb->executeStatement();
	}

	public function deleteByUserAndGid(string $uid, string $gid): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('ocdownloader_queue')
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('gid', $qb->createNamedParameter($gid)));
		$qb->executeStatement();
	}

	public function deleteAll(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('ocdownloader_queue');
		$qb->executeStatement();
	}

	/**
	 * @return array{ALL:int, COMPLETES:int, ACTIVES:int, WAITINGS:int, STOPPED:int, REMOVED:int}
	 */
	public function getCounters(string $uid): array {
		$counters = [
			'ALL' => 0,
			'COMPLETES' => 0,
			'ACTIVES' => 0,
			'WAITINGS' => 0,
			'STOPPED' => 0,
			'REMOVED' => 0,
		];
		if ($uid === '') {
			return $counters;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->createFunction('COUNT(*)'), 'cnt')
			->addSelect('status')
			->from('ocdownloader_queue')
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->groupBy('status');

		$res = $qb->executeQuery();
		$rows = $res->fetchAll();
		$res->closeCursor();

		foreach ($rows as $row) {
			$r = array_change_key_case($row, CASE_LOWER);
			$status = (int)($r['status'] ?? 5);
			$count = (int)($r['cnt'] ?? 0);
			if ($status < 5) {
				$counters['ALL'] += $count;
			}
			match ($status) {
				0 => $counters['COMPLETES'] = $count,
				1 => $counters['ACTIVES'] = $count,
				2 => $counters['WAITINGS'] = $count,
				3 => $counters['STOPPED'] = $count,
				4 => $counters['REMOVED'] = $count,
				default => null,
			};
		}

		return $counters;
	}
}
