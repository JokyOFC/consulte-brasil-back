<?php

declare(strict_types=1);

namespace Src\Modules\Identity\Infrastructure\Console;

use Illuminate\Console\Command;
use Src\Modules\Identity\Application\DTO\CreateAccountInput;
use Src\Modules\Identity\Application\DTO\IssueApiKeyInput;
use Src\Modules\Identity\Application\UseCase\CreateAccount;
use Src\Modules\Identity\Application\UseCase\IssueApiKey;
use Src\Modules\Identity\Domain\Repository\AccountRepository;
use Src\Shared\Domain\ValueObject\Document;

final class IssueApiKeyCommand extends Command
{
    protected $signature = 'identity:issue-key
        {name : Nome do cliente/conta}
        {document : CPF ou CNPJ da conta}
        {--key-name=default : Nome amigável da chave}';

    protected $description = 'Cria (ou reutiliza) uma conta e emite uma API key, exibindo o token uma única vez.';

    public function handle(
        CreateAccount $createAccount,
        IssueApiKey $issueApiKey,
        AccountRepository $accounts,
    ): int {
        $account = $accounts->findByDocument(Document::fromString($this->argument('document')));

        if ($account === null) {
            $account = $createAccount->handle(new CreateAccountInput(
                name: $this->argument('name'),
                document: $this->argument('document'),
            ));
            $this->info("Conta criada: {$account->id->value}");
        } else {
            $this->info("Conta existente reutilizada: {$account->id->value}");
        }

        $issued = $issueApiKey->handle(new IssueApiKeyInput(
            accountId: $account->id->value,
            name: (string) $this->option('key-name'),
        ));

        $this->newLine();
        $this->warn('API key emitida — copie agora, ela NÃO será exibida novamente:');
        $this->line($issued->plainToken);

        return self::SUCCESS;
    }
}
