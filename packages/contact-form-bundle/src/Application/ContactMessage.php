<?php

declare(strict_types=1);

namespace Phil\ContactFormBundle\Application;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * What a visitor writes: who they are, how to answer them, and what they say. The form fills it; the mailer sends it.
 *
 * The email address is checked by RFC 5322 without its warnings, as strictly as Symfony Mime reads the `Reply-To` it
 * goes in: an address the browser's HTML5 rules let through, such as `a..b@example.com`, is a wrong field, never a
 * message the mailer cannot write.
 */
final class ContactMessage
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public ?string $name = null;

    #[Assert\NotBlank]
    #[Assert\Email(mode: Assert\Email::VALIDATION_MODE_STRICT)]
    #[Assert\Length(max: 254)]
    public ?string $email = null;

    #[Assert\Length(max: 150)]
    public ?string $subject = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 10, max: 5000)]
    public ?string $message = null;
}
