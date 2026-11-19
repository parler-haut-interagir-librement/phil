<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Infrastructure\Console;

use Override;
use Phil\ApiKeysBundle\Application\ApiTokens;
use Phil\ApiKeysBundle\Application\Exception\TokenNotFound;
use Phil\ApiKeysBundle\Domain\Exception\InvalidTokenValue;
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:api-keys:revoke 3kTMd9qXbW1z
 *     bin/console phil:api-keys:revoke --owner=acme --owner-type=organization.
 *
 * Revokes tokens by identifier, or every active token of an owner, at once; a token already revoked stays as it was.
 */
#[AsCommand(name: 'phil:api-keys:revoke', description: 'Revokes access tokens by identifier, or every token of an owner')]
final class RevokeTokenCommand extends Command
{
    public function __construct(
        private readonly ApiTokens $tokens,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'The identifiers of the tokens')
            ->addOption('owner', null, InputOption::VALUE_REQUIRED, 'Revokes every active token of this owner instead')
            ->addOption('owner-type', null, InputOption::VALUE_REQUIRED, 'The type of that owner', TokenOwner::USER);
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ids = array_filter((array) $input->getArgument('id'), \is_string(...));
        $owner = $input->getOption('owner');

        if (\is_string($owner) === ([] !== $ids)) {
            $output->writeln('Name the tokens to revoke, or their owner with --owner, not both.');

            return Command::INVALID;
        }

        if (\is_string($owner)) {
            $type = $input->getOption('owner-type');

            return $this->revokeAll($owner, \is_string($type) ? $type : '', $output);
        }

        $status = Command::SUCCESS;

        foreach ($ids as $id) {
            try {
                $this->tokens->revoke($id);
                $output->writeln(\sprintf('Token %s revoked.', $id));
            } catch (TokenNotFound $tokenNotFound) {
                $output->writeln($tokenNotFound->getMessage());
                $status = Command::FAILURE;
            }
        }

        return $status;
    }

    private function revokeAll(string $owner, string $type, OutputInterface $output): int
    {
        try {
            $count = $this->tokens->revokeAll(new TokenOwner($type, $owner));
        } catch (InvalidTokenValue $invalidTokenValue) {
            $output->writeln($invalidTokenValue->getMessage());

            return Command::INVALID;
        }

        $output->writeln(\sprintf('%d token(s) of %s %s revoked.', $count, $type, $owner));

        return Command::SUCCESS;
    }
}
