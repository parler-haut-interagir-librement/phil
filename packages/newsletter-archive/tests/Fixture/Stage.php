<?php

declare(strict_types=1);

namespace Phil\NewsletterArchive\Tests\Fixture;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Markup;
use Phil\NewsletterArchive\Application\Archive;
use Phil\NewsletterArchive\Application\ArchiveReader;
use Phil\NewsletterArchive\Application\NeutralWebVersion;
use Phil\NewsletterArchive\Infrastructure\Dbal\ArchiveSchema;
use Phil\NewsletterArchive\Infrastructure\Dbal\DbalArchiveRepository;
use Phil\NewsletterBundle\Application\Content\ContentRenderer;
use Phil\NewsletterBundle\Application\Content\SandboxedTemplates;
use Phil\NewsletterBundle\Domain\Enum\CampaignStatus;
use Phil\NewsletterBundle\Domain\Enum\ClickTracking;
use Phil\NewsletterBundle\Domain\Model\Campaign;
use Phil\NewsletterBundle\Domain\Model\MailingList;
use Phil\NewsletterBundle\Domain\Model\Template;
use Phil\NewsletterBundle\Domain\ValueObject\Site;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalCampaignRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalMailingListRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\DbalTemplateRepository;
use Phil\NewsletterBundle\Infrastructure\Dbal\NewsletterSchema;
use Phil\Slug\TransliteratingSlugger;
use Symfony\Component\Clock\MockClock;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The archive on SQLite in memory, beside the newsletter's own tables and repositories: two lists, campaigns sent or
 * not, a template of the site that writes the subscriber's addresses, and a clock that stands still.
 */
final readonly class Stage
{
    public const string TEMPLATE = <<<'TWIG'
        <!DOCTYPE html><html><head><title>{{ subject }}</title></head><body>
        <div style="display:none">{{ preheader }}</div>
        <h1>{{ subject }}</h1>
        {{ content }}
        <p>Sent to {{ list_name }} on {{ site }}.</p>
        <img src="https://tracker.example/open.gif" width="1" height="1" alt="">
        <p><a href="{{ preferences_url }}">Preferences</a> · <a href="{{ unsubscribe_url }}">Unsubscribe</a></p>
        <p>Your link: {{ unsubscribe_url }}</p>
        </body></html>
        TWIG;

    public Connection $connection;

    public MockClock $clock;

    public DbalArchiveRepository $issues;

    public DbalCampaignRepository $campaigns;

    public DbalMailingListRepository $lists;

    public DbalTemplateRepository $templates;

    public Markup $markup;

    public NeutralWebVersion $renderer;

    public Archive $archive;

    public ArchiveReader $reader;

    /**
     * @param int<1, 255>                   $slugLength   the longest slug of the slugger
     * @param class-string<Connection>|null $wrapperClass the class of the connection, to count its queries
     */
    public function __construct(int $slugLength = 120, ?string $wrapperClass = null)
    {
        $params = ['driver' => 'pdo_sqlite', 'memory' => true];

        if (null !== $wrapperClass) {
            $params['wrapperClass'] = $wrapperClass;
        }

        $this->connection = DriverManager::getConnection($params);
        $manager = $this->connection->createSchemaManager();
        $manager->migrateSchema(ArchiveSchema::configure(NewsletterSchema::configure($manager->introspectSchema())));

        $this->clock = new MockClock('2026-11-21 10:00:00 UTC');
        $this->issues = new DbalArchiveRepository($this->connection);
        $this->campaigns = new DbalCampaignRepository($this->connection);
        $this->lists = new DbalMailingListRepository($this->connection);
        $this->templates = new DbalTemplateRepository($this->connection);
        $this->markup = new Markup(new DefaultCompiler(), SanitizerPurifier::defaultPolicies());
        $this->renderer = new NeutralWebVersion($this->markup, new ContentRenderer($this->markup, new Environment(new ArrayLoader()), new SandboxedTemplates()), $this->templates);
        $this->archive = new Archive($this->issues, $this->campaigns, $this->lists, $this->renderer, new TransliteratingSlugger(null, $slugLength), $this->clock);
        $this->reader = new ArchiveReader($this->issues);

        $this->lists->save(new MailingList('l1', Site::default(), 'news', 'News', true, 'default', new DateTimeImmutable('2026-01-01')));
        $this->lists->save(new MailingList('l2', Site::default(), 'events', 'Events', false, 'default', new DateTimeImmutable('2026-01-01')));

        $this->templates->save(new Template('branded', Site::default(), '{{ subject }}', self::TEMPLATE, new DateTimeImmutable('2026-01-01')));
    }

    /**
     * A campaign, sent at the instant given unless it is `null`.
     */
    public function campaign(string $id, string $subject, ?string $sentAt = '2026-11-01 09:00:00', string $listId = 'l1', ?string $template = null, string $body = "Hello **readers**.\n\n[Our site](https://example.com/)", string $site = Site::DEFAULT): Campaign
    {
        $campaign = new Campaign($id, Site::of($site), $listId, $subject, $body, 'This month in short', null, $template, null, ClickTracking::Aggregate);

        if (null !== $sentAt) {
            $campaign = Campaign::restore($campaign, CampaignStatus::Sent, null, new DateTimeImmutable($sentAt.' UTC'));
        }

        $this->campaigns->save($campaign);

        return $campaign;
    }
}
