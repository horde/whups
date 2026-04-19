<?php

declare(strict_types=1);

namespace Horde\Whups\Form\Admin;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class AddReplyForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
    ) {
        parent::__construct($vars, _("Add Form Reply"));
        $this->appendButtons(_("Add Reply"));

        $this->addHidden('', 'type', 'int', true, true);
        $this->addVariable(_("Form Reply Name"), 'reply_name', 'text', true);
        $this->addVariable(_("Form Reply Text"), 'reply_text', 'longtext', true);
    }
}
