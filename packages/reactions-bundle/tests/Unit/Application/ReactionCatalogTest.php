<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Unit\Application;

use Override;
use Phil\ReactionsBundle\Application\Contract\EmojiImages;
use Phil\ReactionsBundle\Application\Contract\ReactionSource;
use Phil\ReactionsBundle\Application\DeclaredReactionSource;
use Phil\ReactionsBundle\Application\PlainEmojiImages;
use Phil\ReactionsBundle\Application\ReactionCatalog;
use Phil\ReactionsBundle\Domain\Exception\InvalidReaction;
use Phil\ReactionsBundle\Domain\ValueObject\ReactionDefinition;
use Phil\ReactionsBundle\Domain\ValueObject\ReactionGroup;
use Phil\ReactionsBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReactionCatalog::class)]
#[CoversClass(DeclaredReactionSource::class)]
#[CoversClass(PlainEmojiImages::class)]
#[CoversClass(ReactionGroup::class)]
final class ReactionCatalogTest extends TestCase
{
    public function testTheGroupsInTheOrderTheSiteAsks(): void
    {
        $sources = [Stage::unicode(), Stage::site(), Stage::source(new ReactionGroup('own', 'own', [ReactionDefinition::unicode('own', 'l', 'x')]))];

        self::assertSame(['unicode', 'site', 'own'], $this->names(new ReactionCatalog($sources, new PlainEmojiImages())));
        self::assertSame(['own', 'unicode', 'site'], $this->names(new ReactionCatalog($sources, new PlainEmojiImages(), ['own', 'nothing'])));
        self::assertSame(['site', 'own', 'unicode'], $this->names(new ReactionCatalog($sources, new PlainEmojiImages(), ['site', 'own'])));
    }

    public function testADefinitionByItsCode(): void
    {
        $catalog = new ReactionCatalog([Stage::unicode(), Stage::site()], new PlainEmojiImages());

        self::assertSame('like', $catalog->definition('like')?->code);
        self::assertSame('gnome', $catalog->definition('gnome')?->code);
        self::assertNull($catalog->definition('nothing'));
    }

    public function testASourceIsReadOnceUntilReset(): void
    {
        $source = new class implements ReactionSource {
            public int $reads = 0;

            #[Override]
            public function group(): ReactionGroup
            {
                ++$this->reads;

                return new ReactionGroup('s', 's', [ReactionDefinition::unicode('a', 'l', 'x')]);
            }
        };
        $catalog = new ReactionCatalog([$source], new PlainEmojiImages());

        $catalog->groups();
        $catalog->definition('a');
        $catalog->groups();
        self::assertSame(1, $source->reads);

        $catalog->reset();
        self::assertSame('a', $catalog->definition('a')?->code);
        self::assertSame(2, $source->reads);
    }

    public function testAReactionWhoseImageIsGoneIsNotOffered(): void
    {
        $images = new class implements EmojiImages {
            /** @var list<array<array-key, string>> */
            public array $asked = [];

            #[Override]
            public function urls(string ...$images): array
            {
                $this->asked[] = $images;
                $urls = [];

                foreach ($images as $image) {
                    if ('/gone' !== $image) {
                        $urls[$image] = '/resolved'.$image;
                    }
                }

                return $urls;
            }
        };
        $catalog = new ReactionCatalog([Stage::source(new ReactionGroup('s', 'label', [
            ReactionDefinition::image('gone', 'l', '/gone', 'alt'),
            ReactionDefinition::image('here', 'l', '/here', 'alt'),
            ReactionDefinition::unicode('emoji', 'l', 'x'),
        ], 'domain')), Stage::source(new ReactionGroup('t', 'other', [
            ReactionDefinition::image('again', 'l', '/here', 'alt'),
        ]))], $images);

        [$group, $other] = $catalog->groups();
        self::assertSame(['s', 'label', 'domain'], [$group->name, $group->label, $group->domain]);
        self::assertSame(['here', 'emoji'], array_map(static fn (ReactionDefinition $definition): string => $definition->code, $group->definitions));
        self::assertSame('/resolved/here', $group->definitions[0]->image);
        self::assertSame('x', $group->definitions[1]->emoji);
        self::assertSame('/resolved/here', $other->definitions[0]->image);
        self::assertNull($catalog->definition('gone'));
        self::assertSame([['/gone', '/here']], $images->asked);
    }

    public function testNoImageAskedWithoutImages(): void
    {
        $images = new class implements EmojiImages {
            public int $asked = 0;

            #[Override]
            public function urls(string ...$images): array
            {
                ++$this->asked;

                return [];
            }
        };

        new ReactionCatalog([Stage::unicode()], $images)->groups();

        self::assertSame(0, $images->asked);
    }

    public function testTwoReactionsCannotShareACode(): void
    {
        $catalog = new ReactionCatalog([Stage::unicode(), Stage::source(new ReactionGroup('o', 'o', [ReactionDefinition::unicode('like', 'l', 'x')]))], new PlainEmojiImages());

        $this->expectExceptionObject(InvalidReaction::because('Two reactions have the code "like".'));

        $catalog->groups();
    }

    public function testTheListIsBounded(): void
    {
        $this->build(ReactionCatalog::MAX_REACTIONS)->groups();
        self::assertSame(128, ReactionCatalog::MAX_REACTIONS);

        $this->expectExceptionObject(InvalidReaction::because('A site offers at most 128 reactions.'));

        $this->build(ReactionCatalog::MAX_REACTIONS + 1)->definition('x');
    }

    public function testDeclaredReactions(): void
    {
        $group = new DeclaredReactionSource('unicode', 'label', [
            ['code' => 'a', 'label' => 'la', 'emoji' => 'x', 'private' => true, 'quick' => false, 'translation_domain' => 'd'],
            ['code' => 'b', 'label' => 'lb', 'image' => '/b', 'alt' => 'ab', 'private' => true, 'quick' => true, 'translation_domain' => 'e'],
        ])->group();

        self::assertSame(['unicode', 'label'], [$group->name, $group->label]);
        [$a, $b] = $group->definitions;
        self::assertSame(['a', 'la', 'x', true, false, 'd'], [$a->code, $a->label, $a->emoji, $a->isPrivate, $a->quick, $a->domain]);
        self::assertSame(['b', 'lb', '/b', 'ab', true, true, 'e'], [$b->code, $b->label, $b->image, $b->imageAlt, $b->isPrivate, $b->quick, $b->domain]);

        $this->expectException(InvalidReaction::class);
        new DeclaredReactionSource('s', 'l', [['code' => 'c', 'label' => 'l', 'private' => false, 'quick' => false, 'translation_domain' => 'd']])->group();
    }

    public function testPlainImages(): void
    {
        self::assertSame(['/a.webp' => '/a.webp', 'https://e.test/b.png' => 'https://e.test/b.png'], new PlainEmojiImages()->urls('/a.webp', 'media:1', 'https://e.test/b.png'));
        self::assertSame([], new PlainEmojiImages()->urls());
    }

    private function build(int $reactions): ReactionCatalog
    {
        $definitions = [];

        for ($index = 0; $index < $reactions; ++$index) {
            $definitions[] = ReactionDefinition::unicode('r'.$index, 'l', 'x');
        }

        return new ReactionCatalog([Stage::source(new ReactionGroup('s', 's', $definitions))], new PlainEmojiImages());
    }

    /**
     * @return list<string>
     */
    private function names(ReactionCatalog $catalog): array
    {
        return array_map(static fn (ReactionGroup $group): string => $group->name, $catalog->groups());
    }
}
