<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * The delivery controls of an activity, `<imsss:deliveryControls>`: whether its data is tracked, and whether its
 * content alone sets its completion and the satisfaction of its objectives — else an attempt that ends without them
 * counts as completed and satisfied.
 */
final readonly class DeliveryControls
{
    public function __construct(
        public bool $tracked = true,
        public bool $completionSetByContent = false,
        public bool $objectiveSetByContent = false,
    ) {
    }
}
