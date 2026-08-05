<?php

declare(strict_types=1);

namespace Src\Modules\Support\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SupportTicketAttachmentModel extends Model
{
    protected $table = 'support_ticket_attachments';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /** @return BelongsTo<SupportTicketModel, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicketModel::class, 'support_ticket_id');
    }

    /** @return BelongsTo<SupportTicketMessageModel, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(SupportTicketMessageModel::class, 'support_ticket_message_id');
    }
}
