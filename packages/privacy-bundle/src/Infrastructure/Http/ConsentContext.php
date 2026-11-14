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

    public static function sendsGlobalPrivacyControl(Request $request): bool
    {
        return '1' === $request->headers->get('Sec-GPC');
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
