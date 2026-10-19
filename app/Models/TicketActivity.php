<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketActivity extends Model
{
    /**
     * Audit log is append-only without updated_at.
     */
    public const UPDATED_AT = null;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'ticket_id',
        'user_id',
        'type',
        'old_value',
        'new_value',
        'metadata',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Ticket this activity is logged for.
     *
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * User who performed the action (null if automated system event).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Restrict activities visible to customers (hide internal note entries).
     *
     * @param  Builder<TicketActivity>  $query
     * @return Builder<TicketActivity>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isCustomer()) {
            return $query->where('type', '!=', ActivityType::InternalNote);
        }

        return $query;
    }
}
