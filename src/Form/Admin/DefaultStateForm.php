<?php

declare(strict_types=1);

namespace Horde\Whups\Form\Admin;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class DefaultStateForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        array $states,
        int|string|null $currentDefault,
    ) {
        parent::__construct($vars, _("Set Default State"));
        $this->setButtons([_("Set Default State")]);

        $this->addHidden('', 'type', 'int', true, true);

        if (count($states)) {
            $var = $this->addVariable(_("State Name"), 'state', 'enum', false, false, null, [$states]);
        } else {
            $var = $this->addVariable(_("State Name"), 'state', 'invalid', false, false, null, [_("There are no states to edit")]);
        }
        if ($currentDefault !== null) {
            $var->setDefault($currentDefault);
        }
    }
}
