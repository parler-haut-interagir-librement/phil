<?php

declare(strict_types=1);

namespace Phil\Lti\Contract;

use Phil\Lti\Exception\ServiceFailed;
use Phil\Lti\Http\ServiceRequest;
use Phil\Lti\Http\ServiceResponse;

/**
 * Sends requests to the services of the other side — its token endpoint, its grades, its roster —, which need a body
 * and an `Authorization` header.
 */
interface ServiceClient
{
    /**
     * @throws ServiceFailed when the request cannot be sent, or the answer is not read in full within the limits
     */
    public function send(ServiceRequest $request): ServiceResponse;
}
