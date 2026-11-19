<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\LibraryBundle\Application\Contract\LoanTransaction;
use Phil\LibraryBundle\Application\Contract\MemberResolver;
use Phil\LibraryBundle\Application\Contract\ReminderSender;
use Phil\LibraryBundle\Application\Contract\StockLink;
use Phil\LibraryBundle\Application\Reminders;
use Phil\LibraryBundle\Infrastructure\Console\SendRemindersCommand;
use Phil\LibraryBundle\Infrastructure\Doctrine\DbalLoanTransaction;
use Phil\LibraryBundle\Infrastructure\Http\AdminController;
use Phil\LibraryBundle\Infrastructure\Http\CatalogueController;
use Phil\LibraryBundle\Infrastructure\Http\DeskController;
use Phil\LibraryBundle\Infrastructure\Http\MemberLoansController;
use Phil\LibraryBundle\Infrastructure\Http\Pages;
use Phil\LibraryBundle\Infrastructure\Messenger\MessengerReminderSender;
use Phil\LibraryBundle\Infrastructure\Stockroom\StockroomLink;
use Phil\Stockroom\Stockroom;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomTransactions;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Wires what depends on the application: the member resolver it names, the stock moves
 * when it names a stockroom or installs `phil/stockroom-bundle`, and the transaction of the loans when the stockroom
 * shares the library's connection, the reminders when Messenger's bus or a sender of its own is there, and the pages when Twig
 * and forms are.
 */
final readonly class LibraryPass implements CompilerPassInterface
{
    public const string MEMBER_RESOLVER = 'phil_library.member_resolver';

    public const string STOCKROOM = 'phil_library.stockroom';

    /** The parameter of `phil/stockroom-bundle` that names the connection of its tables. */
    public const string STOCKROOM_CONNECTION = 'phil_stockroom.connection';

    /** The connection of the library's tables, which the entity manager of its repositories writes on. */
    public const string CONNECTION = 'doctrine.dbal.default_connection';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        $resolver = $container->hasParameter(self::MEMBER_RESOLVER) ? $container->getParameter(self::MEMBER_RESOLVER) : null;

        if (\is_string($resolver) && '' !== $resolver) {
            $container->setAlias(MemberResolver::class, $resolver);
        }

        $this->stockroom($container);

        $sender = $container->hasAlias(ReminderSender::class) ? (string) $container->getAlias(ReminderSender::class) : null;

        if (MessengerReminderSender::class === $sender && !$container->has(MessageBusInterface::class)) {
            $container->removeAlias(ReminderSender::class);
        }

        if (!$container->has(MessageBusInterface::class)) {
            $container->removeDefinition(MessengerReminderSender::class);
        }

        if (!$container->has(ReminderSender::class)) {
            $container->removeDefinition(Reminders::class);
            $container->removeDefinition(SendRemindersCommand::class);
        }

        if (!$container->has('twig') || !$container->has('form.factory')) {
            foreach ([Pages::class, CatalogueController::class, DeskController::class, AdminController::class, MemberLoansController::class] as $page) {
                $container->removeDefinition($page);
            }
        }
    }

    /**
     * The stockroom the loans move items in: the service the configuration names, else the one of
     * `phil/stockroom-bundle` when it is installed. When the stockroom is the bundle's, each movement locks its item in
     * one of its transactions; when the bundle's tables are on the library's connection, each loan runs in one transaction with its movement.
     */
    private function stockroom(ContainerBuilder $container): void
    {
        $stockroom = $container->hasParameter(self::STOCKROOM) ? $container->getParameter(self::STOCKROOM) : null;
        $bundled = $container->has(StockroomTransactions::class) && $container->has(Stockroom::class);

        if ((!\is_string($stockroom) || '' === $stockroom) && $bundled) {
            $stockroom = Stockroom::class;
        }

        if (!\is_string($stockroom) || '' === $stockroom) {
            return;
        }

        $bundled = $bundled && $this->resolve($container, $stockroom) === $this->resolve($container, Stockroom::class);
        $container->register(StockroomLink::class, StockroomLink::class)
            ->setArguments([new Reference($stockroom), $bundled ? new Reference(StockroomTransactions::class) : null]);
        $container->setAlias(StockLink::class, StockroomLink::class);
        $connection = $container->hasParameter(self::STOCKROOM_CONNECTION) ? $container->getParameter(self::STOCKROOM_CONNECTION) : null;

        if ($bundled && \is_string($connection) && $container->has(self::CONNECTION) && $this->resolve($container, $connection) === $this->resolve($container, self::CONNECTION)) {
            $container->register(DbalLoanTransaction::class, DbalLoanTransaction::class)->setArguments([new Reference(self::CONNECTION)]);
            $container->setAlias(LoanTransaction::class, DbalLoanTransaction::class);
        }
    }

    /**
     * The service an id names once its aliases are followed.
     */
    private function resolve(ContainerBuilder $container, string $id): string
    {
        $seen = [];

        while ($container->hasAlias($id) && !isset($seen[$id])) {
            $seen[$id] = true;
            $id = (string) $container->getAlias($id);
        }

        return $id;
    }
}
