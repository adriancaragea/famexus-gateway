<?php

namespace App\Notifications;

use App\Models\FamilyInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Emails the invite link. The link opens the React app, which shows the
 * invitation and lets the person choose a name and password.
 */
class FamilyInvitationNotification extends Notification
{
    public function __construct(
        public readonly FamilyInvitation $invitation,
        public readonly string $token,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inviter = $this->invitation->inviter->name ?? 'A family member';

        return (new MailMessage)
            ->subject("Join {$this->invitation->family->name} on Famexus")
            ->line("{$inviter} invited you to join {$this->invitation->family->name} on Famexus.")
            ->action('Accept the invitation', self::url($this->token))
            ->line('The link is valid for '.FamilyInvitation::VALID_DAYS.' days.');
    }

    public static function url(string $token): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/invite/'.$token;
    }
}
