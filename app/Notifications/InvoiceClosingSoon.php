<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

#[DeleteWhenMissingModels]
class InvoiceClosingSoon extends Notification implements ShouldQueue
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
            'body' => __('Review it before it goes out.'),
            'url' => $this->url(false),
        ];
    }

    private function title(): string
    {
        $message = $this->invoice->contact_id === null
            ? ':number for :client has no contact and cannot send tonight at 11:59 PM'
            : ':number for :client sends tonight at 11:59 PM';

        return __($message, [
            'number' => $this->invoice->number,
            'client' => $this->invoice->client->name,
        ]);
    }

    private function url(bool $absolute): string
    {
        return route('invoices.show', ['current_organization' => $this->invoice->organization, 'invoice' => $this->invoice], absolute: $absolute);
    }
}
