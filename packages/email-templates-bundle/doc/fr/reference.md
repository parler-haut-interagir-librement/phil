<!-- phil-dev source-sha256=7e8201b3ed8e5a9ef640af0886d0d496fd47314206ab871215da0b3c60401209 -->
# Référence

Chaque type public du paquet, avec son rôle.

## Domaine

| Type | Rôle |
|---|---|
| `Domain\ValueObject\TemplateKey` | Le nom d'un gabarit : 1 à 64 lettres ASCII minuscules, chiffres, `_`, `.` ou `-`, en commençant par une lettre. |
| `Domain\ValueObject\Site` | Un site, ou la portée partagée, le texte vide. |
| `Domain\ValueObject\Locale` | Une locale, `fr_CA` ; `language()` donne `fr`. |
| `Domain\ValueObject\TemplateDefinition` | Les champs d'un gabarit, vérifiés ; `create()`, `fromArray()`, `toArray()`, `hasSegment()`. |
| `Domain\ValueObject\TemplateVersion` | Une version publiée : clé, site, locale, numéro, définition, moment de l'enregistrement, auteur, moment de la publication. |
| `Domain\ValueObject\TemplateDraft` | La copie de travail d'un gabarit pour un site et une locale : version, révision, définition, état, moment, auteur, version envoyée ; `isPending()`. |
| `Domain\Schema\VariableSchema` | Les emplacements nommés d'un gabarit ; `fromArray()`, `toArray()`, `validate()`, `samples()`, `names()`. |
| `Domain\Schema\Variable` | Un emplacement : type, caractère obligatoire, valeur par défaut, exemple, description, champs, éléments, limites. |
| `Domain\Schema\VariableType` | `string`, `integer`, `number`, `boolean`, `datetime`, `url`, `email`, `list`, `object`. |
| `Domain\Status\BodyFormat` | `html` ou `markdown`. |
| `Domain\Status\TemplateOrigin` | `site`, `shared` ou `default`. |
| `Domain\Status\PublicationState` | L'état d'un brouillon : `unpublished`, `draft` (publié, avec des changements) ou `published`. |
| `Domain\Event\TemplateSaved`, `TemplatePublished`, `TemplateDraftDiscarded`, `TemplateReset` | Les changements, identifiants seulement. |
| `Domain\Exception\EmailTemplatesException` | Ce que chaque exception du bundle implémente. |
| `Domain\Exception\InvalidTemplate` | Un gabarit refusé, chaque raison dans `errors`. |
| `Domain\Exception\InvalidVariables` | Des valeurs qui ne correspondent pas au schéma, chaque chemin dans `violations`. |
| `Domain\Exception\UnknownTemplate` | Aucun gabarit, aucune version publiée de ce numéro, ou aucun brouillon à publier. |
| `Domain\Exception\VersionConflict` | Une autre révision du brouillon a été enregistrée entre-temps. |
| `Domain\Exception\RenderingFailed` | Un rendu qui a échoué. |

## Application

| Type | Rôle |
|---|---|
| `Application\Rendering\EmailRenderer` | Produit le rendu d'un courriel sans l'envoyer ; `render()`, `renderSamples()`, `renderResolved()`. |
| `Application\Rendering\RenderOptions` | Site, locale, segments et version d'un rendu. |
| `Application\Rendering\RenderContext` | À quoi sert un rendu, lu comme `email` par un gabarit. |
| `Application\Rendering\RenderedParts` | Les sources produites, avant la mise en page. |
| `Application\Rendering\RenderedEmail` | Le courriel produit : objet, pré-en-tête, HTML, texte, images ; `isClippedByGmail()`, `htmlForBrowser()`. |
| `Application\Rendering\EmbeddedImage` | Une image jointe en ligne ; `dataUri()`. |
| `Application\Rendering\HtmlToText` | La partie en texte brut écrite à partir du HTML. |
| `Application\Rendering\Theme` | Les couleurs, claires et sombres, et la police, vérifiées. |
| `Application\EmailComposer` | Un `Email` de Symfony Mailer prêt à envoyer. |
| `Application\EmailTemplates` | Enregistrer, publier, rétablir, abandonner, réinitialiser, courant, brouillon, historique, catalogue. |
| `Application\TemplateValidator` | Les vérifications d'un enregistrement et d'une publication. |
| `Application\TemplateResolver` | Quel gabarit un site envoie dans une locale ; le brouillon que rend un aperçu ; une version publiée. |
| `Application\ResolvedTemplate` | Un gabarit trouvé, où, et s'il s'agit d'un brouillon. |
| `Application\CatalogEntry`, `CatalogRow` | Le brouillon qu'un site conserve d'un gabarit dans une locale ; une ligne du catalogue d'un site. |
| `Application\Delivery` | Destinataires, valeurs, locale, segments et site d'un message. |
| `Application\FixedSiteScope` | Toujours le même site. |
| `Application\Preview\SamplePreviews` | L'aperçu du bundle, avec les valeurs d'exemple. |
| `Application\Preview\PreviewVariant` | Une variante d'un gabarit. |
| `Application\Contract\TemplateStore` | Où les brouillons et les versions publiées sont conservés. |
| `Application\Contract\DefaultTemplates` | Les fichiers par défaut. |
| `Application\Contract\TemplateEngine` | Produit le rendu des sources et les vérifie dans un bac à sable. |
| `Application\Contract\EmailLayout` | La mise en page de la maison. |
| `Application\Contract\StyleInliner` | Met les styles en ligne. |
| `Application\Contract\EmbedLoader` | Lit les images intégrées. |
| `Application\Contract\MarkdownRenderer` | Transforme un corps en Markdown en HTML sûr. |
| `Application\Contract\SiteScope` | Le site courant. |
| `Application\Contract\PreviewRenderer` | Les variantes d'un gabarit et leur rendu, pour l'aperçu. |
| `Application\Contract\DeliveryResolver` | L'envoi qui correspond à la référence qu'un message porte. |

