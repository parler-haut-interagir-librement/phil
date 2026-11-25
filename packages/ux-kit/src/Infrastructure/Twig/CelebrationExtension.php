<?php

declare(strict_types=1);

namespace Phil\UxKit\Infrastructure\Twig;

use Phil\UxKit\Application\CelebrationMessages;
use Phil\UxKit\Application\CelebrationStream;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

/**
 * What the `celebration` recipe asks of PHP: `celebrate()`, the stream that plays a celebration from the server, and
 * `celebration_messages()`, the words it says in turn, in the language of the request.
 */
final readonly class CelebrationExtension
{
    public function __construct(
        private CelebrationStream $stream = new CelebrationStream(),
        private CelebrationMessages $messages = new CelebrationMessages(),
        private ?RequestStack $requests = null,
    ) {
    }

    /**
     * `<turbo-stream action="celebrate">`: in a Turbo Stream response, on a Mercure topic, or in the page, it plays the
     * celebration of the page with the message, the effect and the focus given.
     */
    #[AsTwigFunction(name: 'celebrate', isSafe: ['html'])]
    public function celebrate(?string $message = null, string $effect = CelebrationStream::EFFECTS[0], ?string $focus = null): string
    {
        return $this->stream->render($message, $effect, $focus);
    }

    /**
     * The words a celebration says in turn, for its `messages`: those of the locale given, or of the current request,
     * English for a language without its own.
     *
     * @return list<string>
     */
    #[AsTwigFunction(name: 'celebration_messages')]
    public function messages(?string $locale = null): array
    {
        return $this->messages->of($locale ?? $this->requests?->getCurrentRequest()?->getLocale());
    }
}
