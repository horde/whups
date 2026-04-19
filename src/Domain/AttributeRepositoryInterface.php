<?php

declare(strict_types=1);

/**
 * Repository interface for attribute definition data access.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Domain;

interface AttributeRepositoryInterface
{
    /**
     * Return attribute definitions for a type.
     *
     * @return array<int, AttributeDefinition>
     */
    public function getAttributesForType(int $typeId): array;
}
