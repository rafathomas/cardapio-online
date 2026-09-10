<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Payment $payment) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->payment->plan?->name ?? 'Pro';
        $renewal = $this->payment->subscription?->current_period_end?->format('d/m/Y');

        return (new MailMessage)
            ->subject("Pagamento confirmado — plano {$plan}")
            ->greeting("Tudo certo, {$notifiable->name}!")
            ->line("Recebemos seu pagamento de {$this->payment->amount()->format()} e o plano {$plan} já está ativo.")
            ->when($renewal !== null, fn (MailMessage $mail) => $mail->line("Próxima renovação: {$renewal}."))
            ->action('Abrir meu painel', url('/app/assinatura'))
            ->line('Obrigado por usar o '.config('app.name').'.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'amount_cents' => $this->payment->amount_cents,
            'plan' => $this->payment->plan?->slug,
        ];
    }
}
