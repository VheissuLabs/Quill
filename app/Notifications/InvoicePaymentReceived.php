<?php

namespace App\Notifications;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoicePaymentReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public Invoice $invoice;

    public bool $invoiceIsVoid;

    public float $overpaidBy;

    public function __construct(public InvoicePayment $payment)
    {
        $this->invoice = $payment->invoice()->with('client')->firstOrFail();
        $this->invoiceIsVoid = $this->invoice->status === InvoiceStatus::Void;
        $this->overpaidBy = $this->invoiceIsVoid ? 0.0 : max(0.0, round((float) $this->invoice->amount_paid - (float) $this->invoice->total, 2));
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line($this->title())
            ->action(__('View invoice'), $this->url(true));
    }

    /** @return array{title: string, body: string, url: string} */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => __('Paid online through Square.'),
            'url' => $this->url(false),
        ];
    }

    private function title(): string
    {
        $title = __(':client paid :amount on :number by :method.', [
            'client' => $this->invoice->client->name,
            'amount' => $this->money((float) $this->payment->amount),
            'number' => $this->invoice->number,
            'method' => $this->methodName(),
        ]);

        if ($this->invoiceIsVoid) {
            return $title.' '.__('The invoice is void.');
        }

        if ($this->overpaidBy > 0) {
            return $title.' '.__('The invoice was overpaid by :amount.', ['amount' => $this->money($this->overpaidBy)]);
        }

        return $title;
    }

    private function methodName(): string
    {
        return match ($this->payment->method) {
            PaymentMethod::Ach, PaymentMethod::CashApp => $this->payment->method->label(),
            default => strtolower($this->payment->method->label()),
        };
    }

    private function money(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }

    private function url(bool $absolute): string
    {
        return route('invoices.show', ['current_organization' => $this->invoice->organization, 'invoice' => $this->invoice], absolute: $absolute);
    }
}
