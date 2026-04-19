<?php

declare(strict_types=1);

/**
 * Repository interface for version data access.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Domain;

interface VersionRepositoryInterface
{
    /**
     * Return versions for a queue as id => name pairs.
     *
     * @return array<int,string>
     */
    public function getVersionsForQueue(int $queueId): array;
}
