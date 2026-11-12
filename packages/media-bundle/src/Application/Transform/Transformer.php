<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application\Transform;

/**
 * One transformation of a format: given the image as it is, the step that draws it anew.
 */
interface Transformer
{
    public function step(Geometry $image): Step;

    /**
     * What the transformation is, as configured: `['type' => 'crop', 'width' => 300, …]`.
     *
     * @return array<string, bool|int|string|null>
     */
    public function definition(): array;
}
