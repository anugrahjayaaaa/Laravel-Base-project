<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class AdminUserCreated extends Notification
{
    use Queueable;

    public function __construct(public string $plainPassword, public string $loginUrl) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your account has been created')
            ->greeting('Hello '.($notifiable->name ?? '').'!')
            ->line('Your account has been created.')
            ->line('Username: '.$notifiable->username)
            ->line('Password: '.$this->plainPassword)
            ->line('Please verify your email and log in.')
            ->action('Verify email', $this->loginUrl)
            ->line('If you did not expect this email, please ignore it.');
    }
}
