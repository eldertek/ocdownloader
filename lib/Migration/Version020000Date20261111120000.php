<?php

declare(strict_types=1);

namespace OCA\Ocdownloader\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version020000Date20261111120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('ocdownloader_queue')) {
			$table = $schema->getTable('ocdownloader_queue');
			if ($table->hasColumn('gid')) {
				$table->changeColumn('gid', ['length' => 64]);
			}
			if ($table->hasColumn('filename')) {
				$table->changeColumn('filename', ['length' => 512]);
			}
			if ($table->hasColumn('protocol')) {
				$table->changeColumn('protocol', ['length' => 64]);
			}
		}

		if ($schema->hasTable('ocdownloader_admin')) {
			$table = $schema->getTable('ocdownloader_admin');
			if (!$table->hasColumn('cfg_key')) {
				$table->addColumn('cfg_key', Types::STRING, [
					'notnull' => true,
					'length' => 100,
					'default' => '',
				]);
			}
			if (!$table->hasColumn('cfg_val')) {
				$table->addColumn('cfg_val', Types::STRING, [
					'notnull' => false,
					'length' => 512,
				]);
			}
		}

		if ($schema->hasTable('ocdownloader_personal')) {
			$table = $schema->getTable('ocdownloader_personal');
			if (!$table->hasColumn('cfg_key')) {
				$table->addColumn('cfg_key', Types::STRING, [
					'notnull' => true,
					'length' => 100,
					'default' => '',
				]);
			}
			if (!$table->hasColumn('cfg_val')) {
				$table->addColumn('cfg_val', Types::STRING, [
					'notnull' => false,
					'length' => 512,
				]);
			}
		}

		return $schema;
	}
}
