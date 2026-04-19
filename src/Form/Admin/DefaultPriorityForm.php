<?php

declare(strict_types=1);

namespace Horde\Whups\Form\Admin;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class DefaultPriorityForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        array $priorities,
        int|string|null $currentDefault,
    ) {
        parent::__construct($vars, _("Set Default Priority"));
        $this->setButtons([_("Set Default Priority")]);

        $this->addHidden('', 'type', 'int', true, true);

        if (count($priorities)) {
            $var = $this->addVariable(_("Priority Name"), 'priority', 'enum', false, false, null, [$priorities]);
        } else {
            $var = $this->addVariable(_("Priority Name"), 'priority', 'invalid', false, false, null, [_("There are no priorities to edit")]);
        }
        if ($currentDefault !== null) {
            $var->setDefault($currentDefault);
        }
    }
}
