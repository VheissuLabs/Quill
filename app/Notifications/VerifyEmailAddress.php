<?php

namespace App\Notifications;

use App\Models\EmailAddress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailAddress extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public EmailAddress $emailAddress)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Verify your email address'))
            ->line(__(':email was added to the :app account of :name.', [
                'email' => $this->emailAddress->email,
                'app' => config('app.name'),
                'name' => $this->emailAddress->user->name,
            ]))
            ->line(__('Confirm it belongs to you so invitations sent to it reach that account.'))
            ->action(__('Verify email address'), $this->verificationUrl())
            ->line(__('If you did not add this address, no further action is required.'));
    }

    /**
     * Get the signed verification URL for the address.
     */
    protected function verificationUrl(): string
    {
        return URL::temporarySignedRoute(
            'email-addresses.verify',
            now()->addMinutes(60),
            [
                'emailAddress' => $this->emailAddress->id,
                'hash' => sha1($this->emailAddress->email),
            ],
        );
    }
}
