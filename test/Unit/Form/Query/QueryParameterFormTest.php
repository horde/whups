<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Form\Query;

use Horde\Whups\Form\Query\QueryParameterForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryParameterForm::class)]
class QueryParameterFormTest extends TestCase
{
    public function testConstructionCreatesFieldPerParameter(): void
    {
        $form = new QueryParameterForm([], ['username', 'project']);

        $vars = $form->getVariables(flat: true);
        $names = array_map(fn($v) => $v->getVarName(), $vars);

        $this->assertContains('username', $names);
        $this->assertContains('project', $names);
        $this->assertCount(2, $vars);
    }

    public function testFieldsAreRequired(): void
    {
        $form = new QueryParameterForm([], ['status']);

        $vars = $form->getVariables(flat: true);
        $statusVar = $vars[0];

        $this->assertTrue($statusVar->isRequired());
    }

    public function testEmptyParametersCreatesNoFields(): void
    {
        $form = new QueryParameterForm([], []);

        $vars = $form->getVariables(flat: true);
        $this->assertCount(0, $vars);
    }

    public function testButtonLabel(): void
    {
        $form = new QueryParameterForm([], ['x']);

        $buttons = $form->getButtons();
        $this->assertCount(1, $buttons);
        $this->assertSame('Execute Query', $buttons[0]);
    }

    public function testValidationPassesWithAllParameters(): void
    {
        $form = new QueryParameterForm([
            'formname' => 'horde_whups_form_query_queryparameterform',
            'username' => 'alice',
            'project' => 'whups',
        ], ['username', 'project']);

        $this->assertTrue($form->validate());
    }

    public function testValidationFailsWithMissingParameter(): void
    {
        $form = new QueryParameterForm([
            'formname' => 'horde_whups_form_query_queryparameterform',
            'username' => 'alice',
            'project' => '',
        ], ['username', 'project']);

        $this->assertFalse($form->validate());
    }

    public function testGetInfoReturnsParameterValues(): void
    {
        $form = new QueryParameterForm([
            'formname' => 'horde_whups_form_query_queryparameterform',
            'username' => 'bob',
            'project' => 'whups',
        ], ['username', 'project']);

        $form->validate();
        $info = $form->getInfo();

        $this->assertSame('bob', $info['username']);
        $this->assertSame('whups', $info['project']);
    }

    public function testIsSubmitted(): void
    {
        $submitted = new QueryParameterForm([
            'formname' => 'horde_whups_form_query_queryparameterform',
            'username' => 'x',
        ], ['username']);

        $notSubmitted = new QueryParameterForm([], ['username']);

        $this->assertTrue($submitted->isSubmitted());
        $this->assertFalse($notSubmitted->isSubmitted());
    }

    public function testFormTokenDisabled(): void
    {
        $form = new QueryParameterForm([], ['x']);

        // Validation should pass without a CSRF token since useFormToken is false.
        $valid = new QueryParameterForm([
            'formname' => 'horde_whups_form_query_queryparameterform',
            'x' => 'value',
        ], ['x']);

        $this->assertTrue($valid->validate());
    }
}
