<?php

declare(strict_types=1);

namespace Phil\Backup\Restore;

use Phil\Backup\Database\DatabaseIdentity;
use Phil\Backup\Exception\RestoreRefused;

/**
 * What a restore must pass before anything is changed, in this order:
 *
 * 1. in a production environment (`prod` by default), the variable `PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE` holds the
 *    name of the database being restored — a value left set from another restore, or set to `1`, does not do;
 * 2. the restore is confirmed;
 * 3. the name of the database is typed again, as `DatabaseIdentity::typedName()` gives it.
 *
 * A restore replaces every row of the database: the guard makes it a decision taken twice, about this database.
 */
final readonly class RestoreGuard
{
    public const string VARIABLE = 'PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE';

    /**
     * @param list<string> $productionEnvironments
     */
    public function __construct(
        private string $environment,
        private array $productionEnvironments = ['prod'],
    ) {
    }

    /**
     * @param ?string $allowance the value of `PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE`, null when it is not set
     *
     * @throws RestoreRefused
     */
    public function checkEnvironment(DatabaseIdentity $database, ?string $allowance): void
    {
        if (\in_array($this->environment, $this->productionEnvironments, true) && $database->typedName() !== $allowance) {
            throw RestoreRefused::inProduction($this->environment, self::VARIABLE, $database);
        }
    }

    /**
     * @param ?string $typed the name typed again, null when none was
     *
     * @throws RestoreRefused
     */
    public function check(DatabaseIdentity $database, ?string $allowance, bool $confirmed, ?string $typed, string $backup): void
    {
        $this->checkEnvironment($database, $allowance);

        if (!$confirmed) {
            throw RestoreRefused::unconfirmed($database, $backup);
        }

        if (null === $typed) {
            throw RestoreRefused::untyped($database);
        }

        if ($database->typedName() !== $typed) {
            throw RestoreRefused::mistyped($database, $typed);
        }
    }
}
