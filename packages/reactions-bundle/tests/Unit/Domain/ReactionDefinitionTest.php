<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Unit\Domain;

use Phil\ReactionsBundle\Domain\Exception\InvalidReaction;
use Phil\ReactionsBundle\Domain\ValueObject\ReactionDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReactionDefinition::class)]
#[CoversClass(InvalidReaction::class)]
final class ReactionDefinitionTest extends TestCase
{
    public function testAUnicodeReaction(): void
    {
        $definition = ReactionDefinition::unicode('like', 'label', '👍');

        self::assertSame(['like', 'label', '👍', null, null, false, true, 'phil_reactions'], [$definition->code, $definition->label, $definition->emoji, $definition->image, $definition->imageAlt, $definition->isPrivate, $definition->quick, $definition->domain]);
        self::assertSame([true, false, 'site'], (static function (): array {
            $private = ReactionDefinition::unicode('no', 'label', '🫤', true, false, 'site');

            return [$private->isPrivate, $private->quick, $private->domain];
        })());
    }

    public function testAnImageReaction(): void
    {
        $definition = ReactionDefinition::image('gnome', 'label', '/e/g.webp', 'alt');

        self::assertSame([null, '/e/g.webp', 'alt', false, false, 'phil_reactions'], [$definition->emoji, $definition->image, $definition->imageAlt, $definition->isPrivate, $definition->quick, $definition->domain]);

        $moved = ReactionDefinition::image('gnome', 'label', 'media:42', 'alt', true, true, 'site')->withImageAt('/m/42');

        self::assertSame(['gnome', 'label', null, '/m/42', 'alt', true, true, 'site'], [$moved->code, $moved->label, $moved->emoji, $moved->image, $moved->imageAlt, $moved->isPrivate, $moved->quick, $moved->domain]);
    }

    #[TestWith(['a'])]
    #[TestWith(['a0_+-'])]
    public function testACode(string $code): void
    {
        self::assertSame($code, ReactionDefinition::code($code));
        self::assertSame(str_repeat('a', 64), ReactionDefinition::code(str_repeat('a', 64)));
    }

    #[TestWith([''])]
    #[TestWith(['1a'])]
    #[TestWith(['A'])]
    #[TestWith(['a b'])]
    #[TestWith(["a\n"])]
    public function testNotACode(string $code): void
    {
        $this->expectException(InvalidReaction::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not.', $code));

        ReactionDefinition::code($code);
    }

    public function testACodeTooLong(): void
    {
        $this->expectException(InvalidReaction::class);

        ReactionDefinition::code(str_repeat('a', 65));
    }

    #[TestWith([''])]
    public function testAnEmptyEmoji(string $emoji): void
    {
        $this->expectExceptionObject(InvalidReaction::because('The emoji of "a" is 1 to 32 bytes.'));

        ReactionDefinition::unicode('a', 'l', $emoji);
    }

    public function testTheLongestEmoji(): void
    {
        self::assertSame(str_repeat('x', 32), ReactionDefinition::unicode('a', 'l', str_repeat('x', 32))->emoji);

        $this->expectException(InvalidReaction::class);

        ReactionDefinition::unicode('a', 'l', str_repeat('x', 33));
    }

    #[TestWith(['/a.webp'])]
    #[TestWith(['https://x.test/a.webp'])]
    #[TestWith(['media:1'])]
    public function testAnImage(string $image): void
    {
        self::assertSame($image, ReactionDefinition::image('a', 'l', $image, 'alt')->image);
    }

    #[TestWith(['//evil.test/a.webp'])]
    #[TestWith(['javascript:alert(1)'])]
    #[TestWith(['http://x.test/a.webp'])]
    #[TestWith(['/a b'])]
    #[TestWith(["/a\n"])]
    #[TestWith(['/'])]
    #[TestWith(['media:'])]
    public function testNotAnImage(string $image): void
    {
        $this->expectExceptionObject(InvalidReaction::because('The image of "a" is a path from the root of the site, an HTTPS address or "media:" and an identifier.'));

        ReactionDefinition::image('a', 'l', $image, 'alt');
    }

    public function testAnEmptyLabelOrText(): void
    {
        foreach ([static fn (): ReactionDefinition => ReactionDefinition::unicode('a', '', 'x'), static fn (): ReactionDefinition => ReactionDefinition::image('a', 'l', '/a', '')] as $build) {
            try {
                $build();
                self::fail('Accepted.');
            } catch (InvalidReaction $invalidReaction) {
                self::assertSame('The label and the replacement text of "a" are translation keys, and cannot be empty.', $invalidReaction->getMessage());
            }
        }
    }
}
