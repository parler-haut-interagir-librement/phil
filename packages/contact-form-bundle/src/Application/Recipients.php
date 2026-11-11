<?php

declare(strict_types=1);

namespace Phil\ContactFormBundle\Application;

/**
 * Who receives a message. The bundle's recipients by default; an application whose pages write to different people —
 * an organisation of a directory, a shop of a network — decorates or replaces this service.
 */
interface Recipients
{
    /**
     * @return non-empty-list<string> email addresses
     */
    public function for(ContactMessage $message): array;
}
