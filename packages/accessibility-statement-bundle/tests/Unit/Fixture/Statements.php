<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Tests\Unit\Fixture;

/**
 * Statements as the configuration writes them.
 */
final class Statements
{
    private function __construct()
    {
    }

    /**
     * @return array<string, mixed>
     */
    public static function minimal(int $version = 1): array
    {
        return [
            'version' => $version,
            'published_at' => '2026-11-01T00:00:00+00:00',
            'standard' => 'wcag-2.2',
            'level' => 'AA',
            'status' => 'full',
            'feedback' => ['email' => 'accessibility@example.com'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function complete(): array
    {
        return [
            'version' => 3,
            'published_at' => '2026-11-01T00:00:00+00:00',
            'standard' => 'wcag-2.2',
            'level' => 'AA',
            'status' => 'partial',
            'entity' => 'Example Org',
            'scope' => ['en' => 'the site example.com', 'fr' => 'le site example.com'],
            'feedback' => [
                'email' => 'accessibility@example.com',
                'phone' => '+1 514 555 0100',
                'url' => '/feedback',
                'postal_address' => ['en' => "1 Main Street\nMontréal"],
                'response_days' => 10,
            ],
            'non_conformities' => [
                [
                    'criterion' => '1.4.3',
                    'reason' => 'non-compliance',
                    'description' => ['en' => 'Grey text on the event pages.', 'fr' => 'Texte gris des pages d’événements.'],
                    'alternative' => ['en' => 'Ask us for the text.'],
                    'fix_by' => '2027-03-31T00:00:00+00:00',
                    'occurrences' => 12,
                    'from_audit' => true,
                ],
                ['criterion' => '1.1.1', 'occurrences' => 1, 'from_audit' => true],
                ['criterion' => '1.2.2', 'reason' => 'disproportionate-burden', 'description' => ['en' => 'Videos before 2020.']],
                ['criterion' => '2.1.1', 'reason' => 'out-of-scope', 'description' => ['en' => 'The map of a partner.']],
            ],
            'alternatives' => [['en' => 'Call us and we read the page to you.', 'fr' => 'Appelez-nous, nous vous lisons la page.']],
            'enforcement' => [
                'procedure' => ['en' => 'If our answer does not satisfy you, complain to the commissioner.'],
                'authority' => ['en' => 'The Accessibility Commissioner'],
                'url' => 'https://example.org/complaints',
            ],
            'evaluation' => ['evaluated_at' => '2026-10-30T00:00:00+00:00', 'method' => 'automated', 'pages' => 2, 'report_url' => '/audit.html'],
        ];
    }
}
