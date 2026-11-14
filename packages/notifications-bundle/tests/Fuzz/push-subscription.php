<?php

declare(strict_types=1);

/*
 * A browser's subscription as anyone could send it: whatever the JSON, it is read into a subscription or refused with
 * InvalidPushSubscription, never another error. A subscription read has an HTTPS endpoint, its keys of the right length,
 * and reads back the same from its own fields.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\NotificationsBundle\Application\Exception\InvalidPushSubscription;
use Phil\NotificationsBundle\Application\WebPush\PushSubscription;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $subscription = PushSubscription::fromJson($input);
    } catch (InvalidPushSubscription) {
        return;
    }

    if (!str_starts_with($subscription->endpoint, 'https://') || 87 !== strlen($subscription->publicKey) || 22 !== strlen($subscription->authToken)) {
        throw new LogicException('A subscription read is an HTTPS endpoint and two keys of the right length.');
    }

    $again = PushSubscription::of($subscription->endpoint, $subscription->publicKey, $subscription->authToken, $subscription->contentEncoding);

    if ($again->id() !== $subscription->id() || $again->host !== $subscription->host) {
        throw new LogicException('A subscription reads back the same from its own fields.');
    }
});
$config->setMaxLen(4200);
