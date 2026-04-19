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

class EditTypeStepOneForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        array $types,
    ) {
        parent::__construct($vars, _("Edit or Delete Types"));
        $this->setButtons([
            _("Edit Type"),
            _("Clone Type"),
            ['class' => 'horde-delete', 'value' => _("Delete Type")],
        ]);

        if (count($types)) {
            $this->addVariable(
                _("Type Name"),
                'type',
                'enum',
                true,
                false,
                null,
                [$types],
            );
        } else {
            $this->addVariable(
                _("Type Name"),
                'type',
                'invalid',
                true,
                false,
                null,
                [_("There are no types to edit")],
            );
        }
    }
}
