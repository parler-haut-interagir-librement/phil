<!-- phil-dev source-sha256=85ab48c1825f926d5f9b3e8950f9c20efe2c24aff3755cc6d2b9438596b5d8af -->
# Tests

## Les tests du bundle

La suite `tests/Unit` teste les clés, les sites et les locales, la définition d'un gabarit et chaque raison de son
refus, le schéma des variables — chaque type, valeurs par défaut, exemples, chemins imbriqués, limites —, le bac à
sable — ce qu'il autorise, ce qu'il refuse à l'enregistrement et au rendu, l'échappement, le Markdown et ses
substituts, les limites, le cache, la réinitialisation —, chaque composant en HTML, en Markdown et en texte, le texte
brut écrit à partir du HTML, le résolveur de gabarits locale par locale, `EmailRenderer`, `EmailComposer`,
`EmailTemplates` et `TemplateValidator` — enregistrements, publications, rétablissements, abandons, réinitialisations,
événements, auteurs, conflits —, l'aperçu de ce qui est envoyé, d'un brouillon et d'une version, les fichiers par défaut
du bundle, chacun vérifié comme le ferait un enregistrement, les images, la mise en page et la mise en ligne des styles,
la multilocation et l'audit, le message et son *handler*, les trois commandes, le contrat de chaque magasin exécuté en
mémoire, les valeurs de champs dans lesquelles un gabarit est conservé, la collation des clés d'une racine sur chaque
plateforme, ainsi que la configuration et le câblage.

La suite `tests/Functional` démarre une application — FrameworkBundle, DoctrineBundle sur SQLite, PhilContentBundle,
SecurityBundle, TwigBundle, TwigComponentBundle avec les recettes copiées comme le ferait `ux:install` —, exécute le
contrat des magasins sur les contenus — avec les locales que servent les contenus, une ligne écrite en contournant le
bundle, une seconde application qui enregistre à partir de la même révision et un long corps —, et pilote
l'administration avec les recettes du kit PHIL UX et du kit Shadcn : le rôle, la liste, la page, le cadre et sa
politique, chaque changement envoyé avec le jeton CSRF sans état, les refus, les conflits et une origine étrangère ;
puis les services, un message envoyé par Messenger et Mailer, et les commandes.

La suite `tests/Integration` exécute les mêmes tests du magasin de contenus sur MySQL 8.4 et PostgreSQL 17, la
comparaison octet par octet des sites et des clés comprise. Elle lit les serveurs dans `PHIL_TEST_MYSQL_URL` et
`PHIL_TEST_POSTGRESQL_URL`, que `vendor/bin/phil-dev services up` démarre dans Docker, et elle est ignorée sans eux.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/email-templates-bundle/tests
vendor/bin/phil-dev services up
eval "$(vendor/bin/phil-dev services env)" && vendor/bin/phpunit packages/email-templates-bundle/tests/Integration
```

Infection maintient le bundle à 100 % de score de mutation couvert.

## La cible de fuzzing

`tests/Fuzz/render.php` produit le rendu de gabarits que personne n'a écrits avec des valeurs que personne n'a choisies
— un corps, le JSON des valeurs et le JSON d'un schéma, en HTML et en Markdown — et échoue sur tout ce qui n'est pas un
résultat ou une exception du bundle, ou sur une sortie qui dépasse le plafond. Ses germes nommés se trouvent dans
`tests/Fuzz/corpus/render` :

```sh
vendor/bin/phil-dev fuzz render --budget=60
```

Chaque source distincte compile une classe à elle, si bien que la mémoire d'un processus de fuzzing croît avec les
entrées ; une application produit le rendu d'un ensemble borné de gabarits, chacun compilé une seule fois.

## Le banc d'essai

`tests/Benchmark/RenderBench.php` mesure, avec les gabarits compilés déjà chauds comme dans un *worker*, le rendu d'un
gabarit en Markdown et celui d'un gabarit en HTML — bac à sable, mise en page, styles en ligne, texte brut —, la
résolution d'un gabarit et le texte brut seul : environ 2 ms, 0,9 ms, 6 µs et 0,1 ms sur un portable.

```sh
vendor/bin/phil-dev bench packages/email-templates-bundle
```

## Tester une application

```yaml
# config/packages/test/phil_email_templates.yaml
phil_email_templates:
    store: memory
    sandbox: { cache: false }
```

`EmailRenderer::render()` retourne l'objet, le HTML et le texte sur lesquels faire des assertions, sans rien envoyer ;
les assertions de courriel de Symfony voient ce qu'`EmailComposer` a composé et que Mailer a envoyé.
