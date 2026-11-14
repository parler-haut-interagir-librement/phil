<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\OnboardingBundle\Application\Contract\TourPreference;
use Phil\OnboardingBundle\Application\FlowCatalog;
use Phil\OnboardingBundle\Application\Onboarding;
use Phil\OnboardingBundle\Infrastructure\Http\OnboardingRequest;
use Phil\OnboardingBundle\Infrastructure\Memory\InMemoryProgressRepository;
use Phil\OnboardingBundle\Infrastructure\Twig\ChecklistComponent;
use Phil\OnboardingBundle\Infrastructure\Twig\KitComponents;
use Phil\OnboardingBundle\Infrastructure\Twig\OnboardingExtension;
use Phil\OnboardingBundle\Infrastructure\Twig\OnboardingView;
use Phil\OnboardingBundle\Infrastructure\Twig\TourComponent;
use Phil\OnboardingBundle\Tests\Unit\Fixture\ButtonStandIn;
use Phil\OnboardingBundle\Tests\Unit\Fixture\FixedUser;
use Phil\OnboardingBundle\Tests\Unit\Fixture\Kit;
use Phil\OnboardingBundle\Tests\Unit\Fixture\Tokens;
use Phil\OnboardingBundle\Tests\Unit\Fixture\Urls;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

/**
 * The checklist and the tour rendered from the bundle's templates, in English, for a user, a visitor, a dismissed
 * flow and a user who turned the tours off.
 */
#[CoversClass(OnboardingView::class)]
#[CoversClass(OnboardingExtension::class)]
#[CoversClass(ChecklistComponent::class)]
#[CoversClass(TourComponent::class)]
final class TwigTest extends TestCase
{
    private FixedUser $user;

    private Onboarding $onboarding;

    private bool $tours = true;

    private OnboardingView $view;

    private Environment $twig;

