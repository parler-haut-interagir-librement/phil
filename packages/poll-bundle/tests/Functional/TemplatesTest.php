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
use Symfony\Component\DomCrawler\Crawler;
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

    public function testASingleBallotIsAGroupOfRadioButtonsDrawnByTheRecipes(): void
    {
        $poll = $this->polls->create(new PollDraft('Tea or coffee?', [PollOption::of('tea', 'Tea'), PollOption::of('coffee', 'Coffee & milk')], abstention: true), ResourceReference::of('user', 'ada'));
        $html = $this->draw("{{ include('@PhilPoll/ballot.html.twig', {ballot: phil_poll_ballot(poll, 'user:bob'), action: '/vote', csrf_token: 'tok'}) }}", $poll->id);
        $page = new Crawler($html);
        $id = 'phil-poll-'.$poll->id;

        $form = $page->filter('form.phil-poll-ballot');
        self::assertSame('post', $form->attr('method'));
        self::assertSame('/vote', $form->attr('action'));
        self::assertSame($poll->id, $form->attr('data-phil-poll'));
        self::assertSame('tok', $page->filter('input[type="hidden"][name="_token"]')->attr('value'));

        $fieldset = $page->filter('fieldset[data-slot="field-set"].isFieldSet');
        self::assertSame($id.'-help', $fieldset->attr('aria-describedby'));
        self::assertNull($fieldset->attr('disabled'));
        self::assertSame('Tea or coffee?', $fieldset->filter('legend[data-slot="field-legend"][data-variant="legend"].isFieldLegend')->text());
        self::assertSame('Choose one option.', $page->filter('#'.$id.'-help[data-slot="field-description"].isFieldDescription')->text());

        $group = $page->filter('[role="radiogroup"][data-slot="radio-group"].isRadioGroup');
        self::assertSame($id.'-question', $group->attr('aria-labelledby'));
        self::assertSame('Tea or coffee?', $group->filter('#'.$id.'-question')->text());
        self::assertCount(3, $group->filter('[role="group"][data-slot="field"][data-orientation="horizontal"].isField.isHorizontal'));
        $radios = $group->filter('input[type="radio"][data-slot="radio-group-item"][name="choice"]');
        self::assertSame(['tea', 'coffee', ''], $radios->each(static fn (Crawler $radio): ?string => $radio->attr('value')));
        self::assertSame([$id.'-tea', $id.'-coffee', $id.'--blank'], $radios->each(static fn (Crawler $radio): ?string => $radio->attr('id')));
        self::assertSame([null, null, null], $radios->each(static fn (Crawler $radio): ?string => $radio->attr('checked')));
        self::assertSame([null, null, null], $radios->each(static fn (Crawler $radio): ?string => $radio->attr('required')), 'A poll accepting a blank ballot requires no choice.');
        self::assertSame('Coffee & milk', $page->filter('label[data-slot="field-label"].isFieldLabel[for="'.$id.'-coffee"]')->text());
        self::assertStringContainsString('>Coffee &amp; milk</label>', $html);
        self::assertSame('Blank ballot', $page->filter('label[for="'.$id.'--blank"]')->text());

        $button = $page->filter('button[type="submit"][data-slot="button"][data-variant="default"].isPrimary');
        self::assertSame('Vote', $button->text());
        self::assertSame('', $page->filter('p.phil-poll-ballot__status[role="status"]')->text());
    }

    public function testASingleBallotWithoutBlankRequiresAChoiceAndChecksTheVotersOwn(): void
    {
        $poll = $this->polls->create(new PollDraft('Tea or coffee?', [PollOption::of('tea', 'Tea'), PollOption::of('coffee', 'Coffee')]), ResourceReference::of('user', 'ada'));
        $this->polls->cast($poll->id, ResourceReference::of('user', 'bob'), ['coffee']);
        $page = new Crawler($this->draw("{{ include('@PhilPoll/ballot.html.twig', {ballot: phil_poll_ballot(poll, 'user:bob'), action: '/vote'}) }}", $poll->id));
        $radios = $page->filter('input[type="radio"][name="choice"]');

        self::assertSame(['tea', 'coffee'], $radios->each(static fn (Crawler $radio): ?string => $radio->attr('value')));
        self::assertSame([null, 'checked'], $radios->each(static fn (Crawler $radio): ?string => null === $radio->attr('checked') ? null : 'checked'));
        self::assertSame(['required', 'required'], $radios->each(static fn (Crawler $radio): ?string => null === $radio->attr('required') ? null : 'required'));
        self::assertCount(0, $page->filter('input[type="hidden"][name="_token"]'));
    }

    public function testAVoterSeesTheirRankedBallotAndChangesIt(): void
    {
        $poll = $this->polls->create(new PollDraft('Order?', [PollOption::of('a', 'A'), PollOption::of('b', 'B'), PollOption::of('c', 'C')], BallotKind::Ranked, maxChoices: 2), ResourceReference::of('user', 'ada'));
        $this->polls->cast($poll->id, ResourceReference::of('user', 'bob'), ['c', 'a']);
        $html = $this->draw("{{ include('@PhilPoll/ballot.html.twig', {ballot: phil_poll_ballot(poll, viewer), action: '/vote'}) }}", $poll->id, ['viewer' => ResourceReference::of('user', 'bob')]);
        $page = new Crawler($html);
        $id = 'phil-poll-'.$poll->id;

        self::assertStringNotContainsString('_token', $html);
        self::assertSame('Rank 1 to 2 options, 1 being your first choice.', $page->filter('#'.$id.'-help')->text());
        $select = $page->filter('select[data-slot="native-select"][data-size="sm"].isSmall[name="ranks[c]"]');
        self::assertSame($id.'-c', $select->attr('id'));
        self::assertSame('C', $page->filter('label[for="'.$id.'-c"]')->text());
        self::assertSame(['', '1', '2'], $select->filter('option[data-slot="native-select-option"]')->each(static fn (Crawler $option): ?string => $option->attr('value')));
        self::assertSame(['Not ranked', 'Rank 1', 'Rank 2'], $select->filter('option')->each(static fn (Crawler $option): string => $option->text()));
        self::assertSame('1', $select->filter('option[selected]')->attr('value'));
        self::assertSame('2', $page->filter('select[name="ranks[a]"] option[selected]')->attr('value'));
        self::assertCount(0, $page->filter('select[name="ranks[b]"] option[selected]'));
        self::assertCount(0, $page->filter('option[value="3"]'));
        self::assertSame('Your ballot is recorded. You may change it while the poll is open.', $page->filter('p.phil-poll-ballot__status')->text());
        self::assertSame('Change my ballot', $page->filter('button[type="submit"]')->text());
        self::assertSame(['c', 'a'], BallotForm::choices($poll->rules(), ['ranks' => ['a' => '2', 'b' => '', 'c' => '1']]));
    }

    public function testAMultipleBallotSignedOutIsDisabledAndInFrench(): void
    {
        $poll = $this->polls->create(new PollDraft('Lesquels ?', [PollOption::of('a', 'A'), PollOption::of('b', 'B'), PollOption::of('c', 'C')], BallotKind::Multiple, maxChoices: 2, anonymous: true), ResourceReference::of('user', 'ada'));
        $locale = self::getContainer()->get('translation.locale_switcher');
        self::assertInstanceOf(LocaleSwitcher::class, $locale);
        $html = $locale->runWithLocale('fr', fn (): string => $this->draw("{{ include('@PhilPoll/ballot.html.twig', {ballot: phil_poll_ballot(poll), action: '/vote'}) }}", $poll->id));
        $page = new Crawler($html);
        $id = 'phil-poll-'.$poll->id;

        $checkboxes = $page->filter('[data-slot="field"].isHorizontal input[type="checkbox"][data-slot="checkbox"][name="choices[]"]');
        self::assertSame(['a', 'b', 'c'], $checkboxes->each(static fn (Crawler $checkbox): ?string => $checkbox->attr('value')));
        self::assertSame($id.'-b', $checkboxes->eq(1)->attr('id'));
        self::assertCount(0, $page->filter('input[checked]'));
        self::assertSame('Choisissez de 1 à 2 options.', $page->filter('#'.$id.'-help')->text());
        self::assertNotNull($page->filter('fieldset')->attr('disabled'));
        self::assertStringContainsString('Ce sondage est anonyme', $page->filter('p.phil-poll-ballot__status')->text());
        self::assertStringNotContainsString('<button', $html);
    }

    public function testAnEmbeddingTemplateReplacesTheBlocksOfTheBallot(): void
    {
        $poll = $this->polls->create(new PollDraft('Tea or coffee?', [PollOption::of('tea', 'Tea'), PollOption::of('coffee', 'Coffee')]), ResourceReference::of('user', 'ada'));
        $template = "{% embed '@PhilPoll/ballot.html.twig' with {ballot: phil_poll_ballot(poll, 'user:bob'), action: '/vote'} %}"
            ."{% block choices %}<p class=\"mine\">{{ ballot.choices|length }}</p>{{ component(kit.Checkbox, {name: 'agree'}) }}{% endblock %}"
            .'{% block status %}<p class="status">Mine</p>{% endblock %}'
            .'{% block actions %}<button class="send">Send</button>{% endblock %}'
            .'{% endembed %}';
        $page = new Crawler($this->draw($template, $poll->id));

        self::assertSame('2', $page->filter('fieldset > p.mine')->text());
        self::assertSame('checkbox', $page->filter('fieldset input[name="agree"][data-slot="checkbox"]')->attr('type'));
        self::assertCount(0, $page->filter('[role="radiogroup"]'));
        self::assertSame('Tea or coffee?', $page->filter('fieldset legend')->text());
        self::assertSame('Mine', $page->filter('form > p.status')->text());
        self::assertSame('Send', $page->filter('form > button.send')->text());
        self::assertCount(0, $page->filter('[data-slot="button"]'));
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
        $page = new Crawler($html);
        $id = 'phil-poll-results-'.$poll->id;

        self::assertStringContainsString('data-controller="phil--poll-bundle--results" data-phil--poll-bundle--results-poll-value="'.$poll->id.'" data-phil--poll-bundle--results-revealed-value="true" data-phil--poll-bundle--results-winners-value="[&quot;Tea&quot;]"', $html);
        self::assertStringContainsString('2 ballots. 1 of them blank.', $html);
        self::assertSame('true', $page->filter('li.phil-poll-results__line')->first()->attr('data-winner'));
        self::assertSame('Winner', $page->filter('li.phil-poll-results__line strong')->text());
        $bars = $page->filter('li.phil-poll-results__line progress[data-slot="progress"]');
        self::assertSame(['100', '100'], $bars->each(static fn (Crawler $bar): ?string => $bar->attr('max')));
        self::assertSame(['100', '0'], $bars->each(static fn (Crawler $bar): ?string => $bar->attr('value')));
        self::assertSame(['1', '0'], $bars->each(static fn (Crawler $bar): ?string => $bar->attr('aria-valuetext')));
        self::assertSame([$id.'-tea', $id.'-coffee'], $bars->each(static fn (Crawler $bar): ?string => $bar->attr('aria-labelledby')));
        self::assertSame('Tea', $page->filter('#'.$id.'-tea')->text());
        self::assertStringContainsString('<p>Hurray</p>', $html);
    }

    public function testAScaleIsARowOfMarks(): void
    {
        $poll = $this->polls->create(new PollDraft('How was it?', PollDraft::marks(1, 5, [1 => 'Poor', 5 => 'Great']), BallotKind::Scale, abstention: true), ResourceReference::of('user', 'ada'));
        $this->polls->cast($poll->id, ResourceReference::of('user', 'bob'), ['4']);
        $page = new Crawler($this->draw("{{ include('@PhilPoll/ballot.html.twig', {ballot: phil_poll_ballot(poll, 'user:bob'), action: '/vote'}) }}", $poll->id));
        $id = 'phil-poll-'.$poll->id;

        self::assertSame('Choose one mark from 1 to 5.', $page->filter('#'.$id.'-help')->text());
        $group = $page->filter('[role="radiogroup"][data-orientation="horizontal"].isRadioGroup.isHorizontal');
        $radios = $group->filter('input[type="radio"][name="choice"]');
        self::assertSame(['1', '2', '3', '4', '5', ''], $radios->each(static fn (Crawler $radio): ?string => $radio->attr('value')));
        self::assertSame(['Poor', '2', '3', '4', 'Great', 'Blank ballot'], $group->filter('label')->each(static fn (Crawler $label): string => $label->text()));
        self::assertSame(['4'], $page->filter('input[checked]')->each(static fn (Crawler $radio): ?string => $radio->attr('value')));
        self::assertSame(['4'], BallotForm::choices($poll->rules(), ['choice' => '4']));
    }

    public function testABlankBallotIsCheckedWhenItIsTheVotersOwn(): void
    {
        $poll = $this->polls->create(new PollDraft('Tea or coffee?', [PollOption::of('tea', 'Tea'), PollOption::of('coffee', 'Coffee')], abstention: true), ResourceReference::of('user', 'ada'));
        $this->polls->cast($poll->id, ResourceReference::of('user', 'bob'), []);
        $page = new Crawler($this->draw("{{ include('@PhilPoll/ballot.html.twig', {ballot: phil_poll_ballot(poll, 'user:bob'), action: '/vote'}) }}", $poll->id));

        self::assertSame([''], $page->filter('input[checked]')->each(static fn (Crawler $radio): ?string => $radio->attr('value')));
        self::assertCount(0, new Crawler($this->draw("{{ include('@PhilPoll/ballot.html.twig', {ballot: phil_poll_ballot(poll, 'user:cid'), action: '/vote'}) }}", $poll->id))->filter('input[checked]'));
    }

    public function testTheErrorsOfABallotRefusedAreAnnounced(): void
    {
        $poll = $this->polls->create(new PollDraft('Which?', [PollOption::of('a', 'A'), PollOption::of('b', 'B'), PollOption::of('c', 'C')], BallotKind::Multiple, maxChoices: 2), ResourceReference::of('user', 'ada'));
        $id = 'phil-poll-'.$poll->id;
        $page = new Crawler($this->draw("{{ include('@PhilPoll/ballot.html.twig', {ballot: phil_poll_ballot(poll, 'user:bob'), action: '/vote', errors: ['Choose at most 2 options.']}) }}", $poll->id));

        $fieldset = $page->filter('fieldset');
        self::assertSame($id.'-help '.$id.'-error', $fieldset->attr('aria-describedby'));
        self::assertSame('true', $fieldset->attr('aria-invalid'));
        self::assertSame('Choose at most 2 options.', $fieldset->filter('#'.$id.'-error[role="alert"][data-slot="field-error"]')->text());

        $calm = new Crawler($this->draw("{{ include('@PhilPoll/ballot.html.twig', {ballot: phil_poll_ballot(poll, 'user:bob'), action: '/vote'}) }}", $poll->id));
        self::assertSame($id.'-help', $calm->filter('fieldset')->attr('aria-describedby'));
        self::assertNull($calm->filter('fieldset')->attr('aria-invalid'));
        self::assertCount(0, $calm->filter('[role="alert"]'));
    }

    public function testTheResultsOfAScaleAreItsDistributionAndItsMean(): void
    {
        $poll = $this->polls->create(new PollDraft('How was it?', PollDraft::marks(1, 3), BallotKind::Scale), ResourceReference::of('user', 'ada'));
        $this->polls->cast($poll->id, ResourceReference::of('user', 'bob'), ['3']);
        $this->polls->cast($poll->id, ResourceReference::of('user', 'cid'), ['3']);
        $this->polls->cast($poll->id, ResourceReference::of('user', 'dee'), ['2']);

        $page = new Crawler($this->draw("{{ include('@PhilPoll/results.html.twig', {results: phil_poll_results(poll, 'user:bob')}) }}", $poll->id));

        self::assertSame(['1', '2', '3'], $page->filter('li.phil-poll-results__line > span:first-child')->each(static fn (Crawler $label): string => $label->text()));
        self::assertSame(['0', '1', '2'], $page->filter('.phil-poll-results__score')->each(static fn (Crawler $score): string => $score->text()));
        self::assertSame(['0', '50', '100'], $page->filter('progress')->each(static fn (Crawler $bar): ?string => $bar->attr('value')));
        self::assertCount(0, $page->filter('[data-winner]'));
        self::assertSame('Mean mark: 2.67, from 3 marks.', $page->filter('p.phil-poll-results__mean')->text());
        self::assertCount(0, $page->filter('.phil-poll-results__nps'));
    }

    public function testTheResultsOfANetPromoterScoreInFrench(): void
    {
        $poll = $this->polls->create(new PollDraft('Le recommanderiez-vous ?', PollDraft::marks(0, 10), BallotKind::Scale, counting: 'nps'), ResourceReference::of('user', 'ada'));

        foreach (['bob' => '10', 'cid' => '9', 'dee' => '8', 'eve' => '3'] as $voter => $mark) {
            $this->polls->cast($poll->id, ResourceReference::of('user', $voter), [$mark]);
        }

        $locale = self::getContainer()->get('translation.locale_switcher');
        self::assertInstanceOf(LocaleSwitcher::class, $locale);
        $page = new Crawler($locale->runWithLocale('fr', fn (): string => $this->draw("{{ include('@PhilPoll/results.html.twig', {results: phil_poll_results(poll, 'user:bob')}) }}", $poll->id)));

        self::assertSame('Note moyenne : 7,50, sur 4 notes.', $page->filter('p.phil-poll-results__mean')->text());
        self::assertSame(['Net Promoter Score', 'Promoteurs (9–10)', 'Passifs (7–8)', 'Détracteurs (0–6)'], $page->filter('.phil-poll-results__nps dt')->each(static fn (Crawler $term): string => $term->text()));
        self::assertSame(['25', '2', '1', '1'], $page->filter('.phil-poll-results__nps dd')->each(static fn (Crawler $value): string => $value->text()));
        self::assertCount(11, $page->filter('li.phil-poll-results__line'));
    }

    public function testNoMeanIsWrittenWhileEveryBallotIsBlank(): void
    {
        $poll = $this->polls->create(new PollDraft('Le recommanderiez-vous ?', PollDraft::marks(0, 10), BallotKind::Scale, abstention: true, counting: 'nps'), ResourceReference::of('user', 'ada'));
        $this->polls->cast($poll->id, ResourceReference::of('user', 'bob'), []);
        $page = new Crawler($this->draw("{{ include('@PhilPoll/results.html.twig', {results: phil_poll_results(poll, 'user:bob')}) }}", $poll->id));

        self::assertCount(0, $page->filter('.phil-poll-results__mean'));
        self::assertCount(0, $page->filter('.phil-poll-results__nps'));
        self::assertSame(['0'], array_unique($page->filter('progress')->each(static fn (Crawler $bar): ?string => $bar->attr('value'))));
    }

    public function testTheRecipesAreNamedAfterTheSubdirectoryOfTheToolkit(): void
    {
        self::assertSame('ui:Field:Set', $this->draw("{{ phil_poll_kit('Field:Set') }}", ''));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function draw(string $template, string $poll, array $context = []): string
    {
        return $this->twig->createTemplate($template)->render(['poll' => $poll] + $context);
    }
}
