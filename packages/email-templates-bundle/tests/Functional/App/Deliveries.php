<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Tests\Functional\App;

use Override;
use Phil\EmailTemplatesBundle\Application\Contract\DeliveryResolver;
use Phil\EmailTemplatesBundle\Application\Delivery;
use Symfony\Component\Mime\Address;

/**
 * The recipient and the values of a reference, as an application finds them when the message is handled.
 */
final class Deliveries implements DeliveryResolver
{
    #[Override]
    public function resolve(string $template, string $reference): Delivery
    {
        return new Delivery([new Address('ada@example.com')], ['name' => 'Ada '.$reference], 'en', ['vip']);
    }
}
