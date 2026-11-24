<?php

declare(strict_types=1);

/*
 * A search, a vote token and a locale nobody wrote: the input is read as each of them. The normalizer always ends in a
 * search of 100 characters at most; the token and the locale end in a value or in an exception of the bundle — never
 * another error.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\FaqBundle\Application\QueryNormalizer;
use Phil\FaqBundle\Application\VoteTokens;
use Phil\FaqBundle\Domain\Exception\FaqException;
use Phil\FaqBundle\Domain\ValueObject\Locale;
use Symfony\Component\Clock\MockClock;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$normalizer = new QueryNormalizer();
$tokens = new VoteTokens('the secret of the fuzzer', new MockClock('@1000000'));

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($normalizer, $tokens): void {
    $normalized = $normalizer->normalize($input);

    if (mb_strlen($normalized, 'UTF-8') > QueryNormalizer::MAX_LENGTH || count($normalizer->terms($normalized)) > QueryNormalizer::MAX_TERMS) {
        throw new LogicException('A search is past its limits.');
    }

    $normalizer->fold($input);
    QueryNormalizer::textOf($input);

    foreach ([static fn (): mixed => $tokens->read($input), static fn (): mixed => Locale::fromString($input)] as $read) {
        try {
            $read();
        } catch (FaqException) {
            // A token or a locale refused: what the bundle promises.
        }
    }
});
$config->setMaxLen(2048);
