<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketEscalatedNotification extends Notification implements ShouldQueue
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
            ->error()
            ->subject("🚨 [SLA BREACHED] Ticket {$this->ticket->ticket_number} Escalated")
            ->line("Ticket {$this->ticket->ticket_number} has exceeded its resolution SLA commitment and has been escalated to administration.")
            ->line("Subject: {$this->ticket->subject}")
            ->line("Priority: {$this->ticket->priority->label()}")
            ->line('Assigned Agent: '.($this->ticket->assignedAgent?->name ?? 'Unassigned'))
            ->action('Immediate Action Required', route('tickets.show', $this->ticket));
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
            'type' => 'ticket_escalated',
            'breached_at' => $this->ticket->breached_at?->toIso8601String(),
        ];
    }
}
