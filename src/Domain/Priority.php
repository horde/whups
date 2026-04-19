<?php

declare(strict_types=1);

/**
 * Priority value object.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Domain;

final readonly class Priority
{
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
        public int $typeId,
    ) {}

    /**
     * Create from a driver array as returned by getPriority().
     *
     * Expected keys: id, name, description, type
     */
    public static function fromDriverArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            name: (string) ($data['name'] ?? ''),
            description: (string) ($data['description'] ?? ''),
            typeId: (int) $data['type'],
        );
    }
}
