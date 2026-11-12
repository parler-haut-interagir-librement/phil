<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Infrastructure\Http;

use Phil\FormsAutosave\Domain\ValueObject\DraftKey;
use Symfony\Component\Form\FormInterface;

/**
 * A form submitted in the request, kept in its attributes until the response: if it is still valid then — once the
 * application has added its own errors, if any —, its draft is forgotten in the browser and, for a signed-in user of
 * the server store, deleted on the server.
 */
final readonly class SubmittedDraft
{
    /**
     * @param FormInterface<mixed> $form
     * @param ?string              $user the user whose server copy to delete, null when there is none to delete
     */
    public function __construct(
        public FormInterface $form,
        public DraftKey $key,
        public ?string $user,
    ) {
    }
}
