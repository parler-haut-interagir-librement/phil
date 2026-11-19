<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Infrastructure\Http;

use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Phil\CommercePosBundle\Application\OfflineSale;
use Phil\CommercePosBundle\Application\OfflineSales;
use Phil\CommercePosBundle\Domain\Exception\OfflineSaleForbidden;
use Phil\CommercePosBundle\Domain\Exception\PosRefused;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\Money\Exception\MoneyException;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Where a register sends what it rang up offline, one sale per request: the application routes `POST` requests to
 * it, behind its own access control, and binds the sale's cashier and session to the signed-in user with an
 * `OfflineSaleGate`. It reads only a body sent as `application/json` — which a cross-site form cannot send without
 * the browser asking first.
 *
 * It answers `201` for a sale imported now, `200` for one already imported, `403` when the gate refuses it, `409` when
 * the session is closed or unknown, `415` for a body not sent as JSON, and `422` for a sale it cannot import —
 * unreadable, out of bounds, paid by a method the register does not take, or selling what is no longer sold.
 */
final readonly class OfflineSaleController
{
    public function __construct(
        private OfflineSales $sales,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if ('json' !== $request->getContentTypeFormat()) {
            return new JsonResponse(['error' => 'An offline sale is sent as application/json.'], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        try {
            $data = json_decode($request->getContent(), true, flags: \JSON_THROW_ON_ERROR);
            [$imported, $now] = $this->sales->import(OfflineSale::fromArray(\is_array($data) ? $data : []), $this->clock->now());
        } catch (OfflineSaleForbidden $forbidden) {
            return new JsonResponse(['error' => $forbidden->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (PosRefused $refused) {
            return new JsonResponse(['error' => $refused->getMessage()], Response::HTTP_CONFLICT);
        } catch (JsonException|DomainError|MoneyException $invalid) {
            // A Money exception is an amount out of range once re-priced: the sale, not the server, is at fault.
            return new JsonResponse(['error' => $invalid->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->flush();

        return new JsonResponse(['id' => $imported->id, 'discrepancy' => $imported->discrepancy->minorUnits], $now ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
