<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Infrastructure\Doctrine;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Phil\CommerceSyndicationBundle\Domain\Model\FedSku;
use Phil\CommerceSyndicationBundle\Domain\Model\Partner;

/**
 * Keeps `phil_commerce_syndication_fed_sku` in step with the SKUs each reseller is fed: at each flush, a partner
 * inserted or whose list changed gains a row for each SKU added and loses the row of each SKU taken off, in the same
 * transaction as the partner's own row.
 */
final readonly class FedSkuIndex
{
    public function onFlush(OnFlushEventArgs $event): void
    {
        $manager = $event->getObjectManager();
        $work = $manager->getUnitOfWork();
        $metadata = $manager->getClassMetadata(FedSku::class);

        foreach ([...$work->getScheduledEntityInsertions(), ...$work->getScheduledEntityUpdates()] as $partner) {
            if (!$partner instanceof Partner) {
                continue;
            }

            $change = $work->getEntityChangeSet($partner)['skus'] ?? null;

            if (null === $change) {
                continue;
            }

            // A partner inserted had no list before.
            $before = \is_array($change[0]) ? $change[0] : [];
            $after = $partner->skus();

            $removed = [];

            foreach ($before as $sku) {
                if (!\in_array($sku, $after, true)) {
                    $removed[] = $sku;
                }
            }

            foreach ($manager->getRepository(FedSku::class)->findBy(['partner' => $partner->code, 'sku' => $removed]) as $fed) {
                $manager->remove($fed);
            }

            foreach ($after as $sku) {
                if (!\in_array($sku, $before, true)) {
                    $fed = FedSku::of($partner, $sku);
                    $manager->persist($fed);
                    $work->computeChangeSet($metadata, $fed);
                }
            }
        }
    }
}
