<?php

declare(strict_types=1);

namespace Horde\Whups\Form\Admin;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class EditReplyStepTwoForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        array $replyInfo,
        ?string $permsEditUrl = null,
    ) {
        parent::__construct($vars, _("Edit Form Reply"));

        $this->addHidden('', 'type', 'int', true, true);
        $this->addHidden('', 'reply', 'int', true, true);

        $pname = $this->addVariable(_("Form Reply Name"), 'reply_name', 'text', true);
        $pname->setDefault($replyInfo['reply_name']);

        $ptext = $this->addVariable(_("Form Reply Text"), 'reply_text', 'longtext', true);
        $ptext->setDefault($replyInfo['reply_text']);

        if ($permsEditUrl !== null) {
            $this->addVariable('', 'permslink', 'link', false, true, null, [
                ['text' => _("Edit the permissions on this form reply"), 'url' => $permsEditUrl],
            ]);
        }
    }
}
