<?php

declare(strict_types=1);

/**
 * Reply (form reply / canned response) value object.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Domain;

final readonly class Reply
{
    public function __construct(
        public int $id,
        public string $name,
        public string $text,
        public int $typeId,
    ) {}

    /**
     * Create from a driver array as returned by getReply().
     *
     * Expected keys: reply_id (or id as key), reply_name, reply_text, type_id
     */
    public static function fromDriverArray(int $id, array $data): self
    {
        return new self(
            id: $id,
            name: (string) ($data['reply_name'] ?? ''),
            text: (string) ($data['reply_text'] ?? ''),
            typeId: (int) ($data['type_id'] ?? 0),
        );
    }
}
