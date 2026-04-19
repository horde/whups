<?php

declare(strict_types=1);

namespace Horde\Whups\Form\Admin;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class AddStateForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        array $categories,
    ) {
        parent::__construct($vars, _("Add State"));
        $this->appendButtons(_("Add State"));

        $this->addHidden('', 'type', 'int', true, true);
        $this->addVariable(_("State Name"), 'name', 'text', true);
        $this->addVariable(_("State Description"), 'description', 'text', true);
        $this->addVariable(
            _("State Category"),
            'category',
            'enum',
            false,
            false,
            null,
            [$categories],
        );
    }
}
