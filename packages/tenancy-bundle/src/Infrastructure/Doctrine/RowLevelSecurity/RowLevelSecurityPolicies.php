<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity;

use Doctrine\ORM\EntityManagerInterface;
use Phil\TenancyBundle\Infrastructure\Doctrine\TenantColumn;

/**
 * The row-level security policies of PostgreSQL that repeat the Doctrine filter in the database: a second barrier, for
 * the query the filter never sees — native SQL, a DQL query with the filter disabled by mistake, a report written
 * against the tables.
 *
 * Each tenant-scoped table gets its security enabled and forced, so that the owner of the table is held by it too,
 * and one policy that lets a row be read, written and deleted only when its tenant column holds the session
 * variable: `tenant::text = current_setting('phil.tenant_id', true)`. Without the variable, `current_setting()` is
 * null or empty, and no row matches. The comparison is on the text of the column, as the filter compares it to the
 * text of the tenant reference.
 */
final readonly class RowLevelSecurityPolicies
{
    public function __construct(
        private RowLevelSecurityConfig $config,
    ) {
    }

    /**
     * The tenant-scoped tables of the entity manager, each once, in the order of their names.
     *
     * @return list<TenantTable>
     */
    public function tables(EntityManagerInterface $entityManager): array
    {
        $platform = $entityManager->getConnection()->getDatabasePlatform();
        $quotes = $entityManager->getConfiguration()->getQuoteStrategy();
        $tables = [];

        foreach ($entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            // A subclass of a single-table or joined hierarchy is filtered through its root.
            if ($metadata->getName() !== $metadata->rootEntityName || $metadata->isMappedSuperclass || $metadata->isEmbeddedClass) {
                continue;
            }

            $column = TenantColumn::of($metadata);

            if (null === $column) {
                continue;
            }

            $table = $quotes->getTableName($metadata, $platform);
            $tables[$table] = new TenantTable($table, $column);
        }

        ksort($tables);

        return array_values($tables);
    }

    /**
     * The statements that put a table under row-level security, safe to run again: the policy is dropped first.
     *
     * @return list<string>
     */
    public function enable(TenantTable $table): array
    {
        $check = \sprintf("%s::text = current_setting('%s', true)", $table->column, $this->config->setting);

        return [
            \sprintf('ALTER TABLE %s ENABLE ROW LEVEL SECURITY', $table->table),
            \sprintf('ALTER TABLE %s FORCE ROW LEVEL SECURITY', $table->table),
            \sprintf('DROP POLICY IF EXISTS %s ON %s', $this->config->policy, $table->table),
            \sprintf('CREATE POLICY %s ON %s USING (%s) WITH CHECK (%s)', $this->config->policy, $table->table, $check, $check),
        ];
    }

    /**
     * The statements that take a table out of row-level security: the `down()` of a migration.
     *
     * @return list<string>
     */
    public function disable(TenantTable $table): array
    {
        return [
            \sprintf('DROP POLICY IF EXISTS %s ON %s', $this->config->policy, $table->table),
            \sprintf('ALTER TABLE %s NO FORCE ROW LEVEL SECURITY', $table->table),
            \sprintf('ALTER TABLE %s DISABLE ROW LEVEL SECURITY', $table->table),
        ];
    }
}
