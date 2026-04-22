<?php

declare(strict_types=1);

/**
 * BackedEnum-aware Hydrator decorator.
 *
 * Wraps any Hydrator and pre-processes data so that raw string/int
 * values destined for BackedEnum constructor parameters are converted
 * via ::from() before the inner hydrator sees them.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Rdo;

use BackedEnum;
use Horde\Rdo\DefaultHydrator;
use Horde\Rdo\Hydrator;
use Horde\Rdo\TypeSchema;
use ReflectionClass;
use ReflectionNamedType;

final class EnumAwareHydrator implements Hydrator
{
    public function __construct(
        private readonly Hydrator $inner = new DefaultHydrator(),
    ) {}

    public function hydrate(array $data, TypeSchema $schema): object
    {
        $entityClass = $schema->getEntityClass();
        $ref = new ReflectionClass($entityClass);
        $constructor = $ref->getConstructor();

        if ($constructor !== null) {
            foreach ($constructor->getParameters() as $param) {
                $type = $param->getType();
                if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                    continue;
                }

                $typeName = $type->getName();
                if (!is_subclass_of($typeName, BackedEnum::class)) {
                    continue;
                }

                $paramName = $param->getName();
                $column = $schema->columnForField($paramName);

                if (array_key_exists($column, $data) && !$data[$column] instanceof BackedEnum) {
                    $data[$column] = $typeName::from($data[$column]);
                }
            }
        }

        return $this->inner->hydrate($data, $schema);
    }

    public function extract(object $entity, TypeSchema $schema): array
    {
        $data = $this->inner->extract($entity, $schema);

        foreach ($data as $column => $value) {
            if ($value instanceof BackedEnum) {
                $data[$column] = $value->value;
            }
        }

        return $data;
    }
}
