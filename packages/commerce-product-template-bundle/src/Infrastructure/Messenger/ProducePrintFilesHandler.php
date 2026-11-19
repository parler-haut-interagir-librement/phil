<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Infrastructure\Messenger;

use Phil\CommerceProductTemplateBundle\Application\Message\ProducePrintFiles;
use Phil\CommerceProductTemplateBundle\Application\PrintProduction;
use Phil\CommerceProductTemplateBundle\Domain\Exception\TemplateRefused;
use RuntimeException;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Produces the order a `ProducePrintFiles` names, in a worker. Producing is idempotent, so a message handled twice
 * writes nothing new.
 *
 * An order whose draws are still to make, or a store that cannot write, is retried: Messenger tries the message again
 * later. Anything else the module refuses — an unknown order, a text that does not fit — is never retried.
 */
final readonly class ProducePrintFilesHandler
{
    public function __construct(
        private PrintProduction $production,
    ) {
    }

    public function __invoke(ProducePrintFiles $message): void
    {
        try {
            $this->production->produce($message->orderNumber);
        } catch (TemplateRefused $templateRefused) {
            if ($templateRefused->isRetryable()) {
                throw new RecoverableMessageHandlingException($templateRefused->getMessage(), 0, $templateRefused);
            }

            throw new UnrecoverableMessageHandlingException($templateRefused->getMessage(), 0, $templateRefused);
        } catch (RuntimeException $runtimeException) {
            throw new RecoverableMessageHandlingException($runtimeException->getMessage(), 0, $runtimeException);
        }
    }
}
