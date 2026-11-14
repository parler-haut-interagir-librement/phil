<?php

declare(strict_types=1);

use Phil\PrivacyBundle\Infrastructure\Routing\PrivacyRoutes;

/*
 * The pages of the bundle, for an application that mounts them itself under a prefix of its choice:
 *
 *     # config/routes/phil_privacy.yaml
 *     phil_privacy:
 *         resource: '@PhilPrivacyBundle/config/routes.php'
 *         prefix: /{_locale}/privacy-choices
 */
return PrivacyRoutes::collection();
