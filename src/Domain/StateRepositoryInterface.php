<?php

declare(strict_types=1);

/**
 * Repository interface for state data access.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Domain;

interface StateRepositoryInterface
{
    /**
     * Return states for a type as id => name pairs, filtered by category.
     *
     * @param int $typeId
     * @param StateCategory|list<StateCategory>|null $category
     * @return array<int,string>
     */
    public function getStates(int $typeId, StateCategory|array|null $category = null): array;

    /**
     * Return a single state by ID.
     */
    public function getState(int $id): State;

    /**
     * Return the default state ID for a type, or null if none set.
     */
    public function getDefaultState(int $typeId): ?int;

    /**
     * Check whether a state belongs to the given category.
     */
    public function isCategory(StateCategory $category, int $stateId): bool;
}
