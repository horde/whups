<?php

declare(strict_types=1);

namespace Horde\Whups\Form\Admin;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class AddPriorityForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
    ) {
        parent::__construct($vars, _("Add Priority"));
        $this->appendButtons(_("Add Priority"));

        $this->addHidden('', 'type', 'int', true, true);
        $this->addVariable(_("Priority Name"), 'name', 'text', true);
        $this->addVariable(_("Priority Description"), 'description', 'text', true);
    }
}
