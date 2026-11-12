<?php

declare(strict_types=1);

use Phil\BrowserSecurityBundle\Application\Exception\InvalidReport;
use Phil\BrowserSecurityBundle\Application\ReportParser;

/*
 * The report parser on what nobody wrote: the input's first byte picks the media type, the rest is the body. A body is
 * read or refused with `InvalidReport`, nothing else, and every report read keeps within the bounds the parser
 * promises: an address without query, fragment, credentials nor control character, a directive of lower-case letters
 * and hyphens, a sample of 40 characters at most, a bounded number of reports.
 */
$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    $types = [ReportParser::CSP_REPORT, ReportParser::REPORTS_JSON, ReportParser::JSON];
    $type = $types[ord(($input[0] ?? "\0")[0]) % 3];

    try {
        $reports = new ReportParser(5)->parse($type, substr($input, 1));
    } catch (InvalidReport) {
        return;
    }

    if (count($reports) > 5) {
        throw new LogicException('More reports than the bound.');
    }

    foreach ($reports as $report) {
        foreach ([$report->documentUri, $report->blockedUri, $report->sourceFile ?? ''] as $uri) {
            if (strlen($uri) > ReportParser::MAX_URI_LENGTH || 1 === preg_match('/[?#\x00-\x1F\x7F]|^[A-Za-z][A-Za-z0-9+.-]*:\/\/[^\/]*@/', $uri) || !mb_check_encoding($uri, 'UTF-8')) {
                throw new LogicException('An address kept what it must not: '.$uri);
            }
        }

        if ('' === $report->documentUri || 1 !== preg_match('/^[a-z][a-z-]{0,63}$/D', $report->effectiveDirective)) {
            throw new LogicException('A report without a page or with a bad directive.');
        }

        if (null !== $report->sample && (mb_strlen($report->sample, 'UTF-8') > ReportParser::MAX_SAMPLE_LENGTH || 1 === preg_match('/[\x00-\x1F\x7F]/', $report->sample))) {
            throw new LogicException('A sample beyond its bound.');
        }
    }
});
$config->setMaxLen(2048);
