<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit\Infrastructure;

use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Catalog\Requirement;
use Phil\LicensingBundle\Domain\Catalog\ResourceKind;
use Phil\LicensingBundle\Domain\Compliance\ViolationCode;
use Phil\LicensingBundle\Domain\Contract\Licensable;
use Phil\LicensingBundle\Domain\Model\LicenseDeclaration;
use Phil\LicensingBundle\Domain\ValueObject\Attribution;
use Phil\LicensingBundle\Domain\ValueObject\License;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;
use Phil\LicensingBundle\Domain\ValueObject\Violation;
use Phil\LicensingBundle\Infrastructure\Twig\AttributionRenderer;
use Phil\LicensingBundle\Infrastructure\Twig\LicensingExtension;
use Phil\LicensingBundle\Tests\Unit\Support\Declarations;
use Phil\LicensingBundle\Tests\Unit\Support\InMemoryDeclarations;
use Phil\LicensingBundle\Tests\Unit\Support\Twig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(AttributionRenderer::class)]
#[CoversClass(LicensingExtension::class)]
final class TwigTest extends TestCase
{
    public function testThePageOfACompleteDeclaration(): void
    {
        $declarations = new InMemoryDeclarations();
        $declarations->add(Declarations::complete());
        [$twig, $renderer] = Twig::environment($declarations);

        $html = $renderer->html(SubjectReference::of('book', '42'));

        self::assertSame($this->expected(), $this->squeeze($html));
        self::assertSame($html, $twig->createTemplate('{{ phil_licensing_attribution(subject) }}')->render(['subject' => SubjectReference::of('book', '42')]));
        self::assertSame($html, $renderer->html(Declarations::complete()));
        self::assertSame($html, $renderer->html($this->licensable()));
        self::assertSame('book', $renderer->page($this->licensable())?->subject->type);
        self::assertSame('', $renderer->html(SubjectReference::of('book', '43')));
        self::assertNull($renderer->page(SubjectReference::of('book', '43')));
    }

    public function testThePageInFrench(): void
    {
        [, $renderer] = Twig::environment(new InMemoryDeclarations(), 'fr');
        $html = $this->squeeze($renderer->html(Declarations::complete()));

        self::assertStringContainsString('<h2>Licence</h2> <p class="phil-licensing-license"> Cette œuvre est publiée sous la Licence ORC. <a href="https://paizo.com/orclicense" rel="license">', $html);
        self::assertStringContainsString('<h3>Matériel réservé</h3>', $html);
        self::assertStringContainsString('<h3>Sources textuelles</h3>', $html);
        self::assertSame($html, $this->squeeze($renderer->html(Declarations::complete(), 'fr')));
        self::assertStringContainsString('This work is published under the ORC License.', $renderer->html(Declarations::complete(), 'en'));
    }

    public function testEverySectionOfThePage(): void
    {
        $declaration = LicenseDeclaration::declare(SubjectReference::of('book', '1'), License::of('house', 'House', []), Declarations::at());
        $declaration->describe(null, null, 'Logos & names', "Chapter 3\nChapter 4", Declarations::at());
        $declaration->includeLicenseText("Line 1\nLine <2>", Declarations::at());
        $declaration->attribute([Attribution::of(ResourceKind::Map, 'Map', 'house'), Attribution::of(ResourceKind::Other, 'Icons', 'nope')], Declarations::at());
        [, $renderer] = Twig::environment(new InMemoryDeclarations());

        self::assertSame(
            '<section class="phil-licensing"> <h2>License</h2> <p class="phil-licensing-license">This work names the license “house”, which is not in the catalog.</p>'
            .' <h3>Content offered under the license</h3> <p>Chapter 3<br /> Chapter 4</p>'
            .' <h3>Product Identity</h3> <p>Logos &amp; names</p>'
            .' <h2>Attributions</h2> <h3>Maps</h3> <ul class="phil-licensing-resources"> <li> Map. License: house. </li> </ul>'
            .' <h3>Other resources</h3> <ul class="phil-licensing-resources"> <li> Icons. License: nope. </li> </ul>'
            .' <h2>License text</h2> <div class="phil-licensing-text">Line 1<br /> Line &lt;2&gt;</div> </section>',
            $this->squeeze($renderer->html($declaration)),
        );

        $located = Declarations::of(LicenseCatalog::CC0);
        self::assertSame(
            '<section class="phil-licensing"> <h2>License</h2> <p class="phil-licensing-license"> This work is published under the CC0 1.0 Universal. <a href="https://creativecommons.org/publicdomain/zero/1.0/" rel="license">https://creativecommons.org/publicdomain/zero/1.0/</a> </p> </section>',
            $this->squeeze($renderer->html($located)),
        );
        self::assertSame(
            '<section class="phil-licensing"> <h2>License</h2> <p class="phil-licensing-license"> This work is published under the Custom license. </p> </section>',
            $this->squeeze($renderer->html(Declarations::of(LicenseCatalog::CUSTOM))),
        );
    }

