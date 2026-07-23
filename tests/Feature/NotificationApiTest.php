<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\NotificationStatus;
use App\Models\NotificationType;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    $this->user = User::query()->create([
        'name' => 'Test User',
        'country_code' => '+855',
        'phone_number' => '123456789',
        'password' => bcrypt('password'),
        'user_status_id' => UserStatus::ID_ACTIVE,
    ]);

    $this->notification = Notification::create([
        'user_id' => $this->user->id,
        'notification_type_id' => NotificationType::ID_WELCOME,
        'notification_status_id' => NotificationStatus::ID_UNREAD,
        'title' => 'Test Notification',
        'body' => 'This is a test.',
    ]);
});

test('user can list their active notifications', function () {
    $response = $this->actingAs($this->user)->getJson('/api/v1/notifications');

    $response->assertStatus(200)
        ->assertJsonPath('data.0.title', 'Test Notification')
        ->assertJsonPath('data.0.status.code', NotificationStatus::UNREAD)
        ->assertJsonPath('meta.unread_count', 1);
});

test('user can mark notification as read', function () {
    $response = $this->actingAs($this->user)->patchJson("/api/v1/notifications/{$this->notification->id}", [
        'status' => NotificationStatus::READ,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.status.code', NotificationStatus::READ)
        ->assertJsonPath('meta.unread_count', 0);

    $this->assertDatabaseHas('notifications', [
        'id' => $this->notification->id,
        'notification_status_id' => NotificationStatus::ID_READ,
    ]);
});

test('user can mark notification as deleted and it is hidden from list', function () {
    $response = $this->actingAs($this->user)->patchJson("/api/v1/notifications/{$this->notification->id}", [
        'status' => NotificationStatus::DELETED,
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('meta.unread_count', 0);

    $listResponse = $this->actingAs($this->user)->getJson('/api/v1/notifications');
    $listResponse->assertStatus(200)->assertJsonCount(0, 'data');
});
