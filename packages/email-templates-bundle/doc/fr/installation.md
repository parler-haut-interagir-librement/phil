<!-- phil-dev source-sha256=29c8c251a14507e0af373eeabd34ac506ba000b4c0b3d0132e0831d4f566ed94 -->
# Installation

```sh
composer require phil/email-templates-bundle
```

Avec Symfony Flex, la recette enregistre le bundle, écrit `config/packages/phil_email_templates.yaml` et les routes de
l'administration, et crée `email_templates/`, le répertoire des fichiers par défaut de l'application. Sans elle,
enregistrez le bundle dans `config/bundles.php` :

```php
Phil\EmailTemplatesBundle\PhilEmailTemplatesBundle::class => ['all' => true],
```

Le paquet exige PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente et Twig 3.31 ou une
version plus récente, avec les extensions `dom` et `mbstring`. FrameworkBundle lui fournit le service `clock` ;
TwigBundle produit le rendu de la mise en page de la maison, que l'application remplace ensuite comme n'importe quel
gabarit d'un bundle.

## Les contenus

Les gabarits qu'écrivent les sites sont des contenus de `phil/content-bundle`, que le paquet exige. Enregistrez
DoctrineBundle et PhilContentBundle à côté, et déclarez sous `phil_content.locales` chaque locale dans laquelle les
sites écrivent des gabarits :

```php
// config/bundles.php
Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
Phil\ContentBundle\PhilContentBundle::class => ['all' => true],
Phil\EmailTemplatesBundle\PhilEmailTemplatesBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_content.yaml
phil_content:
    locales:
        en: []
        fr: []
        fr_CA: [fr]
```

Quand le noyau a les deux bundles, le paquet ajoute le mappage de ses deux entités à la configuration `doctrine` et leur
racine, `EmailTemplateContent`, à `phil_content.roots` ; rien d'autre n'est à configurer. Les tables —
`phil_email_template_content` et `phil_email_template_dimension`, à côté de celles de `phil/content-bundle` — viennent
avec la prochaine migration :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Un gestionnaire d'entités autre que celui par défaut se nomme dans `entity_manager`, le même que nomme
`phil_content.entity_manager`. Une application sans base de données définit `store: memory`, ou le service d'un
`TemplateStore` à elle, et n'a besoin d'aucun des deux bundles. Les contenus sont décrits dans
[Sites, brouillons et versions](sites-and-versions.md#les-contenus).

## Les gabarits conservés dans une table de versions

Une application dont les gabarits sont dans une table de versions numérotées — une ligne par site, clé, locale et
version, comme les colonnes `site`, `template_key`, `locale`, `version`, `subject`, `preheader`, `format`, `body`,
`text_part`, `variables`, `segments`, `embeds`, `author` et `saved_at` d'une table `phil_email_template` — les copie une
fois dans les contenus, puis supprime la table. Chaque version est enregistrée puis publiée dans l'ordre, si bien que les
versions publiées gardent leur ordre et que la plus récente est envoyée :

```php
use Doctrine\DBAL\Connection;
use Phil\EmailTemplatesBundle\Application\EmailTemplates;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\EmailTemplatesBundle\Domain\ValueObject\TemplateDefinition;

final readonly class CopyTheTemplates
{
    public function __construct(private Connection $connection, private EmailTemplates $templates)
    {
    }

    public function __invoke(): void
    {
        $rows = $this->connection->iterateAssociative(
            'SELECT * FROM phil_email_template ORDER BY site, template_key, locale, version',
        );

        foreach ($rows as $row) {
            $site = Site::fromString($row['site']);
            $definition = TemplateDefinition::fromArray([
                'subject' => $row['subject'],
                'preheader' => $row['preheader'],
                'format' => $row['format'],
                'body' => $row['body'],
                'text' => $row['text_part'],
                'variables' => json_decode($row['variables'], true, flags: \JSON_THROW_ON_ERROR),
                'segments' => json_decode($row['segments'], true, flags: \JSON_THROW_ON_ERROR),
                'embeds' => json_decode($row['embeds'], true, flags: \JSON_THROW_ON_ERROR),
            ]);
            $revision = $this->templates->draft($row['template_key'], $row['locale'], $site)->revision ?? 0;

            $this->templates->save($row['template_key'], $row['locale'], $definition, $revision, $site, $row['author']);
            $this->templates->publish($row['template_key'], $row['locale'], $site, $row['author']);
        }
    }
}
```

Exécutez-la depuis une commande ou une migration Doctrine une fois que les tables des contenus existent — une migration
qui écrit des contenus a besoin qu'une migration antérieure les ait créées —, vérifiez les gabarits avec
`phil:email-templates:list`, puis supprimez la table dans la migration suivante. Chaque copie est vérifiée comme l'est un
enregistrement : une version qui ne passe plus les vérifications arrête la copie avec les raisons, à corriger d'abord
dans la table, ou à ignorer en interceptant `InvalidTemplate`. Les moments des copies sont ceux de la copie, non
`saved_at` ; gardez la table, ou une exportation de celle-ci, aussi longtemps que les dates d'origine sont voulues.

## Les paquets facultatifs

| Paquet | Apporte |
|---|---|
| `twig/cssinliner-extra` | Les styles de la mise en page mis en ligne dans chaque élément |
| `twig/intl-extra` | `format_date`, `format_number`, `format_currency` dans les gabarits |
| `symfony/mailer`, `symfony/messenger` | `SendTemplatedEmail`, envoyé quand le message est traité |
| `symfony/console` | `phil:email-templates:list`, `check` et `render` |
| `symfony/security-bundle`, `symfony/security-csrf`, `symfony/ux-twig-component` | L'administration et son aperçu |
| `phil/tenancy-bundle` | Le locataire actif comme site courant |
| `phil/audit-bundle` | Chaque changement consigné dans le journal d'audit |

## Les recettes du kit

Les pages de l'administration dessinent leurs parties avec des recettes du kit PHIL UX, installées dans l'application
par le Symfony UX Toolkit ; rien n'est produit depuis `vendor/` :

```sh
for recipe in alert badge button field input native-select table textarea; do
    php bin/console ux:install $recipe --kit=github.com/parler-haut-interagir-librement/ux-kit
done
```

Un site sous Tailwind installe plutôt les mêmes recettes depuis le kit Shadcn du Toolkit, avec `label`, que ses appels à
`Field:Label` utilisent, et Symfony UX Icons, que son élément de sélection dessine ; les pages ne donnent aux recettes
que ce que les deux kits lisent de la même façon. On les trouve sous le sous-répertoire des composants de la
configuration `ux_toolkit` ; dans un environnement où le Toolkit n'est pas activé, comme l'est souvent la production,
répétez-le dans `kit_subdirectory`.
