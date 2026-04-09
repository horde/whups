<?php

/**
 * This file contains all Horde_Form classes for queue administration.
 *
 * Copyright 2002-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @package Whups
 */

class Whups_Form_Admin_EditQueueStepTwo extends Horde_Form
{
    public function __construct($vars)
    {
        global $whups_driver, $registry;

        parent::__construct($vars);

        $queue = $vars->get('queue');
        try {
            $info = $whups_driver->getQueue($queue);
        } catch (Whups_Exception $e) {
            $this->addVariable(
                _("Invalid Queue"),
                'invalid',
                'invalid',
                true,
                false,
                null,
                [_("Invalid Queue")]
            );
            return;
        }

        $this->setTitle(sprintf(_("Edit %s"), $info['name']));
        $this->addHidden('', 'queue', 'int', true, true);

        $mname = $this->addVariable(
            _("Queue Name"),
            'name',
            'text',
            true,
            $info['readonly']
        );
        $mname->setDefault($info['name']);

        $mdesc = $this->addVariable(
            _("Queue Description"),
            'description',
            'text',
            true,
            $info['readonly']
        );
        $mdesc->setDefault($info['description']);

        $mslug = $this->addVariable(
            _("Queue Slug"),
            'slug',
            'text',
            false,
            $info['readonly']
        );
        $mslug->setDefault($info['slug']);

        $memail = $this->addVariable(
            _("Queue Email"),
            'email',
            'email',
            false,
            $info['readonly']
        );
        $memail->setDefault($info['email']);

        $types = $whups_driver->getAllTypes();
        $mtypes = $this->addVariable(
            _("Ticket Types associated with this Queue"),
            'types',
            'set',
            true,
            false,
            null,
            [$types]
        );
        $mtypes->setDefault(array_keys($whups_driver->getTypes($queue)));
        $mdefaults = $this->addVariable(
            _("Default Ticket Type"),
            'default',
            'enum',
            false,
            false,
            null,
            [$types]
        );
        $mdefaults->setDefault($whups_driver->getDefaultType($queue));

        /* Versioned and version link. */
        $mversioned = $this->addVariable(
            _("Keep a set of versions for this queue?"),
            'versioned',
            'boolean',
            false,
            $info['readonly']
        );
        $mversioned->setDefault($info['versioned']);
        if ($registry->hasMethod('tickets/listVersions') == $registry->getApp()) {
            $versionlink = [
                'text' => _("Edit the versions for this queue"),
                'url' => Horde::url('admin/?formname=whups_form_admin_editversionstepone')->add('queue', $queue)];
            $this->addVariable('', 'link', 'link', false, true, null, [$versionlink]);
        }

        /* Usertype and usertype link. */
        $users = $whups_driver->getQueueUsers($queue);
        $f_users = [];
        foreach ($users as $user) {
            $f_users[$user] = Whups::formatUser($user);
        }
        asort($f_users);
        $musers = $this->addVariable(
            _("Users responsible for this Queue"),
            'users',
            'set',
            false,
            true,
            null,
            [$f_users]
        );
        $musers->setDefault($whups_driver->getQueueUsers($queue));
        $userlink = [
            'text' => _("Edit the users responsible for this queue"),
            'url' => Horde::url('admin/?formname=edituserform')->add('queue', $queue)];
        $this->addVariable(
            '',
            'link',
            'link',
            false,
            true,
            null,
            [$userlink]
        );

        /* Permissions link. */
        if ($GLOBALS['registry']->isAdmin(['permission' => 'whups:admin', 'permlevel' => Horde_Perms::EDIT])) {
            $permslink = [
                'text' => _("Edit the permissions on this queue"),
                'url' => Horde::url(
                    'admin/perms/edit.php',
                    false,
                    ['app' => 'horde']
                )
                            ->add(['category' => 'whups:queues:' . $queue,
                                'autocreate' => '1'])];
            $this->addVariable(
                '',
                'link',
                'link',
                false,
                true,
                null,
                [$permslink]
            );
        }
    }

}
