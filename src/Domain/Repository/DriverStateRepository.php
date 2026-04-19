<?php

declare(strict_types=1);

/**
 * State repository backed by Whups_Driver.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Domain\Repository;

use Horde\Whups\Domain\State;
use Horde\Whups\Domain\StateCategory;
use Horde\Whups\Domain\StateRepositoryInterface;
use Whups_Driver;

final class DriverStateRepository implements StateRepositoryInterface
{
    public function __construct(
        private readonly Whups_Driver $driver,
    ) {}

    public function getStates(int $typeId, StateCategory|array|null $category = null): array
    {
        $driverCategory = $this->convertCategory($category);

        return $this->driver->getStates($typeId, $driverCategory);
    }

    public function getState(int $id): State
    {
        return State::fromDriverArray($this->driver->getState($id));
    }

    public function getDefaultState(int $typeId): ?int
    {
        $default = $this->driver->getDefaultState($typeId);

        return $default !== null ? (int) $default : null;
    }

    public function isCategory(StateCategory $category, int $stateId): bool
    {
        return $this->driver->isCategory($category->value, $stateId);
    }

    /**
     * Convert typed category parameter to the string|array the driver expects.
     */
    private function convertCategory(StateCategory|array|null $category): string|array
    {
        if ($category === null) {
            return '';
        }

        if ($category instanceof StateCategory) {
            return $category->value;
        }

        return array_map(
            static fn(StateCategory $c): string => $c->value,
            $category,
        );
    }
}
