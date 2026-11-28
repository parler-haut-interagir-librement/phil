<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Content\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Phil\ContentBundle\Domain\Content\AbstractContentRoot;
use Symfony\Component\Uid\Uuid;

/**
 * The root of one template of one site, or of the shared scope, in one locale: what its stages and versions share. Its
 * rows are `EmailTemplateDimension`, the localized one and the unlocalized one of each stage and version, which
 * `phil/content-bundle` stores and publishes.
 *
 * The site, the key and the locale are unique together. The shared scope is the empty site.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: self::TABLE.'_scope', columns: ['site', 'template_key', 'locale'])]
class EmailTemplateContent extends AbstractContentRoot
{
    public const string TABLE = 'phil_email_template_content';

    /** The kind of content, as `phil/content-bundle` names it in references and commands. */
    public const string RESOURCE_KEY = 'phil_email_template';

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: Types::GUID)]
    private string $id;

    public function __construct(
        #[ORM\Column(name: 'template_key', length: 64)]
        private string $templateKey,
        #[ORM\Column(name: 'site', length: 64)]
        private string $site,
        #[ORM\Column(name: 'locale', length: 16)]
        private string $locale,
    ) {
        $this->id = Uuid::v7()->toRfc4122();
    }

    #[Override]
    public static function dimensionContentClass(): string
    {
        return EmailTemplateDimension::class;
    }

    #[Override]
    public static function resourceKey(): string
    {
        return self::RESOURCE_KEY;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function templateKey(): string
    {
        return $this->templateKey;
    }

    public function site(): string
    {
        return $this->site;
    }

    public function locale(): string
    {
        return $this->locale;
    }
}
