<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Infrastructure\Console;

use Phil\EmbedBundle\Application\EmbedTokens;
use Phil\EmbedBundle\Domain\Exception\InvalidEmbed;
use Phil\EmbedBundle\Domain\ValueObject\Origin;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `phil:embed:token`: issues the token of an embed for one origin, signed by the first key.
 */
#[AsCommand(name: 'phil:embed:token', description: 'Issues the token of an embed for one origin.')]
final readonly class IssueTokenCommand
{
    public function __construct(
        private EmbedTokens $tokens,
        private bool $allowLocalhost = false,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The name of the embed.')]
        string $embed,
        #[Argument('The origin of the site that embeds it, such as https://partner.example.')]
        string $origin,
        #[Option('The theme the embed is shown in.')]
        ?string $theme = null,
        #[Option('The subject of the embed, an identifier of the application.')]
        ?string $subject = null,
    ): int {
        try {
            $io->writeln($this->tokens->issue($embed, Origin::fromString($origin, $this->allowLocalhost), $theme, $subject));
        } catch (InvalidEmbed $invalidEmbed) {
            $io->error($invalidEmbed->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
