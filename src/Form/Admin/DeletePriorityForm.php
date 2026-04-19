<?php

declare(strict_types=1);

namespace Horde\Whups\Form\Admin;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class DeletePriorityForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        string $priorityName,
        string $priorityDescription,
    ) {
        parent::__construct($vars, _("Delete Priority Confirmation"));
        $this->setButtons([
            ['class' => 'horde-delete', 'value' => _("Delete Priority")],
        ]);

        $this->addHidden('', 'type', 'int', true, true);
        $this->addHidden('', 'priority', 'int', true, true);

        $pname = $this->addVariable(_("Priority Name"), 'name', 'text', false, true);
        $pname->setDefault($priorityName);

        $pdesc = $this->addVariable(_("Priority Description"), 'description', 'text', false, true);
        $pdesc->setDefault($priorityDescription);

        $this->addVariable(
            _("Really delete this priority? This may cause data problems!"),
            'yesno',
            'enum',
            true,
            false,
            null,
            [[0 => _("No"), 1 => _("Yes")]],
        );
    }
}
