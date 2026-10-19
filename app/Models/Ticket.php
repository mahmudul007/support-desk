<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'ticket_number',
        'external_ref',
        'subject',
        'description',
        'priority',
        'status',
        'category_id',
        'created_by',
        'assigned_agent_id',
        'assigned_at',
        'sla_due_at',
        'sla_warning_at',
        'approaching_notified_at',
        'breached_at',
        'escalated_at',
        'escalation_count',
        'first_response_at',
        'resolved_at',
        'closed_at',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => TicketPriority::class,
            'status' => TicketStatus::class,
            'assigned_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'sla_warning_at' => 'datetime',
            'approaching_notified_at' => 'datetime',
            'breached_at' => 'datetime',
            'escalated_at' => 'datetime',
            'escalation_count' => 'integer',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    /**
     * Category this ticket belongs to.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Customer who created the ticket.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Support agent assigned to the ticket.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    /**
     * Conversation replies on this ticket.
     *
     * @return HasMany<TicketReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class);
    }

    /**
     * All direct attachments on this ticket.
     *
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * Audit activities trail for this ticket.
     *
     * @return HasMany<TicketActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class)->orderBy('created_at', 'asc');
    }

    /**
     * Determine if ticket is currently active/open.
     */
    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /**
     * Determine if ticket is assigned.
     */
    public function isAssigned(): bool
    {
        return $this->assigned_agent_id !== null;
    }

    /**
     * Determine if ticket is assigned to a specific user.
     */
    public function isAssignedTo(User $user): bool
    {
        return $this->assigned_agent_id === $user->id;
    }

    /**
     * Restrict tickets visible to a specific user according to their role.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isAgent()) {
            // Agents can view assigned tickets or unassigned pool
            return $query->where(function (Builder $q) use ($user): void {
                $q->where('assigned_agent_id', $user->id)
                    ->orWhereNull('assigned_agent_id');
            });
        }

        // Customers can ONLY see tickets they created
        return $query->where('created_by', $user->id);
    }

    /**
     * Scope query to only open tickets.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [TicketStatus::Resolved, TicketStatus::Closed]);
    }

    /**
     * Scope query to only resolved or closed tickets.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeResolvedOrClosed(Builder $query): Builder
    {
        return $query->whereIn('status', [TicketStatus::Resolved, TicketStatus::Closed]);
    }

    /**
     * Scope query to tickets that have breached their SLA.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeSlaBreached(Builder $query): Builder
    {
        return $query->open()
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now());
    }

    /**
     * Scope query to tickets approaching SLA breach (past warning threshold and before due date).
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeSlaApproaching(Builder $query): Builder
    {
        $now = now();

        return $query->open()
            ->whereNotNull('sla_warning_at')
            ->whereNotNull('sla_due_at')
            ->where('sla_warning_at', '<=', $now)
            ->where('sla_due_at', '>=', $now);
    }

    /**
     * Scope query to tickets within normal SLA window (before warning threshold).
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function scopeSlaWithin(Builder $query): Builder
    {
        $now = now();

        return $query->open()
            ->whereNotNull('sla_warning_at')
            ->where('sla_warning_at', '>', $now);
    }
}
