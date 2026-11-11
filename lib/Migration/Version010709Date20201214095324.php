<?php

declare(strict_types=1);

namespace OCA\Ocdownloader\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010709Date20201214095324 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('ocdownloader_queue')) {
			$table = $schema->createTable('ocdownloader_queue');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('uid', Types::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('gid', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('filename', Types::STRING, [
				'notnull' => true,
				'length' => 512,
			]);
			$table->addColumn('protocol', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('status', Types::SMALLINT, [
				'notnull' => true,
				'default' => 1,
			]);
			$table->addColumn('is_cleaned', Types::SMALLINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('timestamp', Types::BIGINT, [
				'notnull' => true,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['uid', 'status'], 'ocdl_queue_uid_status');
			$table->addIndex(['uid', 'gid'], 'ocdl_queue_uid_gid');
		}

		if (!$schema->hasTable('ocdownloader_admin')) {
			$table = $schema->createTable('ocdownloader_admin');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('cfg_key', Types::STRING, [
				'notnull' => true,
				'length' => 100,
			]);
			$table->addColumn('cfg_val', Types::STRING, [
				'notnull' => false,
				'length' => 512,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['cfg_key'], 'ocdl_admin_cfg_key');
		}

		if (!$schema->hasTable('ocdownloader_personal')) {
			$table = $schema->createTable('ocdownloader_personal');
			$table->addColumn('id', Types::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
			]);
			$table->addColumn('uid', Types::STRING, [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('cfg_key', Types::STRING, [
				'notnull' => true,
				'length' => 100,
			]);
			$table->addColumn('cfg_val', Types::STRING, [
				'notnull' => false,
				'length' => 512,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['uid', 'cfg_key'], 'ocdl_pers_uid_key');
		}

		return $schema;
	}
}
