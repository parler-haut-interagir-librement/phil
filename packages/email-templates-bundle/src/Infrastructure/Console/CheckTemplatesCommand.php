<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Console;

use Override;
use Phil\EmailTemplatesBundle\Application\Contract\DefaultTemplates;
use Phil\EmailTemplatesBundle\Application\TemplateValidator;
use Phil\EmailTemplatesBundle\Domain\Exception\InvalidTemplate;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:email-templates:check.
 *
 * Checks every default file as a save would: it is read, its sources compile in the sandbox and read only what its
 * schema declares, and every variant renders with the sample values. Fails when one does not, for a deployment to
 * stop on.
 */
#[AsCommand(name: 'phil:email-templates:check', description: 'Checks every default email template')]
final class CheckTemplatesCommand extends Command
{
    public function __construct(
        private readonly DefaultTemplates $defaults,
        private readonly TemplateValidator $validator,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $failed = 0;
        $count = 0;

        foreach ($this->defaults->catalog() as $key => $locales) {
            foreach ($locales as $locale) {
                ++$count;

                try {
                    $template = TemplateKey::fromString($key);
                    $language = Locale::fromString($locale);
                    $definition = $this->defaults->find($template, $language);
                    \assert($definition instanceof TemplateDefinition);
                    $this->validator->validate($template, Site::shared(), $language, $definition);
                    $output->writeln(\sprintf('<info>OK</info>   %s (%s)', $key, $locale), OutputInterface::VERBOSITY_VERBOSE);
                } catch (InvalidTemplate $invalidTemplate) {
                    ++$failed;
                    $output->writeln(\sprintf('<error>FAIL</error> %s (%s)', $key, $locale));

                    foreach ($invalidTemplate->errors as $error) {
                        $output->writeln('     '.$error);
                    }
                }
            }
        }

        $output->writeln(\sprintf('%d of %d default templates checked without error.', $count - $failed, $count));

        return 0 === $failed ? Command::SUCCESS : Command::FAILURE;
    }
}
