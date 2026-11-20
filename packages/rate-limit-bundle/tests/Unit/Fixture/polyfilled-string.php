<?php

declare(strict_types=1);

/*
 * The intl and mbstring functions symfony/string calls, unqualified, defined in its namespace on the polyfills of
 * symfony/polyfill-intl-normalizer and symfony/polyfill-mbstring: once this file is loaded, in a process of its own,
 * symfony/string works as it does on a server without the extensions. Each call is counted, so that a test can tell
 * the polyfills did the work. The polyfills are only installed when the bundle is installed on its own, hence the
 * exceptions to PHPStan in phil-dev.php.
 */

namespace Symfony\Component\String;

use Phil\RateLimitBundle\Tests\Unit\Fixture\PolyfillCalls;
use Symfony\Polyfill\Intl\Normalizer\Normalizer;
use Symfony\Polyfill\Mbstring\Mbstring;

function normalizer_is_normalized(string $string, int $form = Normalizer::FORM_C): bool
{
    ++PolyfillCalls::$count;

    return Normalizer::isNormalized($string, $form);
}

function normalizer_normalize(string $string, int $form = Normalizer::FORM_C): string|false
{
    ++PolyfillCalls::$count;

    return Normalizer::normalize($string, $form);
}

function mb_strtolower(string $string, ?string $encoding = null): string
{
    ++PolyfillCalls::$count;

    return Mbstring::mb_strtolower($string, $encoding);
}
