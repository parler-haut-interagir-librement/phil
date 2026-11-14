<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Application;

/**
 * The identifier of the person sending a form, when the application knows them — the identifier its access and
 * erasure requests are made with —, or null for a visitor.
 */
interface CurrentSubject
{
    public function current(): ?string;
}
