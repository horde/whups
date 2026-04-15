<?php

declare(strict_types=1);

/**
 * Injectable user display formatting for Whups.
 *
 * Replaces the static Whups::getUserAttributes(), Whups::formatUser(),
 * and Whups::getOwners() methods with an injectable service.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsdl.php BSD
 * @package  Whups
 */

namespace Horde\Whups\Service;

use Horde_Core_Factory_Identity;
use Horde_Exception;
use Horde_Group;
use Horde_Mail_Rfc822_Address;
use Horde_Registry;
use Horde_Themes_Image;
use Whups_Driver;

class UserFormatter
{
    /**
     * In-memory cache of resolved user attributes.
     *
     * @var array<string, array{type: string, user: string, name: string, email: string}>
     */
    private array $cache = [];

    public function __construct(
        private readonly Horde_Core_Factory_Identity $identityFactory,
        private readonly Horde_Group $groupService,
        private readonly Horde_Registry $registry,
        private readonly Whups_Driver $driver,
        private readonly bool $obfuscateEmail = false,
    ) {}

    /**
     * Resolve a Whups user identifier to an attribute hash.
     *
     * Handles plain user names, 'user:name', 'group:id', '**email',
     * and negative IDs (guest tickets).
     *
     * @return array{type: string, user: string, name: string, email: string}
     */
    public function getAttributes(?string $user = null): array
    {
        $user ??= $this->registry->getAuth();

        if (empty($user)) {
            return ['type' => 'user', 'user' => '', 'name' => '', 'email' => ''];
        }

        if (isset($this->cache[$user])) {
            return $this->cache[$user];
        }

        if (str_contains($user, ':')) {
            [$type, $identifier] = explode(':', $user, 2);
        } else {
            $type = 'user';
            $identifier = $user;
        }

        $attrs = ['type' => $type, 'user' => $identifier, 'name' => '', 'email' => ''];

        switch ($type) {
            case 'user':
                $attrs = $this->resolveUser($identifier);
                break;

            case 'group':
                $attrs = $this->resolveGroup($identifier);
                break;
        }

        $this->cache[$user] = $attrs;

        return $attrs;
    }

    /**
     * Format a user for display.
     *
     * @param string|array|null $user       A user identifier or an attribute
     *                                      hash from getAttributes().
     * @param bool              $showEmail  Include the email address.
     * @param bool              $showName   Include the full name.
     * @param bool              $html       HTML-escape and prettify output.
     */
    public function format(
        string|array|null $user = null,
        bool $showEmail = true,
        bool $showName = true,
        bool $html = false,
    ): string {
        if ($user !== null && empty($user)) {
            return '';
        }

        $details = is_array($user) ? $user : $this->getAttributes($user);

        $name = !empty($details['name']) ? $details['name'] : $details['user'];

        if (($showEmail || empty($name) || !$showName)
            && !empty($details['email'])
        ) {
            $email = $details['email'];

            if ($html && $this->obfuscateEmail && str_contains($email, '@')) {
                $email = str_replace(
                    ['@', '.'],
                    [' (at) ', ' (dot) '],
                    $email,
                );
            }

            if (!empty($name) && $showName) {
                $addrOb = new Horde_Mail_Rfc822_Address($email);
                $addrOb->personal = $name;
                $name = (string) $addrOb;
            } else {
                $name = $email;
            }
        }

        if ($html) {
            $name = htmlspecialchars($name);
            if ($details['type'] === 'group') {
                $name = Horde_Themes_Image::tag(
                    'group.png',
                    ['alt' => !empty($details['name'])
                        ? $details['name']
                        : $details['user']],
                ) . $name;
            }
        }

        return $name;
    }

    /**
     * Format the owners of a ticket as a comma-separated string.
     *
     * @param int        $ticketId   The ticket ID.
     * @param bool       $showEmail  Include email addresses.
     * @param bool       $showName   Include full names.
     * @param array|null $owners     Pre-fetched owner list (null = load from driver).
     */
    public function formatOwners(
        int $ticketId,
        bool $showEmail = true,
        bool $showName = true,
        ?array $owners = null,
    ): string {
        $owners ??= $this->driver->getOwners($ticketId);

        $results = [];
        if ($owners) {
            foreach (reset($owners) as $owner) {
                $results[] = $this->format($owner, $showEmail, $showName);
            }
        }

        return implode(', ', $results);
    }

    /**
     * Resolve a plain user identifier to attributes.
     *
     * @return array{type: string, user: string, name: string, email: string}
     */
    private function resolveUser(string $user): array
    {
        $attrs = ['type' => 'user', 'user' => $user, 'name' => '', 'email' => ''];

        // External email address (prefixed with **).
        if (str_starts_with($user, '**')) {
            $user = substr($user, 2);
            $attrs['user'] = $user;

            $addrOb = new Horde_Mail_Rfc822_Address($user);
            if ($addrOb->valid) {
                $attrs['name'] = $addrOb->personal ?? '';
                $attrs['email'] = $addrOb->bare_address;
            }

            return $attrs;
        }

        // Guest ticket (negative ID).
        if ((int) $user < 0) {
            $attrs['user'] = '';
            $attrs['email'] = $this->driver->getGuestEmail($user);

            $addrOb = new Horde_Mail_Rfc822_Address($attrs['email']);
            if ($addrOb->valid) {
                $attrs['name'] = $addrOb->personal ?? '';
                $attrs['email'] = $addrOb->bare_address;
            }

            return $attrs;
        }

        // Regular Horde user — look up via identity.
        $identity = $this->identityFactory->create($user);
        $attrs['name'] = $identity->getName();
        $attrs['email'] = $identity->getDefaultFromAddress();

        return $attrs;
    }

    /**
     * Resolve a group identifier to attributes.
     *
     * @return array{type: string, user: string, name: string, email: string}
     */
    private function resolveGroup(string $groupId): array
    {
        $attrs = ['type' => 'group', 'user' => $groupId, 'name' => '', 'email' => ''];

        try {
            $group = $this->groupService->getData($groupId);
            $attrs['user'] = $group['name'];
            $attrs['name'] = $group['name'];
            $attrs['email'] = $group['email'];
        } catch (Horde_Exception) {
            // Group not found — return empty attributes.
        }

        return $attrs;
    }
}
