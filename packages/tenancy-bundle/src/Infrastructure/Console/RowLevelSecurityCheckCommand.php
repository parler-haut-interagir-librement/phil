<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Console;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity\RowLevelSecurityConfig;
use Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity\RowLevelSecurityPolicies;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:tenancy:rls:check.
 *
 * Checks, on the database itself, that the second barrier holds: the connection is PostgreSQL; its role is neither a
 * superuser nor allowed to bypass row-level security, which would turn every policy off; and every tenant-scoped
 * table has its security enabled and forced, with the policy reading the session variable. Each problem is printed;
 * the command fails while there is one, so that a deployment can stop on it.
 */
#[AsCommand(name: 'phil:tenancy:rls:check', description: 'Checks that every tenant-scoped table is held by its row-level security policy')]
final class RowLevelSecurityCheckCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RowLevelSecurityPolicies $policies,
        private readonly RowLevelSecurityConfig $config,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $problems = $this->problems();

        foreach ($problems as $problem) {
            $output->writeln('<error>✗</error> '.$problem);
        }

        if ([] !== $problems) {
            return Command::FAILURE;
        }

        $output->writeln(\sprintf('<info>✓</info> %d tenant-scoped tables are held by the policy %s.', \count($this->policies->tables($this->entityManager)), $this->config->policy));

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function problems(): array
    {
        $connection = $this->entityManager->getConnection();

        if (!$connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return ['Row-level security needs PostgreSQL; this connection is not.'];
        }

        $problems = [];
        $role = $connection->fetchAssociative('SELECT current_user AS name, rolsuper, rolbypassrls FROM pg_roles WHERE rolname = current_user');

        if (false !== $role && (true === $role['rolsuper'] || true === $role['rolbypassrls'])) {
            $problems[] = \sprintf('The role %s bypasses row-level security: connect with a role that is neither a superuser nor BYPASSRLS.', $this->text($role['name']));
        }

        foreach ($this->policies->tables($this->entityManager) as $table) {
            $state = $connection->fetchAssociative(
                'SELECT c.relrowsecurity, c.relforcerowsecurity, pg_get_expr(p.polqual, p.polrelid) AS qual, pg_get_expr(p.polwithcheck, p.polrelid) AS with_check
                   FROM pg_class c LEFT JOIN pg_policy p ON p.polrelid = c.oid AND p.polname = ?
                  WHERE c.oid = to_regclass(?)',
                [$this->config->policy, $table->table],
            );

            if (false === $state) {
                $problems[] = \sprintf('The table %s does not exist.', $table->table);

                continue;
            }

            if (true !== $state['relrowsecurity']) {
                $problems[] = \sprintf('The table %s does not have row-level security enabled.', $table->table);
            }

            if (true !== $state['relforcerowsecurity']) {
                $problems[] = \sprintf('The table %s does not force row-level security on its owner.', $table->table);
            }

            $setting = \sprintf("current_setting('%s'", $this->config->setting);

            if (!str_contains($this->text($state['qual']), $setting) || !str_contains($this->text($state['with_check']), $setting)) {
                $problems[] = \sprintf('The table %s has no policy %s that reads %s for reading and writing.', $table->table, $this->config->policy, $this->config->setting);
            }
        }

        return $problems;
    }

    private function text(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
