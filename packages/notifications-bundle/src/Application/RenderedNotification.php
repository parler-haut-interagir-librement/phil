<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application;

use Phil\NotificationsBundle\Domain\Model\Notification;

/**
 * A notification written out for its recipient: its title and body from its type's templates, and the page it opens.
 */
final readonly class RenderedNotification
{
    public function __construct(
        public Notification $notification,
        public string $title,
        public string $body,
        public ?string $url = null,
    ) {
    }

    /**
     * What a toast shows, as the browser receives it.
     *
     * @return array{id: string, type: string, title: string, body: string, url: ?string, count: int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->notification->id,
            'type' => $this->notification->type,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'count' => $this->notification->count,
        ];
    }
}
