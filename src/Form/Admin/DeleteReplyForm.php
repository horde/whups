<?php

declare(strict_types=1);

namespace Horde\Whups\Form\Admin;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class DeleteReplyForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        string $replyName,
        string $replyText,
    ) {
        parent::__construct($vars, _("Delete Form Reply Confirmation"));
        $this->setButtons([
            ['class' => 'horde-delete', 'value' => _("Delete Reply")],
        ]);

        $this->addHidden('', 'type', 'int', true, true);
        $this->addHidden('', 'reply', 'int', true, true);

        $pname = $this->addVariable(_("Form Reply Name"), 'reply_name', 'text', false, true);
        $pname->setDefault($replyName);

        $ptext = $this->addVariable(_("Form Reply Text"), 'reply_text', 'text', false, true);
        $ptext->setDefault($replyText);

        $this->addVariable(
            _("Really delete this form reply?"),
            'yesno',
            'enum',
            true,
            false,
            null,
            [[0 => _("No"), 1 => _("Yes")]],
        );
    }
}
