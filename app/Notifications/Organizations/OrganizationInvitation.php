<?php

namespace App\Notifications\Organizations;

use App\Models\OrganizationInvitation as InvitationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public InvitationModel $invitation,
        public bool $inApp = false,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $this->inApp ? ['database', 'broadcast'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__("You've been invited to :organizationName", ['organizationName' => $this->organizationName()]))
            ->line($this->summary())
            ->line(__('Set a password to accept the invitation and get started.'))
            ->action(
                __('Accept invitation'),
                route('join.create', ['invitation' => $this->invitation->code]),
            );
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            ...$this->toArray($notifiable),
            'created_at_diff' => __('just now'),
        ]);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->summary(),
            'organization_name' => $this->organizationName(),
            'invitation_id' => $this->invitation->id,
            'invitation_code' => $this->invitation->code,
            'organization_id' => $this->invitation->organization_id,
            'client_id' => $this->invitation->client_id,
            'role' => $this->invitation->role,
        ];
    }

    protected function summary(): string
    {
        $client = $this->invitation->client;

        if ($client !== null) {
            return __(':inviterName invited you to :organizationName as a contact for :clientName', [
                'inviterName' => $this->invitation->inviter->name,
                'organizationName' => $this->organizationName(),
                'clientName' => $client->name,
            ]);
        }

        return __(':inviterName invited you to join :organizationName', [
            'inviterName' => $this->invitation->inviter->name,
            'organizationName' => $this->organizationName(),
        ]);
    }

    protected function organizationName(): string
    {
        return (string) $this->invitation->organization->name;
    }
}
