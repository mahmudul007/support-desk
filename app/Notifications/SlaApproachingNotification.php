<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SlaApproachingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Ticket $ticket
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("⚠️ [SLA Warning] {$this->ticket->ticket_number} Approaching SLA Breach")
            ->line("Ticket {$this->ticket->ticket_number} ({$this->ticket->subject}) has consumed 80% of its resolution window.")
            ->line("Priority: {$this->ticket->priority->label()}")
            ->line("Due Date: {$this->ticket->sla_due_at?->toDayDateTimeString()}")
            ->action('Prioritize Ticket', route('tickets.show', $this->ticket));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'subject' => $this->ticket->subject,
            'type' => 'sla_approaching',
            'sla_due_at' => $this->ticket->sla_due_at?->toIso8601String(),
        ];
    }
}
