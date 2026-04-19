<?php

declare(strict_types=1);

/**
 * Copyright 2002-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 */

namespace Horde\Whups\Form\Admin;

use Horde\Form\V3\BaseForm;
use Psr\Http\Message\ServerRequestInterface;

class AddQueueForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        string $webroot,
    ) {
        parent::__construct($vars, _("Add Queue"));
        $this->appendButtons(_("Add Queue"));

        $this->addVariable(_("Queue Name"), 'name', 'text', true);
        $this->addVariable(_("Queue Description"), 'description', 'text', true);
        $this->addVariable(
            _("Queue Slug"),
            'slug',
            'text',
            false,
            false,
            sprintf(
                _("Slugs allows direct access to this queue's open tickets by visiting: %s. Slug names may contain only letters, numbers or the _ (underscore) character."),
                htmlspecialchars($webroot . '/queue/slugname')
            ),
            ['/^[a-zA-Z1-9_]*$/']
        );
        $this->addVariable(
            _("Queue Email"),
            'email',
            'email',
            false,
            false,
            _("This email address will be used when sending notifications for any queue tickets.")
        );
    }
}
