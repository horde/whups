<?php

declare(strict_types=1);

/**
 * Ticket type value object.
 *
 * Named TicketType to avoid collision with PHP's built-in type keyword.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Domain;

final readonly class TicketType
{
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
    ) {}

    /**
     * Create from a driver array as returned by getType().
     *
     * Expected keys: id, name, description
     */
    public static function fromDriverArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            name: (string) ($data['name'] ?? ''),
            description: (string) ($data['description'] ?? ''),
        );
    }
}
