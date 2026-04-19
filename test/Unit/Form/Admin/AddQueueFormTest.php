<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit\Form\Admin;

use Horde\Whups\Form\Admin\AddQueueForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AddQueueForm::class)]
class AddQueueFormTest extends TestCase
{
    public function testConstructionAddsExpectedVariables(): void
    {
        $form = new AddQueueForm([], '/whups');

        $vars = $form->getVariables(flat: true);
        $names = array_map(fn ($v) => $v->getVarName(), $vars);

        $this->assertContains('name', $names);
        $this->assertContains('description', $names);
        $this->assertContains('slug', $names);
        $this->assertContains('email', $names);
    }

    public function testButtonLabel(): void
    {
        $form = new AddQueueForm([], '/whups');

        $buttons = $form->getButtons();
        $this->assertCount(1, $buttons);
        $this->assertSame('Add Queue', $buttons[0]);
    }

    public function testValidationPassesWithRequiredFields(): void
    {
        $form = new AddQueueForm([
            'formname' => 'horde_whups_form_admin_addqueueform',
            'name' => 'Support',
            'description' => 'Support queue',
        ], '/whups');

        $this->assertTrue($form->validate());
    }

    public function testValidationFailsWithoutName(): void
    {
        $form = new AddQueueForm([
            'formname' => 'horde_whups_form_admin_addqueueform',
            'name' => '',
            'description' => 'Support queue',
        ], '/whups');

        $this->assertFalse($form->validate());
    }

    public function testValidationFailsWithoutDescription(): void
    {
        $form = new AddQueueForm([
            'formname' => 'horde_whups_form_admin_addqueueform',
            'name' => 'Support',
            'description' => '',
        ], '/whups');

        $this->assertFalse($form->validate());
    }

    public function testValidationPassesWithOptionalFieldsEmpty(): void
    {
        $form = new AddQueueForm([
            'formname' => 'horde_whups_form_admin_addqueueform',
            'name' => 'Support',
            'description' => 'Support queue',
            'slug' => '',
            'email' => '',
        ], '/whups');

        $this->assertTrue($form->validate());
    }

    public function testGetInfoReturnsSubmittedValues(): void
    {
        $form = new AddQueueForm([
            'formname' => 'horde_whups_form_admin_addqueueform',
            'name' => 'Support',
            'description' => 'Help desk',
            'slug' => 'support',
            'email' => 'help@example.com',
        ], '/whups');

        $form->validate();
        $info = $form->getInfo();

        $this->assertSame('Support', $info['name']);
        $this->assertSame('Help desk', $info['description']);
        $this->assertSame('support', $info['slug']);
        $this->assertSame('help@example.com', $info['email']);
    }

    public function testIsSubmittedDetectsFormname(): void
    {
        $submitted = new AddQueueForm([
            'formname' => 'horde_whups_form_admin_addqueueform',
            'name' => 'X',
        ], '/whups');

        $notSubmitted = new AddQueueForm([], '/whups');

        $this->assertTrue($submitted->isSubmitted());
        $this->assertFalse($notSubmitted->isSubmitted());
    }

    public function testSlugValidationRejectsInvalidCharacters(): void
    {
        $form = new AddQueueForm([
            'formname' => 'horde_whups_form_admin_addqueueform',
            'name' => 'Support',
            'description' => 'desc',
            'slug' => 'invalid slug!',
        ], '/whups');

        $this->assertFalse($form->validate());
    }

    public function testSlugValidationAcceptsValidSlug(): void
    {
        $form = new AddQueueForm([
            'formname' => 'horde_whups_form_admin_addqueueform',
            'name' => 'Support',
            'description' => 'desc',
            'slug' => 'my_queue_1',
        ], '/whups');

        $this->assertTrue($form->validate());
    }
}
