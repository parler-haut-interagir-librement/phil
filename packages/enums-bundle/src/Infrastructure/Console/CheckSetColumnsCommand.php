<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Console;

use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Override;
use Phil\EnumsBundle\Infrastructure\Doctrine\EnumSetType;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Reads every stored value of every `EnumSet` column the entities map, and lists those that no longer name cases
 * of their enum — a case removed, a flag renumbered, a value renamed. Run it before deploying a change to such an
 * enum, and after the migration that rewrites the rows: the rows it lists would fail to load.
 */
#[AsCommand(name: 'phil:enums:check-sets', description: 'Lists the stored EnumSet values that no longer name cases of their enum')]
final class CheckSetColumnsCommand extends Command
{
    public function __construct(
        private readonly ?ManagerRegistry $doctrine = null,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->doctrine instanceof ManagerRegistry) {
            $io->error('Doctrine is not configured: register DoctrineBundle to check the EnumSet columns.');

            return Command::FAILURE;
        }

        $problems = [];
        $columns = 0;

        foreach ($this->doctrine->getManagers() as $manager) {
            if (!$manager instanceof EntityManagerInterface) {
                continue;
            }

            $connection = $manager->getConnection();
            $platform = $connection->getDatabasePlatform();

            foreach ($manager->getMetadataFactory()->getAllMetadata() as $metadata) {
                foreach ($metadata->getFieldNames() as $field) {
                    $mapping = $metadata->getFieldMapping($field);
                    $type = Type::hasType($mapping->type) ? Type::getType($mapping->type) : null;

                    if (!$type instanceof EnumSetType) {
                        continue;
                    }

                    ++$columns;
                    $column = $connection->quoteSingleIdentifier($mapping->columnName);
                    $values = $connection->fetchFirstColumn(\sprintf('SELECT DISTINCT %s FROM %s WHERE %1$s IS NOT NULL', $column, $connection->quoteSingleIdentifier($metadata->getTableName())));

                    foreach ($values as $value) {
                        try {
                            $type->convertToPHPValue($value, $platform);
                        } catch (ConversionException) {
                            $problems[] = [$metadata->getName().'::$'.$field, $type->enum(), json_encode($value, \JSON_THROW_ON_ERROR)];
                        }
                    }
                }
            }
        }

        if ([] === $problems) {
            $io->success(\sprintf('Every stored value of the %d EnumSet column(s) names cases of its enum.', $columns));

            return Command::SUCCESS;
        }

        $io->table(['Field', 'Enum', 'Stored value'], $problems);
        $io->error(\sprintf('%d stored value(s) name no case of their enum: migrate them before the change ships.', \count($problems)));

        return Command::FAILURE;
    }
}
