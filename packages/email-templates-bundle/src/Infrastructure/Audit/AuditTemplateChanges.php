<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Audit;

use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Domain\Chain\Facts;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\EmailTemplatesBundle\Domain\Event\TemplateDraftDiscarded;
use Phil\EmailTemplatesBundle\Domain\Event\TemplatePublished;
use Phil\EmailTemplatesBundle\Domain\Event\TemplateReset;
use Phil\EmailTemplatesBundle\Domain\Event\TemplateSaved;

/**
 * Writes each change of a template to the trail of `phil/audit-bundle`: who saved, reverted, published, discarded or
 * reset which template, for which site and locale, at which version — identifiers only, never a source.
 */
final readonly class AuditTemplateChanges
{
    public const string SUBJECT = 'email_template';

    public function __construct(
        private AuditTrail $trail,
        private string $segment = 'email_templates',
    ) {
    }

    public function saved(TemplateSaved $event): void
    {
        $this->trail->record(
            $this->segment,
            ['site' => $event->site, 'locale' => $event->locale, 'version' => $event->version, 'revision' => $event->revision, 'reverted_from' => $event->revertedFrom],
            Facts::of(null === $event->revertedFrom ? 'email_templates.saved' : 'email_templates.reverted', $event->author, Subject::of(self::SUBJECT, $event->key), $this->tenant($event->site)),
        );
    }

    public function published(TemplatePublished $event): void
    {
        $this->trail->record(
            $this->segment,
            ['site' => $event->site, 'locale' => $event->locale, 'version' => $event->version],
            Facts::of('email_templates.published', $event->author, Subject::of(self::SUBJECT, $event->key), $this->tenant($event->site)),
        );
    }

    public function discarded(TemplateDraftDiscarded $event): void
    {
        $this->trail->record(
            $this->segment,
            ['site' => $event->site, 'locale' => $event->locale, 'version' => $event->version],
            Facts::of('email_templates.discarded', $event->author, Subject::of(self::SUBJECT, $event->key), $this->tenant($event->site)),
        );
    }

    public function reset(TemplateReset $event): void
    {
        $this->trail->record(
            $this->segment,
            ['site' => $event->site, 'locale' => $event->locale],
            Facts::of('email_templates.reset', $event->author, Subject::of(self::SUBJECT, $event->key), $this->tenant($event->site)),
        );
    }

    private function tenant(string $site): ?string
    {
        return '' === $site ? null : $site;
    }
}
