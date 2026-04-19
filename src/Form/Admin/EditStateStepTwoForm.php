<?php

declare(strict_types=1);

namespace Horde\Whups\Form\Admin;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class EditStateStepTwoForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        array $stateInfo,
        array $categories,
    ) {
        parent::__construct($vars, _("Edit State"));

        $this->addHidden('', 'type', 'int', true, true);
        $this->addHidden('', 'state', 'int', true, true);

        $sname = $this->addVariable(_("State Name"), 'name', 'text', true);
        $sname->setDefault($stateInfo['name']);

        $sdesc = $this->addVariable(_("State Description"), 'description', 'text', true);
        $sdesc->setDefault($stateInfo['description']);

        $scat = $this->addVariable(
            _("State Category"),
            'category',
            'enum',
            true,
            false,
            null,
            [$categories],
        );
        $scat->setDefault($stateInfo['category']);
    }
}
