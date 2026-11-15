<?php

declare(strict_types=1);

namespace Phil\TermsBundle\Infrastructure\Console;

use Override;
use Phil\LegalNotices\DocumentType;
use Phil\LegalNoticesBundle\Infrastructure\Twig\FooterLinks;
use Phil\TermsBundle\Application\Policies;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Twig\Environment;
use Twig\Error\Error;

/**
 *     bin/console phil:terms:check-legal-links.
 *
 * The templates of the bundle leave the legal links to the layout of the application; this checks that the site has
 * them: every route of the footer links is imported, each legal document has a version in force, each required policy
 * is among the links, and the layout of the terms pages shows them all. One line for each thing missing; it fails when
 * there is any, so that a deployment can run it.
 */
#[AsCommand(name: 'phil:terms:check-legal-links', description: 'Checks that the legal links exist on the site')]
final class LegalLinksCommand extends Command
{
    public function __construct(
        private readonly FooterLinks $links,
        private readonly Policies $policies,
        private readonly Environment $twig,
        private readonly RequestStack $requests,
        private readonly string $layout,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $problems = $this->problems();

        if ([] === $problems) {
            $io->success('The legal links exist and the layout of the terms shows them.');

            return Command::SUCCESS;
        }

        foreach ($problems as $problem) {
            $io->writeln($problem, OutputInterface::OUTPUT_RAW);
        }

        $io->error(\sprintf('%d problem(s) found.', \count($problems)));

        return Command::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function problems(): array
    {
        try {
            $links = $this->links->links();
        } catch (RouteNotFoundException $routeNotFoundException) {
            return ['missing_route: '.$routeNotFoundException->getMessage()];
        }

        $problems = [];

        foreach (DocumentType::cases() as $type) {
            if (!$this->linked($links, 'link.'.$type->value)) {
                $problems[] = \sprintf('missing_document: the %s has no version in force.', $type->value);
            }
        }

        foreach ($this->policies->required() as $policy) {
            if (!$this->linked($links, 'policy.'.$policy->key)) {
                $problems[] = \sprintf('missing_policy: the policy "%s" is not in phil_legal_notices.footer.terms_policies.', $policy->key);
            }
        }

        return [...$problems, ...$this->layoutProblems($links)];
    }

    /**
     * @param list<array{url: string, label: string, domain: string}> $links
     */
    private function linked(array $links, string $label): bool
    {
        return array_any($links, static fn (array $link): bool => $label === $link['label']);
    }

    /**
     * What the layout does not show: it is rendered as a page would be, a request standing for the page's.
     *
     * @param list<array{url: string, label: string, domain: string}> $links
     *
     * @return list<string>
     */
    private function layoutProblems(array $links): array
    {
        $this->requests->push(Request::create('/'));

        try {
            $html = $this->twig->render($this->layout);
        } catch (Error $error) {
            return [\sprintf('unreadable_layout: %s: %s', $this->layout, $error->getRawMessage())];
        } finally {
            $this->requests->pop();
        }

        $problems = [];

        foreach ($links as $link) {
            if (!str_contains($html, 'href="'.htmlspecialchars($link['url'], \ENT_QUOTES).'"')) {
                $problems[] = \sprintf('not_in_layout: %s does not link to %s; include @PhilLegalNotices/_links.html.twig.', $this->layout, $link['url']);
            }
        }

        return $problems;
    }
}
