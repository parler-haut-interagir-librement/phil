<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Tests\Functional;

use Override;
use Phil\SurveyBundle\Application\Questionnaires;
use Phil\SurveyBundle\Domain\ValueObject\Questionnaire;
use Phil\SurveyBundle\Infrastructure\DependencyInjection\RespondentPass;
use Phil\SurveyBundle\Infrastructure\DependencyInjection\SurveyServices;
use Phil\SurveyBundle\Infrastructure\Doctrine\SurveySchemaListener;
use Phil\SurveyBundle\Infrastructure\Http\SurveyController;
use Phil\SurveyBundle\Infrastructure\Http\SurveyGuard;
use Phil\SurveyBundle\Tests\Fixtures\Samples;
use Phil\SurveyBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The bundle in an application: its tables from the ORM's schema, its templates, and its forms posted as a browser
 * without JavaScript posts them.
 */
#[CoversClass(SurveyController::class)]
#[CoversClass(SurveyGuard::class)]
#[CoversClass(SurveySchemaListener::class)]
#[CoversClass(SurveyServices::class)]
#[CoversClass(RespondentPass::class)]
final class SurveyPagesTest extends WebTestCase
{
    private KernelBrowser $client;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $kernel = $this->client->getKernel();
        $this->assertInstanceOf(TestKernel::class, $kernel);
        $kernel->resetDatabase();
    }

    public function testTheSchemaOfTheOrmHoldsTheTables(): void
    {
        $kernel = $this->client->getKernel();
        $this->assertInstanceOf(TestKernel::class, $kernel);

        self::assertTrue($kernel->connection()->createSchemaManager()->tablesExist(['phil_survey_questionnaires', 'phil_survey_responses', 'phil_survey_addenda', 'phil_survey_quota_counts', 'phil_survey_participations']));
    }

    public function testTheQuestionnaireSaysItsAnonymityBeforeItsFirstQuestion(): void
    {
        $this->save(Samples::questionnaire(['pacing' => 'question']));
        $crawler = $this->client->request('GET', '/survey/games');

        self::assertResponseIsSuccessful();
        self::assertSame('Games', $crawler->filter('h1')->text());
        self::assertStringContainsString('Your answers are anonymous.', $crawler->filter('.phil-survey__anonymity')->text());
        self::assertSame('question', $crawler->filter('form')->attr('data-phil--survey-bundle--progress-pacing-value'));
        self::assertSame('phil--form-logic--logic phil--survey-bundle--progress', $crawler->filter('form')->attr('data-controller'));
        self::assertStringContainsString('"field":"fun"', (string) $crawler->filter('form')->attr('data-phil--form-logic--logic-steps-value'));
        self::assertCount(14, $crawler->filter('[data-form-logic-field]'));
        self::assertCount(3, $crawler->filter('fieldset.phil-survey__section'));
        self::assertCount(2, $crawler->filter('input[name="answers[age]"][type=radio]'));
        self::assertCount(3, $crawler->filter('input[name="answers[genres][]"][type=checkbox]'));
        self::assertCount(3, $crawler->filter('select[name^="answers[order]"]'));
        self::assertCount(4, $crawler->filter('input[name^="answers[grid]"]'));
        self::assertCount(11, $crawler->filter('input[name="answers[recommend]"]'));
        self::assertSame('email', $crawler->filter('#phil-survey-email-control')->attr('type'));
        self::assertSame('10', $crawler->filter('#phil-survey-nickname-control')->attr('maxlength'));
        self::assertSame('1', $crawler->filter('#phil-survey-players-control')->attr('min'));
        self::assertNull($crawler->filter('#phil-survey-email-control')->attr('aria-describedby'));
        self::assertCount(0, $crawler->filter('[role=alert]'));
    }

    public function testAnUnknownQuestionnaireIsNotFound(): void
    {
        $this->client->request('GET', '/survey/nothing');

        self::assertResponseStatusCodeSame(404);
    }

    public function testARespondentAnswersWithoutJavaScriptAndReachesTheEndingTheLogicChose(): void
    {
        $this->save(Samples::questionnaire());
        $crawler = $this->client->request('GET', '/survey/games');
        $form = $crawler->filter('form')->form();
        $form->setValues(['answers[age]' => 'adult']);
        $form->setValues(['answers[fun]' => '5']);
        $form->setValues(['answers[rules]' => '4']);

        $crawler = $this->client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertSame('A fan', $crawler->filter('h1')->text());
        self::assertSame('Your profile: Enthusiast', $crawler->filter('.phil-survey__profile')->text());
        self::assertCount(0, $crawler->filter('.phil-survey__results'));
        self::assertCount(0, $crawler->filter('.phil-survey__addendum'));
    }

    public function testTheErrorsAreListedAndTheAnswersKept(): void
    {
        $this->save(Samples::questionnaire());
        $crawler = $this->client->request('GET', '/survey/games');
        $form = $crawler->filter('form')->form();
        $form->setValues(['answers[age]' => 'adult']);
        $form->setValues(['answers[email]' => 'not an email']);
        $form->setValues(['answers[nickname]' => 'kept']);

        $crawler = $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('2 answers need your attention.', $crawler->filter('.phil-survey__errors p')->text());
        self::assertSame(['#phil-survey-email', '#phil-survey-fun'], $crawler->filter('.phil-survey__errors a')->each(static fn (Crawler $link): ?string => $link->attr('href')));
        self::assertSame('This is not an email address.', $crawler->filter('#phil-survey-email-error')->text());
        self::assertSame('true', $crawler->filter('#phil-survey-email-control')->attr('aria-invalid'));
        self::assertSame('phil-survey-email-error', $crawler->filter('#phil-survey-email-control')->attr('aria-describedby'));
        self::assertSame('kept', $crawler->filter('#phil-survey-nickname-control')->attr('value'));
        self::assertCount(1, $crawler->filter('input[name="answers[age]"][value=adult][checked]'));
    }

    public function testAFormFromElsewhereIsRefused(): void
    {
        $this->save(Questionnaire::fromArray(Samples::colour()));
        $this->client->request('POST', '/survey/colour', ['_token' => 'forged', 'answers' => ['colour' => 'red']], [], ['HTTP_ORIGIN' => 'https://evil.example']);

        self::assertResponseStatusCodeSame(403);
        self::assertStringContainsString('The form expired', (string) $this->client->getResponse()->getContent());
    }

    public function testAnEndingRedirectsToAnAllowedOrigin(): void
    {
        $this->save(Questionnaire::fromArray(Samples::colour(['kind' => 'routing', 'endings' => [
            ['name' => 'red', 'title' => 'Red', 'redirect' => 'https://example.org/book/red', 'when' => ['==' => [['var' => 'colour'], 'red']]],
            ['name' => 'other', 'title' => 'Other'],
        ]])));
        $form = $this->client->request('GET', '/survey/colour')->filter('form')->form();
        $form->setValues(['answers[colour]' => 'red']);

        $this->client->submit($form);

        self::assertResponseRedirects('https://example.org/book/red', 303);
    }

    public function testTheResultsAreShownAfterAnsweringWhenTheQuestionnaireShowsThem(): void
    {
        $this->save(Questionnaire::fromArray(Samples::colour(['results' => ['public' => true, 'minimum' => 2]])));

        $crawler = $this->answerColour('red');
        self::assertStringContainsString('The results are shown from 2 answers', $crawler->filter('.phil-survey__results')->text());

        $crawler = $this->answerColour('blue');
        self::assertSame('2 responses.', $crawler->filter('.phil-survey__results > p')->text());
        self::assertSame(['Red', 'Blue'], $crawler->filter('.phil-survey__counts th')->each(static fn (Crawler $cell): string => $cell->text()));
    }

    public function testARespondentAddsToTheirResponse(): void
    {
        $this->save(Questionnaire::fromArray(Samples::colour(['addenda' => true])));
        $crawler = $this->answerColour('red');
        $form = $crawler->filter('.phil-survey__addendum')->form();
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $crawler->filter('[name=receipt]')->attr('value'));
        $form->setValues(['text' => 'One more thing.']);
        $crawler = $this->client->submit($form);

        self::assertResponseIsSuccessful();
        self::assertSame('Your addition is kept with your response.', $crawler->filter('p')->text());

        $form->setValues(['receipt' => str_repeat('0', 64)]);
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(409);
    }

    public function testTheRefusalsAreSaidBeforeTheFirstQuestion(): void
    {
        $this->save(Questionnaire::fromArray(Samples::colour(['requires_member' => true])));
        $this->save(Questionnaire::fromArray(Samples::colour(['key' => 'talk', 'kind' => 'session_feedback'])));
        $this->save(Questionnaire::fromArray(Samples::colour(['key' => 'later', 'window' => ['opens_at' => '2026-12-01T00:00:00+00:00']])));

        self::assertSame('Sign in to answer this questionnaire.', $this->client->request('GET', '/survey/colour')->filter('.phil-survey__refused')->text());
        self::assertSame('This questionnaire is answered from the page of what it is about.', $this->client->request('GET', '/survey/talk')->filter('.phil-survey__refused')->text());
        self::assertSame('This questionnaire is not open now.', $this->client->request('GET', '/survey/talk?subject=session:1')->filter('.phil-survey__refused')->text());
        self::assertSame('This questionnaire is not open now.', $this->client->request('GET', '/survey/later')->filter('.phil-survey__refused')->text());

        $this->client->setServerParameter('HTTP_X_MEMBER', 'member:ann');
        $this->answerColour('red');
        $crawler = $this->client->request('GET', '/survey/colour');
        self::assertSame('You answered this questionnaire already.', $crawler->filter('.phil-survey__refused')->text());
        self::assertCount(0, $crawler->filter('form'));

        $this->client->request('POST', '/survey/colour', ['_token' => $this->token(), 'answers' => ['colour' => 'red']]);
        self::assertResponseStatusCodeSame(409);
    }

    private function answerColour(string $colour): Crawler
    {
        $form = $this->client->request('GET', '/survey/colour')->filter('form')->form();
        $form->setValues(['answers[colour]' => $colour]);

        return $this->client->submit($form);
    }

    private function token(): string
    {
        $guard = self::getContainer()->get(SurveyGuard::class);
        $this->assertInstanceOf(SurveyGuard::class, $guard);

        return $guard->token();
    }

    private function save(Questionnaire $questionnaire): void
    {
        $questionnaires = self::getContainer()->get(Questionnaires::class);
        $this->assertInstanceOf(Questionnaires::class, $questionnaires);
        $questionnaires->save($questionnaire);
    }
}