    public function testTheOtherFunctions(): void
    {
        [$twig] = Twig::environment(new InMemoryDeclarations(), 'fr');

        self::assertSame(
            'Licence ORC|ORC License|Licence ORC|missing_credit: « SRD » est sous la Licence ORC, qui demande de créditer ses auteurs.|L’œuvre n’a pas de déclaration de licence.|orc',
            $twig->createTemplate("{{ phil_licensing_license_name('orc') }}|{{ phil_licensing_license_name('orc', 'en') }}|{{ phil_licensing_license_name(license) }}|missing_credit: {{ phil_licensing_violation(violation) }}|{{ phil_licensing_violation(missing, 'fr') }}|{{ phil_licensing_page(declaration).licenseCode }}")
                ->render([
                    'license' => new LicenseCatalog()->get('orc'),
                    'violation' => new Violation(ViolationCode::MissingCredit, 'orc', 0, 'SRD'),
                    'missing' => new Violation(ViolationCode::NoDeclaration),
                    'declaration' => Declarations::complete(),
                ]),
        );
        self::assertStringContainsString('Licence ORC', $twig->createTemplate('{{ phil_licensing_attribution(declaration, "fr") }}')->render(['declaration' => Declarations::complete()]));
        self::assertStringContainsString('ORC License', $twig->createTemplate('{{ phil_licensing_attribution(declaration, "en") }}')->render(['declaration' => Declarations::complete()]));
    }

    public function testATemplateOfTheApplication(): void
    {
        $twig = new Environment(new ArrayLoader(['mine.html.twig' => '{{ page.licenseCode }} {{ locale ?? "none" }}']));
        $renderer = new AttributionRenderer($twig, new InMemoryDeclarations(), new LicenseCatalog(), 'mine.html.twig');

        self::assertSame('orc none', $renderer->html(Declarations::complete()));
        self::assertSame('orc fr', $renderer->html(Declarations::complete(), 'fr'));
    }

    public function testTheTranslationsSayEverything(): void
    {
        $english = require \dirname(__DIR__, 3).'/translations/phil_licensing.en.php';
        $french = require \dirname(__DIR__, 3).'/translations/phil_licensing.fr.php';
        self::assertIsArray($english);
        self::assertIsArray($french);

        $keys = [
            ...array_map(static fn (License $license): string => 'license.'.$license->code, LicenseCatalog::builtIn()),
            ...array_map(static fn (Requirement $requirement): string => 'requirement.'.$requirement->value, Requirement::cases()),
            ...array_map(static fn (ResourceKind $kind): string => 'kind.'.$kind->value, ResourceKind::cases()),
            ...array_map(static fn (ViolationCode $code): string => $code->messageKey(), ViolationCode::cases()),
        ];

        self::assertSame(array_keys($english), array_keys($french));

        foreach ($keys as $key) {
            self::assertArrayHasKey($key, $english);
        }
    }

    private function expected(): string
    {
        return '<section class="phil-licensing"> <h2>License</h2> <p class="phil-licensing-license"> This work is published under the ORC License. <a href="https://paizo.com/orclicense" rel="license">https://paizo.com/orclicense</a> </p>'
            .' <p class="phil-licensing-reference">License reference: Library of Congress TX 9-307-067.</p>'
            .' <h3>Reserved Material</h3> <p>The names of the setting, its maps and its art.</p>'
            .' <h2>Attributions</h2>'
            .' <h3>Text sources</h3> <ul class="phil-licensing-resources"> <li> Example Reference Document, by Example Games. License: ORC License. <a href="https://paizo.com/orclicense" rel="license">https://paizo.com/orclicense</a> </li> </ul>'
            .' <h3>Images</h3> <ul class="phil-licensing-resources"> <li> <a href="https://example.com/cover">Cover &lt;art&gt;</a>, by Jane Doe. License: Creative Commons Attribution 4.0 International. <a href="https://creativecommons.org/licenses/by/4.0/" rel="license">https://creativecommons.org/licenses/by/4.0/</a> <br>Changes: Cropped </li> </ul>'
            .' <h3>Fonts</h3> <ul class="phil-licensing-resources"> <li> Example Serif. License: CC0 1.0 Universal. <a href="https://creativecommons.org/publicdomain/zero/1.0/" rel="license">https://creativecommons.org/publicdomain/zero/1.0/</a> </li> </ul>'
            .' <h2>Copyright notices</h2> <p class="phil-licensing-copyright">© 2026 Example Press</p> <p class="phil-licensing-copyright">© 2025 Example Games<br /> All rights reserved.</p> <p class="phil-licensing-copyright">© 2024 Jane Doe</p> </section>';
    }

    private function squeeze(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $html));
    }

    private function licensable(): Licensable
    {
        return new class implements Licensable {
            public function licenseSubject(): SubjectReference
            {
                return SubjectReference::of('book', '42');
            }
        };
    }
}
