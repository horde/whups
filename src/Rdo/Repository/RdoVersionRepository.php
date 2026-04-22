<?php

declare(strict_types=1);

/**
 * Rdo-backed version repository.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Rdo\Repository;

use Horde\Db\Adapter;
use Horde\Rdo\DefaultHydrator;
use Horde\Rdo\FieldType;
use Horde\Rdo\SqlRepository;
use Horde\Rdo\TypeSchema;
use Horde\Whups\Domain\Version;
use Horde\Whups\Domain\VersionRepositoryInterface;

final class RdoVersionRepository implements VersionRepositoryInterface
{
    private SqlRepository $repo;
    private TypeSchema $schema;

    public function __construct(
        private readonly Adapter $db,
    ) {
        $this->schema = (new TypeSchema(Version::class, 'whups_versions'))
            ->id('id', FieldType::INT, column: 'version_id')
            ->field('name', FieldType::STRING, column: 'version_name')
            ->field('description', FieldType::STRING, column: 'version_description')
            ->field('active', FieldType::BOOL, column: 'version_active');

        $this->repo = new SqlRepository($this->db, $this->schema, new DefaultHydrator());
    }

    public function getVersionsForQueue(int $queueId): array
    {
        return $this->db->selectAssoc(
            'SELECT version_id, version_name FROM whups_versions WHERE queue_id = ?',
            [$queueId],
        );
    }
}
