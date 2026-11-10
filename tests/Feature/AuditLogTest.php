<?php

use App\Models\AuditLog;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('audit history is restricted to active administrators', function () {
    $this->get('/audit-logs')->assertRedirect('/login');
    $staff = User::factory()->create();
    $staff->forceFill(['role' => 'staff'])->save();
    $this->actingAs($staff)->get('/audit-logs')->assertForbidden();
    $staff->forceFill(['role' => 'admin', 'is_active' => false])->save();
    $this->actingAs($staff)->get('/audit-logs')->assertForbidden();
});

test('administrators can filter audit history without receiving sensitive metadata', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['role' => 'admin', 'is_active' => true])->save();
    $this->actingAs($admin);
    AuditLog::record('contact.created', 'contact', 7, ['private_value' => 'must-not-leak']);
    AuditLog::record('category.created', 'category', 8);

    $this->get('/audit-logs?action=contact.created&subject=contact')->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('menu/audit-logs')
            ->has('logs.data', 1)->where('logs.data.0.actor_name', $admin->name)
            ->where('logs.data.0.subject_id', 7)->missing('logs.data.0.metadata'));
    $this->get('/audit-logs?page=0')->assertSessionHasErrors('page');
});
