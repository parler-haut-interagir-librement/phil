<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Fixture;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Phil\EmailAddress\DisposableDomains;
use Phil\EmailAddress\EmailValidator;
use Phil\EmailAddress\TopLevelDomains;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Markup;
use Phil\NewsletterBundle\Application\Content\ContentRenderer;
use Phil\NewsletterBundle\Application\Content\SandboxedTemplates;
use Phil\NewsletterBundle\Application\NewsletterUrls;
use Phil\NewsletterBundle\Application\SendRuns;
use Phil\NewsletterBundle\Application\SignedLinks;
use Phil\NewsletterBundle\Application\Subscriptions;
use Phil\NewsletterBundle\Domain\Model\MailingList;
use Phil\NewsletterBundle\Domain\ValueObject\BreakerPolicy;
use Phil\NewsletterBundle\Domain\ValueObject\Site;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalCampaignRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalDeliveryRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalEngagementRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalMailingListRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalSendRunRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalSubscriberRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalSuppressionRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalTemplateRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\NewsletterSchema;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * The newsletter on SQLite in memory, a frozen clock, a bus, a dispatcher and a mailer that keep what they get, the
 * bundle's templates and translations: what the unit tests build on.
 */
final class Newsletter
{
    public const string KEY = 'a-key-of-sixteen-bytes-at-least';

    public Connection $connection;

    public MockClock $clock;

    public RecordingBus $bus;

    public RecordingDispatcher $events;

    public RecordingMailer $mailer;

    public DbalMailingListRepository $lists;

    public DbalSubscriberRepository $subscribers;

    public DbalCampaignRepository $campaigns;

    public DbalDeliveryRepository $deliveries;

    public DbalTemplateRepository $templates;

    public DbalEngagementRepository $engagement;

    public DbalSuppressionRepository $suppressions;

    public DbalSendRunRepository $runs;

    public SignedLinks $links;

    public Subscriptions $subscriptions;

    public function __construct(?Connection $connection = null, bool $migrate = true)
    {
        $this->connection = $connection ?? DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        if ($migrate) {
            $manager = $this->connection->createSchemaManager();
            $manager->migrateSchema(NewsletterSchema::configure($manager->introspectSchema()));
        }

        $this->clock = new MockClock('2026-11-13 10:00:00 UTC');
        $this->bus = new RecordingBus();
        $this->events = new RecordingDispatcher();
        $this->mailer = new RecordingMailer();
        $this->lists = new DbalMailingListRepository($this->connection);
        $this->subscribers = new DbalSubscriberRepository($this->connection);
        $this->campaigns = new DbalCampaignRepository($this->connection);
        $this->deliveries = new DbalDeliveryRepository($this->connection);
        $this->templates = new DbalTemplateRepository($this->connection);
        $this->engagement = new DbalEngagementRepository($this->connection);
        $this->suppressions = new DbalSuppressionRepository($this->connection, $this->clock, self::KEY);
        $this->runs = new DbalSendRunRepository($this->connection);
        $this->links = new SignedLinks(self::KEY, $this->clock);
        $this->subscriptions = new Subscriptions($this->lists, $this->subscribers, $this->suppressions, $this->deliveries, $this->engagement, new EmailValidator(disposable: DisposableDomains::of(['mailinator.com']), topLevelDomains: TopLevelDomains::of(['com', 'org', 'net', 'ca'])), $this->bus, $this->events, $this->clock);
    }

    public function list(string $key = 'news', bool $doubleOptIn = true, string $site = 'default', string $profile = 'default'): MailingList
    {
        $list = new MailingList('list-'.$key.'-'.$site, Site::of($site), $key, 'The '.$key, $doubleOptIn, $profile, $this->clock->now());
        $this->lists->save($list);

        return $list;
    }

    /**
     * The send runs, with the circuit breaker given; none by default, so that a test reaches it only on purpose.
     */
    public function sendRuns(?BreakerPolicy $breaker = null): SendRuns
    {
        return new SendRuns($this->runs, $this->deliveries, $this->bus, $this->events, $this->clock, $breaker);
    }

    public function twig(): Environment
    {
        $translator = new Translator('en');
        $translator->addLoader('php', new PhpFileLoader());

        foreach (['en', 'fr'] as $locale) {
            $translator->addResource('php', \dirname(__DIR__, 3).'/translations/phil_newsletter.'.$locale.'.php', $locale, 'phil_newsletter');
        }

        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3).'/templates', 'PhilNewsletter');

        $twig = new Environment($loader, ['strict_variables' => true]);
        $twig->addExtension(new TranslationExtension($translator));

        return $twig;
    }

    public function renderer(): ContentRenderer
    {
        return new ContentRenderer(new Markup(new DefaultCompiler(), SanitizerPurifier::defaultPolicies()), $this->twig(), new SandboxedTemplates());
    }

    public function urls(): NewsletterUrls
    {
        $routes = new RouteCollection();

        foreach ([NewsletterUrls::CONFIRM => 'confirm', NewsletterUrls::UNSUBSCRIBE => 'unsubscribe', NewsletterUrls::PREFERENCES => 'preferences', NewsletterUrls::CLICK => 'c'] as $name => $path) {
            $routes->add($name, new Route('/newsletter/'.$path.'/{token}'));
        }

        return new NewsletterUrls(new UrlGenerator($routes, new RequestContext(host: 'fallback.example')), ['default' => 'https://example.com/']);
    }
}
