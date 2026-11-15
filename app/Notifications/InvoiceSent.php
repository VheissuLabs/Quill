<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceSent extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Invoice $invoice)
    {
        $this->afterCommit();
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->invoice->organization;
        $data = [
            'organization' => $organization->name,
            'number' => $this->invoice->number,
            'total' => $this->money($this->invoice->total),
            'dueDate' => $this->invoice->due_date->toFormattedDateString(),
            'amountDue' => $this->money($this->invoice->amountDue()),
            'url' => $this->invoice->publicUrl(),
        ];

        $message = (new MailMessage)
            ->from(config('mail.from.address'), $organization->name)
            ->subject(__('Invoice :number from :organization', ['number' => $this->invoice->number, 'organization' => $organization->name]))
            ->view('mail.invoice-sent', $data)
            ->text('mail.invoice-sent-text', $data);

        foreach ($this->invoice->cc_emails ?? [] as $address) {
            $message->cc($address);
        }

        return $message;
    }

    public function money(string $amount): string
    {
        return '$'.number_format((float) $amount, 2);
    }
}
