<?php

declare(strict_types=1);

namespace Src\Modules\Support\Infrastructure\Http\Policies;

use App\Models\User;
use Src\Modules\Support\Infrastructure\Persistence\Eloquent\Models\SupportTicketModel;

final class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role !== 'admin';
    }

    public function view(User $user, SupportTicketModel $ticket): bool
    {
        return $user->role === 'admin' || (int) $ticket->user_id === (int) $user->id;
    }

    public function reply(User $user, SupportTicketModel $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    public function updateStatus(User $user, SupportTicketModel $ticket): bool
    {
        return $user->role === 'admin';
    }

    public function downloadAttachment(User $user, SupportTicketModel $ticket): bool
    {
        return $this->view($user, $ticket);
    }
}
