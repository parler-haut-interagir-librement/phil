<?php

declare(strict_types=1);

namespace Phil\Lti\Message;

use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Support\Fields;
use Phil\Url\Url;

/**
 * The claims of LTI that a launch carries, typed: what the platform builds and signs, what the tool reads once the token
 * verifies. A resource link launch names its resource link; a deep linking launch carries its settings.
 */
final readonly class LaunchMessage
{
    /**
     * The most custom parameters read.
     */
    public const int MAX_CUSTOM = 100;

    /**
     * @param array<string, string> $custom          the custom parameters, after the platform substituted its variables
     * @param list<string>          $roleScopeMentor the subjects a mentor launch is about
     */
    public function __construct(
        public MessageType $type,
        public string $deploymentId,
        public Url $targetLinkUri,
        public Person $person,
        public Roles $roles,
        public ?ResourceLink $resourceLink = null,
        public ?Context $context = null,
        public ?ToolPlatform $platform = null,
        public ?LaunchPresentation $presentation = null,
        public array $custom = [],
        public ?AgsEndpoint $ags = null,
        public ?NrpsEndpoint $nrps = null,
        public ?DeepLinkingSettings $deepLinking = null,
        public array $roleScopeMentor = [],
    ) {
        if (MessageType::DeepLinkingResponse === $type) {
            throw InvalidToken::because('A launch is a resource link or a deep linking request.');
        }

        if (MessageType::ResourceLink === $type && !$resourceLink instanceof ResourceLink) {
            throw InvalidToken::because('A resource link launch names its resource link.');
        }

        if (MessageType::DeepLinking === $type && !$deepLinking instanceof DeepLinkingSettings) {
            throw InvalidToken::because('A deep linking launch carries its deep linking settings.');
        }

        if ('' === $deploymentId || \strlen($deploymentId) > 255) {
            throw InvalidToken::because('The deployment is named by 1 to 255 bytes.');
        }
    }

    /**
     * The message of the claims of a token, already verified.
     *
     * @param array<array-key, mixed> $claims
     *
     * @throws InvalidToken when a claim is missing or malformed
     */
    public static function fromClaims(array $claims, bool $allowLocalhost = false): self
    {
        $fields = new Fields($claims, allowLocalhost: $allowLocalhost);

        if (Claim::LTI_VERSION !== $fields->optionalString(Claim::VERSION, 16)) {
            throw InvalidToken::because(\sprintf('A message of LTI 1.3 carries the version "%s".', Claim::LTI_VERSION));
        }

        $type = MessageType::tryFrom($fields->string(Claim::MESSAGE_TYPE, 64)) ?? throw InvalidToken::because('The message type is not one of LTI 1.3.');

        if (!\is_array($claims[Claim::ROLES] ?? null)) {
            throw InvalidToken::because('A launch carries the roles of the person, an empty list when it has none.');
        }

        $resourceLink = $fields->optionalObject(Claim::RESOURCE_LINK);
        $context = $fields->optionalObject(Claim::CONTEXT);
        $platform = $fields->optionalObject(Claim::TOOL_PLATFORM);
        $presentation = $fields->optionalObject(Claim::LAUNCH_PRESENTATION);
        $ags = $fields->optionalObject(Claim::ENDPOINT);
        $nrps = $fields->optionalObject(Claim::NAMES_ROLE_SERVICE);
        $deepLinking = $fields->optionalObject(Claim::DEEP_LINKING_SETTINGS);

        return new self(
            $type,
            $fields->string(Claim::DEPLOYMENT_ID, 255),
            $fields->url(Claim::TARGET_LINK_URI),
            Person::read($fields),
            Roles::of($fields->stringList(Claim::ROLES, 64)),
            $resourceLink instanceof Fields ? ResourceLink::read($resourceLink) : null,
            $context instanceof Fields ? Context::read($context) : null,
            $platform instanceof Fields ? ToolPlatform::read($platform) : null,
            $presentation instanceof Fields ? LaunchPresentation::read($presentation) : null,
            $fields->stringMap(Claim::CUSTOM, self::MAX_CUSTOM),
            $ags instanceof Fields ? AgsEndpoint::read($ags) : null,
            $nrps instanceof Fields ? NrpsEndpoint::read($nrps) : null,
            $deepLinking instanceof Fields ? DeepLinkingSettings::read($deepLinking) : null,
            $fields->stringList(Claim::ROLE_SCOPE_MENTOR, 64),
        );
    }

    /**
     * The claims of LTI of the message, without those of OpenID Connect that the platform adds when it signs it.
     *
     * @return array<string, mixed>
     */
    public function toClaims(): array
    {
        $claims = $this->person->toClaims() + [
            Claim::MESSAGE_TYPE => $this->type->value,
            Claim::VERSION => Claim::LTI_VERSION,
            Claim::DEPLOYMENT_ID => $this->deploymentId,
            Claim::TARGET_LINK_URI => $this->targetLinkUri->toString(),
            Claim::ROLES => $this->roles->toClaim(),
            Claim::RESOURCE_LINK => $this->resourceLink?->toClaim(),
            Claim::CONTEXT => $this->context?->toClaim(),
            Claim::TOOL_PLATFORM => $this->platform?->toClaim(),
            Claim::LAUNCH_PRESENTATION => $this->presentation?->toClaim(),
            Claim::CUSTOM => [] === $this->custom ? null : $this->custom,
            Claim::ENDPOINT => $this->ags?->toClaim(),
            Claim::NAMES_ROLE_SERVICE => $this->nrps?->toClaim(),
            Claim::DEEP_LINKING_SETTINGS => $this->deepLinking?->toClaim(),
            Claim::ROLE_SCOPE_MENTOR => [] === $this->roleScopeMentor ? null : $this->roleScopeMentor,
        ];

        return array_filter($claims, static fn (mixed $value): bool => null !== $value);
    }
}
