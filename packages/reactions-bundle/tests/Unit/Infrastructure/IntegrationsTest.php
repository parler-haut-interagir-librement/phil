<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\MediaBundle\Application\UrlSigner;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\Model\MediaCollection;
use Phil\MediaBundle\Domain\Model\MediaVersion;
use Phil\MediaBundle\Domain\Repository\MediaRepository;
use Phil\MediaBundle\Domain\ValueObject\FileInfo;
use Phil\MediaBundle\Infrastructure\Twig\MediaExtension;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\ReactionsBundle\Application\Event\ReactionAdded;
use Phil\ReactionsBundle\Application\Event\ReactionRemoved;
use Phil\ReactionsBundle\Application\Exception\TooManyReactions;
use Phil\ReactionsBundle\Application\Reactions;
use Phil\ReactionsBundle\Application\ReactionSubject;
use Phil\ReactionsBundle\Domain\Exception\InvalidReaction;
use Phil\ReactionsBundle\Domain\Model\Reaction;
use Phil\ReactionsBundle\Domain\Status\Audience;
use Phil\ReactionsBundle\Domain\ValueObject\ReactionDefinition;
use Phil\ReactionsBundle\Domain\ValueObject\ReactionVisibility;
use Phil\ReactionsBundle\Infrastructure\Media\MediaEmojiImages;
use Phil\ReactionsBundle\Infrastructure\Memory\InMemoryReactionRepository;
use Phil\ReactionsBundle\Infrastructure\Messenger\EventsOnTheBus;
use Phil\ReactionsBundle\Infrastructure\Privacy\ReactionsPrivacy;
use Phil\ReactionsBundle\Infrastructure\RateLimiter\SymfonyThrottle;
use Phil\ReactionsBundle\Infrastructure\Security\AuthorizationCheckerAccess;
use Phil\ReactionsBundle\Infrastructure\Security\TokenCurrentMember;
use Phil\ReactionsBundle\Infrastructure\Settings\SettingsVisibility;
use Phil\ReactionsBundle\Infrastructure\Twig\KitComponents;
use Phil\ReactionsBundle\Infrastructure\Twig\ReactionsTwig;
use Phil\ReactionsBundle\Tests\Fixtures\Stage;
use Phil\Resource\ResourceReference;
use Phil\SettingsBundle\Application\Contract\CurrentUser;
use Phil\SettingsBundle\Application\Contract\VisitorPreferences;
use Phil\SettingsBundle\Application\SettingCatalog;
use Phil\SettingsBundle\Application\Settings;
use Phil\SettingsBundle\Domain\Setting\Scope;
use Phil\SettingsBundle\Domain\Setting\SectionDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingType;
use Phil\SettingsBundle\Domain\ValueObject\Subject;
use Phil\SettingsBundle\Infrastructure\Memory\InMemorySettingRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(AuthorizationCheckerAccess::class)]
#[CoversClass(TokenCurrentMember::class)]
#[CoversClass(SymfonyThrottle::class)]
#[CoversClass(EventsOnTheBus::class)]
#[CoversClass(ReactionsPrivacy::class)]
#[CoversClass(MediaEmojiImages::class)]
#[CoversClass(SettingsVisibility::class)]
#[CoversClass(KitComponents::class)]
#[CoversClass(ReactionsTwig::class)]
final class IntegrationsTest extends TestCase
{
    public function testTheVotersAreAskedTheAttributeAboutTheSubject(): void
    {
        $checker = new class implements AuthorizationCheckerInterface {
            /** @var list<array{mixed, mixed}> */
            public array $asked = [];

            public bool $answer = false;

            #[Override]
            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                $this->asked[] = [$attribute, $subject];

                return $this->answer;
            }
        };
        $subject = new ReactionSubject(ResourceReference::of('article', '1'), 'ada', ReactionDefinition::unicode('a', 'l', 'x'));
        $access = new AuthorizationCheckerAccess($checker);

