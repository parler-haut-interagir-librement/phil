<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Console;

use Override;
use Phil\EmailTemplatesBundle\Application\EmailTemplates;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:email-templates:list --site=main.
 *
 * Lists every template a site sends or keeps a draft of, by key then locale: where what it sends comes from — the
 * site, the shared scope or the default file; nothing when another locale is sent —, the version sent, and where the
 * site's own draft stands.
 */
#[AsCommand(name: 'phil:email-templates:list', description: 'Lists the email templates a site sends')]
final class ListTemplatesCommand extends Command
{
    public function __construct(
        private readonly EmailTemplates $templates,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addOption('site', null, InputOption::VALUE_REQUIRED, 'The site; the current one when omitted, "" for the shared scope');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $option = $input->getOption('site');

        try {
            $site = \is_string($option) ? Site::fromString($option) : null;
        } catch (InvalidTemplate $invalidTemplate) {
            $output->writeln($invalidTemplate->getMessage());

            return Command::INVALID;
        }

        $table = new Table($output)->setHeaders(['Key', 'Locale', 'Origin', 'Version', 'Draft', 'Saved at']);

        foreach ($this->templates->catalog($site) as $row) {
            $table->addRow([$row->key, $row->locale, $row->origin->value ?? '', $row->version ?? '', $row->state->value ?? '', $row->savedAt?->format('Y-m-d H:i:s \U\T\C') ?? '']);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
