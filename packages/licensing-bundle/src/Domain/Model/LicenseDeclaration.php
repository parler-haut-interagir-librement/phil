<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\LicensingBundle\Domain\Exception\InvalidLicensing;
use Phil\LicensingBundle\Domain\Text;
use Phil\LicensingBundle\Domain\ValueObject\Attribution;
use Phil\LicensingBundle\Domain\ValueObject\License;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;
use Symfony\Component\Uid\Uuid;

/**
 * The license a work is published under, attached to its subject by type and identifier, with what the license asks
 * the work to declare and the attribution of each resource it uses.
 *
 * One subject has one declaration. The license is kept by its code, so that a license the application removes from
 * its catalog shows as a violation rather than a failure to read.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_licensing_declaration')]
#[ORM\UniqueConstraint(name: 'phil_licensing_declaration_subject', columns: ['subject_type', 'subject_id'])]
final class LicenseDeclaration
{
    public const int MAX_ATTRIBUTIONS = 500;

    public const int NOTICE_LENGTH = 4000;

    public const int DECLARATION_LENGTH = 20000;

    public const int LICENSE_TEXT_LENGTH = 100000;

    #[ORM\Column(name: 'license', length: 64)]
    private string $license;

    #[ORM\Column(name: 'notice', type: 'text', nullable: true)]
    private ?string $notice = null;

    #[ORM\Column(name: 'reserved_material', type: 'text', nullable: true)]
    private ?string $reservedMaterial = null;

    #[ORM\Column(name: 'product_identity', type: 'text', nullable: true)]
    private ?string $productIdentity = null;

    #[ORM\Column(name: 'designated_content', type: 'text', nullable: true)]
    private ?string $designatedContent = null;

    #[ORM\Column(name: 'license_text', type: 'text', nullable: true)]
    private ?string $licenseText = null;

    /** @var list<array<array-key, mixed>> */
    #[ORM\Column(name: 'attributions', type: 'json')]
    private array $attributions = [];

    #[ORM\Column(name: 'changed_at')]
    private DateTimeImmutable $changedAt;

    #[ORM\Version]
    #[ORM\Column(name: 'version', type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'subject_type', length: 128)]
        private readonly string $subjectType,
        #[ORM\Column(name: 'subject_id', length: 64)]
        private readonly string $subjectId,
        License $license,
        #[ORM\Column(name: 'declared_at')]
        public readonly DateTimeImmutable $declaredAt,
    ) {
        $this->license = $license->code;
        $this->changedAt = $declaredAt;
    }

    public static function declare(SubjectReference $subject, License $license, DateTimeImmutable $at): self
    {
        return new self(Uuid::v7()->toRfc4122(), $subject->type, $subject->id, $license, $at);
    }

    public function subject(): SubjectReference
    {
        return SubjectReference::of($this->subjectType, $this->subjectId);
    }

    /**
     * The code of the license in the catalog.
     */
    public function license(): string
    {
        return $this->license;
    }

    /**
     * The work's own copyright notice or credit line: "© 2026 Example Press".
     */
    public function notice(): ?string
    {
        return $this->notice;
    }

    /**
     * The elements of the work kept out of the license: names, trade dress, artwork, setting.
     */
    public function reservedMaterial(): ?string
    {
        return $this->reservedMaterial;
    }

    public function productIdentity(): ?string
    {
        return $this->productIdentity;
    }

    /**
     * The portions of the work offered under the license: its open content, or the material designated as licensed.
     */
    public function designatedContent(): ?string
    {
        return $this->designatedContent;
    }

    /**
     * The full text of the license, or the work's own terms.
     */
    public function licenseText(): ?string
    {
        return $this->licenseText;
    }

    /**
     * @return list<Attribution>
     */
    public function attributions(): array
    {
        return array_map(Attribution::fromArray(...), $this->attributions);
    }

    public function changedAt(): DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function relicense(License $license, DateTimeImmutable $at): void
    {
        $this->license = $license->code;
        $this->changedAt = $at;
    }

    /**
     * @throws InvalidLicensing
     */
    public function describe(
        ?string $notice,
        ?string $reservedMaterial,
        ?string $productIdentity,
        ?string $designatedContent,
        DateTimeImmutable $at,
    ): void {
        $this->notice = Text::optional($notice, self::NOTICE_LENGTH, 'notice');
        $this->reservedMaterial = Text::optional($reservedMaterial, self::DECLARATION_LENGTH, 'reserved material');
        $this->productIdentity = Text::optional($productIdentity, self::DECLARATION_LENGTH, 'product identity');
        $this->designatedContent = Text::optional($designatedContent, self::DECLARATION_LENGTH, 'designated content');
        $this->changedAt = $at;
    }

    /**
     * @throws InvalidLicensing
     */
    public function includeLicenseText(?string $text, DateTimeImmutable $at): void
    {
        $this->licenseText = Text::optional($text, self::LICENSE_TEXT_LENGTH, 'license text');
        $this->changedAt = $at;
    }

    /**
     * Replaces the attributions, in the order given.
     *
     * @param list<Attribution> $attributions
     *
     * @throws InvalidLicensing when there are more than 500
     */
    public function attribute(array $attributions, DateTimeImmutable $at): void
    {
        if (\count($attributions) > self::MAX_ATTRIBUTIONS) {
            throw InvalidLicensing::because(\sprintf('A declaration holds %d attributions at most.', self::MAX_ATTRIBUTIONS));
        }

        $this->attributions = array_map(static fn (Attribution $attribution): array => $attribution->toArray(), $attributions);
        $this->changedAt = $at;
    }
}