        self::assertFalse($access->canReact($subject));
        $checker->answer = true;
        self::assertTrue($access->canReact($subject));
        self::assertSame([Reactions::REACT, $subject], $checker->asked[0]);
    }

    public function testTheMemberOfTheToken(): void
    {
        $tokens = new TokenStorage();
        $member = new TokenCurrentMember($tokens);

        self::assertNull($member->current());
        $tokens->setToken(new NullToken());
        self::assertNull($member->current());
        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('ada', null), 'main'));
        self::assertSame('ada', $member->current());

        $tokens->setToken(new UsernamePasswordToken(new InMemoryUser('ada@example.com', null), 'main'));
        $this->expectExceptionObject(InvalidReaction::because('A member identifier is a stable identifier the site gives, never an email address: it cannot contain "@".'));
        $member->current();
    }

    public function testTheThrottleKeepsToEachMember(): void
    {
        $clock = new MockClock('2026-05-01 10:00:00');
        $throttle = new SymfonyThrottle(new RateLimiterFactory(['id' => 'r', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute'], new InMemoryStorage()), $clock);

        $throttle->consume('ada');
        $throttle->consume('linus');

        $this->expectException(TooManyReactions::class);
        $throttle->consume('ada');
    }

    public function testTheThrottleSaysHowLongToWaitAtLeastASecond(): void
    {
        $factory = new RateLimiterFactory(['id' => 'r', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute'], new InMemoryStorage());
        $now = new MockClock();
        $throttle = new SymfonyThrottle($factory, $now);
        $throttle->consume('ada');

        try {
            $throttle->consume('ada');
            self::fail('Accepted.');
        } catch (TooManyReactions $tooManyReactions) {
            self::assertGreaterThanOrEqual(1, $tooManyReactions->retryAfter);
        }

        $late = new SymfonyThrottle($factory, new MockClock('+1 year'));

        try {
            $late->consume('ada');
            self::fail('Accepted.');
        } catch (TooManyReactions $tooManyReactions) {
            self::assertSame(1, $tooManyReactions->retryAfter);
        }
    }

    public function testTheEventsOnTheBus(): void
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $sent = [];

            #[Override]
            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->sent[] = $message;

                return new Envelope($message);
            }
        };
        $added = new ReactionAdded('article:1', 'ada', 'like', new DateTimeImmutable());
        $removed = new ReactionRemoved('article:1', 'ada', 'like', new DateTimeImmutable());

        $on = new EventsOnTheBus($bus);
        $on($added);
        $on($removed);

        self::assertSame([$added, $removed], $bus->sent);
        self::assertSame(['content', 'member', 'code', 'at'], array_keys(get_object_vars($added)));
    }

    public function testAMembersReactionsExportedAndErased(): void
    {
        $store = new InMemoryReactionRepository();
        $article = ResourceReference::of('article', '1');
        $store->add(Reaction::of('r1', $article, 'ada', 'not_useful', new DateTimeImmutable('2026-05-01 10:00:00+00:00')));
        $store->add(Reaction::of('r2', $article, 'linus', 'like', new DateTimeImmutable('2026-05-01 10:00:00+00:00')));

        $privacy = new ReactionsPrivacy($store);
        $ada = DataSubject::fromString('ada');

        self::assertSame('reactions', $privacy->module()->value);
        self::assertSame(['reactions' => [['content' => 'article:1', 'code' => 'not_useful', 'reacted_at' => '2026-05-01T10:00:00+00:00']]], $privacy->export($ada));

        $report = $privacy->erase($ada);
        self::assertSame([], $store->ofMember('ada'));
        self::assertCount(1, $store->ofMember('linus'));
        self::assertSame('reactions', $report->module->value);
        self::assertSame([Reaction::TABLE => ErasureEffect::Deleted], $report->effects);
        self::assertSame([], $privacy->export($ada));
        self::assertSame('reactions', $privacy->erase($ada)->module->value);
    }

    public function testImagesKeptAsMedia(): void
    {
        $media = Media::upload(FileInfo::of('g.webp', 'image/webp', 10), null, new DateTimeImmutable('2026-05-01'));
        $repository = new class($media) implements MediaRepository {
            /** @var list<list<string>> */
            public array $asked = [];

            public function __construct(private readonly Media $media)
            {
            }

            #[Override]
            public function getMany(string ...$ids): array
            {
                $this->asked[] = array_values($ids);

                return \in_array($this->media->id, $ids, true) ? [$this->media->id => $this->media] : [];
            }

            #[Override]
            public function save(Media|MediaVersion|MediaCollection ...$objects): void
            {
            }

            #[Override]
            public function remove(Media|MediaCollection ...$objects): void
            {
            }

            #[Override]
            public function get(string $id): ?Media
            {
                return $id === $this->media->id ? $this->media : null;
            }

            #[Override]
            public function collection(string $key): ?MediaCollection
            {
                return null;
            }

            #[Override]
            public function collections(): array
            {
                return [];
            }

            #[Override]
            public function inCollection(?MediaCollection $collection, int $limit = 50, int $offset = 0): array
            {
                return [];
            }

            #[Override]
            public function versions(Media $media): array
            {
                return [];
            }
        };
        $generator = new class implements UrlGeneratorInterface {
            private RequestContext $context;

            public function __construct()
            {
                $this->context = new RequestContext();
            }

            #[Override]
            public function setContext(RequestContext $context): void
            {
                $this->context = $context;
            }

            #[Override]
            public function getContext(): RequestContext
            {
                return $this->context;
            }

            /**
             * @param array<array-key, mixed> $parameters
             */
            #[Override]
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                $id = $parameters['id'] ?? '';
                \assert(\is_string($id));

                return '/media/'.$id;
            }
        };
        $clock = new MockClock();
        $urls = new MediaExtension($generator, new UrlSigner(str_repeat('s', 32)), $clock);
        $images = new MediaEmojiImages($repository, $urls);

        $found = $images->urls('/a.webp', 'media:'.$media->id, 'media:0192f3c4-0000-7000-8000-000000000000', 'media:not-an-identifier');

        self::assertSame(['/a.webp', 'media:'.$media->id], array_keys($found));
        self::assertSame('/a.webp', $found['/a.webp']);
        self::assertStringStartsWith('/media/'.$media->id.'?', $found['media:'.$media->id]);
        self::assertSame([[$media->id, '0192f3c4-0000-7000-8000-000000000000']], $repository->asked);
        self::assertSame(['/b.webp' => '/b.webp'], $images->urls('/b.webp', 'media:nothing'));
        self::assertCount(1, $repository->asked);
        self::assertSame(['/c.webp' => '/c.webp', '/d.webp' => '/d.webp'], $images->urls('/c.webp', '/d.webp'));
        self::assertSame(['/c.webp', 'media:'.$media->id], array_keys($images->urls('media:not-an-identifier', '/c.webp', 'media:'.$media->id)));
    }

    public function testTheVisibilityChosenInTheSettings(): void
    {
        $repository = new InMemorySettingRepository();
        $settings = $this->settings($repository);
        $configured = new Stage();
        $configured->visibility = new ReactionVisibility(Audience::Members, Audience::Nobody, Audience::Everyone);

        $article = ResourceReference::of('article', '1');
        $policy = new SettingsVisibility($configured, $settings);

        self::assertEquals($configured->visibility, $policy->of($article));

        $settings->save(Subject::site(), [SettingsVisibility::PANEL => 'everyone', SettingsVisibility::COUNTS => 'members', SettingsVisibility::NAMES => 'nobody']);
        $settings->reset();

        self::assertEquals(new ReactionVisibility(Audience::Everyone, Audience::Members, Audience::Nobody), $policy->of($article));
    }

    public function testTheNamesOfTheRecipes(): void
    {
        self::assertSame('ReactionButton', new KitComponents()->name('ReactionButton'));
        self::assertSame('ReactionButton', new KitComponents('/')->name('ReactionButton'));
        self::assertSame('ui:ReactionButton', new KitComponents('ui')->name('ReactionButton'));
        self::assertSame('ui:kit:ReactionPicker', new KitComponents('ui/kit/')->name('ReactionPicker'));
        self::assertSame('ui:kit:ReactionPicker', new KitComponents('/ui/kit')->name('ReactionPicker'));
    }

    public function testPreloadingFromATemplate(): void
    {
        $stage = new Stage();

        new ReactionsTwig($stage->board)->preload(['article:1', 'article:2']);

        self::assertSame([['article:1', 'article:2']], $stage->repository->counted);
    }

    private function settings(InMemorySettingRepository $repository): Settings
    {
        $choices = ['configured', 'everyone', 'members', 'nobody'];
        $catalog = new SettingCatalog([new SectionDefinition('general')], [
            new SettingDefinition(SettingsVisibility::PANEL, SettingType::Choice, 'configured', Scope::Site, choices: $choices),
            new SettingDefinition(SettingsVisibility::COUNTS, SettingType::Choice, 'configured', Scope::Site, choices: $choices),
            new SettingDefinition(SettingsVisibility::NAMES, SettingType::Choice, 'configured', Scope::Site, choices: $choices),
        ]);
        $current = new readonly class implements CurrentUser {
            #[Override]
            public function id(): ?string
            {
                return null;
            }
        };
        $visitor = new class implements VisitorPreferences {
            #[Override]
            public function values(): array
            {
                return [];
            }
        };

        return new Settings($catalog, $repository, $current, $visitor, new MockClock());
    }
}
