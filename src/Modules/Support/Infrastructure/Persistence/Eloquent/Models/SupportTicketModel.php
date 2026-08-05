<?php

declare(strict_types=1);

namespace Src\Modules\Support\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SupportTicketModel extends Model
{
    protected $table = 'support_tickets';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'last_reply_at' => 'datetime',
        'last_reply_by_staff' => 'boolean',
        'user_last_read_at' => 'datetime',
        'staff_last_read_at' => 'datetime',
        'closed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<SupportTicketMessageModel, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessageModel::class, 'support_ticket_id');
    }

    /** @return HasMany<SupportTicketAttachmentModel, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachmentModel::class, 'support_ticket_id');
    }

    public function isUnreadForClient(): bool
    {
        if (! $this->last_reply_by_staff || $this->last_reply_at === null) {
            return false;
        }

        if ($this->user_last_read_at === null) {
            return true;
        }

        return $this->last_reply_at->greaterThan($this->user_last_read_at);
    }

    public function isUnreadForStaff(): bool
    {
        if ($this->last_reply_at === null) {
            return $this->staff_last_read_at === null;
        }

        if ($this->last_reply_by_staff) {
            return false;
        }

        if ($this->staff_last_read_at === null) {
            return true;
        }

        return $this->last_reply_at->greaterThan($this->staff_last_read_at);
    }
}
