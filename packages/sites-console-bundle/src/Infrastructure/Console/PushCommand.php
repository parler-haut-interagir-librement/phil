<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Console;

use JsonException;
use Phil\SitesConsoleBundle\Application\Console\ConfigurationPushes;
use Phil\SitesConsoleBundle\Application\Exception\NotFound;
use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Phil\SitesConsoleBundle\Domain\Model\ConfigurationPush;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Pushes common settings to sites, or delivers an existing push again to the sites it has not reached.
 */
#[AsCommand(name: 'phil:sites-console:push', description: 'Pushes common settings to several sites')]
final readonly class PushCommand
{
    public function __construct(
        private ConfigurationPushes $pushes,
    ) {
    }

    /**
     * @param list<string> $site
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The settings as a JSON object, such as {"mail.footer": "Hello"}')]
        ?string $settings = null,
        #[Option(description: 'A site to push to (repeatable); every site when none')]
        array $site = [],
        #[Option(description: 'The identifier of a push to deliver again')]
        ?string $retry = null,
        #[Option(description: 'The operator recorded in the journal')]
        ?string $actor = null,
    ): int {
        try {
            $push = null === $retry ? $this->pushes->create($this->settings($settings), $site, $actor) : $this->pushes->get($retry);
            $push = $this->pushes->deliver($push->id(), $actor);
        } catch (InvalidValue|NotFound $error) {
            $io->error($error->getMessage());

            return Command::INVALID;
        }

        return $this->report($io, $push);
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(?string $json): array
    {
        try {
            $settings = json_decode($json ?? '', true, 4, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw InvalidValue::because('The settings are a JSON object.');
        }

        if (!\is_array($settings)) {
            throw InvalidValue::because('The settings are a JSON object.');
        }

        $object = [];

        foreach ($settings as $key => $value) {
            $object[(string) $key] = $value;
        }

        return $object;
    }

    private function report(SymfonyStyle $io, ConfigurationPush $push): int
    {
        $rows = [];

        foreach ($push->deliveries() as $delivery) {
            $rows[] = [$delivery->siteId, $delivery->outcome->value, $delivery->message];
        }

        $io->writeln(\sprintf('Push %s', $push->id()));
        $io->table(['Site', 'Outcome', 'Message'], $rows);

        return [] === $push->undeliveredSites() ? Command::SUCCESS : Command::FAILURE;
    }
}
