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

class EditTypeStepTwoForm extends BaseForm
{
    public function __construct(
        ServerRequestInterface|array $vars,
        array $typeInfo,
        array $states,
        array $priorities,
        array $attributes,
        array $replies,
        string $webroot,
        int $typeId,
    ) {
        parent::__construct(
            $vars,
            sprintf(_("Edit %s"), $typeInfo['name']),
        );

        $this->addHidden('', 'type', 'int', true, true);

        $tname = $this->addVariable(_("Type Name"), 'name', 'text', true);
        $tname->setDefault($typeInfo['name']);

        $tdesc = $this->addVariable(
            _("Type Description"),
            'description',
            'text',
            true,
        );
        $tdesc->setDefault($typeInfo['description']);

        // States (read-only display)
        $tstates = $this->addVariable(
            _("States for this Type"),
            'state',
            'set',
            false,
            true,
            null,
            [$states],
        );
        $tstates->setDefault(array_keys($states));

        $stateLinks = [
            ['text' => _("Edit States"),
                'url' => $webroot . '/admin/?action=type&subaction=editstates&type=' . $typeId],
        ];
        if (!count($states)) {
            $stateLinks[] = [
                'text' => _("Create Default States"),
                'url' => $webroot . '/admin/?action=type&subaction=createdefaultstates&type=' . $typeId,
            ];
        }
        $this->addVariable('', 'statelink', 'link', false, true, null, [$stateLinks]);

        // Priorities (read-only display)
        $tpriorities = $this->addVariable(
            _("Priorities for this Type"),
            'priority',
            'set',
            false,
            true,
            null,
            [$priorities],
        );
        $tpriorities->setDefault(array_keys($priorities));

        $priorityLinks = [
            ['text' => _("Edit Priorities"),
                'url' => $webroot . '/admin/?action=type&subaction=editpriorities&type=' . $typeId],
        ];
        if (!count($priorities)) {
            $priorityLinks[] = [
                'text' => _("Create Default Priorities"),
                'url' => $webroot . '/admin/?action=type&subaction=createdefaultpriorities&type=' . $typeId,
            ];
        }
        $this->addVariable('', 'prioritylink', 'link', false, true, null, [$priorityLinks]);

        // Attributes (read-only display)
        $attrNames = [];
        foreach ($attributes as $key => $attribute) {
            $attrNames[$key] = $attribute['human_name'];
        }
        $tattributes = $this->addVariable(
            _("Attributes for this Type"),
            'attribute',
            'set',
            false,
            true,
            null,
            [$attrNames],
        );
        $tattributes->setDefault(array_keys($attributes));

        $this->addVariable('', 'attributelink', 'link', false, true, null, [
            ['text' => _("Edit Attributes"),
                'url' => $webroot . '/admin/?action=type&subaction=editattributes&type=' . $typeId],
        ]);

        // Form replies (read-only display)
        $replyNames = [];
        foreach ($replies as $key => $reply) {
            $replyNames[$key] = $reply['reply_name'];
        }
        $treplies = $this->addVariable(
            _("Form Replies for this Type"),
            'reply',
            'set',
            false,
            true,
            null,
            [$replyNames],
        );
        $treplies->setDefault(array_keys($replies));

        $this->addVariable('', 'replylink', 'link', false, true, null, [
            ['text' => _("Edit Form Replies"),
                'url' => $webroot . '/admin/?action=type&subaction=editreplies&type=' . $typeId],
        ]);
    }
}
