<?php

namespace App\Mail;

use App\Models\User;
use Src\Modules\Billing\Domain\ValueObject\PaymentType;

final class PaymentConfirmedMail extends BrandedMailable
{
    public function __construct(
        public User $user,
        public PaymentType $paymentType,
        public int $amountCents,
        public int $creditsGranted,
    ) {}

    protected function mailSubject(): string
    {
        return $this->paymentType === PaymentType::Invoice
            ? 'Pagamento de fatura confirmado'
            : 'Recarga de saldo confirmada';
    }

    protected function mailTitle(): string
    {
        return 'Pagamento confirmado';
    }

    protected function mailPreview(): string
    {
        return 'Seu saldo já está disponível na carteira.';
    }

    protected function mailView(): string
    {
        return 'mail.payment-confirmed';
    }

    protected function mailData(): array
    {
        $message = $this->paymentType === PaymentType::Invoice
            ? 'Recebemos o pagamento da sua fatura. O saldo do plano foi adicionado à sua carteira.'
            : 'Sua recarga foi processada com sucesso. O saldo já está disponível para consultas.';

        return [
            'userName' => $this->user->name,
            'message' => $message,
            'amountFormatted' => $this->formatMoney($this->amountCents),
            'creditsFormatted' => $this->formatMoney($this->creditsGranted),
            'billingUrl' => url('/client/billing'),
        ];
    }

    private function formatMoney(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }
}
