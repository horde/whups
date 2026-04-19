<?php

declare(strict_types=1);

/**
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Form\Ticket;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class DeleteListenerForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        string $title = '',
    ) {
        parent::__construct($vars, $title ?: _("Remove Watcher"));

        $this->addHidden('', 'id', 'int', true, true);
        $this->addVariable(_("Email address to remove"), 'del_listener', 'email', true);
    }
}
