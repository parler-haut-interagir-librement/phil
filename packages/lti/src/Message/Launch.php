<?php

declare(strict_types=1);

namespace Phil\Lti\Message;

/**
 * A launch the tool verified: the platform that sent it, by its issuer and the client it gave the tool, and the message.
 * `claims` keeps every claim of the token, those of other specifications included, for what this package does not type.
 */
final readonly class Launch
{
    /**
     * @param array<string, mixed> $claims
     */
    public function __construct(
        public string $issuer,
        public string $clientId,
        public LaunchMessage $message,
        public array $claims,
    ) {
    }

    public function type(): MessageType
    {
        return $this->message->type;
    }

    public function deploymentId(): string
    {
        return $this->message->deploymentId;
    }

    /**
     * The subject, the stable identifier of the person for this platform and client; null in an anonymous launch.
     */
    public function subject(): ?string
    {
        return $this->message->person->subject;
    }

    public function roles(): Roles
    {
        return $this->message->roles;
    }

    public function isDeepLinking(): bool
    {
        return MessageType::DeepLinking === $this->message->type;
    }

    /**
     * An identifier of the person unique across platforms: the issuer, the client and the subject, hashed, so that it
     * fits a column and says nothing of the platform. Null in an anonymous launch.
     */
    public function userKey(): ?string
    {
        $subject = $this->message->person->subject;

        return null === $subject ? null : hash('sha256', $this->issuer."\n".$this->clientId."\n".$subject);
    }
}
