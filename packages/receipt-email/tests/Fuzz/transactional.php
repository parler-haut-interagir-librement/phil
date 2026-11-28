<?php

declare(strict_types=1);

/*
 * The transactional checks on HTML nobody wrote: `TransactionalCheck::check()` returns the receipt or throws
 * NotTransactional; nothing else escapes, whatever the markup, the styles or the links.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\ReceiptEmail\Application\RenderedReceipt;
use Phil\ReceiptEmail\Application\TransactionalCheck;
use Phil\ReceiptEmail\Domain\Exception\NotTransactional;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$check = new TransactionalCheck();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($check): void {
    $receipt = new RenderedReceipt('Receipt', '', $input, 'Paid');

    try {
        if ($receipt !== $check->check($receipt)) {
            throw new LogicException('The check returns the receipt it was given.');
        }
    } catch (NotTransactional) {
        // A refusal is an answer.
    }
});
$config->setMaxLen(4096);
