<?php

namespace Database\Factories;

use App\Enums\MessageChannel;
use App\Models\MessageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MessageTemplate>
 */
class MessageTemplateFactory extends Factory
{
    protected $model = MessageTemplate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'channel' => MessageChannel::Email,
            'code' => 'tpl_'.Str::lower(Str::random(8)),
            'name' => fake()->sentence(3),
            'subject' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'is_system' => false,
        ];
    }
}
