<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Http;

use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Phil\PrivacyBundle\Domain\Consent\ConsentState;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The consent of the visitor of the current request, kept on the request, so that nothing outlives it in a worker. The
 * attribute also tells `ConsentVaryListener` that the response depends on it. Outside a request, nothing is granted.
 */
final readonly class ConsentContext
{
    public const string ATTRIBUTE = '_phil_privacy_consent';

    public function __construct(
        private RequestStack $requests,
        private ConsentCookie $cookie,
        private ConsentPolicy $policy,
    ) {
    }

    /**
     * Whether the request carries the Global Privacy Control: every occurrence of `Sec-GPC` is read, each split on its
     * commas, and one value `1`, once trimmed, is the signal.
     */
    public static function sendsGlobalPrivacyControl(Request $request): bool
    {
        foreach ($request->headers->all('sec-gpc') as $header) {
            foreach (explode(',', (string) $header) as $value) {
                if ('1' === trim($value)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function state(): ConsentState
    {
        $request = $this->requests->getMainRequest();

        if (!$request instanceof Request) {
            return new ConsentState($this->policy, null, false);
        }

        $state = $request->attributes->get(self::ATTRIBUTE);

        if (!$state instanceof ConsentState) {
            $state = new ConsentState($this->policy, $this->cookie->read($request), self::sendsGlobalPrivacyControl($request));
            $request->attributes->set(self::ATTRIBUTE, $state);
        }

        return $state;
    }
}