    #[Override]
    protected function setUp(): void
    {
        $catalog = FlowCatalog::fromDeclarations(['author' => ['title' => 'Welcome, author', 'description' => 'Your first steps', 'translation_domain' => 'messages'], 'member' => ['title' => 'Welcome', 'description' => null, 'translation_domain' => 'messages']], [
            Kit::step('author', 'profile', ['title' => 'Fill in your profile', 'description' => 'Who you are', 'route' => 'app_profile', 'route_parameters' => ['tab' => 'bio'], 'target' => '#profile']),
            Kit::step('author', 'publish', ['completion' => 'event', 'event' => 'app.published', 'skippable' => false]),
            Kit::step('author', 'rules', ['skippable' => false]),
            Kit::step('member', 'level', ['title' => 'Your level', 'completion' => 'question', 'question' => ['choices' => ['new' => ['label' => 'New here'], 'old' => ['label' => 'Old hand']]]]),
            Kit::step('member', 'topics', ['title' => 'Your topics', 'completion' => 'question', 'skippable' => false, 'question' => ['multiple' => true, 'choices' => ['news' => ['label' => 'News'], 'events' => ['label' => 'Events']]]]),
        ]);
        $this->user = new FixedUser('ada');
        $this->onboarding = new Onboarding($catalog, new InMemoryProgressRepository(), new ServiceLocator([]), new MockClock('2026-11-08 10:00:00 UTC'));
        $preference = new readonly class($this) implements TourPreference {
            public function __construct(
                private TwigTest $test,
            ) {
            }

            public function wantsTours(string $user): bool
            {
                return $this->test->tours();
            }
        };
        $this->view = new OnboardingView($this->onboarding, $this->user, new OnboardingRequest(new Tokens()), new Urls(), $preference);

        $translator = new Translator('en');
        $translator->addLoader('php', new PhpFileLoader());
        $translator->addResource('php', \dirname(__DIR__, 3).'/translations/phil_onboarding.en.php', 'en', 'phil_onboarding');

        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3).'/templates', 'PhilOnboarding');

        $this->twig = new Environment(new ButtonStandIn($loader), ['strict_variables' => true]);
        $this->twig->addExtension(new TranslationExtension($translator));

        $extension = new OnboardingExtension($this->view);
        $this->twig->addExtension(new AttributeExtension(OnboardingExtension::class));
        $this->twig->addRuntimeLoader(new FactoryRuntimeLoader([OnboardingExtension::class => static fn (): OnboardingExtension => $extension]));
    }

    public function tours(): bool
    {
        return $this->tours;
    }

    public function testTheKitNameFollowsTheSubdirectory(): void
    {
        self::assertSame('ui:Button', new OnboardingExtension($this->view, new KitComponents('ui'))->kit('Button'));
        self::assertSame('Button', new OnboardingExtension($this->view)->kit('Button'));
    }

    public function testTheChecklistOfAUser(): void
    {
        $this->onboarding->complete('ada', 'author', 'publish');
        $page = new Crawler($this->twig->createTemplate("{{ onboarding_checklist('author') }}")->render());

        self::assertSame('phil-onboarding-author-title', $page->filter('section')->attr('aria-labelledby'));
        self::assertSame('Welcome, author', $page->filter('h2')->text());
        self::assertSame('Your first steps', $page->filter('.phil-onboarding-checklist__description')->text());
        self::assertSame('1 of 3 steps done', $page->filter('.phil-onboarding-checklist__summary')->text());
        self::assertSame(['3', '1'], [$page->filter('progress')->attr('max'), $page->filter('progress')->attr('value')]);
        self::assertSame(['phil-onboarding-step phil-onboarding-step--pending', 'phil-onboarding-step phil-onboarding-step--completed', 'phil-onboarding-step phil-onboarding-step--pending'], $page->filter('li')->each(static fn (Crawler $item): ?string => $item->attr('class')));
        self::assertSame('step', $page->filter('li')->first()->attr('aria-current'));
        self::assertNull($page->filter('li')->eq(1)->attr('aria-current'));
        self::assertSame('/app_profile/bio', $page->filter('li a')->attr('href'));
        self::assertSame('(to do)', $page->filter('li .phil-onboarding-visually-hidden')->first()->text());
        self::assertSame('(done)', $page->filter('li')->eq(1)->filter('.phil-onboarding-visually-hidden')->text());
        self::assertSame('✓', $page->filter('li')->eq(1)->filter('.phil-onboarding-step__mark')->text());
        self::assertSame(
            ['/phil_onboarding_done/author/profile', '/phil_onboarding_skip/author/profile', '/phil_onboarding_done/author/rules', '/phil_onboarding_dismiss/author'],
            $page->filter('form')->each(static fn (Crawler $form): ?string => $form->attr('action')),
        );
        self::assertSame(['good'], array_values(array_unique($page->filter('input[name="_token"]')->each(static fn (Crawler $input): ?string => $input->attr('value')))));
        self::assertSame('Mark as done: Fill in your profile', $page->filter('button')->first()->text());
        self::assertSame('Hide this list', $page->filter('button')->last()->text());
    }

    public function testAQuestionInTheChecklistAndTheTour(): void
    {
        $page = new Crawler($this->twig->createTemplate("{{ onboarding_checklist('member') }}")->render());

        $level = $page->filter('form.phil-onboarding-question')->first();
        self::assertSame('/phil_onboarding_answer/member/level', $level->attr('action'));
        self::assertSame('good', $level->filter('input[name="_token"]')->attr('value'));
        self::assertSame('Your level', $level->filter('fieldset legend')->text());
        self::assertSame([['radio', 'new', 'phil-onboarding-member-level-new', ''], ['radio', 'old', 'phil-onboarding-member-level-old', '']], $level->filter('input[name="choices[]"]')->each(static fn (Crawler $input): array => [$input->attr('type'), $input->attr('value'), $input->attr('id'), $input->attr('required')]));
        self::assertSame(['New here', 'Old hand'], $level->filter('label')->each(static fn (Crawler $label): string => trim($label->text())));
        self::assertSame(['submit', 'default', 'sm', 'Answer: Your level'], [$level->filter('button')->attr('type'), $level->filter('button')->attr('data-variant'), $level->filter('button')->attr('data-size'), $level->filter('button')->text()]);

        $topics = $page->filter('form.phil-onboarding-question')->last();
        self::assertSame([['checkbox', null], ['checkbox', null]], $topics->filter('input[name="choices[]"]')->each(static fn (Crawler $input): array => [$input->attr('type'), $input->attr('required')]));
        self::assertSame(
            ['/phil_onboarding_answer/member/level', '/phil_onboarding_skip/member/level', '/phil_onboarding_answer/member/topics', '/phil_onboarding_dismiss/member'],
            $page->filter('form')->each(static fn (Crawler $form): ?string => $form->attr('action')),
        );

        $tour = new Crawler($this->twig->createTemplate("{{ onboarding_tour('member') }}")->render());
        self::assertSame(['', ''], $tour->filter('template')->each(static fn (Crawler $step): ?string => $step->attr('data-done-url')));
        self::assertStringContainsString('action="/phil_onboarding_answer/member/level"', $tour->filter('template')->first()->html());

        $this->onboarding->answer('ada', 'member', 'level', ['old']);
        $page = new Crawler($this->twig->createTemplate("{{ onboarding_checklist('member') }}")->render());
        self::assertSame(['/phil_onboarding_answer/member/topics'], $page->filter('form.phil-onboarding-question')->each(static fn (Crawler $form): ?string => $form->attr('action')));
    }

    public function testAFinishedChecklistOffersToStartAgain(): void
    {
        $this->onboarding->skip('ada', 'author', 'profile');
        $this->onboarding->complete('ada', 'author', 'publish');
        $this->onboarding->complete('ada', 'author', 'rules');

        $page = new Crawler($this->twig->createTemplate("{{ onboarding_checklist('author') }}")->render());

        self::assertSame('Every step is done.', $page->filter('.phil-onboarding-checklist__summary')->text());
        self::assertSame('Fill in your profile', $page->filter('span.phil-onboarding-step__title')->first()->text());
        self::assertSame('–', $page->filter('.phil-onboarding-step__mark')->first()->text());
        self::assertCount(0, $page->filter('li a'));
        self::assertSame(['/phil_onboarding_reset/author', '/phil_onboarding_dismiss/author'], $page->filter('form')->each(static fn (Crawler $form): ?string => $form->attr('action')));
        self::assertSame('', $this->twig->createTemplate("{{ onboarding_tour('author') }}")->render(), 'no tour for a finished flow');
    }

    public function testTheTourOfAUser(): void
    {
        $page = new Crawler($this->twig->createTemplate("{{ onboarding_tour('author', false) }}")->render());
        $root = $page->filter('div.phil-onboarding-tour');

        self::assertSame('phil--onboarding-bundle--tour', $root->attr('data-controller'));
        self::assertSame('keydown->phil--onboarding-bundle--tour#keydown phil-onboarding:start@window->phil--onboarding-bundle--tour#start', $root->attr('data-action'));
        self::assertSame(
            ['author', 'good', 'X-Phil-Onboarding-Token', '/phil_onboarding_dismiss/author', 'false', 'Step %current% of %total%', 'Next', 'Finish'],
            array_map(static fn (string $value): ?string => $root->attr('data-phil--onboarding-bundle--tour-'.$value.'-value'), ['flow', 'token', 'header', 'dismiss-url', 'autostart', 'progress', 'next', 'finish']),
        );

        $steps = $page->filter('template');
        self::assertSame(['profile', 'publish', 'rules'], $steps->each(static fn (Crawler $step): ?string => $step->attr('data-id')));
        self::assertSame(['#profile', '', ''], $steps->each(static fn (Crawler $step): ?string => $step->attr('data-selector')));
        self::assertSame(['/phil_onboarding_done/author/profile', '', '/phil_onboarding_done/author/rules'], $steps->each(static fn (Crawler $step): ?string => $step->attr('data-done-url')));
        self::assertSame(['/phil_onboarding_skip/author/profile', '', ''], $steps->each(static fn (Crawler $step): ?string => $step->attr('data-skip-url')));
        self::assertSame(['pending', 'pending', 'pending'], $steps->each(static fn (Crawler $step): ?string => $step->attr('data-state')));
        self::assertStringContainsString('Fill in your profile', $steps->first()->html());
        self::assertStringContainsString('<a href="/app_profile/bio">Go there</a>', $steps->first()->html());
        self::assertStringContainsString('Who you are', $steps->first()->html());

        $panel = $page->filter('[role="dialog"]');
        self::assertSame(['false', 'phil-onboarding-author-tour-title', '-1', ''], [$panel->attr('aria-modal'), $panel->attr('aria-labelledby'), $panel->attr('tabindex'), $panel->attr('hidden')]);
        self::assertSame(['Previous', 'Skip this step', 'Next'], $panel->filter('.phil-onboarding-tour__actions button')->each(static fn (Crawler $button): string => $button->text()));
        self::assertSame('End the tour', $panel->filter('.phil-onboarding-tour__end')->attr('aria-label'));
        self::assertSame('status', $page->filter('.phil-onboarding-visually-hidden')->attr('role'));

        self::assertSame('true', new Crawler($this->twig->createTemplate("{{ onboarding_tour('author') }}")->render())->filter('div')->attr('data-phil--onboarding-bundle--tour-autostart-value'));
    }

    public function testNothingForAVisitorADismissedFlowOrToursTurnedOff(): void
    {
        $this->tours = false;
        self::assertSame('', $this->twig->createTemplate("{{ onboarding_tour('author') }}")->render());
        self::assertNotSame('', $this->twig->createTemplate("{{ onboarding_checklist('author') }}")->render());

        $this->tours = true;
        $this->onboarding->dismiss('ada', 'author');
        self::assertSame('', $this->twig->createTemplate("{{ onboarding_checklist('author') }}{{ onboarding_tour('author') }}")->render());
        self::assertTrue($this->view->progress('author')?->isDismissed());

        $this->user->id = null;
        self::assertSame('', $this->twig->createTemplate("{{ onboarding_checklist('author') }}{{ onboarding_tour('author') }}")->render());
        self::assertSame('none', $this->twig->createTemplate("{{ onboarding_progress('author') is null ? 'none' }}")->render());
        self::assertNull($this->view->checklist('author'));
    }

    public function testTheProgressInATemplate(): void
    {
        self::assertSame('0/3', $this->twig->createTemplate("{% set p = onboarding_progress('author') %}{{ p.done }}/{{ p.total }}")->render());
    }

    public function testTheComponentsReadTheView(): void
    {
        $checklist = new ChecklistComponent($this->view);
        $checklist->flow = 'author';

        $tour = new TourComponent($this->view);
        $tour->flow = 'author';

        self::assertSame('phil-onboarding-author', $checklist->getData()['id'] ?? null);
        self::assertSame('phil-onboarding-author', $tour->getData()['id'] ?? null);
        self::assertTrue($tour->autostart);

        $this->user->id = null;
        self::assertNull($checklist->getData());
        self::assertNull($tour->getData());
    }
}
