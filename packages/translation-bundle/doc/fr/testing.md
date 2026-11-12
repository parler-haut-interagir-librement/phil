<!-- phil-dev source-sha256=e6a9be4aef51a98501f54daddee6c3889382978a6f58e84eefd4f2c26d72c0a4 -->
# Tests

## Les tests du bundle

La suite `tests/Unit` teste les lignes ; `Translations` sur les stockages en mémoire (la préparation, la proposition
des correspondances exactes, la mise à jour des segments, chaque action et par qui elle est signée, l'apprentissage, les
correspondances, les termes, les problèmes, les contrôles et les indicateurs, l'avancement, l'exportation,
l'importation, la traduction automatique, les crédits, les variantes régionales et les deux lectures du document
traduit) ; `Glossaries` avec un fléchisseur et le TBX, `Comments` et `Progress` ; un même contrat pour les deux sortes de
stockages, exécuté sur les stockages en mémoire et sur Doctrine avec SQLite en mémoire ; les contrôleurs avec les
gabarits Twig du bundle (chaque réponse, chaque action, les commentaires, la traduction automatique, les cibles
héritées, les pages de glossaire, les formulaires venus d'un autre site, sans leur jeton CSRF ou avec un mauvais, les
téléversements) ; les crédits en Twig et les liens EasyAdmin ; et la configuration et le branchement. La suite
`tests/Functional` démarre une petite application avec FrameworkBundle, DoctrineBundle sur SQLite et TwigBundle,
traduit une page par les routes, refusée sans jeton, acceptée avec un jeton et son témoin, modifie un glossaire et
commente un segment. `tests/js` exécute le module du jeton à double soumission sous l'exécuteur de tests de Node.

À partir de la racine du monodépôt, puis du bundle :

```sh
vendor/bin/phpunit packages/translation-bundle/tests
node --test tests/js     # from packages/translation-bundle
```

Infection maintient le bundle à un score de mutation couverte de 100 %.

## Tester une application qui l'utilise

Réglez `store: memory` dans l'environnement de test pour garder les segments, les termes et la mémoire dans des
tableaux, et appelez `Translations` à partir du conteneur :

```yaml
# config/packages/test/phil_translation.yaml
phil_translation:
    store: memory
```

```php
$translations = self::getContainer()->get(Translations::class);
$translations->prepare('article:42', $content, LanguageTag::of('en'), LanguageTag::of('fr'));
$translations->translate('article:42', LanguageTag::of('fr'), 'b1/text', 'Bonjour.');
```

Pour tester l'écran, envoyez ses formulaires avec l'en-tête `Sec-Fetch-Site: same-origin` et le champ `_csrf_token`
réglé sur le nom du témoin du jeton, `csrf-token` par défaut, comme le fait un navigateur sans JavaScript. Ou bien
envoyez un jeton de 24 caractères ou plus dans le champ, avec le témoin `csrf-token_<token>` réglé sur `csrf-token`,
comme le fait le contrôleur `csrf`.
