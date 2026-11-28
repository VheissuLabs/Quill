<?php

namespace App\Notifications\Organizations;

use App\Models\OrganizationInvitation as OrganizationInvitationModel;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;

#[DeleteWhenMissingModels]
class OrganizationInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public OrganizationInvitationModel $invitation)
    {
        //
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->invitation->organization;
        $inviter = $this->invitation->inviter;

        return (new MailMessage)
            ->subject(__("You've been invited to join :organizationName", ['organizationName' => $organization->name]))
            ->line(__(':inviterName has invited you to join the :organizationName organization.', [
                'inviterName' => $inviter->name,
                'organizationName' => $organization->name,
            ]))
            ->when(User::query()->withEmailAddress($this->invitation->email)->exists(), fn (MailMessage $mail) => $mail
                ->line(__('Log in and visit your dashboard to accept or decline this invitation.'))
                ->action(__('Log in'), route('login', ['invitation' => $this->invitation->code])),
                fn (MailMessage $mail) => $mail
                    ->line(__('Create your account with this email address to accept the invitation.'))
                    ->action(__('Create account'), route('register', ['invitation' => $this->invitation->code])));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'organization_id' => $this->invitation->organization_id,
            'organization_name' => $this->invitation->organization->name,
            'role' => $this->invitation->role->name,
        ];
    }
}
