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

class AddUserForm extends BaseForm
{
    /**
     * @param ServerRequestInterface|array $vars            Request or form data
     * @param array<string,string>         $availableUsers  User id => display name (excludes current queue users)
     * @param bool                         $canList         Whether the auth backend supports user listing
     */
    public function __construct(
        ServerRequestInterface|array $vars,
        array $availableUsers,
        bool $canList = true,
    ) {
        parent::__construct($vars, _("Add Users"));
        $this->appendButtons(_("Add User"));

        $this->addHidden('', 'queue', 'int', true, true);

        if ($canList) {
            if ($availableUsers) {
                $this->addVariable(_("User"), 'user', 'multienum', true, false, null, [$availableUsers]);
            } else {
                $this->addVariable(
                    _("User"),
                    'user',
                    'invalid',
                    false,
                    false,
                    null,
                    [_("All users are already assigned to this queue.")],
                );
            }
        } else {
            $this->addVariable(_("User"), 'user', 'text', true);
        }
    }
}
