<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Src\Modules\Support\Domain\ValueObject\SupportTicketStatus;
use Src\Modules\Support\Infrastructure\Persistence\Eloquent\Models\SupportTicketModel;
use Tests\TestCase;

final class SupportTicketsTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_open_ticket_and_admin_can_reply_changing_status(): void
    {
        Mail::fake();
        Storage::fake('local');
        config(['support.notify_email' => 'equipe@example.com']);

        $accountId = app(CreateAccount::class)->handle(new CreateAccountInput('ACME', '11.222.333/0001-81'))->id->value;
        $client = User::factory()->create(['role' => 'client', 'account_id' => $accountId]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($client)
            ->post('/client/tickets', [
                'category' => 'technical',
                'title' => 'Erro na consulta',
                'body' => 'Não consigo consultar CPF.',
                'attachments' => [
                    UploadedFile::fake()->create('print.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertRedirect();

        $ticket = SupportTicketModel::query()->first();
        $this->assertNotNull($ticket);
        $this->assertSame('open', $ticket->status);
        $this->assertSame(1, $ticket->attachments()->count());

        $this->actingAs($admin)
            ->post("/admin/tickets/{$ticket->id}/reply", [
                'body' => 'Estamos verificando.',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame(SupportTicketStatus::InProgress->value, $ticket->status);
        $this->assertTrue($ticket->last_reply_by_staff);
        $this->assertSame(1, $ticket->messages()->count());
    }

    public function test_client_reply_reopens_closed_ticket(): void
    {
        Mail::fake();
        $accountId = app(CreateAccount::class)->handle(new CreateAccountInput('ACME', '11.222.333/0001-81'))->id->value;
        $client = User::factory()->create(['role' => 'client', 'account_id' => $accountId]);

        $ticket = SupportTicketModel::query()->create([
            'id' => (string) str()->uuid(),
            'user_id' => $client->id,
            'account_id' => $accountId,
            'category' => 'questions',
            'title' => 'Dúvida',
            'body' => 'Como recarregar?',
            'status' => 'closed',
            'last_reply_at' => now()->subDay(),
            'last_reply_by_staff' => true,
            'user_last_read_at' => now()->subDay(),
            'staff_last_read_at' => now()->subDay(),
            'closed_at' => now()->subDay(),
        ]);

        $this->actingAs($client)
            ->post("/client/tickets/{$ticket->id}/reply", [
                'body' => 'Ainda preciso de ajuda.',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame('open', $ticket->status);
        $this->assertNull($ticket->closed_at);
        $this->assertFalse($ticket->last_reply_by_staff);
    }

    public function test_client_cannot_view_another_clients_ticket(): void
    {
        $accountA = app(CreateAccount::class)->handle(new CreateAccountInput('A', '11.222.333/0001-81'))->id->value;
        $accountB = app(CreateAccount::class)->handle(new CreateAccountInput('B', '11.444.555/0001-99'))->id->value;
        $clientA = User::factory()->create(['role' => 'client', 'account_id' => $accountA]);
        $clientB = User::factory()->create(['role' => 'client', 'account_id' => $accountB]);

        $ticket = SupportTicketModel::query()->create([
            'id' => (string) str()->uuid(),
            'user_id' => $clientA->id,
            'account_id' => $accountA,
            'category' => 'financial',
            'title' => 'Fatura',
            'body' => 'Preciso de 2ª via',
            'status' => 'open',
            'last_reply_at' => now(),
            'last_reply_by_staff' => false,
            'user_last_read_at' => now(),
        ]);

        $this->actingAs($clientB)
            ->get("/client/tickets/{$ticket->id}")
            ->assertForbidden();
    }
}
