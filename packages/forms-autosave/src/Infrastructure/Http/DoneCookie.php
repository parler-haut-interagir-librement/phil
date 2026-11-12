<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Infrastructure\Http;

use Phil\FormsAutosave\Application\Drafts;
use Phil\FormsAutosave\Domain\ValueObject\DraftKey;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Forgets the drafts of the forms submitted in the request and still valid when the response leaves: their copies on
 * the server are deleted, and their keys are set in a cookie on the response — a redirection, usually — that the
 * controller reads on the next page it runs on, then deletes. The keys tell nothing of the forms or the user; the
 * cookie keeps the ten latest, for a day, readable by scripts.
 */
final readonly class DoneCookie
{
    public const string ATTRIBUTE = '_phil_forms_autosave_done';

    public const string COOKIE = 'phil_forms_autosave_done';

    /** How many keys the cookie keeps, the latest. */
    public const int KEEP = 10;

    /** The separator of keys, a character no key has. */
    public const string SEPARATOR = '.';

    public function __construct(
        private ClockInterface $clock,
        private ?Drafts $drafts = null,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $submitted = $request->attributes->get(self::ATTRIBUTE);
        $done = [];

        foreach (\is_array($submitted) ? $submitted : [] as $draft) {
            if ($draft instanceof SubmittedDraft && $draft->form->isValid()) {
                $done[] = $draft->key->value;

                if (null !== $draft->user) {
                    $this->drafts?->discard($draft->user, $draft->key);
                }
            }
        }

        if ([] === $done) {
            return;
        }

        $previous = $request->cookies->getString(self::COOKIE);
        $keys = array_filter([...explode(self::SEPARATOR, $previous), ...$done], static fn (string $key): bool => 1 === preg_match(DraftKey::PATTERN, $key));
        // A key sent again moves to the end, among the latest.
        $keys = \array_slice(array_reverse(array_unique(array_reverse($keys))), -self::KEEP);

        $event->getResponse()->headers->setCookie(Cookie::create(self::COOKIE)
            ->withValue(implode(self::SEPARATOR, $keys))
            ->withExpires($this->clock->now()->modify('+1 day'))
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly(false)
            ->withSameSite(Cookie::SAMESITE_LAX));
    }
}
