<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Unit\Infrastructure;

use Phil\ApiContractBundle\Infrastructure\Http\ApiPlatformRequests;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(ApiPlatformRequests::class)]
final class ApiPlatformRequestsTest extends TestCase
{
    public function testARequestWithEitherAttributeIsHandledByApiPlatform(): void
    {
        self::assertSame(['_api_resource_class', '_api_operation'], [ApiPlatformRequests::RESOURCE_CLASS, ApiPlatformRequests::OPERATION]);

        $resource = Request::create('/api/books');
        $resource->attributes->set('_api_resource_class', 'App\Entity\Book');
        self::assertTrue(ApiPlatformRequests::handles($resource));

        $operation = Request::create('/api/books');
        $operation->attributes->set('_api_operation', new stdClass());
        self::assertTrue(ApiPlatformRequests::handles($operation));
    }

    public function testAnyOtherRequestIsNot(): void
    {
        self::assertFalse(ApiPlatformRequests::handles(Request::create('/api/orders')));

        $other = Request::create('/api/orders');
        $other->attributes->set('_api_operation_name', '_api_/books{._format}_get_collection');
        $other->attributes->set('_route', 'orders');
        self::assertFalse(ApiPlatformRequests::handles($other));

        $empty = Request::create('/api/orders');
        $empty->attributes->set('_api_resource_class', null);
        $empty->attributes->set('_api_operation', null);
        self::assertFalse(ApiPlatformRequests::handles($empty));
    }
}
