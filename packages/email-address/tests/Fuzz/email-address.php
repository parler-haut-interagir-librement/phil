<?php

declare(strict_types=1);

/*
 * The parser on values nobody wrote: whatever a form posts, EmailAddress::parse() gives an address whose ASCII form
 * parses again to the same address, or refuses it with InvalidEmailAddress; never another error nor a warning. The
 * most permissive policy reaches every branch.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\EmailAddress\EmailAddress;
use Phil\EmailAddress\Exception\InvalidEmailAddress;
use Phil\EmailAddress\SyntaxPolicy;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$policy = new SyntaxPolicy()->withInternationalLocalPart(true)->withQuotedLocalPart(true)->withIpLiteral(true);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($policy): void {
    try {
        $address = EmailAddress::parse($input, $policy);
    } catch (InvalidEmailAddress) {
        return;
    }

    if (!EmailAddress::parse($address->toAscii(), $policy)->equals($address)) {
        throw new LogicException('An accepted address does not parse again from its ASCII form.');
    }
});
