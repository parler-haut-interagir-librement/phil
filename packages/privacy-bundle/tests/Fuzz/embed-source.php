<?php

declare(strict_types=1);

/*
 * The address of a third-party frame on values nobody wrote: whatever a template passes to privacy_embed(), the frame
 * gets an HTTPS address, maybe turned into its version without cookies, and its link the address given, or the call
 * refuses it; never another error nor a warning.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\PrivacyBundle\Domain\Consent\ConsentCategory;
use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Phil\PrivacyBundle\Domain\Consent\ConsentToken;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentContext;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentCookie;
use Phil\PrivacyBundle\Infrastructure\Http\PrivacyPages;
use Phil\PrivacyBundle\Infrastructure\Twig\EmbedExtension;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$twig = new Environment(new ArrayLoader(['@PhilPrivacy/embed/frame.html.twig' => "{{ source.url }}\n{{ source.original }}"]), ['autoescape' => false]);
$policy = new ConsentPolicy('1', [new ConsentCategory('videos')]);
$stack = new RequestStack();
$tokens = new class implements CsrfTokenManagerInterface {
    public function getToken(string $tokenId): CsrfToken
    {
        return new CsrfToken($tokenId, 'tok');
    }

    public function refreshToken(string $tokenId): CsrfToken
    {
        return new CsrfToken($tokenId, 'tok');
    }

    public function removeToken(string $tokenId): ?string
    {
        return null;
    }

    public function isTokenValid(CsrfToken $token): bool
    {
        return true;
    }
};
$urls = new class implements UrlGeneratorInterface {
    private RequestContext $context;

    public function __construct()
    {
        $this->context = new RequestContext();
    }

    public function setContext(RequestContext $context): void
    {
        $this->context = $context;
    }

    public function getContext(): RequestContext
    {
        return $this->context;
    }

    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        return '/'.$name;
    }
};
$embeds = new EmbedExtension(new ConsentContext($stack, new ConsentCookie(new ConsentToken('s'), new MockClock(), 'c', 'P1D'), $policy), $policy, new PrivacyPages($twig, $tokens, $urls, 'l'), $stack);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($embeds, $twig): void {
    try {
        [$url, $original] = explode("\n", $embeds->embed($twig, $input, 'videos', ['title' => 't']), 2);
    } catch (InvalidPrivacyValue) {
        return;
    }

    if (!str_starts_with(strtolower($url), 'https://') || $original !== $input) {
        throw new LogicException('An accepted frame is not the HTTPS address it was given, or its version without cookies.');
    }
});
