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

class AddCommentForm extends BaseForm
{
    /**
     * @param ServerRequestInterface|array $vars         Request or form data
     * @param bool                         $isGuest      Whether the user is a guest
     * @param string|null                  $captchaText  CAPTCHA text (null to skip CAPTCHA)
     * @param string|null                  $captchaFont  Path to figlet font file
     * @param array<int|string,string>     $groups       Group id => name, empty to omit
     * @param string                       $title        Form title
     */
    public function __construct(
        ServerRequestInterface|array $vars,
        bool $isGuest = false,
        ?string $captchaText = null,
        ?string $captchaFont = null,
        array $groups = [],
        string $title = '',
    ) {
        parent::__construct($vars, $title ?: _("Add Comment"));

        $this->addHidden('', 'id', 'int', true, true);

        if ($isGuest) {
            $this->addVariable(_("Your Email Address"), 'user_email', 'email', true);
            if ($captchaText !== null && $captchaFont !== null) {
                $this->addVariable(
                    _("Spam protection"),
                    'captcha',
                    'figlet',
                    true,
                    false,
                    null,
                    [$captchaText, $captchaFont],
                );
            }
        }

        $this->addVariable(_("Comment"), 'newcomment', 'longtext', false);
        $this->addVariable(_("Attachment"), 'newattachment', 'file', false);
        $this->addVariable(_("Watch this ticket"), 'add_watch', 'boolean', false);

        if ($groups) {
            $this->addVariable(
                _("Make this comment visible only to members of a group?"),
                'group',
                'enum',
                true,
                false,
                null,
                [$groups],
            );
        }
    }
}
