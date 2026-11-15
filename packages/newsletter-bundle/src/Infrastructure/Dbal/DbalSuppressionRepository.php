<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Connection;
use Override;
use Phil\NewsletterBundle\Domain\Contract\SuppressionRepository;
use Phil\NewsletterBundle\Domain\ValueObject\Site;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

/**
 * The suppressed addresses in the table `phil_newsletter_suppression`, each as an HMAC-SHA256 of the site and the
 * address under the application's key: the table names no address, and cannot be matched against a list of addresses
 * without the key.
 */
final readonly class DbalSuppressionRepository implements SuppressionRepository
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
        #[SensitiveParameter]
        private string $key,
    ) {
    }

    #[Override]
    public function suppress(Site $site, string $emailKey, string $reason): void
    {
        Sql::insertUnlessPresent($this->connection, NewsletterSchema::SUPPRESSIONS, [
            'site' => $site->key,
            'email_hash' => $this->hash($site, $emailKey),
            'reason' => substr($reason, 0, 16),
            'created_at' => $this->clock->now()->getTimestamp(),
        ], ['site', 'email_hash']);
    }

    #[Override]
    public function isSuppressed(Site $site, string $emailKey): bool
    {
        return false !== $this->connection->fetchOne(\sprintf('SELECT 1 FROM %s WHERE site = ? AND email_hash = ?', NewsletterSchema::SUPPRESSIONS), [$site->key, $this->hash($site, $emailKey)]);
    }

    private function hash(Site $site, string $emailKey): string
    {
        return hash_hmac('sha256', $site->key."\0".$emailKey, $this->key);
    }
}
