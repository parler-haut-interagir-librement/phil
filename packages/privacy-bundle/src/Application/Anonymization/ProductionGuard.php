<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Anonymization;

use Phil\PrivacyBundle\Application\Exception\AnonymizationRefused;
use Phil\PrivacyBundle\Domain\Anonymization\DatabaseIdentity;

/**
 * Refuses to anonymize a production database.
 *
 * Anonymization rewrites personal data for good: on production it destroys the business's records. The guard
 * refuses when the application runs in one of the production environments (`prod` by default), and when the
 * database's host or name is one the configuration protects — the second check catches a developer's machine
 * pointed at the production server by a stray `DATABASE_URL`. There is no option to bypass it: copy the database
 * elsewhere, then anonymize the copy.
 */
final readonly class ProductionGuard
{
    /**
     * @param list<string> $environments
     * @param list<string> $hosts
     * @param list<string> $databases
     */
    public function __construct(
        private string $environment,
        private array $environments = ['prod'],
        private array $hosts = [],
        private array $databases = [],
    ) {
    }

    /**
     * @throws AnonymizationRefused
     */
    public function check(DatabaseIdentity $database): void
    {
        if (\in_array($this->environment, $this->environments, true)) {
            throw AnonymizationRefused::inEnvironment($this->environment);
        }

        if (\in_array($database->host, $this->hosts, true) || \in_array($database->name, $this->databases, true)) {
            throw AnonymizationRefused::protectedDatabase($database);
        }
    }
}
