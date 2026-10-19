<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ticket = Ticket::factory();
        $ext = fake()->randomElement(['pdf', 'png', 'jpg', 'txt', 'csv']);

        return [
            'ticket_id' => $ticket,
            'ticket_reply_id' => null,
            'uploaded_by' => User::factory(),
            'original_name' => fake()->word().'.'.$ext,
            'path' => 'attachments/'.Str::uuid().'.'.$ext,
            'mime_type' => 'application/'.$ext,
            'size_bytes' => fake()->numberBetween(1024, 2048000),
            'sha256' => hash('sha256', Str::random(32)),
        ];
    }
}
