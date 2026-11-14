<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\OnboardingBundle\Application\Onboarding;
use Phil\OnboardingBundle\Domain\Model\Progress;
use Phil\OnboardingBundle\Infrastructure\Settings\SettingsTourPreference;
use Phil\OnboardingBundle\PhilOnboardingBundle;
use Phil\OnboardingBundle\Tests\Functional\App\AvatarChecker;
use Phil\OnboardingBundle\Tests\Functional\App\PageWritten;
use Phil\OnboardingBundle\Tests\Functional\App\RecordedGrants;
use Phil\OnboardingBundle\Tests\Functional\App\TestKernel;
use Phil\SettingsBundle\Application\Settings;
use Phil\SettingsBundle\Domain\ValueObject\Subject;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * The bundle in an application: the checklist and the tour of a member on a page, a step ticked by its form with the
 * CSRF token, a step done by the application's event and a step done by its checker, the progress kept with Doctrine,
 * and the tours turned off in the member's preferences.
 */
#[CoversClass(PhilOnboardingBundle::class)]
#[CoversClass(SettingsTourPreference::class)]
final class OnboardingBundleTest extends WebTestCase
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

        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema([$entityManager->getClassMetadata(Progress::class)]);
    }

    public function testAMemberGoesThroughTheFlow(): void
    {
        $crawler = $this->client->request('GET', '/page', server: ['HTTP_X_USER' => 'ada']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('section.phil-onboarding-checklist.sidebar h2', 'Welcome, author');
        self::assertSelectorTextSame('.phil-onboarding-checklist__summary', '0 of 3 steps done');
        self::assertSame(['profile', 'avatar', 'first-page'], $crawler->filter('template')->each(static fn (Crawler $step): ?string => $step->attr('data-id')));
        self::assertSame('false', $crawler->filter('[data-controller="phil--onboarding-bundle--tour"]')->attr('data-phil--onboarding-bundle--tour-autostart-value'));
        self::assertSame('/page', $crawler->filter('li a')->attr('href'));

        $form = $crawler->filter('form[action="/onboarding/author/profile/done"]')->form();
        $this->client->submit($form, [], ['HTTP_X_USER' => 'ada', 'HTTP_REFERER' => 'http://localhost/page', 'HTTP_ORIGIN' => 'http://localhost']);
        self::assertResponseRedirects('http://localhost/page', 303);

        $events = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $events);
        $events->dispatch(new PageWritten('ada'));

        $checker = self::getContainer()->get(AvatarChecker::class);
        self::assertInstanceOf(AvatarChecker::class, $checker);
        $checker->members = ['ada'];

        $this->client->request('GET', '/page', server: ['HTTP_X_USER' => 'ada']);
        self::assertSelectorTextSame('.phil-onboarding-checklist__summary', 'Every step is done.');
        self::assertSelectorNotExists('.phil-onboarding-tour');

        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->clear();
        self::assertNotNull($entityManager->find(Progress::class, ['user' => 'ada', 'flow' => 'author'])?->finishedAt());
    }

    public function testTheButtonsAreTheRecipeOfTheKit(): void
    {
        $crawler = $this->client->request('GET', '/page', server: ['HTTP_X_USER' => 'ada']);

        $done = $crawler->filter('form[action="/onboarding/author/profile/done"] button');
        self::assertSame(['submit', 'secondary', 'sm'], [$done->attr('type'), $done->attr('data-variant'), $done->attr('data-size')]);
        self::assertStringContainsString('isSecondary', (string) $done->attr('class'));
        self::assertSame('Mark as done: Fill in your profile', $done->text());
        self::assertCount(0, $crawler->filter('.phil-button'));

        $tour = $crawler->filter('.phil-onboarding-tour');
        $next = $tour->filter('[data-phil--onboarding-bundle--tour-target="next"]');
        self::assertSame(['button', 'phil--onboarding-bundle--tour#next', 'default'], [$next->attr('type'), $next->attr('data-action'), $next->attr('data-variant')]);
        $end = $tour->filter('.phil-onboarding-tour__end');
        self::assertSame(['End the tour', 'icon-sm'], [$end->attr('aria-label'), $end->attr('data-size')]);
    }

    public function testAMemberAnswersAQuestion(): void
    {
        $crawler = $this->client->request('GET', '/page', server: ['HTTP_X_USER' => 'ada']);

        $form = $crawler->filter('form[action="/onboarding/member/topics/answer"]');
        self::assertSame('Which topics interest you?', $form->filter('legend')->text());
        self::assertSame(['news', 'events'], $form->filter('input[name="choices[]"]')->each(static fn (Crawler $input): ?string => $input->attr('value')));
        self::assertSame(['checkbox', 'checkbox'], $form->filter('input[name="choices[]"]')->each(static fn (Crawler $input): ?string => $input->attr('type')));

        $this->client->request('POST', '/onboarding/member/topics/answer', ['_token' => 'csrf-token', 'choices' => ['events', 'news']], server: ['HTTP_X_USER' => 'ada', 'HTTP_REFERER' => 'http://localhost/page', 'HTTP_ORIGIN' => 'http://localhost']);
        self::assertResponseRedirects('http://localhost/page', 303);

        $grants = self::getContainer()->get(RecordedGrants::class);
        self::assertInstanceOf(RecordedGrants::class, $grants);
        [$answer] = $grants->answers;
        self::assertSame(['ada', 'member', 'topics', ['news', 'events'], ['ROLE_EVENTS'], ['newsletter.news', 'newsletter.events']], [$answer->user, $answer->flow, $answer->step, $answer->choices, $answer->roles, $answer->subscriptions]);

        $this->client->request('POST', '/onboarding/member/topics/answer', ['_token' => 'csrf-token', 'choices' => ['news']], server: ['HTTP_X_USER' => 'ada', 'HTTP_ORIGIN' => 'http://localhost']);
        self::assertResponseStatusCodeSame(409);
        self::assertCount(1, $grants->answers);

        $this->client->request('GET', '/page', server: ['HTTP_X_USER' => 'ada']);
        self::assertSelectorNotExists('form[action="/onboarding/member/topics/answer"]');
    }

    public function testAnAnswerTheQuestionCannotTake(): void
    {
        $this->client->request('POST', '/onboarding/member/topics/answer', ['_token' => 'csrf-token', 'choices' => ['sports']], server: ['HTTP_X_USER' => 'ada', 'HTTP_ORIGIN' => 'http://localhost']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('{"error":"invalid_answer"}', $this->client->getResponse()->getContent());

        $this->client->request('POST', '/onboarding/member/topics/answer', ['_token' => 'csrf-token', 'choices' => 'news'], server: ['HTTP_X_USER' => 'ada', 'HTTP_ORIGIN' => 'http://localhost']);
        self::assertResponseStatusCodeSame(422);

        $this->client->request('POST', '/onboarding/author/profile/answer', ['_token' => 'csrf-token', 'choices' => ['news']], server: ['HTTP_X_USER' => 'ada', 'HTTP_ORIGIN' => 'http://localhost']);
        self::assertResponseStatusCodeSame(409);
    }

    public function testTheTourSendsItsChangesAsJson(): void
    {
        $this->client->request('POST', '/onboarding/author/profile/skip', server: [
            'HTTP_X_USER' => 'ada',
            'HTTP_ORIGIN' => 'http://localhost',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PHIL_ONBOARDING_TOKEN' => 'csrf-token',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame('{"flow":"author","done":1,"total":3,"current":"avatar","finished":false,"dismissed":false}', $this->client->getResponse()->getContent());

        $this->client->request('POST', '/onboarding/author/dismiss', server: ['HTTP_X_USER' => 'ada', 'HTTP_ORIGIN' => 'https://evil.test', 'HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_X_PHIL_ONBOARDING_TOKEN' => 'csrf-token']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testAVisitorSeesNothing(): void
    {
        $this->client->request('GET', '/page');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.phil-onboarding-checklist');
        self::assertSelectorNotExists('.phil-onboarding-tour');
    }

    public function testAMemberTurnsTheToursOff(): void
    {
        $settings = self::getContainer()->get(Settings::class);
        self::assertInstanceOf(Settings::class, $settings);
        $settings->save(Subject::user('ada'), ['onboarding.tours' => false]);

        $this->client->request('GET', '/page', server: ['HTTP_X_USER' => 'ada']);

        self::assertSelectorExists('.phil-onboarding-checklist');
        self::assertSelectorNotExists('.phil-onboarding-tour');
        self::assertInstanceOf(Onboarding::class, self::getContainer()->get(Onboarding::class));
    }
}
