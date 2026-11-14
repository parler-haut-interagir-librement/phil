<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\Poll\BallotKind;
use Phil\PollBundle\Application\PollDraft;
use Phil\PollBundle\Application\Polls;
use Phil\PollBundle\Domain\ValueObject\PollOption;
use Phil\PollBundle\Infrastructure\Twig\PollTwig;
use Phil\PollBundle\Tests\Functional\App\TestKernel;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;

/**
 * The same templates drawn by the recipes of the Shadcn kit of the Symfony UX Toolkit, installed at the root of the
 * components directory: each radio button carries its name, its checked state and whether it is required, as the
 * Shadcn group passes none of them; each bar is a share from 0 to 100, as the Shadcn Progress takes it.
 */
#[CoversClass(PollTwig::class)]
final class ShadcnTemplatesTest extends KernelTestCase
{
    private Polls $polls;

    private Environment $twig;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    protected static function createKernel(array $options = []): KernelInterface
    {
        return parent::createKernel(['environment' => TestKernel::SHADCN, 'debug' => false] + $options);
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        if (null === TestKernel::shadcnKit()) {
            self::markTestSkipped('The Symfony UX Toolkit is not installed.');
        }

        self::bootKernel();
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $polls = self::getContainer()->get(Polls::class);
        self::assertInstanceOf(Polls::class, $polls);
        $this->polls = $polls;
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $this->twig = $twig;
    }

    public function testASingleBallotCarriesItsStateOnEveryRadioButton(): void
    {
        $poll = $this->polls->create(new PollDraft('Tea or coffee?', [PollOption::of('tea', 'Tea'), PollOption::of('coffee', 'Coffee')]), ResourceReference::of('user', 'ada'));
        $this->polls->cast($poll->id, ResourceReference::of('user', 'bob'), ['coffee']);
        $page = $this->ballot($poll->id, 'user:bob');
        $id = 'phil-poll-'.$poll->id;

        self::assertSame('Field:Set', $this->twig->createTemplate("{{ phil_poll_kit('Field:Set') }}")->render());
        $group = $page->filter('div[role="radiogroup"][data-slot="radio-group"]');
        self::assertSame($id.'-question', $group->attr('aria-labelledby'));
        $radios = $group->filter('input[type="radio"][data-slot="radio-group-item"]');
        self::assertSame(['choice', 'choice'], $radios->each(static fn (Crawler $radio): ?string => $radio->attr('name')));
        self::assertSame(['tea', 'coffee'], $radios->each(static fn (Crawler $radio): ?string => $radio->attr('value')));
        self::assertSame([null, 'checked'], $radios->each(static fn (Crawler $radio): ?string => null === $radio->attr('checked') ? null : 'checked'));
        self::assertSame(['required', 'required'], $radios->each(static fn (Crawler $radio): ?string => null === $radio->attr('required') ? null : 'required'));
        self::assertSame('Coffee', $page->filter('label[for="'.$id.'-coffee"]')->text());
        self::assertSame('Your ballot is recorded. You may change it while the poll is open.', $page->filter('p[role="status"]')->text());
        self::assertSame('Change my ballot', $page->filter('button[type="submit"][data-slot="button"]')->text());
    }

    public function testTheOtherKindsAreDrawnByTheShadcnRecipes(): void
    {
        $ada = ResourceReference::of('user', 'ada');
        $multiple = $this->polls->create(new PollDraft('Which?', [PollOption::of('a', 'A'), PollOption::of('b', 'B')], BallotKind::Multiple), $ada);
        $ranked = $this->polls->create(new PollDraft('Order?', [PollOption::of('a', 'A'), PollOption::of('b', 'B')], BallotKind::Ranked), $ada);
        $scale = $this->polls->create(new PollDraft('How likely?', PollDraft::marks(0, 10), BallotKind::Scale, abstention: true, counting: 'nps'), $ada);

        self::assertSame(['a', 'b'], $this->ballot($multiple->id, 'user:bob')->filter('input[type="checkbox"][name="choices[]"]')->each(static fn (Crawler $box): ?string => $box->attr('value')));
        self::assertSame(['', '1', '2'], $this->ballot($ranked->id, 'user:bob')->filter('select[data-slot="native-select"][name="ranks[a]"] option')->each(static fn (Crawler $option): ?string => $option->attr('value')));
        $marks = $this->ballot($scale->id, 'user:bob')->filter('input[type="radio"][name="choice"]');
        self::assertCount(12, $marks);
        self::assertSame([], array_filter($marks->each(static fn (Crawler $radio): ?string => $radio->attr('required')), static fn (?string $required): bool => null !== $required), 'A scale accepting a blank ballot requires no mark.');
    }

    public function testTheErrorsAreAnAlert(): void
    {
        $poll = $this->polls->create(new PollDraft('Tea or coffee?', [PollOption::of('tea', 'Tea'), PollOption::of('coffee', 'Coffee')]), ResourceReference::of('user', 'ada'));
        $page = new Crawler($this->twig->createTemplate("{{ include('@PhilPoll/ballot.html.twig', {ballot: phil_poll_ballot(poll, 'user:bob'), action: '/vote', errors: ['Choose one option.']}) }}")->render(['poll' => $poll->id]));
        $id = 'phil-poll-'.$poll->id;

        self::assertSame('Choose one option.', $page->filter('#'.$id.'-error[role="alert"]')->text());
        self::assertSame($id.'-help '.$id.'-error', $page->filter('fieldset')->attr('aria-describedby'));
    }

    public function testTheBarsAreSharesOfTheHighestScore(): void
    {
        $poll = $this->polls->create(new PollDraft('Tea or coffee?', [PollOption::of('tea', 'Tea'), PollOption::of('coffee', 'Coffee'), PollOption::of('cocoa', 'Cocoa')]), ResourceReference::of('user', 'ada'));
        $this->polls->cast($poll->id, ResourceReference::of('user', 'bob'), ['tea']);
        $this->polls->cast($poll->id, ResourceReference::of('user', 'cid'), ['tea']);
        $this->polls->cast($poll->id, ResourceReference::of('user', 'dee'), ['tea']);
        $this->polls->cast($poll->id, ResourceReference::of('user', 'eve'), ['coffee']);

        $page = new Crawler($this->twig->createTemplate("{{ include('@PhilPoll/results.html.twig', {results: phil_poll_results(poll, 'user:bob')}) }}")->render(['poll' => $poll->id]));
        $bars = $page->filter('[role="progressbar"][data-slot="progress"]');
        $id = 'phil-poll-results-'.$poll->id;

        self::assertSame(['100', '33', '0'], $bars->each(static fn (Crawler $bar): ?string => $bar->attr('aria-valuenow')));
        self::assertSame(['100', '100', '100'], $bars->each(static fn (Crawler $bar): ?string => $bar->attr('aria-valuemax')));
        self::assertSame(['3', '1', '0'], $bars->each(static fn (Crawler $bar): ?string => $bar->attr('aria-valuetext')));
        self::assertSame([$id.'-tea', $id.'-coffee', $id.'-cocoa'], $bars->each(static fn (Crawler $bar): ?string => $bar->attr('aria-labelledby')));
    }

    private function ballot(string $poll, string $viewer): Crawler
    {
        return new Crawler($this->twig->createTemplate("{{ include('@PhilPoll/ballot.html.twig', {ballot: phil_poll_ballot(poll, viewer), action: '/vote'}) }}")->render(['poll' => $poll, 'viewer' => $viewer]));
    }
}
