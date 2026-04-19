<?php

declare(strict_types=1);

/**
 * Copyright 2002-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Form\Admin;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class EditQueueStepTwoForm extends BaseForm
{
    /**
     * @param ServerRequestInterface|array $vars         Request or form data
     * @param array                        $queueInfo    Queue record (name, description, slug, email, versioned, readonly)
     * @param array<int,string>            $allTypes     All type id => name
     * @param array<int>                   $queueTypeIds Type IDs currently assigned to this queue
     * @param int|null                     $defaultType  Default type ID for this queue
     * @param array<string,string>         $queueUsers   User id => formatted name (already sorted)
     * @param string                       $webroot      Whups webroot
     * @param int                          $queueId      Queue ID
     * @param string|null                  $versionEditUrl  URL to edit versions, null if not available
     * @param string|null                  $permsEditUrl    URL to edit permissions, null if not available
     * @param string|null                  $userEditUrl     URL to edit responsible users, null if not available
     */
    public function __construct(
        ServerRequestInterface|array $vars,
        array $queueInfo,
        array $allTypes,
        array $queueTypeIds,
        ?int $defaultType,
        array $queueUsers,
        string $webroot,
        int $queueId,
        ?string $versionEditUrl = null,
        ?string $permsEditUrl = null,
        ?string $userEditUrl = null,
    ) {
        parent::__construct($vars);
        $this->setTitle(sprintf(_("Edit %s"), $queueInfo['name']));
        $this->addHidden('', 'queue', 'int', true, true);

        $readonly = (bool) ($queueInfo['readonly'] ?? false);

        $mname = $this->addVariable(_("Queue Name"), 'name', 'text', true, $readonly);
        $mname->setDefault($queueInfo['name']);

        $mdesc = $this->addVariable(_("Queue Description"), 'description', 'text', true, $readonly);
        $mdesc->setDefault($queueInfo['description']);

        $mslug = $this->addVariable(_("Queue Slug"), 'slug', 'text', false, $readonly);
        $mslug->setDefault($queueInfo['slug'] ?? '');

        $memail = $this->addVariable(_("Queue Email"), 'email', 'email', false, $readonly);
        $memail->setDefault($queueInfo['email'] ?? '');

        $mtypes = $this->addVariable(
            _("Ticket Types associated with this Queue"),
            'types',
            'set',
            true,
            false,
            null,
            [$allTypes]
        );
        $mtypes->setDefault($queueTypeIds);

        $mdefaults = $this->addVariable(
            _("Default Ticket Type"),
            'default',
            'enum',
            false,
            false,
            null,
            [$allTypes]
        );
        $mdefaults->setDefault($defaultType);

        $mversioned = $this->addVariable(
            _("Keep a set of versions for this queue?"),
            'versioned',
            'boolean',
            false,
            $readonly
        );
        $mversioned->setDefault($queueInfo['versioned'] ?? false);

        if ($versionEditUrl !== null) {
            $versionlink = [
                'text' => _("Edit the versions for this queue"),
                'url' => $versionEditUrl,
            ];
            $this->addVariable('', 'versionlink', 'link', false, true, null, [$versionlink]);
        }

        $musers = $this->addVariable(
            _("Users responsible for this Queue"),
            'users',
            'set',
            false,
            true,
            null,
            [$queueUsers]
        );
        $musers->setDefault(array_keys($queueUsers));

        if ($userEditUrl !== null) {
            $userlink = [
                'text' => _("Edit the users responsible for this queue"),
                'url' => $userEditUrl,
            ];
            $this->addVariable('', 'userlink', 'link', false, true, null, [$userlink]);
        }

        if ($permsEditUrl !== null) {
            $permslink = [
                'text' => _("Edit the permissions on this queue"),
                'url' => $permsEditUrl,
            ];
            $this->addVariable('', 'permslink', 'link', false, true, null, [$permslink]);
        }
    }
}
