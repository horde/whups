<?php

declare(strict_types=1);

namespace Horde\Whups\Form\Admin;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class EditPriorityStepTwoForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        array $priorityInfo,
    ) {
        parent::__construct($vars, _("Edit Priority"));

        $this->addHidden('', 'type', 'int', true, true);
        $this->addHidden('', 'priority', 'int', true, true);

        $pname = $this->addVariable(_("Priority Name"), 'name', 'text', true);
        $pname->setDefault($priorityInfo['name']);

        $pdesc = $this->addVariable(_("Priority Description"), 'description', 'text', true);
        $pdesc->setDefault($priorityInfo['description']);
    }
}
