<?php

declare(strict_types=1);

/**
 * Attribute definition value object.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Domain;

final readonly class AttributeDefinition
{
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
        public string $type = 'text',
        public array $params = [],
        public bool $required = false,
        public bool $readonly = false,
    ) {}

    /**
     * Create from a driver array as returned by getAttributesForType().
     *
     * The driver uses the attribute ID as the array key, so it must be
     * passed separately.
     *
     * Expected keys: human_name, type, required, readonly, desc, params
     */
    public static function fromDriverArray(int $id, array $data): self
    {
        $params = $data['params'] ?? [];
        if (is_string($params)) {
            $params = unserialize($params) ?: [];
        }

        return new self(
            id: $id,
            name: (string) ($data['human_name'] ?? ''),
            description: (string) ($data['desc'] ?? ''),
            type: (string) ($data['type'] ?? 'text'),
            params: (array) $params,
            required: !empty($data['required']),
            readonly: !empty($data['readonly']),
        );
    }
}
