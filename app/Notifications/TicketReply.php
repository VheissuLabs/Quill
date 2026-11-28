<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Throwable;

#[DeleteWhenMissingModels]
class TicketReply extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public Comment $comment)
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
        $ticket = $this->ticket();
        $message = $this->comment->emailMessage ?? throw new RuntimeException('A ticket reply has no email to send.');

        return (new MailMessage)
            ->from(config('services.resend.support_address'), config('services.resend.support_name'))
            ->replyTo($ticket->replyAddress(), config('services.resend.support_name'))
            ->subject(__('Re: :subject [:reference]', ['subject' => $ticket->email_subject ?? $ticket->subject, 'reference' => $ticket->reference()]))
            ->view('mail.ticket-reply', ['body' => $this->comment->body, 'author' => $this->comment->author?->name])
            ->text('mail.ticket-reply-text', ['body' => $this->comment->body_text, 'author' => $this->comment->author?->name])
            ->withSymfonyMessage(function (Email $email) use ($message): void {
                $headers = $email->getHeaders();

                $headers->remove('Message-ID');
                $headers->addIdHeader('Message-ID', $message->message_id);

                if ($message->in_reply_to) {
                    $headers->addIdHeader('In-Reply-To', $message->in_reply_to);
                }

                if ($message->references) {
                    preg_match_all('/<([^>]+)>/', $message->references, $matches);
                    $headers->addIdHeader('References', $matches[1]);
                }
            });
    }

    public function afterSending(object $notifiable, string $channel, mixed $response = null): void
    {
        $this->comment->emailMessage?->markSent();
    }

    public function failed(Throwable $exception): void
    {
        $this->comment->emailMessage?->markFailed();
    }

    private function ticket(): Ticket
    {
        $ticket = $this->comment->commentable;

        return $ticket instanceof Ticket ? $ticket : throw new RuntimeException('A ticket reply belongs to something other than a ticket.');
    }
}
