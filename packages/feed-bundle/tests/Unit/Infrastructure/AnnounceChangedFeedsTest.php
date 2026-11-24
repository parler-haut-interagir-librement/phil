<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Unit\Infrastructure;

use Phil\FeedBundle\Application\Feeds;
use Phil\FeedBundle\Infrastructure\WebSub\AnnounceChangedFeeds;
use Phil\FeedBundle\Infrastructure\WebSub\AnnounceFeedUpdate;
use Phil\FeedBundle\Tests\Fixtures\StaticSource;
use Phil\Publishing\PagePublished;
use Phil\Publishing\PageRetired;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(AnnounceChangedFeeds::class)]
final class AnnounceChangedFeedsTest extends TestCase
{
    public function testEachFeedOfThePageIsAnnouncedByItsNameAlone(): void
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $messages = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->messages[] = $message;

                return new Envelope($message, $stamps);
            }
        };
        $listener = new AnnounceChangedFeeds(new Feeds([new StaticSource('news'), new StaticSource('events', prefix: 'event:'), new StaticSource('all', prefix: '')]), $bus);

        $listener(new PagePublished(Url::fromString('https://example.org/n/1'), 'fr', true, 'news:1'));
        $listener(new PageRetired(Url::fromString('https://example.org/x'), 'fr', false, 'other:1'));

        self::assertEquals([new AnnounceFeedUpdate('news'), new AnnounceFeedUpdate('all'), new AnnounceFeedUpdate('all')], $bus->messages);
        self::assertSame(['feed' => 'news'], get_object_vars($bus->messages[0]));
    }
}
