<?php

declare(strict_types=1);

/**
 * Attribute definition repository backed by Whups_Driver.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Domain\Repository;

use Horde\Whups\Domain\AttributeDefinition;
use Horde\Whups\Domain\AttributeRepositoryInterface;
use Whups_Driver;

final class DriverAttributeRepository implements AttributeRepositoryInterface
{
    public function __construct(
        private readonly Whups_Driver $driver,
    ) {}

    public function getAttributesForType(int $typeId): array
    {
        $raw = $this->driver->getAttributesForType($typeId);
        $result = [];

        foreach ($raw as $id => $data) {
            $result[$id] = AttributeDefinition::fromDriverArray((int) $id, $data);
        }

        return $result;
    }
}
