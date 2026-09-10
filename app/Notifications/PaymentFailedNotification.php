<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Payment $payment,
        private readonly PaymentStatus $status,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $motivo = $this->status === PaymentStatus::Canceled
            ? 'o pagamento foi cancelado'
            : 'o pagamento não foi aprovado';

        return (new MailMessage)
            ->subject('Não conseguimos confirmar seu pagamento')
            ->greeting("Olá, {$notifiable->name}.")
            // Sem detalhes do meio de pagamento: nada sensível por e-mail.
            ->line("Tentamos processar {$this->payment->amount()->format()}, mas {$motivo}.")
            ->line('Seu cardápio continua no ar. Você pode tentar novamente quando quiser.')
            ->action('Tentar de novo', url('/app/assinatura'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'status' => $this->status->value,
        ];
    }
}
