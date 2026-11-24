<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Tests\Unit\Application;

use Phil\ReviewsBundle\Application\IdentifierDirectory;
use Phil\ReviewsBundle\Application\NoParticipations;
use Phil\ReviewsBundle\Application\RemovalCriteria;
use Phil\ReviewsBundle\Application\SubjectType;
use Phil\ReviewsBundle\Application\SubjectTypes;
use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewContent;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubjectType::class)]
#[CoversClass(SubjectTypes::class)]
#[CoversClass(RemovalCriteria::class)]
#[CoversClass(NoParticipations::class)]
#[CoversClass(IdentifierDirectory::class)]
final class SettingsTest extends TestCase
{
    public function testTheDefaultsOfAType(): void
    {
        $type = new SubjectType('game');

        self::assertSame(5, $type->scale);
        self::assertSame([], $type->criteria);
        self::assertSame([], $type->superlatives);
        self::assertSame(3, $type->maxSuperlatives);
        self::assertSame(3, $type->superlativesShown);
        self::assertFalse($type->participantsOnly);
        self::assertSame(30, $type->window);
        self::assertSame(5000, $type->bodyLength);
        self::assertSame(5, $type->prior->count);
        self::assertSame(3.0, $type->prior->mean);
        self::assertSame(3, $type->prior->minimum);
        self::assertSame(5.5, new SubjectType('game', scale: 10)->prior->mean);
        self::assertSame(2.0, new SubjectType('game', priorMean: 2.0)->prior->mean);
        self::assertNull(new SubjectType('game', window: null)->window);
        self::assertSame(['a', 'b'], new SubjectType('game', criteria: ['a', 'a', 'b'])->criteria);
        self::assertSame(['x', 'y'], new SubjectType('game', superlatives: ['x', 'x', 'y'])->superlatives);
    }

    /**
     * @return iterable<string, array{callable(): SubjectType, string}>
     */
    public static function refusedTypes(): iterable
    {
        yield 'name' => [static fn (): SubjectType => new SubjectType('Game'), '"Game" is not a subject type.'];
        yield 'scale one' => [static fn (): SubjectType => new SubjectType('game', scale: 1), 'The scale of "game" is from 2 to 10.'];
        yield 'scale eleven' => [static fn (): SubjectType => new SubjectType('game', scale: 11), 'The scale of "game" is from 2 to 10.'];
        yield 'four superlatives' => [static fn (): SubjectType => new SubjectType('game', maxSuperlatives: 4), 'A review of "game" gives from 0 to 3 superlatives.'];
        yield 'negative superlatives' => [static fn (): SubjectType => new SubjectType('game', maxSuperlatives: -1), 'A review of "game" gives from 0 to 3 superlatives.'];
        yield 'negative shown' => [static fn (): SubjectType => new SubjectType('game', superlativesShown: -1), 'A review of "game" gives from 0 to 3 superlatives.'];
        yield 'empty text' => [static fn (): SubjectType => new SubjectType('game', bodyLength: 0), 'The text of a review of "game" holds from 1 to 20000 characters.'];
        yield 'long text' => [static fn (): SubjectType => new SubjectType('game', bodyLength: 20_001), 'The text of a review of "game" holds from 1 to 20000 characters.'];
        yield 'window' => [static fn (): SubjectType => new SubjectType('game', window: 0), 'The window of "game" is one day or more, or none.'];
        yield 'mean above the scale' => [static fn (): SubjectType => new SubjectType('game', priorMean: 5.5), 'The neutral value of "game" is within its scale.'];
        yield 'malformed criterion' => [static fn (): SubjectType => new SubjectType('game', criteria: ['Bad']), '"Bad" is not a criterion: a lowercase letter, then up to 63 lowercase letters, digits, "-" and "_".'];
        yield 'too many criteria' => [static fn (): SubjectType => new SubjectType('game', criteria: array_map(static fn (int $i): string => 'c'.$i, range(1, 21))), 'A subject type has 20 of a criterion at most.'];
        yield 'too many superlatives' => [static fn (): SubjectType => new SubjectType('game', superlatives: array_map(static fn (int $i): string => 's'.$i, range(1, 65))), 'A subject type has 64 of a superlative at most.'];
    }

    /**
     * @param callable(): SubjectType $type
     */
    #[DataProvider('refusedTypes')]
    public function testATypeIsRefused(callable $type, string $message): void
    {
        $this->expectExceptionObject(new InvalidReview($message));
        $type();
    }

