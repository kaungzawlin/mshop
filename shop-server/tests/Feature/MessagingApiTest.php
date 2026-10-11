<?php

use App\Enums\MessageChannel;
use App\Models\MessageTemplate;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
});

it('creates an email template with variables and sample sends', function (): void {
    Mail::fake();
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('web', ['*'], now()->addDays(30))->plainTextToken;

    $create = $this->withToken($token)
        ->postJson('/api/admin/message-templates', [
            'channel' => 'email',
            'code' => 'welcome_email',
            'name' => 'Welcome',
            'locale' => 'en',
            'subject' => 'Hello {{customer_name}}',
            'body' => 'Hi {{customer_name}}, welcome to mshop.',
            'variables' => ['customer_name'],
        ])
        ->assertCreated();

    $id = $create->json('data.id');

    $this->withToken($token)
        ->postJson("/api/admin/message-templates/{$id}/sample", [
            'to' => 'sample@example.test',
            'variables' => ['customer_name' => 'Aung'],
        ])
        ->assertOk()
        ->assertJsonPath('message_log.status', 'sent');
});

it('uploads csv audience and processes campaign', function (): void {
    Mail::fake();
    $admin = User::factory()->admin()->create();
    $token = $admin->createToken('web', ['*'], now()->addDays(30))->plainTextToken;
    $template = MessageTemplate::factory()->create([
        'channel' => MessageChannel::Email,
        'subject' => 'Hi {{customer_name}}',
        'body' => 'Hello {{customer_name}}',
        'variables' => ['customer_name'],
    ]);

    $campaign = $this->withToken($token)
        ->postJson('/api/admin/message-campaigns', [
            'template_id' => $template->id,
            'name' => 'Spring',
            'channel' => 'email',
        ])
        ->assertCreated()
        ->json('data.id');

    $csv = UploadedFile::fake()->createWithContent(
        'audience.csv',
        "email,customer_name\nsomeone@example.test,Aung\n"
    );

    $this->withToken($token)
        ->post("/api/admin/message-campaigns/{$campaign}/upload", [
            'file' => $csv,
        ], [
            'Accept' => 'application/json',
        ])
        ->assertOk();

    $this->withToken($token)
        ->postJson("/api/admin/message-campaigns/{$campaign}/schedule")
        ->assertOk();

    $this->withToken($token)
        ->getJson('/api/admin/message-logs?channel=email')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});
