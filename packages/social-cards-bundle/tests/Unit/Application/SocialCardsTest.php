<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Tests\Unit\Application;

use Phil\SocialCards\Card\CardContent;
use Phil\SocialCards\Card\CardGenerator;
use Phil\SocialCards\Card\GdCardRenderer;
use Phil\SocialCards\Card\InMemoryCardStore;
use Phil\SocialCardsBundle\Application\CardRequest;
use Phil\SocialCardsBundle\Application\CardTemplates;
use Phil\SocialCardsBundle\Application\Exception\UnknownCardTemplate;
use Phil\SocialCardsBundle\Application\SharePage;
use Phil\SocialCardsBundle\Application\SocialCards;
use Phil\SocialCardsBundle\Tests\Support\MemoryRequests;
use Phil\SocialCardsBundle\Tests\Support\RecordingQueue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SocialCards::class)]
#[CoversClass(CardTemplates::class)]
#[CoversClass(CardRequest::class)]
#[CoversClass(UnknownCardTemplate::class)]
#[CoversClass(SharePage::class)]
final class SocialCardsTest extends TestCase
{
    private const string FONT = __DIR__.'/../../Fixtures/card-font.ttf';

    public function testACardIsRequestedThenDrawnOnce(): void
    {
        $templates = new CardTemplates(['default' => ['title_font' => self::FONT], 'dark' => ['title_font' => self::FONT, 'background' => '#000']], 'default');
        $store = new InMemoryCardStore();
        $requests = new MemoryRequests();
        $queue = new RecordingQueue();
        $generator = new CardGenerator(new GdCardRenderer(), $store);
        $cards = new SocialCards($templates, $generator, $requests, $store, $queue);
        $content = new CardContent('Title', 'Subtitle', 'fr');

        $fingerprint = $cards->request($content);
        self::assertSame([$fingerprint], $queue->queued);
        self::assertSame($generator->fingerprint($templates->get('default'), $content), $fingerprint);
        self::assertSame($fingerprint, $cards->request($content));
        self::assertSame($fingerprint, $cards->request($content));
        self::assertSame([$fingerprint], $queue->queued, 'A card is queued the first time only.');
        self::assertEquals(new CardRequest('default', $content), $requests->find($fingerprint));

        $dark = $cards->request($content, 'dark');
        self::assertNotSame($fingerprint, $dark);
        self::assertSame('dark', $requests->find($dark)?->template);

        self::assertSame([null], [$store->get($fingerprint)], 'The card is not drawn before it is asked for.');
        self::assertTrue($cards->generate($fingerprint));
        $png = $store->get($fingerprint);
        self::assertNotNull($png);
        self::assertStringStartsWith("\x89PNG", $png);
        self::assertSame($png, $cards->image($fingerprint));
        self::assertFalse($cards->generate(str_repeat('0', 32)), 'No page named that card.');
        self::assertNull($cards->image('../etc/passwd'));
        self::assertNull($cards->image(str_repeat('A', 32)));
    }

    public function testACardWhoseTemplateIsGone(): void
    {
        $requests = new MemoryRequests();
        $requests->save(str_repeat('a', 32), new CardRequest('removed', new CardContent('T')));

        $store = new InMemoryCardStore();
        $cards = new SocialCards(new CardTemplates(['default' => ['title_font' => self::FONT]], 'default'), new CardGenerator(new GdCardRenderer(), $store), $requests, $store);

        self::assertNull($cards->image(str_repeat('a', 32)));
        self::assertNotSame('', $cards->request(new CardContent('Without a queue')));
        $store->put(str_repeat('b', 32), 'kept');
        self::assertSame('kept', $cards->image(str_repeat('b', 32)), 'A kept card is served as it is, without its request.');
    }

    public function testTheTemplates(): void
    {
        $templates = new CardTemplates(['a' => ['title_font' => self::FONT], 'b' => ['title_font' => self::FONT, 'padding' => 10]], 'b');

        self::assertSame(['a', 'b'], $templates->names());
        self::assertSame('b', $templates->default());
        self::assertSame(10, $templates->get()->padding);
        self::assertSame(80, $templates->get('a')->padding);
        self::assertSame($templates->get('a'), $templates->get('a'), 'A template is read once.');
        self::assertTrue($templates->has('a'));
        self::assertFalse($templates->has('c'));

        $this->expectException(UnknownCardTemplate::class);
        $this->expectExceptionMessageIsOrContains('No card template is named "c"; the configuration declares "a", "b".');
        $templates->get('c');
    }

    public function testARequestKeptAsAnArray(): void
    {
        $request = new CardRequest('default', new CardContent('Title', 'Sub', 'fr_CA'));

        self::assertSame(['template' => 'default', 'title' => 'Title', 'subtitle' => 'Sub', 'locale' => 'fr_CA'], $request->toArray());
        self::assertEquals($request, CardRequest::fromArray($request->toArray()));
        self::assertEquals(new CardRequest('d', new CardContent('T')), CardRequest::fromArray(['template' => 'd', 'title' => 'T', 'locale' => 'en']));

        foreach ([[], ['template' => 1, 'title' => 'T', 'locale' => 'en'], ['template' => 'd', 'title' => 1, 'locale' => 'en'], ['template' => 'd', 'title' => 'T', 'locale' => 1], ['template' => 'd', 'title' => 'T', 'subtitle' => 1, 'locale' => 'en'], ['template' => 'd', 'title' => ' ', 'locale' => 'en'], ['template' => 'd', 'title' => 'T', 'locale' => 'no locale']] as $values) {
            self::assertNull(CardRequest::fromArray($values));
        }
    }

    public function testThePageOfTheRequest(): void
    {
        $page = new SharePage();
        $page->set(['title' => 'A', 'description' => 'D', 'author' => 'Kept']);
        $page->set(['title' => 'B', 'description' => null, 'type' => 'article']);

        self::assertSame(['title' => 'B', 'author' => 'Kept', 'type' => 'article'], $page->values());
        $page->reset();
        self::assertSame([], $page->values());
    }
}
