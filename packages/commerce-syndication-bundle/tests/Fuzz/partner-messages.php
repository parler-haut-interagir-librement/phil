<?php

declare(strict_types=1);

/*
 * The data of a parcel or a return as nobody wrote it: whatever JSON a partner signs, the reader returns the lines,
 * the parcel or the return, or refuses the message with `InvalidValue`, and never throws anything else nor warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\CommerceSyndicationBundle\Application\MessageData;
use Phil\CommerceSyndicationBundle\Domain\Model\Partner;
use Phil\CommerceSyndicationBundle\Domain\Model\PartnerReturn;
use Phil\CommerceSyndicationBundle\Domain\Model\PartnerShipment;
use Phil\CommerceSyndicationBundle\Domain\Partner\PartnerRole;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Exception\MoneyException;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$partner = Partner::of('supplier', 'The supplier', PartnerRole::Supplier, 'https://supplier.example/in', 'whpk_iojj3XQJ8ZX9UtstPLpdcspnCb8dlBIb83SIAbQPb1w=');
$at = new DateTimeImmutable('2026-11-12 10:00:00');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($partner, $at): void {
    try {
        $data = json_decode($input, true, 32, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
    } catch (JsonException) {
        // The verifier refuses a body that is not JSON before the data is read.
        return;
    }

    if (!is_array($data)) {
        return;
    }

    $read = new MessageData($data);

    try {
        PartnerShipment::of($partner, $read->string('shipment'), $read->string('order'), $read->quantities(), $at, $read->optionalString('carrier'), $read->optionalString('tracking'));
    } catch (InvalidValue) {
        // A message refused whole is a message.
    }

    try {
        PartnerReturn::request($partner, $read->string('return'), $read->string('order'), Currency::of($read->string('currency')), $read->quantities(), $at);
    } catch (InvalidValue|MoneyException) {
        // An unknown currency, like any field out of bounds, refuses the message.
    }
});
$config->setMaxLen(4096);
