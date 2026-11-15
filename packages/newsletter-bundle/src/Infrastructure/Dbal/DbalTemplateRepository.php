<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Connection;
use Override;
use Phil\NewsletterBundle\Domain\Contract\TemplateRepository;
use Phil\NewsletterBundle\Domain\Model\Template;
use Phil\NewsletterBundle\Domain\ValueObject\Site;

/**
 * The templates in the table `phil_newsletter_template`; a shared template has an empty site.
 */
final readonly class DbalTemplateRepository implements TemplateRepository
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function save(Template $template): void
    {
        Sql::upsert($this->connection, NewsletterSchema::TEMPLATES, [
            'site' => $template->site->key ?? '',
            'template_key' => $template->key,
            'subject' => $template->subject,
            'source' => $template->source,
            'updated_at' => $template->updatedAt->getTimestamp(),
        ], ['site', 'template_key']);
    }

    #[Override]
    public function find(Site $site, string $key): ?Template
    {
        $row = $this->connection->fetchAssociative(
            \sprintf("SELECT site, template_key, subject, source, updated_at FROM %s WHERE template_key = ? AND site IN (?, '') ORDER BY site DESC", NewsletterSchema::TEMPLATES),
            [$key, $site->key],
        );

        if (false === $row) {
            return null;
        }

        $owner = Sql::string($row['site']);

        return new Template(Sql::string($row['template_key']), '' === $owner ? null : Site::of($owner), Sql::string($row['subject']), Sql::string($row['source']), Sql::instant($row['updated_at']));
    }

    #[Override]
    public function remove(?Site $site, string $key): void
    {
        $this->connection->delete(NewsletterSchema::TEMPLATES, ['site' => $site->key ?? '', 'template_key' => $key]);
    }
}
