<?php

declare(strict_types=1);

/*
 * The readers of records a domain publishes, on text nobody wrote: the SPF, DKIM and DMARC parsers, then the SPF
 * analysis of a domain whose record, and every record it includes, is that text. Each returns or throws an
 * InvalidRecord; nothing else escapes, and the analysis never counts more than eleven lookups.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\MailAuthCheck\Contract\TxtResolver;
use Phil\MailAuthCheck\Dkim\DkimKeyParser;
use Phil\MailAuthCheck\Dmarc\DmarcParser;
use Phil\MailAuthCheck\Exception\InvalidRecord;
use Phil\MailAuthCheck\Spf\SpfAnalyser;
use Phil\MailAuthCheck\Spf\SpfParser;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$spf = new SpfParser();
$dkim = new DkimKeyParser();
$dmarc = new DmarcParser();
$analyser = new SpfAnalyser($spf);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($spf, $dkim, $dmarc, $analyser): void {
    foreach ([$spf->parse(...), $dkim->parse(...), $dmarc->parse(...)] as $parse) {
        try {
            $parse($input);
        } catch (InvalidRecord) {
        }
    }

    // Every name answers with the input, so its includes and redirects lead back to it or to names of its own.
    $resolver = new readonly class($input) implements TxtResolver {
        public function __construct(private string $input)
        {
        }

        public function txt(string $asciiName): array
        {
            return [$this->input];
        }
    };

    if ($analyser->analyse('example.org', $resolver)->lookups > SpfAnalyser::LOOKUP_LIMIT + 1) {
        throw new LogicException('The analysis counted more lookups than the walk allows.');
    }
});
$config->setMaxLen(4200);
