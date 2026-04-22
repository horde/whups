<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Form\Admin;

use Horde\Whups\Form\Admin\AddVersionForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AddVersionForm::class)]
class AddVersionFormTest extends TestCase
{
    public function testConstructionAddsExpectedVariables(): void
    {
        $form = new AddVersionForm([]);

        $vars = $form->getVariables(flat: true);
        $names = array_map(fn($v) => $v->getVarName(), $vars);

        $this->assertContains('name', $names);
        $this->assertContains('description', $names);
        $this->assertContains('active', $names);
    }

    public function testHiddenQueueField(): void
    {
        $form = new AddVersionForm([]);

        $all = $form->getVariables(flat: true, withHidden: true);
        $hiddenNames = [];
        foreach ($all as $var) {
            if ($var->isHidden()) {
                $hiddenNames[] = $var->getVarName();
            }
        }

        $this->assertContains('queue', $hiddenNames);
    }

    public function testButtonLabel(): void
    {
        $form = new AddVersionForm([]);

        $buttons = $form->getButtons();
        $this->assertCount(1, $buttons);
        $this->assertSame('Add Version', $buttons[0]);
    }

    public function testActiveDefaultsToTrue(): void
    {
        $form = new AddVersionForm([]);

        $vars = $form->getVariables(flat: true);
        $byName = [];
        foreach ($vars as $var) {
            $byName[$var->getVarName()] = $var;
        }

        $this->assertTrue($byName['active']->getDefault());
    }

    public function testValidationPassesWithRequiredFields(): void
    {
        $form = new AddVersionForm([
            'formname' => 'horde_whups_form_admin_addversionform',
            'queue' => '1',
            'name' => '1.0',
            'description' => 'Initial release',
        ]);

        $this->assertTrue($form->validate());
    }

    public function testValidationFailsWithoutName(): void
    {
        $form = new AddVersionForm([
            'formname' => 'horde_whups_form_admin_addversionform',
            'queue' => '1',
            'name' => '',
            'description' => 'Initial release',
        ]);

        $this->assertFalse($form->validate());
    }

    public function testValidationFailsWithoutDescription(): void
    {
        $form = new AddVersionForm([
            'formname' => 'horde_whups_form_admin_addversionform',
            'queue' => '1',
            'name' => '1.0',
            'description' => '',
        ]);

        $this->assertFalse($form->validate());
    }

    public function testGetInfoReturnsSubmittedValues(): void
    {
        $form = new AddVersionForm([
            'formname' => 'horde_whups_form_admin_addversionform',
            'queue' => '3',
            'name' => '2.0',
            'description' => 'Second release',
            'active' => '1',
        ]);

        $form->validate();
        $info = $form->getInfo();

        $this->assertSame(3, $info['queue']);
        $this->assertSame('2.0', $info['name']);
        $this->assertSame('Second release', $info['description']);
    }

    public function testIsSubmitted(): void
    {
        $submitted = new AddVersionForm([
            'formname' => 'horde_whups_form_admin_addversionform',
            'queue' => '1',
            'name' => 'X',
        ]);

        $notSubmitted = new AddVersionForm([]);

        $this->assertTrue($submitted->isSubmitted());
        $this->assertFalse($notSubmitted->isSubmitted());
    }
}
