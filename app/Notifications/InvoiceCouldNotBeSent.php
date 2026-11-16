<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceCouldNotBeSent extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Invoice $invoice) {}

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
            'body' => __('Add what is missing and send it by hand.'),
            'url' => $this->url(false),
        ];
    }

    private function title(): string
    {
        return __(':number for :client couldn\'t be sent: it has :reason.', [
            'number' => $this->invoice->number,
            'client' => $this->invoice->client->name,
            'reason' => $this->invoice->contact_id === null ? __('no contact') : __('no lines'),
        ]);
    }

    private function url(bool $absolute): string
    {
        return route('invoices.show', ['current_organization' => $this->invoice->organization, 'invoice' => $this->invoice], absolute: $absolute);
    }
}
