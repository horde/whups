<?php

declare(strict_types=1);

/**
 * State value object.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Domain;

final readonly class State
{
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
        public StateCategory $category,
        public int $typeId,
    ) {}

    /**
     * Create from a driver array as returned by getState().
     *
     * Expected keys: id, name, description, category, type
     */
    public static function fromDriverArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            name: (string) ($data['name'] ?? ''),
            description: (string) ($data['description'] ?? ''),
            category: StateCategory::from((string) $data['category']),
            typeId: (int) $data['type'],
        );
    }

    public function isUnconfirmed(): bool
    {
        return $this->category === StateCategory::Unconfirmed;
    }

    public function isNew(): bool
    {
        return $this->category === StateCategory::New;
    }

    public function isAssigned(): bool
    {
        return $this->category === StateCategory::Assigned;
    }

    public function isResolved(): bool
    {
        return $this->category === StateCategory::Resolved;
    }
}
