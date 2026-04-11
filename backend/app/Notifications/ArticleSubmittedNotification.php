<?php

namespace App\Notifications;

use App\Models\Article;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ArticleSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Article $article)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line("New article '{$this->article->title}' submitted for approval by {$this->article->author->name}.")
            ->action('Review Article', url("/admin/articles/{$this->article->id}/edit"))
            ->line('Thank you!');
    }
}
