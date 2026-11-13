<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\Poll\BallotKind;
use Phil\PollBundle\Application\BallotForm;
use Phil\PollBundle\Application\PollDraft;
use Phil\PollBundle\Application\Polls;
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\PollBundle\Domain\ValueObject\PollOption;
use Phil\PollBundle\Infrastructure\Twig\PollTwig;
use Phil\PollBundle\Tests\Functional\App\TestKernel;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;

/**
 * The templates of a ballot and of the results, drawn by Twig in a booted kernel from the functions of the bundle, in
 * English and in French; and a ballot posted by the form read back by `BallotForm`.
 */
#[CoversClass(PollTwig::class)]
final class TemplatesTest extends KernelTestCase
{
    private Polls $polls;

    private Environment $twig;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
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

    public function testASingleBallotIsAGroupOfRadioButtons(): void
    {
        $poll = $this->polls->create(new PollDraft('Tea or coffee?', [PollOption::of('tea', 'Tea'), PollOption::of('coffee', 'Coffee & milk')], abstention: true), ResourceReference::of('user', 'ada'));
        $html = $this->draw("{{ include('@PhilPoll/ballot.html.twig', {ballot: phil_poll_ballot(poll, 'user:bob'), action: '/vote', csrf_token: 'tok'}) }}", $poll->id);

        self::assertStringContainsString('<form method="post" action="/vote" class="phil-poll-ballot" data-phil-poll="'.$poll->id.'">', $html);
        self::assertStringContainsString('<input type="hidden" name="_token" value="tok">', $html);
        self::assertStringContainsString('<legend data-slot="field-legend" data-variant="legend" class="isFieldLegend">Tea or coffee?</legend>', $html);
        self::assertStringContainsString('Choose one option.', $html);
        self::assertStringContainsString('role="radiogroup" data-slot="radio-group"', $html);
        self::assertStringContainsString('<input type="radio" data-slot="radio-group-item" name="choice" value="tea" id="phil-poll-'.$poll->id.'-tea">', $html);
        self::assertStringContainsString('>Coffee &amp; milk</label>', $html);
        self::assertStringContainsString('name="choice" value="" id="phil-poll-'.$poll->id.'--blank"', $html);
        self::assertStringContainsString('Blank ballot', $html);
        self::assertStringContainsString('class="isPrimary">Vote</button>', $html);
        self::assertStringNotContainsString(' disabled', $html);
    }

    public function testAVoterSeesTheirRankedBallotAndChangesIt(): void
    {
        $poll = $this->polls->create(new PollDraft('Order?', [PollOption::of('a', 'A'), PollOption::of('b', 'B'), PollOption::of('c', 'C')], BallotKind::Ranked, maxChoices: 2), ResourceReference::of('user', 'ada'));
        $this->polls->cast($poll->id, ResourceReference::of('user', 'bob'), ['c', 'a']);
        $html = $this->draw("{{ include('@PhilPoll/ballot.html.twig', {ballot: phil_poll_ballot(poll, viewer), action: '/vote'}) }}", $poll->id, ['viewer' => ResourceReference::of('user', 'bob')]);

        self::assertStringNotContainsString('_token', $html);
        self::assertStringContainsString('Rank 1 to 2 options, 1 being your first choice.', $html);
        self::assertStringContainsString('<select data-slot="native-select" data-size="sm" class="isSmall" name="ranks[c]"', $html);
        self::assertMatchesRegularExpression('/name="ranks\[c\]".*?value="1" selected>Rank 1</s', $html);
        self::assertMatchesRegularExpression('/name="ranks\[a\]".*?value="2" selected>Rank 2</s', $html);
        self::assertStringContainsString('<option data-slot="native-select-option" value="">Not ranked</option>', $html);
        self::assertStringNotContainsString('value="3"', $html);
        self::assertStringContainsString('Your ballot is recorded. You may change it while the poll is open.', $html);
        self::assertStringContainsString('>Change my ballot</button>', $html);
        self::assertSame(['c', 'a'], BallotForm::choices($poll->rules(), ['ranks' => ['a' => '2', 'b' => '', 'c' => '1']]));
    }

    public function testAMultipleBallotSignedOutIsDisabledAndInFrench(): void
    {
        $poll = $this->polls->create(new PollDraft('Lesquels ?', [PollOption::of('a', 'A'), PollOption::of('b', 'B'), PollOption::of('c', 'C')], BallotKind::Multiple, maxChoices: 2, anonymous: true), ResourceReference::of('user', 'ada'));
        $locale = self::getContainer()->get('translation.locale_switcher');
        self::assertInstanceOf(LocaleSwitcher::class, $locale);
        $html = $locale->runWithLocale('fr', fn (): string => $this->draw("{{ include('@PhilPoll/ballot.html.twig', {ballot: phil_poll_ballot(poll), action: '/vote'}) }}", $poll->id));

        self::assertStringContainsString('<input type="checkbox" data-slot="checkbox" name="choices[]" value="b"', $html);
        self::assertStringContainsString('Choisissez de 1 à 2 options.', $html);
        self::assertStringContainsString('aria-describedby="phil-poll-'.$poll->id.'-help" disabled>', $html);
        self::assertStringContainsString('Ce sondage est anonyme', $html);
        self::assertStringNotContainsString('<button', $html);
    }

    public function testTheResultsAreBarsAndRevealedOnceClosed(): void
    {
        $poll = $this->polls->create(new PollDraft('Tea or coffee?', [PollOption::of('tea', 'Tea'), PollOption::of('coffee', 'Coffee')], abstention: true, results: ResultsVisibility::AfterClosing), ResourceReference::of('user', 'ada'));
        $this->polls->cast($poll->id, ResourceReference::of('user', 'bob'), ['tea']);
        $this->polls->cast($poll->id, ResourceReference::of('user', 'cid'), []);

        $template = "{% embed '@PhilPoll/results.html.twig' with {results: phil_poll_results(poll, viewer)} %}{% block revealed %}<p>Hurray</p>{% endblock %}{% endembed %}";

        self::assertStringContainsString('<p class="phil-poll-results phil-poll-results--hidden" role="status">The results are not shown yet.</p>', $this->draw($template, $poll->id, ['viewer' => 'user:bob']));

        $open = $this->draw($template, $poll->id, ['viewer' => 'user:ada']);
        self::assertStringContainsString('data-phil--poll-bundle--results-revealed-value="false"', $open);
        self::assertStringNotContainsString('Hurray', $open);

        $this->polls->close($poll->id, ResourceReference::of('user', 'ada'));
        $html = $this->draw($template, $poll->id, ['viewer' => 'user:bob']);

        self::assertStringContainsString('data-controller="phil--poll-bundle--results" data-phil--poll-bundle--results-poll-value="'.$poll->id.'" data-phil--poll-bundle--results-revealed-value="true" data-phil--poll-bundle--results-winners-value="[&quot;Tea&quot;]"', $html);
        self::assertStringContainsString('2 ballots. 1 of them blank.', $html);
        self::assertStringContainsString('<li class="phil-poll-results__line" data-winner="true">', $html);
        self::assertStringContainsString('<strong>Winner</strong>', $html);
        self::assertStringContainsString('<progress data-slot="progress" max="1" value="1" aria-labelledby="phil-poll-results-'.$poll->id.'-tea"></progress>', $html);
        self::assertStringContainsString('<progress data-slot="progress" max="1" value="0"', $html);
        self::assertStringContainsString('<p>Hurray</p>', $html);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function draw(string $template, string $poll, array $context = []): string
    {
        return $this->twig->createTemplate($template)->render(['poll' => $poll] + $context);
    }
}