## Infrastructure

| Type | Rôle |
|---|---|
| `Infrastructure\Twig\SandboxedEngine` | Le moteur : le `Sandbox` de Twig en mode strict, son propre environnement, un cache par empreinte ; réinitialisé entre les requêtes. |
| `Infrastructure\Twig\SandboxRules` | La liste d'éléments autorisés. |
| `Infrastructure\Twig\LimitsVisitor` | La profondeur des boucles, les variables lues, les appels faits. |
| `Infrastructure\Twig\SourceLoader` | Les sources, par l'empreinte de leur contenu. |
| `Infrastructure\Twig\EmailComponents` | Les composants, par l'`AttributeExtension` de Twig. |
| `Infrastructure\Twig\RenderStash`, `RenderMode` | Ce que les composants d'un même rendu partagent. |
| `Infrastructure\Twig\TwigLayout` | La mise en page de la maison dans le Twig de l'application. |
| `Infrastructure\Twig\CssInlinerStyles`, `NoStyleInliner` | Les styles mis en ligne par `twig/cssinliner-extra`, ou laissés tels quels. |
| `Infrastructure\Twig\KitComponents`, `AdminExtension` | Les noms des recettes du kit, `phil_email_templates_kit()`. |
| `Infrastructure\Markup\MarkupMarkdown` | Le Markdown par `phil/markup`. |
| `Infrastructure\Filesystem\DirectoryEmbedLoader` | Les images d'un répertoire. |
| `Infrastructure\Yaml\YamlDefaultTemplates` | Les fichiers par défaut en YAML. |
| `Infrastructure\Content\ContentTemplateStore` | Les gabarits comme contenus de `phil/content-bundle`. |
| `Infrastructure\Content\Entity\EmailTemplateContent`, `EmailTemplateDimension` | La racine d'un gabarit, portée et locale, et ses lignes par étape et par version. |
| `Infrastructure\Content\TemplatePayload` | Un gabarit comme valeurs de champs de sa ligne. |
| `Infrastructure\Content\TemplateKeysCollation` | Le site, la clé et la locale d'une racine en `utf8mb4_bin` sur MySQL et MariaDB. |
| `Infrastructure\Memory\InMemoryTemplateStore` | Les brouillons et les versions en mémoire. |
| `Infrastructure\Tenancy\TenancySiteScope` | Le locataire actif comme site. |
| `Infrastructure\Audit\AuditTemplateChanges` | Les changements dans le journal d'audit. |
| `Infrastructure\Messenger\SendTemplatedEmail`, `SendTemplatedEmailHandler` | Le message et son *handler*. |
| `Infrastructure\Http\AdminController` | L'administration et ses cadres d'aperçu. |
| `Infrastructure\Console\ListTemplatesCommand`, `CheckTemplatesCommand`, `RenderTemplateCommand` | Les commandes. |
| `Infrastructure\DependencyInjection\EmailTemplatesServices`, `CsrfProtectionPass`, `KitSubdirectoryPass` | Le câblage. |
| `PhilEmailTemplatesBundle` | Le bundle : sa configuration `phil_email_templates` et ses services. |

## Services

Chaque classe ci-dessus que détient un service est enregistrée sous son nom ; les interfaces de `Application\Contract`
sont des alias vers leur implémentation. Publics : `EmailRenderer`, `EmailComposer`, `EmailTemplates`,
`TemplateValidator`, `TemplateResolver` et les alias de `TemplateStore`, `DefaultTemplates`, `SiteScope`,
`TemplateEngine` et `PreviewRenderer`.
