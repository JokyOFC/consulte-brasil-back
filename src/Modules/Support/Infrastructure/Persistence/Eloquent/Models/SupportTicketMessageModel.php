<?php

declare(strict_types=1);

namespace Src\Modules\Support\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SupportTicketMessageModel extends Model
{
    protected $table = 'support_ticket_messages';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'is_staff' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<SupportTicketModel, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicketModel::class, 'support_ticket_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<SupportTicketAttachmentModel, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachmentModel::class, 'support_ticket_message_id');
    }
}
