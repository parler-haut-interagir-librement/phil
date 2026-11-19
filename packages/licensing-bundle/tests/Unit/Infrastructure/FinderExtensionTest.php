<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit\Infrastructure;

use Phil\LicensingBundle\Domain\Catalog\LicenseCatalog;
use Phil\LicensingBundle\Domain\Finder\CustomTerms;
use Phil\LicensingBundle\Domain\Finder\Modifications;
use Phil\LicensingBundle\Domain\Finder\Recommendation;
use Phil\LicensingBundle\Domain\Finder\TermsClause;
use Phil\LicensingBundle\Infrastructure\Translation\CustomTermsWriter;
use Phil\LicensingBundle\Infrastructure\Translation\LicenseNames;
use Phil\LicensingBundle\Infrastructure\Twig\FinderExtension;
use Phil\LicensingBundle\Tests\Unit\Support\Twig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

#[CoversClass(FinderExtension::class)]
#[CoversClass(CustomTermsWriter::class)]
final class FinderExtensionTest extends TestCase
{
    public function testCustomTermsAreWrittenInTheReadersLanguage(): void
    {
        $catalog = new LicenseCatalog();
        $terms = CustomTerms::of(false, false, Modifications::Forbidden);

        self::assertSame(
            "Terms of use of the work offered by Example Press\n\n"
            ."Anyone may copy the work and share it, in any medium or format.\n\n"
            ."The work may not be used for commercial purposes without the written permission of Example Press.\n\n"
            ."Adaptations of the work may not be shared without the written permission of Example Press.\n\n"
            ."Crediting Example Press is welcome but not required.\n\n"
            .'The work is offered as it is, without warranty of any kind; Example Press is not liable for its use.',
            new CustomTermsWriter(new LicenseNames($catalog, Twig::translator()))->write($terms, 'Example Press'),
        );

        $french = new CustomTermsWriter(new LicenseNames($catalog, Twig::translator()))->write(CustomTerms::of(true, true, Modifications::ShareAlike), 'Éditions Exemple', 'fr');
        self::assertStringStartsWith("Conditions d’utilisation de l’œuvre offerte par Éditions Exemple\n\nToute personne peut copier l’œuvre", $french);
        self::assertStringContainsString('à ces mêmes conditions seulement', $french);
        self::assertStringContainsString('Qui partage l’œuvre ou une adaptation crédite Éditions Exemple', $french);
    }

    public function testEveryClauseIsTranslated(): void
    {
        $translator = Twig::translator();

        foreach (['en', 'fr'] as $locale) {
            foreach (['terms.heading', ...array_map(static fn (TermsClause $clause): string => 'terms.'.$clause->value, TermsClause::cases())] as $key) {
                self::assertTrue($translator->getCatalogue($locale)->defines($key, LicenseNames::DOMAIN), $locale.' '.$key);
            }
        }
    }

    public function testTheRecommendationIsShown(): void
    {
        $twig = $this->twig();

        $html = $twig->render('page', ['recommendation' => Recommendation::catalog(LicenseCatalog::CC_BY_SA)]);
        self::assertStringContainsString('<h2>Recommended license</h2>', $html);
        self::assertStringContainsString('Publish the work under the Creative Commons Attribution-ShareAlike 4.0 International.', $html);
        self::assertStringContainsString('<a href="https://creativecommons.org/licenses/by-sa/4.0/" rel="license">', $html);
        self::assertStringNotContainsString('Terms of the custom license', $html);

        $imposed = $twig->render('page', ['recommendation' => Recommendation::catalog(LicenseCatalog::ORC, true), 'locale' => 'fr']);
        self::assertStringContainsString('Le matériel que l’œuvre adapte demande la Licence ORC : publiez l’œuvre sous celle-ci.', $imposed);

        $custom = $twig->render('page', ['recommendation' => Recommendation::custom(CustomTerms::of(false, false, Modifications::Allowed)), 'holder' => 'Example <Press>']);
        self::assertStringContainsString('Publish the work under the Custom license.', $custom);
        self::assertStringNotContainsString('rel="license"', $custom);
        self::assertStringContainsString('<h3>Terms of the custom license</h3>', $custom);
        self::assertStringContainsString('offered by Example &lt;Press&gt;<br />', $custom);

        self::assertSame("Terms of use of the work offered by \n\nAnyone", substr($twig->render('terms', ['terms' => CustomTerms::of(true, true, Modifications::Allowed)]), 0, 44));
    }

    private function twig(): Environment
    {
        $files = new FilesystemLoader();
        $files->addPath(\dirname(__DIR__, 3).'/templates', 'PhilLicensing');

        $twig = new Environment(new ChainLoader([new ArrayLoader([
            'page' => "{{ phil_licensing_recommendation(recommendation, holder|default(''), locale|default(null)) }}",
            'terms' => '{{ phil_licensing_terms(terms) }}',
        ]), $files]), ['strict_variables' => true]);
        $catalog = new LicenseCatalog();
        $names = new LicenseNames($catalog, Twig::translator());
        $extension = new FinderExtension($twig, $catalog, $names, new CustomTermsWriter($names));
        $twig->addExtension(new TranslationExtension(Twig::translator()));
        $twig->addExtension(new AttributeExtension(FinderExtension::class));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([FinderExtension::class => static fn (): FinderExtension => $extension]));

        return $twig;
    }
}
