<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Fixtures;

use DateTimeImmutable;
use Phil\FeedBundle\Domain\ValueObject\Attachment;
use Phil\FeedBundle\Domain\ValueObject\Author;
use Phil\FeedBundle\Domain\ValueObject\Category;
use Phil\FeedBundle\Domain\ValueObject\Channel;
use Phil\FeedBundle\Domain\ValueObject\Feed;
use Phil\FeedBundle\Domain\ValueObject\FeedItem;
use Phil\FeedBundle\Domain\ValueObject\FeedLinks;
use Phil\Url\Url;

/**
 * The feeds the writers are tested with: a full one, with every field, characters outside ASCII, HTML to escape and a
 * podcast episode, and an empty one.
 */
final class SampleFeeds
{
    public static function channel(): Channel
    {
        return new Channel(
            'Nouvelles & « échos »',
            Url::fromString('https://example.org/fr/nouvelles'),
            'Ce qui se passe <ici>.',
            'fr-CA',
            [new Author('Équipe', Url::fromString('https://example.org/equipe'), Url::fromString('https://example.org/equipe.png'))],
            Url::fromString('https://example.org/icon.png'),
        );
    }

    public static function full(): Feed
    {
        return new Feed(self::channel(), [
            new FeedItem(
                'tag:example.org,2026:news-2',
                'Ouverture <b>officielle</b> & café',
                Url::fromString('https://example.org/fr/nouvelles/ouverture'),
                new DateTimeImmutable('2026-11-20T09:30:00-05:00'),
                new DateTimeImmutable('2026-11-21T10:00:00+01:00'),
                'Un résumé où 2 < 3 & « guillemets ».',
                '<p>Le <em>contenu</em> &amp; une <a href="https://example.org/?a=1&amp;b=2">adresse</a>.</p>',
                [new Author('Zoé Lévesque', Url::fromString('https://example.org/zoe'))],
                [new Category('ouverture', 'Ouverture'), new Category('café')],
                new Attachment(Url::fromString('https://example.org/episode-2.mp3'), 'audio/mpeg', 12_345_678, 'Épisode 2', 1_830),
                'fr',
            ),
            new FeedItem(
                'https://example.org/fr/nouvelles/annonce',
                'Annonce',
                Url::fromString('https://example.org/fr/nouvelles/annonce'),
                new DateTimeImmutable('2026-11-19T08:00:00Z'),
            ),
        ]);
    }

    public static function empty(): Feed
    {
        return new Feed(new Channel('Empty', Url::fromString('https://example.org/')), []);
    }

    public static function links(): FeedLinks
    {
        return new FeedLinks(
            Url::fromString('https://example.org/feeds/news.atom?page=2'),
            [Url::fromString('https://hub.example.net/')],
            Url::fromString('https://example.org/feeds/news.atom'),
            Url::fromString('https://example.org/feeds/news.atom'),
            Url::fromString('https://example.org/feeds/news.atom?page=3'),
        );
    }

    public static function plainLinks(): FeedLinks
    {
        return new FeedLinks(Url::fromString('https://example.org/feeds/empty.atom'));
    }
}
