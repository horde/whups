<?php

declare(strict_types=1);

/**
 * Contract for ticket ecosystem participants (creator, commenter, assignee).
 *
 * All places that currently accept or emit participant strings should
 * accept string|ParticipantInterface for backward compatibility while
 * allowing typed participants where desired.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Domain;

interface ParticipantInterface
{
    /**
     * Return the canonical string form used by legacy code.
     *
     * Examples: "user:uid", "group:42", "guest@example.com"
     */
    public function toLegacyString(): string;

    /**
     * Return a human-readable display name.
     */
    public function displayName(): string;

    /**
     * Whether this participant is a guest (unauthenticated).
     */
    public function isGuest(): bool;
}
