<?php

declare(strict_types=1);

/**
 * Repository interface for priority data access.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Domain;

interface PriorityRepositoryInterface
{
    /**
     * Return priorities for a type as id => name pairs.
     *
     * @return array<int,string>
     */
    public function getPriorities(int $typeId): array;

    /**
     * Return the default priority ID for a type, or null if none set.
     */
    public function getDefaultPriority(int $typeId): ?int;
}
