<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Console;

use Override;
use Phil\EmailTemplatesBundle\Application\Contract\PreviewRenderer;
use Phil\EmailTemplatesBundle\Application\Preview\PreviewVariant;
use Phil\EmailTemplatesBundle\Domain\Exception\EmailTemplatesException;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Locale;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateKey;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:email-templates:render ticket --locale=fr --segment=vip --part=text.
 *
 * Renders a template with its sample values, without sending it — as the site sends it, as its draft with `--draft`, or
 * at a published version —, and writes one part: the subject, the preheader, the HTML or the plain text.
 */
#[AsCommand(name: 'phil:email-templates:render', description: 'Renders an email template with its sample values')]
final class RenderTemplateCommand extends Command
{
    private const array PARTS = ['subject', 'preheader', 'html', 'text'];

    public function __construct(
        private readonly PreviewRenderer $previews,
        private readonly string $defaultLocale = 'en',
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('key', InputArgument::REQUIRED, 'The template key')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'The locale', $this->defaultLocale)
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'The site; "" for the shared scope', '')
            ->addOption('segment', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A segment of the recipient')
            ->addOption('template-version', null, InputOption::VALUE_REQUIRED, 'A version the site published, rather than the one it sends')
            ->addOption('draft', null, InputOption::VALUE_NONE, 'The draft the site keeps in the locale, rather than what it sends')
            ->addOption('part', null, InputOption::VALUE_REQUIRED, 'subject, preheader, html or text', 'html');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $part = $input->getOption('part');
        $version = $input->getOption('template-version');
        $number = null === $version ? null : filter_var($version, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (!\in_array($part, self::PARTS, true) || false === $number) {
            $output->writeln('The part is subject, preheader, html or text; the version a whole number from 1.');

            return Command::INVALID;
        }

        try {
            $variant = new PreviewVariant(
                TemplateKey::fromString($this->text($input->getArgument('key'))),
                Site::fromString($this->text($input->getOption('site'))),
                Locale::fromString($this->text($input->getOption('locale'))),
                array_values(array_filter((array) $input->getOption('segment'), is_string(...))),
                $number,
                true === $input->getOption('draft'),
            );
            $email = $this->previews->render($variant);
        } catch (EmailTemplatesException $emailTemplatesException) {
            $output->writeln($emailTemplatesException->getMessage());

            return Command::FAILURE;
        }

        $output->write(match ($part) {
            'subject' => $email->subject."\n",
            'preheader' => $email->preheader."\n",
            'text' => $email->text,
            default => $email->html,
        }, false, OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }

    private function text(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