    public function testTheLimitsOfATypeAreTaken(): void
    {
        $type = new SubjectType('game', scale: 2, criteria: array_map(static fn (int $i): string => 'c'.$i, range(1, 20)), superlatives: array_map(static fn (int $i): string => 's'.$i, range(1, 64)), maxSuperlatives: 0, window: 1, priorMean: 2.0, bodyLength: 20_000, superlativesShown: 0);

        self::assertSame(2, $type->scale);
        self::assertCount(20, $type->criteria);
        self::assertCount(64, $type->superlatives);
        self::assertSame(1, $type->window);
        self::assertSame(10, new SubjectType('game', scale: 10)->scale);
        self::assertSame(1, new SubjectType('game', bodyLength: 1)->bodyLength);
    }

    public function testAContentIsCheckedAgainstTheType(): void
    {
        $type = new SubjectType('game', criteria: ['rules'], superlatives: ['quick', 'family'], maxSuperlatives: 1, bodyLength: 5);
        $content = new ReviewContent(5, ['rules' => 5], null, 'abcde', ['quick']);

        self::assertSame($content, $type->check($content));
        self::assertSame($content = new ReviewContent(1), $type->check($content));
        self::assertSame($content = new ReviewContent(5, [], null, 'ééééé'), $type->check($content), 'The text is counted in characters, not bytes.');

        $refusals = [
            ['The scores of a review of "game" are from 1 to 5.', new ReviewContent(6)],
            ['The scores of a review of "game" are from 1 to 5.', new ReviewContent(5, ['rules' => 6])],
            ['"art", "luck" is not a criterion of "game".', new ReviewContent(5, ['luck' => 1, 'art' => 2])],
            ['"thinky" is not a superlative of "game".', new ReviewContent(5, [], null, null, ['thinky'])],
            ['A review of "game" gives 1 superlatives at most.', new ReviewContent(5, [], null, null, ['quick', 'family'])],
            ['A review of "game" holds 5 characters at most.', new ReviewContent(5, [], null, 'abcdef')],
        ];

        foreach ($refusals as [$message, $refused]) {
            try {
                $type->check($refused);
                self::fail(\sprintf('Expected: %s', $message));
            } catch (InvalidReview $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }

        $this->expectExceptionObject(new InvalidReview('The scores of a review of "game" are from 1 to 5.'));
        $type->check(new ReviewContent(5, ['rules' => 6]));
    }

    public function testTheTypes(): void
    {
        $game = new SubjectType('game');
        $place = new SubjectType('place');
        $types = new SubjectTypes($game, $place);

        self::assertSame($game, $types->get('game'));
        self::assertTrue($types->has('place'));
        self::assertFalse($types->has('book'));
        self::assertSame([$game, $place], $types->all());

        $this->expectExceptionObject(new InvalidReview('"book" is not a type of subject the reviews declare.'));
        $types->get('book');
    }

    public function testTheRemovalCriteria(): void
    {
        $criteria = new RemovalCriteria();

        self::assertSame(RemovalCriteria::DEFAULTS, $criteria->all());
        self::assertTrue($criteria->has('spam'));
        self::assertFalse($criteria->has('negative'));
        self::assertSame(['spam', 'fake'], new RemovalCriteria(['spam', 'spam', 'fake'])->all());
        self::assertSame('spam', $criteria->fromReason('spam'));
        self::assertSame('spam', $criteria->fromReason('  Spam: links to a shop'));
        self::assertSame('off-topic', $criteria->fromReason("off-topic\nabout the courier"));
        self::assertSame('spam', $criteria->fromReason('spam:'));
        self::assertNull($criteria->fromReason('It is spam'));
        self::assertNull($criteria->fromReason('negative'));
        self::assertNull($criteria->fromReason(''));
        self::assertNull($criteria->fromReason(null));

        try {
            new RemovalCriteria(['Bad']);
            self::fail('A malformed criterion was taken.');
        } catch (InvalidReview) {
            self::addToAssertionCount(1);
        }

        $this->expectExceptionObject(new InvalidReview('The reviews publish at least one removal criterion.'));
        new RemovalCriteria([]);
    }

    public function testTheDefaultPorts(): void
    {
        $subject = SubjectRef::of('game', 'catan');
        $member = MemberId::fromString('ada');
        $directory = new IdentifierDirectory();

        self::assertNull(new NoParticipations()->of($subject, $member));
        self::assertSame('ada', $directory->displayName($member));
        self::assertSame('game catan', $directory->title($subject));
        self::assertNull($directory->url($subject));
    }
}
