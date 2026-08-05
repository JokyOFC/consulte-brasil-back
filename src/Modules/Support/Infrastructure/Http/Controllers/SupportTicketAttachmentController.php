<?php

declare(strict_types=1);

namespace Src\Modules\Support\Infrastructure\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Src\Modules\Support\Infrastructure\Persistence\Eloquent\Models\SupportTicketAttachmentModel;
use Src\Modules\Support\Infrastructure\Persistence\Eloquent\Models\SupportTicketModel;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SupportTicketAttachmentController
{
    public function download(string $ticket, string $attachment, Request $request): StreamedResponse
    {
        $ticketModel = SupportTicketModel::query()->findOrFail($ticket);

        if (! $request->user()->can('downloadAttachment', $ticketModel)) {
            abort(403);
        }

        $file = SupportTicketAttachmentModel::query()
            ->where('id', $attachment)
            ->where('support_ticket_id', $ticket)
            ->firstOrFail();

        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }
}
