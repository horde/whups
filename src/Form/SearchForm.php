<?php

declare(strict_types=1);

/**
 * V3 ticket search form.
 *
 * Copyright 2001-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsdl.php BSD
 * @package  Whups
 */

namespace Horde\Whups\Form;

use Horde\Form\V3\BaseForm;
use Horde\Form\V3\FieldGroup;
use Psr\Http\Message\ServerRequestInterface;

class SearchForm extends BaseForm
{
    /**
     * @param ServerRequestInterface|array $vars        Request or form data
     * @param array<int,string>            $queues      Queue id => name (permission-filtered)
     * @param array<int,array{typeName:string,states:array<int,string>,defaults:list<int>}> $typeStates
     *     Per-type state data: typeName, states (id => name), defaults (non-resolved state ids)
     * @param string|null                  $title       Form title override
     */
    public function __construct(
        ServerRequestInterface|array $vars,
        array $queues,
        array $typeStates,
        ?string $title = null,
    ) {
        parent::__construct($vars, $title ?? _("Ticket Search"));

        $this->setButtons(true);
        $this->appendButtons([['class' => 'horde-create', 'value' => _("Save as Query")]]);

        $this->setSection('attributes', _("Attributes"));

        $queueCount = count($queues);

        if ($queueCount === 1) {
            $this->addHidden('', 'queue', 'int', false, true);
            $this->setVar('queue', key($queues));
        } elseif ($queueCount > 0) {
            $this->addVariable(
                _("Queue"),
                'queue',
                'enum',
                false,
                false,
                null,
                [['0' => _("Any")] + $queues],
            );
        } else {
            $this->addVariable(
                _("Queue"),
                'queue',
                'invalid',
                false,
                false,
                null,
                [_("There are no queues which you can search.")],
            );
        }

        $this->addVariable(_("Summary like"), 'summary', 'text', false);

        foreach ($typeStates as $typeId => $typeData) {
            $v = $this->addVariable(
                $typeData['typeName'],
                "states[$typeId]",
                'multienum',
                false,
                false,
                null,
                [$typeData['states'], 4],
            );
            if (!$this->isSubmitted()) {
                $v->setDefault($typeData['defaults']);
            }
        }

        $this->setSection('dates', _("Dates"));

        $dateFields = [
            ['ticket_timestamp', _("Created")],
            ['date_updated', _("Updated")],
            ['date_resolved', _("Resolved")],
            ['date_assigned', _("Assigned")],
            ['ticket_due', _("Due")],
        ];

        $startYear = (int) date('Y') - 10;

        foreach ($dateFields as [$field, $label]) {
            $this->addGroup(new FieldGroup($field, $field));
            $this->addVariable(
                $label . ' ' . _("from"),
                'from',
                'monthdayyear',
                false,
                false,
                null,
                [$startYear],
            );
            $this->addVariable(
                _("to"),
                'to',
                'monthdayyear',
                false,
                false,
                null,
                [$startYear],
            );
        }
    }
}
