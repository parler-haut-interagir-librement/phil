<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Infrastructure\Console;

use DateTimeImmutable;
use Override;
use Phil\ApiKeysBundle\Application\ApiTokens;
use Phil\ApiKeysBundle\Domain\Exception\InvalidTokenValue;
use Phil\ApiKeysBundle\Domain\Model\ApiToken;
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:api-keys:list --owner=alice@example.com
 *     bin/console phil:api-keys:list --owner=acme --owner-type=organization.
 *
 * Lists tokens, the newest first, without their secrets, which the store does not have.
 */
#[AsCommand(name: 'phil:api-keys:list', description: 'Lists the access tokens, without their secrets')]
final class ListTokensCommand extends Command
{
    private const string DATE = 'Y-m-d H:i';

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
            ->addOption('owner', null, InputOption::VALUE_REQUIRED, 'Only the tokens of this owner')
            ->addOption('owner-type', null, InputOption::VALUE_REQUIRED, 'The type of that owner', TokenOwner::USER)
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'How many tokens at most', '100');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = filter_var($input->getOption('limit'), \FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if (!\is_int($limit)) {
            $output->writeln('The limit is a whole number of at least 1.');

            return Command::INVALID;
        }

        $owner = $input->getOption('owner');
        $type = $input->getOption('owner-type');

        try {
            $tokens = $this->tokens->find(\is_string($owner) ? new TokenOwner(\is_string($type) ? $type : '', $owner) : null, $limit);
        } catch (InvalidTokenValue $invalidTokenValue) {
            $output->writeln($invalidTokenValue->getMessage());

            return Command::INVALID;
        }

        $now = $this->clock->now();
        $table = new Table($output);
        $table->setHeaders(['ID', 'Owner type', 'Owner', 'Name', 'Scopes', 'Created', 'Expires', 'Last used', 'State']);

        foreach ($tokens as $token) {
            $table->addRow([
                $token->id(),
                $token->ownerType(),
                $token->owner(),
                $token->name(),
                implode(' ', $token->scopes()),
                $token->createdAt()->format(self::DATE),
                $token->expiresAt()->format(self::DATE),
                $token->lastUsedAt()?->format(self::DATE) ?? 'never',
                $this->state($token, $now),
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }

    private function state(ApiToken $token, DateTimeImmutable $now): string
    {
        return match (true) {
            $token->isRevoked() => 'revoked',
            $token->hasExpiredAt($now) => 'expired',
            default => 'active',
        };
    }
}
