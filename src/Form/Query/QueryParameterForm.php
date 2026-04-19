<?php

declare(strict_types=1);

/**
 * Form for entering query parameter values before execution.
 *
 * Queries may contain named parameters (e.g. ${username}) that must be
 * filled in before the query can run. This form adds a required text
 * field for each parameter.
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

namespace Horde\Whups\Form\Query;

use Horde\Form\V3\BaseForm;

class QueryParameterForm extends BaseForm
{
    protected bool $useFormToken = false;

    /**
     * @param array<string, mixed>|\Psr\Http\Message\ServerRequestInterface $vars
     * @param list<string> $parameters  Parameter names from the query.
     */
    public function __construct(
        $vars,
        array $parameters,
    ) {
        parent::__construct($vars, _("Query Parameters"));

        foreach ($parameters as $name) {
            $this->addVariable($name, $name, 'text', true);
        }

        $this->setButtons([_("Execute Query")]);
    }
}
