<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Form\Admin;

use Horde\Whups\Form\Admin\EditQueueStepTwoForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EditQueueStepTwoForm::class)]
class EditQueueStepTwoFormTest extends TestCase
{
    private function buildForm(array $vars = []): EditQueueStepTwoForm
    {
        return new EditQueueStepTwoForm(
            $vars,
            queueInfo: [
                'name' => 'Support',
                'description' => 'Help desk queue',
                'slug' => 'support',
                'email' => 'help@example.com',
                'versioned' => false,
            ],
            allTypes: [10 => 'Bug', 20 => 'Feature'],
            queueTypeIds: [10],
            defaultType: 10,
            queueUsers: ['admin' => 'Admin User', 'dev' => 'Developer'],
            webroot: '/whups',
            queueId: 5,
        );
    }

    public function testConstructionSetsTitle(): void
    {
        $form = $this->buildForm();
        $this->assertSame('Edit Support', $form->getTitle());
    }

    public function testConstructionSetsDefaults(): void
    {
        $form = $this->buildForm();

        $vars = $form->getVariables(flat: true);
        $byName = [];
        foreach ($vars as $var) {
            $byName[$var->getVarName()] = $var;
        }

        $this->assertSame('Support', $byName['name']->getDefault());
        $this->assertSame('Help desk queue', $byName['description']->getDefault());
        $this->assertSame('support', $byName['slug']->getDefault());
        $this->assertSame('help@example.com', $byName['email']->getDefault());
        $this->assertSame(false, $byName['versioned']->getDefault());
        $this->assertSame([10], $byName['types']->getDefault());
        $this->assertSame(10, $byName['default']->getDefault());
    }

    public function testHiddenQueueField(): void
    {
        $form = $this->buildForm();

        $hidden = $form->getVariables(flat: true, withHidden: true);
        $hiddenNames = [];
        foreach ($hidden as $var) {
            if ($var->isHidden()) {
                $hiddenNames[] = $var->getVarName();
            }
        }

        $this->assertContains('queue', $hiddenNames);
    }

    public function testValidationPassesWithRequiredFields(): void
    {
        $form = $this->buildForm([
            'formname' => 'horde_whups_form_admin_editqueuesteptwoform',
            'queue' => '5',
            'name' => 'Support Updated',
            'description' => 'Updated description',
            'types' => ['10', '20'],
        ]);

        $this->assertTrue($form->validate());
    }

    public function testValidationFailsWithoutName(): void
    {
        $form = $this->buildForm([
            'formname' => 'horde_whups_form_admin_editqueuesteptwoform',
            'queue' => '5',
            'name' => '',
            'description' => 'Updated description',
            'types' => ['10'],
        ]);

        $this->assertFalse($form->validate());
    }

    public function testGetInfoReturnsTypedValues(): void
    {
        $form = $this->buildForm([
            'formname' => 'horde_whups_form_admin_editqueuesteptwoform',
            'queue' => '5',
            'name' => 'New Name',
            'description' => 'New Desc',
            'slug' => 'new_slug',
            'email' => 'new@example.com',
            'types' => ['10', '20'],
            'default' => '20',
            'versioned' => 'on',
        ]);

        $form->validate();
        $info = $form->getInfo();

        $this->assertSame('New Name', $info['name']);
        $this->assertSame('New Desc', $info['description']);
        $this->assertSame('new_slug', $info['slug']);
        $this->assertSame('new@example.com', $info['email']);
        $this->assertSame(5, $info['queue']);
        // Boolean coercion
        $this->assertTrue($info['versioned']);
        // Enum should return typed key
        $this->assertSame(20, $info['default']);
    }

    public function testVersionLinkIncludedWhenUrlProvided(): void
    {
        $form = new EditQueueStepTwoForm(
            [],
            queueInfo: ['name' => 'Q', 'description' => 'D'],
            allTypes: [],
            queueTypeIds: [],
            defaultType: null,
            queueUsers: [],
            webroot: '/whups',
            queueId: 1,
            versionEditUrl: '/whups/admin/?formname=editversions&queue=1',
        );

        $vars = $form->getVariables(flat: true);
        $names = array_map(fn($v) => $v->getVarName(), $vars);
        $this->assertContains('versionlink', $names);
    }

    public function testVersionLinkOmittedWhenUrlNull(): void
    {
        $form = $this->buildForm();

        $vars = $form->getVariables(flat: true);
        $names = array_map(fn($v) => $v->getVarName(), $vars);
        $this->assertNotContains('versionlink', $names);
    }

    public function testPermsLinkIncludedWhenUrlProvided(): void
    {
        $form = new EditQueueStepTwoForm(
            [],
            queueInfo: ['name' => 'Q', 'description' => 'D'],
            allTypes: [],
            queueTypeIds: [],
            defaultType: null,
            queueUsers: [],
            webroot: '/whups',
            queueId: 1,
            permsEditUrl: '/horde/admin/perms/edit.php?category=whups:queues:1',
        );

        $vars = $form->getVariables(flat: true);
        $names = array_map(fn($v) => $v->getVarName(), $vars);
        $this->assertContains('permslink', $names);
    }
}
