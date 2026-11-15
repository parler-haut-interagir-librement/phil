<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Tests\Unit\Fixture;

use Phil\TermsBundle\Application\AcceptanceChecker;
use Phil\TermsBundle\Application\Outstanding;
use Phil\TermsBundle\Application\Policies;
use Phil\TermsBundle\Application\Terms;
use Phil\TermsBundle\Application\VersionCatalog;
use Phil\TermsBundle\Domain\ValueObject\Policy;
use Phil\TermsBundle\Infrastructure\Http\Pages;
use Phil\TermsBundle\Infrastructure\Text\PolicyTextRenderer;
use Phil\TermsBundle\PhilTermsBundle;
use Phil\TextDiff\TextDiff;
use Phil\Ujo\Ujo;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Loader\PhpFileLoader as RoutesLoader;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Translation\Loader\PhpFileLoader as MessagesLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;

/**
 * The terms without a kernel: two required policies — `terms`, and `privacy` renewed every 30 days — and an optional
 * `conduct`, the stores in memory, a clock at 2026-05-01 10:00, and the pages rendered by the Twig of `TemplatesKernel`,
 * with the bundle's templates, routes and translations and the recipes of the PHIL UX kit.
 */
final readonly class Kit
{
    public InMemoryVersions $versions;

    public InMemoryAcceptances $acceptances;

    public MockClock $clock;

    public Policies $policies;

    public VersionCatalog $catalog;

    public RecordingNotifier $notifier;

    public RecordingEvents $events;

    public PolicyTextRenderer $texts;

    public Terms $terms;

    public AcceptanceChecker $checker;

    public CsrfTokenManager $csrf;

    public Translator $translator;

    public UrlGenerator $urls;

    public Environment $twig;

    /**
     * @param bool $licensing whether a required policy `contributions` names the licence of the contributions
     */
    public function __construct(string $locale = 'en', bool $licensing = false)
    {
        $root = \dirname(__DIR__, 3);
        $this->versions = new InMemoryVersions();
        $this->acceptances = new InMemoryAcceptances();
        $this->clock = new MockClock('2026-05-01 10:00:00');
        $policies = [new Policy('terms'), new Policy('privacy', renewAfterDays: 30), new Policy('conduct', false)];
        $this->policies = new Policies(...$licensing ? [...$policies, new Policy('contributions', contributionLicence: true)] : $policies);
        $this->catalog = new VersionCatalog($this->versions);
        $this->notifier = new RecordingNotifier();
        $this->events = new RecordingEvents();
        $this->texts = new PolicyTextRenderer(new Ujo(), new TextDiff());
        $this->terms = new Terms($this->policies, $this->catalog, $this->versions, $this->texts, $this->acceptances, $this->clock, null, $this->notifier, $this->events);
        $this->checker = new AcceptanceChecker($this->policies, $this->catalog, $this->acceptances);
        $this->csrf = new CsrfTokenManager(null, new ArrayTokenStorage());

        $routes = new RouteCollection();
        $loader = new RoutesLoader(new FileLocator($root.'/config/routes'));
        $routes->addCollection($loader->load('pages.php'));
        $routes->addCollection($loader->load('admin.php'));

        $this->urls = new UrlGenerator($routes, new RequestContext());

        $this->translator = new Translator($locale);
        $this->translator->addLoader('php', new MessagesLoader());
        $this->translator->addResource('php', $root.'/translations/phil_terms.en.php', 'en', 'phil_terms');
        $this->translator->addResource('php', $root.'/translations/phil_terms.fr.php', 'fr', 'phil_terms');

        $this->twig = TemplatesKernel::twig($locale);
    }

    public function pages(): Pages
    {
        return new Pages($this->twig, $this->csrf, $this->urls, PhilTermsBundle::LAYOUT);
    }

    /**
     * @param list<string> $members
     */
    public function outstanding(array $members = [], int $numbered = 0): Outstanding
    {
        return new Outstanding($this->policies, $this->catalog, $this->acceptances, new ListedMembers($members, $numbered));
    }

    /**
     * A form posted with a valid token, or the token given.
     *
     * @param array<string, mixed> $form
     */
    public function post(string $uri, array $form = [], ?string $token = null): Request
    {
        return Request::create($uri, 'POST', $form + ['_token' => $token ?? $this->csrf->getToken(Pages::CSRF_TOKEN_ID)->getValue()]);
    }

    /**
     * Moves the clock by a modifier such as `+1 day`.
     */
    public function later(string $modifier): void
    {
        $this->clock->modify($modifier);
    }
}
