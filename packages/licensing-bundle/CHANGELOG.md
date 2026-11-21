# Changelog

All notable changes to `phil/licensing-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-20

### Added

- `PhilLicensingBundle` and its configuration `phil_licensing`: the repository, the template of the page, the
  printing — `strict`, off by default, and `unlicensed_subjects` —, and the licenses of the application, each built
  when the container is built so that a license the catalog refuses fails the build; the Doctrine mapping prepended
  with DoctrineBundle; each integration registered only when its package or bundle is.
- `Domain\Catalog\LicenseCatalog`, `Domain\ValueObject\License` and `Domain\Catalog\Requirement`: the ORC License,
  Creative Commons Attribution 4.0, Attribution-ShareAlike 4.0, Attribution-NonCommercial 4.0,
  Attribution-NonCommercial-ShareAlike 4.0, Attribution-NoDerivatives 4.0 and Attribution-NonCommercial-NoDerivatives
  4.0, CC0 1.0, the Open Game License 1.0a and custom terms, with their address, reference, SPDX identifier and
  requirements, then the licenses of the application; `UnknownLicense`.
- `Domain\Model\LicenseDeclaration`, the license of a subject by type and identifier, its notice, Reserved Material,
  Product Identity, designated content and license text, and its attributions, in the table
  `phil_licensing_declaration`, one per subject, versioned; `Domain\ValueObject\SubjectReference`,
  `Domain\ValueObject\Attribution` and `Domain\Catalog\ResourceKind`, each text bounded and checked, each address read
  by `phil/url` (user information, IP addresses and `localhost` refused, international hosts accepted);
  `InvalidLicensing`.
- `Domain\Contract\LicenseDeclarationRepository`, `Infrastructure\Doctrine\DoctrineLicenseDeclarations` and
  `Domain\Contract\Licensable`.
- `Application\LicenseCompliance`, `Domain\ValueObject\ComplianceReport`, `Domain\ValueObject\Violation` and
  `Domain\Compliance\ViolationCode`: the declarations the license of the work asks for, and the credit, the notice, the
  statement of the changes and the share-alike license each resource's license asks for.
- `Application\ExportGuard`, `Application\Event\ExportChecking` and `Application\Exception\ExportBlocked`: the check
  an application calls before an export, a subject without a declaration refused, listeners adding their own
  violations; `Infrastructure\Security\ExportVoter`, `PHIL_LICENSING_EXPORT`, each violation a reason of the vote.
- `Infrastructure\PagedMedia\GuardPrintedWork`: the export guard before each print of `phil/paged-media-bundle` that
  names its work, a subject whose type is declared unlicensed printed unchecked.
- The strict printing: `Infrastructure\PagedMedia\RequirePrintSubject` refuses a print of
  `phil/paged-media-bundle` that names no work with `Application\Exception\PrintSubjectRequired`, whose message is
  translatable.
- `Infrastructure\Form\AttributionType` and `AttributionTransformer`: the credit of one resource as a form, each text
  checked as the attribution checks it, the changes required when the resource's license asks for them, and the box
  "No modifications" that fills them.
- `Domain\Finder\LicenseFinder`, `LicenseChoices`, `Question`, `Modifications`, `Recommendation`, `CustomTerms` and
  `TermsClause`: the decision tree that finds the license of a work — share-alike material imposing its license, else
  a Creative Commons 4.0 license, CC0 1.0 or custom terms written from the answers.
- `Infrastructure\Form\Finder\LicenseFinderType`, `LicenseFinderStepType` and `LicenseFinderData`: the license finder
  as a form in steps on the form flow of Symfony, without JavaScript, the answers kept in the session;
  `Infrastructure\Translation\CustomTermsWriter`, the text of custom terms; template `finder/form.html.twig`, and
  `finder/recommendation.html.twig` through `phil_licensing_recommendation()`, beside `phil_licensing_terms()` in
  `Infrastructure\Twig\FinderExtension`.
- `Application\AttributionPage` and `AttributedResource`: the license, the declarations, the resources by kind, every
  notice and every license once.
- Template `attribution.html.twig`, an HTML fragment; `Infrastructure\Twig\AttributionRenderer` and
  `Infrastructure\Twig\LicensingExtension`: `phil_licensing_attribution()`, `phil_licensing_page()`,
  `phil_licensing_license_name()` and `phil_licensing_violation()`; `Infrastructure\Translation\LicenseNames`;
  translations in English and French.
- `Infrastructure\Ujo\AttributionDocument`, the page as a UJO (Universal JSON Output) document, every text escaped.
- `Infrastructure\StructuredData\LicenseStructuredData`, `license`, `copyrightNotice` and `isBasedOn` as schema.org
  properties or a node.
- The Flex recipe in `recipe/0.1`: the bundle and `config/packages/phil_licensing.yaml`, with the printing guard.
