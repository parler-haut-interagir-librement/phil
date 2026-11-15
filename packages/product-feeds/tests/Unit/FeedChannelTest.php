<?php

declare(strict_types=1);

namespace Phil\ProductFeeds\Tests\Unit;

use Phil\ProductFeeds\Exception\InvalidFeedItem;
use Phil\ProductFeeds\FeedChannel;
use Phil\ProductFeeds\Text;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FeedChannel::class)]
#[CoversClass(Text::class)]
final class FeedChannelTest extends TestCase
{
    public function testAChannelHasATitleALinkAndADescription(): void
    {
        $channel = new FeedChannel(" The\nDice Shop ", Items::url('https://shop.example/'), "Dice\r\n and games ");

        self::assertSame(['The Dice Shop', 'https://shop.example/', "Dice\n and games"], [$channel->title, $channel->link->toString(), $channel->description]);
    }

    public function testTheTitleHoldsAHundredAndFiftyCharacters(): void
    {
        self::assertSame(str_repeat('a', 150), new FeedChannel(str_repeat('a', 150), Items::url('https://shop.example/'), 'A')->title);

        $this->expectException(InvalidFeedItem::class);
        $this->expectExceptionMessageIs('The title of a feed holds 150 characters at most.');

        new FeedChannel(str_repeat('a', 151), Items::url('https://shop.example/'), 'A');
    }

    public function testTheDescriptionIsRequired(): void
    {
        self::assertSame(str_repeat('a', 5_000), new FeedChannel('A', Items::url('https://shop.example/'), str_repeat('a', 5_000))->description);

        $this->expectException(InvalidFeedItem::class);
        $this->expectExceptionMessageIs('The description of a feed holds 1 to 5000 characters, not blank.');

        new FeedChannel('A', Items::url('https://shop.example/'), " \n ");
    }
}
