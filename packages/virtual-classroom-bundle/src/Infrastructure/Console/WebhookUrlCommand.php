<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Infrastructure\Console;

use Phil\VirtualClassroom\Webhook;
use Phil\VirtualClassroomBundle\Application\Providers;
use Phil\VirtualClassroomBundle\Application\WebhookSigner;
use Phil\VirtualClassroomBundle\Infrastructure\Http\WebhookController;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Writes the one webhook address of a provider's account, with its proof, to set in the console of the provider —
 * the webhooks of JaaS, for instance. The host is the router's: set `router.request_context.host` and `scheme`.
 */
#[AsCommand(name: 'phil:virtual-classroom:webhook-url', description: 'Writes the webhook address of a provider account.')]
final class WebhookUrlCommand extends Command
{
    public function __construct(
        private readonly Providers $providers,
        private readonly WebhookSigner $signer,
        private readonly UrlGeneratorInterface $urls,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('provider', InputArgument::REQUIRED, 'The name of the provider in the configuration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $provider = $input->getArgument('provider');

        if (!\is_string($provider) || !$this->providers->has($provider)) {
            $output->writeln('<error>No provider of the configuration has this name.</error>');

            return Command::INVALID;
        }

        $output->writeln($this->urls->generate(WebhookController::ROUTE, ['provider' => $provider, 'token' => $this->signer->token($provider, null, Webhook::ACCOUNT)], UrlGeneratorInterface::ABSOLUTE_URL));

        return Command::SUCCESS;
    }
}
