<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Infrastructure\Outbox;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Override;
use Phil\Outbox\Contract\EventCodec;
use Phil\Outbox\Exception\ForgottenRecord;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\Payload;
use Phil\SearchBundle\Domain\ValueObject\Index;

/**
 * Rebuilds the records of `OutboxIndexingListener`: an `IndexEntity` from a class Doctrine maps and its identifier,
 * a `RemoveDocument` from an index and a document identifier. A record naming a class no entity manager maps any more
 * is skipped instead of relayed.
 */
final readonly class SearchOutboxCodec implements EventCodec
{
    public function __construct(
        private ManagerRegistry $doctrine,
    ) {
    }

    #[Override]
    public function types(): array
    {
        return [IndexEntity::TYPE, RemoveDocument::TYPE];
    }

    #[Override]
    public function decode(OutboxRecord $record, Payload $payload): IndexEntity|RemoveDocument
    {
        if (RemoveDocument::TYPE === $record->type) {
            return new RemoveDocument(Index::check($payload->string('index')), $payload->string('document'));
        }

        $class = $payload->string('class');

        if (!class_exists($class) || !$this->doctrine->getManagerForClass($class) instanceof ObjectManager) {
            throw ForgottenRecord::because(\sprintf('No entity manager maps %s any more.', mb_strimwidth($class, 0, 120, '…')));
        }

        $identifier = [];

        foreach ($payload->values as $name => $value) {
            if (str_starts_with($name, 'id_') && (\is_string($value) || \is_int($value))) {
                $identifier[substr($name, 3)] = $value;
            }
        }

        return new IndexEntity($class, $identifier);
    }
}
