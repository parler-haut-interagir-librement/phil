<!-- phil-dev source-sha256=bb7d3c0e80d5e643275db3b5e2c6b76489e059389fd3bcaa192eb63602c233f5 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `PhilLegalNoticesBundle`, `LegalNoticesServices`, `KitSubdirectoryPass` | Les valeurs par défaut, chaque option, les sources de l'application, les fichiers et les paramètres, chaque configuration refusée ; le sous-répertoire des recettes venu de la configuration, du Toolkit ou d'aucun des deux ; l'extension de twig/intl-extra enregistrée, ou laissée à TwigExtraBundle |
| `ConfigDocumentSource` | Les versions de la configuration, les formes d'une date, les versions refusées |
| `MarkupBody`, `ScheduleRetentionDurations`, `ConfiguredConsentCategories` | Markdown et HTML assainis, un format inconnu, les règles d'un calendrier, les catégories |
| `DocumentController` | La version en vigueur, une version, l'historique, les sections de la politique de confidentialité, ce qui est introuvable |
| `FooterLinks`, `LegalNoticesExtension`, `KitComponents`, les commandes | Les liens, les fonctions Twig telles que Twig les déclare depuis leurs attributs, les noms des recettes, la vérification qui échoue et qui réussit, les annonces et leur période |
| `LegalUrlProvider` | Les pages des documents en vigueur, par section |
| Les gabarits | Aucun texte hors de Twig ; chaque clé de traduction qu'ils nomment, en anglais et en français, avec les mêmes clés dans les deux catalogues |
| La recette Flex | Le manifeste, les routes, la configuration chargée par le bundle : deux brouillons dont les corps, en anglais et en français, ne nomment que des champs de l'identité et chaque champ qu'exige leur document, refusés une fois publiés sans l'identité, construits une fois celle-ci remplie |
| Un noyau | TwigComponentBundle avec les recettes du kit PHIL UX, ou du kit Shadcn du Symfony UX Toolkit, copiées où les met `ux:install`, dans un sous-répertoire des composants ou à leur racine : les badges et le tableau des durées de conservation dessinés par les recettes, chaque page dans chaque locale rendue avec chaque kit ; les pages en anglais et en français sous un préfixe par locale, un corps lu dans un fichier Markdown, les durées de conservation d'un calendrier de phil/privacy-bundle, les catégories de consentement et leurs témoins comme une liste dans chaque langue, l'historique, les 404, la vérification |

```sh
vendor/bin/phpunit packages/legal-notices-bundle/tests
```
