<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Form\Admin;

use Horde\Whups\Form\Admin\EditVersionStepTwoForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EditVersionStepTwoForm::class)]
class EditVersionStepTwoFormTest extends TestCase
{
    private array $versionInfo = [
        'name' => '2.0',
        'description' => 'Second release',
        'active' => true,
    ];

    public function testConstructionSetsDefaults(): void
    {
        $form = new EditVersionStepTwoForm([], $this->versionInfo);

        $vars = $form->getVariables(flat: true);
        $byName = [];
        foreach ($vars as $var) {
            $byName[$var->getVarName()] = $var;
        }

        $this->assertSame('2.0', $byName['name']->getDefault());
        $this->assertSame('Second release', $byName['description']->getDefault());
        $this->assertTrue($byName['active']->getDefault());
    }

    public function testTitleIncludesVersionName(): void
    {
        $form = new EditVersionStepTwoForm([], $this->versionInfo);

        $this->assertStringContainsString('2.0', $form->getTitle());
    }

    public function testHiddenFields(): void
    {
        $form = new EditVersionStepTwoForm([], $this->versionInfo);

        $all = $form->getVariables(flat: true, withHidden: true);
        $hiddenNames = [];
        foreach ($all as $var) {
            if ($var->isHidden()) {
                $hiddenNames[] = $var->getVarName();
            }
        }

        $this->assertContains('queue', $hiddenNames);
        $this->assertContains('version', $hiddenNames);
    }

    public function testValidationPassesWithRequiredFields(): void
    {
        $form = new EditVersionStepTwoForm([
            'formname' => 'horde_whups_form_admin_editversionsteptwoform',
            'queue' => '1',
            'version' => '5',
            'name' => '2.1',
            'description' => 'Updated',
        ], $this->versionInfo);

        $this->assertTrue($form->validate());
    }

    public function testValidationFailsWithoutName(): void
    {
        $form = new EditVersionStepTwoForm([
            'formname' => 'horde_whups_form_admin_editversionsteptwoform',
            'queue' => '1',
            'version' => '5',
            'name' => '',
            'description' => 'Updated',
        ], $this->versionInfo);

        $this->assertFalse($form->validate());
    }

    public function testGetInfoReturnsSubmittedValues(): void
    {
        $form = new EditVersionStepTwoForm([
            'formname' => 'horde_whups_form_admin_editversionsteptwoform',
            'queue' => '1',
            'version' => '5',
            'name' => '2.1',
            'description' => 'Updated release',
            'active' => '1',
        ], $this->versionInfo);

        $form->validate();
        $info = $form->getInfo();

        $this->assertSame(5, $info['version']);
        $this->assertSame('2.1', $info['name']);
        $this->assertSame('Updated release', $info['description']);
    }

    public function testInactiveVersionPreservesDefault(): void
    {
        $inactiveInfo = ['name' => 'Old', 'description' => 'Deprecated', 'active' => false];
        $form = new EditVersionStepTwoForm([], $inactiveInfo);

        $vars = $form->getVariables(flat: true);
        $byName = [];
        foreach ($vars as $var) {
            $byName[$var->getVarName()] = $var;
        }

        $this->assertFalse($byName['active']->getDefault());
    }

    public function testIsSubmitted(): void
    {
        $submitted = new EditVersionStepTwoForm([
            'formname' => 'horde_whups_form_admin_editversionsteptwoform',
            'queue' => '1',
            'version' => '5',
            'name' => 'X',
        ], $this->versionInfo);

        $notSubmitted = new EditVersionStepTwoForm([], $this->versionInfo);

        $this->assertTrue($submitted->isSubmitted());
        $this->assertFalse($notSubmitted->isSubmitted());
    }
}
