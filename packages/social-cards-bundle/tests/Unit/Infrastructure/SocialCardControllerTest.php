<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Tests\Unit\Infrastructure;

use Phil\HttpCacheTagsBundle\Application\ResponseTags;
use Phil\SocialCards\Card\CardContent;
use Phil\SocialCards\Card\CardGenerator;
use Phil\SocialCards\Card\GdCardRenderer;
use Phil\SocialCards\Card\InMemoryCardStore;
use Phil\SocialCardsBundle\Application\CardTemplates;
use Phil\SocialCardsBundle\Application\SocialCards;
use Phil\SocialCardsBundle\Infrastructure\Http\SocialCardController;
use Phil\SocialCardsBundle\Infrastructure\Messenger\GenerateSocialCard;
use Phil\SocialCardsBundle\Infrastructure\Messenger\GenerateSocialCardHandler;
use Phil\SocialCardsBundle\Infrastructure\Messenger\MessengerCardQueue;
use Phil\SocialCardsBundle\Tests\Support\MemoryRequests;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(SocialCardController::class)]
#[CoversClass(GenerateSocialCard::class)]
#[CoversClass(GenerateSocialCardHandler::class)]
#[CoversClass(MessengerCardQueue::class)]
final class SocialCardControllerTest extends TestCase
{
    private InMemoryCardStore $store;

    private SocialCards $cards;

    protected function setUp(): void
    {
        $this->store = new InMemoryCardStore();
        $this->cards = new SocialCards(new CardTemplates(['default' => ['title_font' => __DIR__.'/../../Fixtures/card-font.ttf']], 'default'), new CardGenerator(new GdCardRenderer(), $this->store), new MemoryRequests(), $this->store);
    }

    public function testACardIsServedForAYear(): void
    {
        $fingerprint = $this->cards->request(new CardContent('Title'));
        $tags = new ResponseTags();
        $response = new SocialCardController($this->cards, tags: $tags)(Request::create('/'), $fingerprint);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['image/png', 'nosniff'], [$response->headers->get('Content-Type'), $response->headers->get('X-Content-Type-Options')]);
        self::assertStringStartsWith("\x89PNG", (string) $response->getContent());
        self::assertSame('"'.$fingerprint.'"', $response->getEtag());
        self::assertSame(31_536_000, $response->getMaxAge());
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertTrue($response->headers->hasCacheControlDirective('immutable'));
        self::assertSame(['social-card', 'social-card:'.$fingerprint], $tags->all());

        $again = new SocialCardController($this->cards, 60)(Request::create('/', server: ['HTTP_IF_NONE_MATCH' => '"'.$fingerprint.'"']), $fingerprint);
        self::assertSame([304, 60, ''], [$again->getStatusCode(), $again->getMaxAge(), (string) $again->getContent()]);
    }

    public function testACardNoPageNamedIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('No page has the card "%s".', str_repeat('0', 32)));

        new SocialCardController($this->cards)(Request::create('/'), str_repeat('0', 32));
    }

    public function testACardIsDrawnByAWorker(): void
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $messages = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->messages[] = $message;

                return new Envelope($message);
            }
        };
        new MessengerCardQueue($bus)->queue(str_repeat('a', 32));
        self::assertEquals([new GenerateSocialCard(str_repeat('a', 32))], $bus->messages);

        $fingerprint = $this->cards->request(new CardContent('Drawn by a worker'));
        new GenerateSocialCardHandler($this->cards)(new GenerateSocialCard($fingerprint));
        self::assertNotNull($this->store->get($fingerprint));
        new GenerateSocialCardHandler($this->cards)(new GenerateSocialCard(str_repeat('f', 32)));
    }
}
