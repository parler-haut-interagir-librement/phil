<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Twig;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Incident\Incident;
use Phil\PrivacyBundle\Domain\Incident\IncidentRevision;
use Phil\PrivacyBundle\Domain\Incident\NoticeOrganisation;
use Phil\PrivacyBundle\Domain\Incident\PublicNoticeReason;
use Phil\PrivacyBundle\Domain\Subject\DataCategory;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * The registers and the organisation the drafts of the notices are written from, and a Twig environment with the
 * bundle's templates and texts: a fictitious business, Atelier Fictif inc.
 */
final class NoticeFixtures
{
    public const string ID = '0123456789abcdef0123456789abcdef';

    public const string SNAPSHOTS = __DIR__.'/../../../Fixtures/incident-notices';

    public static function twig(): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__.'/../../../../templates', 'PhilPrivacy');

        $translator = new Translator('fr');
        $translator->addLoader('php', new PhpFileLoader());

        foreach (['fr', 'en'] as $locale) {
            $translator->addResource('php', __DIR__.'/../../../../translations/phil_privacy.'.$locale.'.php', $locale, 'phil_privacy');
        }

        $twig = new Environment($loader, ['strict_variables' => true, 'autoescape' => 'html']);
        $twig->addExtension(new TranslationExtension($translator));

        return $twig;
    }

    public static function organisation(): NoticeOrganisation
    {
        return new NoticeOrganisation('Atelier Fictif inc.', '1170000000', 'Camille Exemple, responsable de la protection des renseignements personnels', 'vie-privee@atelier-fictif.example, 514 555-0100');
    }

    /**
     * A line that answers every paragraph the register keeps, notified to the Commission and to the people.
     */
    public static function complete(?PublicNoticeReason $reason = null): Incident
    {
        return new Incident(
            self::ID,
            'A mailing list was exported to a shared folder that was public by mistake.',
            new DateTimeImmutable('2026-11-10T14:00:00Z'),
            new DateTimeImmutable('2026-11-08T05:00:00Z'),
            [DataCategory::fromString('email'), DataCategory::fromString('person_name')],
            120,
            true,
            'Folder removed, link revoked, sharing settings reviewed.',
            new DateTimeImmutable('2026-11-11T15:30:00Z'),
            new DateTimeImmutable('2026-11-12T05:00:00Z'),
            information: 'Names and email addresses of the subscribers.',
            occurredUntil: new DateTimeImmutable('2026-11-10T05:00:00Z'),
            occurrenceApproximate: true,
            affectedApproximate: true,
            riskAssessment: 'The folder was indexed by search engines: misuse for phishing is likely.',
            publicNoticeAt: $reason instanceof PublicNoticeReason ? new DateTimeImmutable('2026-11-12T17:00:00Z') : null,
            publicNoticeReason: $reason,
        );
    }

    /**
     * A line just recorded: the circumstances and the discovery, nothing else.
     */
    public static function incomplete(?PublicNoticeReason $reason = null): Incident
    {
        return new Incident(
            self::ID,
            'A laptop holding a customer export was stolen.',
            new DateTimeImmutable('2026-11-10T14:00:00Z'),
            null,
            [],
            null,
            true,
            publicNoticeAt: $reason instanceof PublicNoticeReason ? new DateTimeImmutable('2026-11-12T17:00:00Z') : null,
            publicNoticeReason: $reason,
        );
    }

    /**
     * The corrections of the complete line: one before the notice to the Commission, two after it that change the
     * circumstances and the number of people, and the closing, which feeds no rubric.
     *
     * @return list<IncidentRevision>
     */
    public static function revisions(): array
    {
        return [
            new IncidentRevision(self::ID, 1, 'measures', '', new DateTimeImmutable('2026-11-11T10:00:00Z')),
            new IncidentRevision(self::ID, 2, 'summary', 'A list was exported.', new DateTimeImmutable('2026-11-13T16:00:00Z')),
            new IncidentRevision(self::ID, 2, 'affected', '80', new DateTimeImmutable('2026-11-13T16:00:00Z')),
            new IncidentRevision(self::ID, 3, 'closed_at', null, new DateTimeImmutable('2026-11-20T16:00:00Z')),
        ];
    }
}
