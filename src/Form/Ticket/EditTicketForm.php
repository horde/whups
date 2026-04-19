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

class EditTicketForm extends BaseForm
{
    /**
     * @param ServerRequestInterface|array $vars           Request or form data
     * @param array<string,array>          $fieldData      Pre-loaded field data (see buildFieldData)
     * @param array<string,array>|null     $groupedFields  Grouped field order from hook, null = default order
     * @param string                       $title          Form title
     */
    public function __construct(
        ServerRequestInterface|array $vars,
        array $fieldData,
        ?array $groupedFields = null,
        string $title = '',
    ) {
        parent::__construct($vars, $title ?: _("Update Ticket"));

        $this->addHidden('', 'id', 'int', true, true);
        $this->addHidden('', 'type', 'int', true, true);

        if ($groupedFields === null) {
            // Default: single flat list of all fields.
            foreach ($fieldData as $fieldName => $data) {
                $this->addFieldByName($fieldName, $data);
            }
        } else {
            // Hook-based grouping: fields ordered into named sections.
            foreach ($groupedFields as $header => $fields) {
                $this->addVariable($header, null, 'header', false);
                foreach ($fields as $fieldName) {
                    if (isset($fieldData[$fieldName])) {
                        $this->addFieldByName($fieldName, $fieldData[$fieldName]);
                    }
                }
            }
        }
    }

    /**
     * Add a field by its canonical name using pre-loaded data.
     */
    private function addFieldByName(string $fieldName, array $data): void
    {
        switch ($data['kind'] ?? 'standard') {
            case 'multienum':
                $this->addVariable(
                    $data['label'],
                    $data['varName'],
                    'multienum',
                    $data['required'] ?? false,
                    $data['readonly'] ?? false,
                    $data['description'] ?? null,
                    [$data['values']],
                );
                break;

            case 'enum':
                $var = $this->addVariable(
                    $data['label'],
                    $data['varName'],
                    'enum',
                    $data['required'] ?? true,
                    $data['readonly'] ?? false,
                    $data['description'] ?? null,
                    $data['params'] ?? [$data['values'] ?? []],
                );
                if (!empty($data['default'])) {
                    $var->setDefault($data['default']);
                }
                break;

            case 'attribute':
                $var = $this->addVariable(
                    $data['label'],
                    $data['varName'],
                    $data['type'],
                    $data['required'] ?? false,
                    $data['readonly'] ?? false,
                    $data['description'] ?? null,
                    $data['params'] ?? [],
                );
                if (isset($data['default'])) {
                    $var->setDefault($data['default']);
                }
                break;

            default:
                $this->addVariable(
                    $data['label'],
                    $data['varName'],
                    $data['type'] ?? 'text',
                    $data['required'] ?? false,
                    $data['readonly'] ?? false,
                    $data['description'] ?? null,
                    $data['params'] ?? [],
                );
                break;
        }
    }
}
