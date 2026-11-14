<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional\App;

use Doctrine\ORM\EntityManagerInterface;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Tests\Functional\App\Entity\Invoice;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A command that knows nothing of tenants: it prints the tenant it runs in, then the invoices it can read.
 */
#[AsCommand('app:invoices')]
final class TenantCommand extends Command
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln($this->context->current()->value ?? '(none)');

        foreach ($this->entityManager->getRepository(Invoice::class)->findBy([], ['label' => 'ASC']) as $invoice) {
            $output->writeln($invoice->label);
        }

        return Command::SUCCESS;
    }
}
