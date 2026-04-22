<?php

declare(strict_types=1);

/**
 * Queue value object.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Domain;

final readonly class Queue
{
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
        public bool $versioned,
        public string $slug,
        public string $email,
        public bool $readonly = false,
    ) {}

    /**
     * Create from a driver array as returned by getQueueInternal().
     *
     * Expected keys: id, name, description, versioned, slug, email, readonly
     */
    public static function fromDriverArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            name: (string) ($data['name'] ?? ''),
            description: (string) ($data['description'] ?? ''),
            versioned: !empty($data['versioned']),
            slug: (string) ($data['slug'] ?? ''),
            email: (string) ($data['email'] ?? ''),
            readonly: !empty($data['readonly']),
        );
    }
}
