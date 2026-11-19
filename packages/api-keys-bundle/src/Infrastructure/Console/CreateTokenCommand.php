<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Infrastructure\Console;

use DateMalformedStringException;
use DateTimeInterface;
use Override;
use Phil\ApiKeysBundle\Application\ApiTokens;
use Phil\ApiKeysBundle\Application\Exception\UnknownScope;
use Phil\ApiKeysBundle\Domain\Exception\InvalidTokenValue;
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:api-keys:create alice@example.com "Deploy script" --scope=projects:read --expires="+30 days"
 *     bin/console phil:api-keys:create acme "Billing export" --owner-type=organization --scope=billing.
 *
 * Creates a token and prints its text once, on a line of its own; only its keyed hash is kept.
 */
#[AsCommand(name: 'phil:api-keys:create', description: 'Creates an access token and prints it once')]
final class CreateTokenCommand extends Command
{
    public function __construct(
        private readonly ApiTokens $tokens,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('owner', InputArgument::REQUIRED, 'The identifier the owner is loaded by')
            ->addArgument('name', InputArgument::REQUIRED, 'The name the owner recognises the token by')
            ->addOption('owner-type', null, InputOption::VALUE_REQUIRED, 'The type of the owner, as phil_api_keys.owner_types declares it', TokenOwner::USER)
            ->addOption('scope', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A scope the token holds; repeat it for several')
            ->addOption('expires', null, InputOption::VALUE_REQUIRED, 'When it expires: a date, or a relative time such as "+30 days"; the default lifetime without it');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $owner = $input->getArgument('owner');
        $type = $input->getOption('owner-type');
        $name = $input->getArgument('name');
        $expires = $input->getOption('expires');

        try {
            $issued = $this->tokens->create(
                new TokenOwner(\is_string($type) ? $type : '', \is_string($owner) ? $owner : ''),
                \is_string($name) ? $name : '',
                array_filter((array) $input->getOption('scope'), \is_string(...)),
                \is_string($expires) ? $this->clock->now()->modify($expires) : null,
            );
        } catch (DateMalformedStringException) {
            $output->writeln('The expiry is a date, or a relative time such as "+30 days".');

            return Command::INVALID;
        } catch (InvalidTokenValue|UnknownScope $invalid) {
            $output->writeln($invalid->getMessage());

            return Command::INVALID;
        }

        $output->writeln(\sprintf('Token %s created for %s %s, expiring %s. Copy it now: it is not shown again.', $issued->token->id(), $issued->token->ownerType(), $issued->token->owner(), $issued->token->expiresAt()->format(DateTimeInterface::ATOM)));
        $output->writeln($issued->plainText);

        return Command::SUCCESS;
    }
}
