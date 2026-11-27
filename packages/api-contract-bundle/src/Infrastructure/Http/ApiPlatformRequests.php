<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * Tells the requests API Platform handles from the others, by the attributes its routes and listeners put on them:
 * `_api_resource_class` or `_api_operation`. API Platform answers its own errors — a `422` with its violations among
 * them —, keys and announces its own operations, so the listeners of the bundle leave these requests to it. Nothing of
 * API Platform is loaded: the attributes are read by name.
 */
final class ApiPlatformRequests
{
    /** The attribute that names the resource class of an API Platform route. */
    public const string RESOURCE_CLASS = '_api_resource_class';

    /** The attribute that holds the API Platform operation of the request. */
    public const string OPERATION = '_api_operation';

    private function __construct()
    {
    }

    public static function handles(Request $request): bool
    {
        return null !== $request->attributes->get(self::RESOURCE_CLASS) || null !== $request->attributes->get(self::OPERATION);
    }
}
