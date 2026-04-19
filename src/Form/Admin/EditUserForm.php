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

class EditUserForm extends BaseForm
{
    /**
     * @param ServerRequestInterface|array $vars        Request or form data
     * @param array<string,string>         $queueUsers  User id => formatted display name
     */
    public function __construct(
        ServerRequestInterface|array $vars,
        array $queueUsers,
    ) {
        parent::__construct($vars, _("Responsible Users"));
        $this->setButtons([
            ['class' => 'horde-delete', 'value' => _("Remove User")],
        ]);

        $this->addHidden('', 'queue', 'int', true, true);

        if ($queueUsers) {
            $this->addVariable(
                _("Users responsible for this queue"),
                'user',
                'enum',
                true,
                false,
                null,
                [$queueUsers],
            );
        } else {
            $this->addVariable(
                _("Users responsible for this queue"),
                'user',
                'invalid',
                false,
                false,
                null,
                [_("There are no users responsible for this queue.")],
            );
        }
    }
}
