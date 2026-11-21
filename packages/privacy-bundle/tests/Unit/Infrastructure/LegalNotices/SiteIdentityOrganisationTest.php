<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\LegalNotices;

use Phil\LegalNotices\SiteIdentity;
use Phil\PrivacyBundle\Domain\Incident\NoticeOrganisation;
use Phil\PrivacyBundle\Infrastructure\LegalNotices\SiteIdentityOrganisation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SiteIdentityOrganisation::class)]
final class SiteIdentityOrganisationTest extends TestCase
{
    public function testThePublisherAndThePrivacyOfficerGiveTheNotices(): void
    {
        $identity = new SiteIdentity([
            'site_name' => 'Atelier Fictif',
            'publisher_name' => 'Atelier Fictif inc.',
            'publisher_registration' => '1170000000',
            'dpo_name' => 'Camille Exemple',
            'dpo_contact' => 'vie-privee@atelier-fictif.example',
            'contact_email' => 'bonjour@atelier-fictif.example',
        ]);

        self::assertEquals(new NoticeOrganisation('Atelier Fictif inc.', '1170000000', 'Camille Exemple', 'vie-privee@atelier-fictif.example'), new SiteIdentityOrganisation($identity)->organisation());
        self::assertEquals(new NoticeOrganisation(), new SiteIdentityOrganisation(new SiteIdentity(['site_name' => 'Atelier Fictif']))->organisation());
    }
}
