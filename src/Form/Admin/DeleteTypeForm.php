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

class DeleteTypeForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        string $typeName,
        string $typeDescription,
        array $states,
        array $priorities,
    ) {
        parent::__construct($vars, _("Delete Type Confirmation"));
        $this->setButtons([
            ['class' => 'horde-delete', 'value' => _("Delete Type")],
        ]);

        $this->addHidden('', 'type', 'int', true, true);

        $tname = $this->addVariable(_("Type Name"), 'name', 'text', false, true);
        $tname->setDefault($typeName);

        $tdesc = $this->addVariable(
            _("Type Description"),
            'description',
            'text',
            false,
            true,
        );
        $tdesc->setDefault($typeDescription);

        $tstates = $this->addVariable(
            _("States for this Type"),
            'state',
            'set',
            false,
            true,
            null,
            [$states],
        );
        $tstates->setDefault(array_keys($states));

        $tpriorities = $this->addVariable(
            _("Priorities for this Type"),
            'priority',
            'set',
            false,
            true,
            null,
            [$priorities],
        );
        $tpriorities->setDefault(array_keys($priorities));

        $this->addVariable(
            _("Really delete this type? This may cause data problems!"),
            'yesno',
            'enum',
            true,
            false,
            null,
            [[0 => _("No"), 1 => _("Yes")]],
        );
    }
}
