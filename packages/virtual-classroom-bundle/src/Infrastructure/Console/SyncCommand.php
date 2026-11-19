<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Infrastructure\Console;

use Phil\VirtualClassroom\Exception\InvalidValue;
use Phil\VirtualClassroom\Exception\VirtualClassroomException;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroomBundle\Application\Classrooms;
use Phil\VirtualClassroomBundle\Application\Contract\ClassroomStore;
use Phil\VirtualClassroomBundle\Domain\Exception\ClassroomException;
use Phil\VirtualClassroomBundle\Domain\Model\Room;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Asks the servers who is in the open rooms and what they recorded — every minute from a scheduler, for the servers
 * that send no notifications —; one room with `--room`. A room whose server fails is reported and the others go on;
 * the command then fails.
 */
#[AsCommand(name: 'phil:virtual-classroom:sync', description: 'Asks the conferencing servers who is in the open rooms and what they recorded.')]
final class SyncCommand extends Command
{
    public function __construct(
        private readonly Classrooms $classrooms,
        private readonly ClassroomStore $store,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('room', null, InputOption::VALUE_REQUIRED, 'The identifier of one room to sync')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'The most open rooms synced', '100');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $room = $input->getOption('room');
        $limit = $input->getOption('limit');

        try {
            $rooms = \is_string($room) ? [RoomId::fromString($room)] : array_map(static fn (Room $open): RoomId => $open->id, $this->store->openRooms(max(1, min(1000, is_numeric($limit) ? (int) $limit : 100))));
        } catch (InvalidValue $invalidValue) {
            $output->writeln(\sprintf('<error>%s</error>', $invalidValue->getMessage()));

            return Command::INVALID;
        }

        $failed = 0;

        foreach ($rooms as $id) {
            try {
                $this->classrooms->sync($id);
            } catch (ClassroomException|VirtualClassroomException $failure) {
                ++$failed;
                $output->writeln(\sprintf('<error>%s: %s</error>', $id->value, $failure->getMessage()));
            }
        }

        $output->writeln(\sprintf('%d room(s) synced, %d failed.', \count($rooms) - $failed, $failed));

        return 0 === $failed ? Command::SUCCESS : Command::FAILURE;
    }
}
