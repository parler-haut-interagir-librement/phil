<!-- phil-dev source-sha256=cca2f0f32a0e292ac98b9887aefb80cc4f37473f5dbfcd55c5b8e930aadeaf8c -->
# Traduction

Une enum devient traduisible en implémentant le `TranslatableInterface` de Symfony avec `AsTranslatableEnumTrait`. Ses
cas peuvent alors être donnés à tout ce qui traduit un `TranslatableInterface` : le filtre `|trans` de Twig, un libellé
de choix de formulaire, un message traduit.

```php
use Phil\Enums\Attribute\Description;
use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Traits\AsMetadatableEnumTrait;
use Phil\EnumsBundle\Infrastructure\Translation\AsTranslatableEnumTrait;
use Symfony\Contracts\Translation\TranslatableInterface;

#[Meta(Label::class, Description::class)]
enum OrderStatus: string implements TranslatableInterface
{
    use AsMetadatableEnumTrait;
    use AsTranslatableEnumTrait;

    #[Label('In transit')] #[Description('The parcel has left the warehouse.')]
    case IN_TRANSIT = 'in_transit';

    case REFUNDED = 'refunded';
}
```

```twig
{{ status|trans }}                              {# the translated label #}
{{ status.translatableDescription|trans }}      {# the translated description #}
```

## Les clés

- **La clé de libellé est le `Label` du cas** : le texte source sert de clé, donc une traduction manquante affiche le
  libellé tel qu'écrit, et un fichier de traduction se lit comme les libellés qu'il traduit :

  ```yaml
  # translations/enums.fr.yaml
  'In transit': 'En transit'
  'The parcel has left the warehouse.': "Le colis a quitté l'entrepôt."
  ```

- **Sans libellé, la clé est générée** : `<prefix>.<enum>.<case>`, avec le nom court de la classe et le nom du cas en
  snake case, par exemple `enum.order_status.refunded`. Un libellé vide compte comme une absence de libellé.
- **La clé de description est la `Description` du cas.** Un cas qui n'en a pas, ou qui en a une vide, n'a pas de
  description : `translatableDescription()` retourne `null`.

## Le domaine et le préfixe

Le domaine est `enums` et le préfixe `enum`, sauf si l'enum indique autre chose :

```php
use Phil\EnumsBundle\Application\Translation\Translatable;

#[Translatable(domain: 'shop', prefix: 'order')]
enum OrderStatus: string implements TranslatableInterface { … }
```

## Ce qu'ajoute le trait

| Méthode | Retourne |
|---|---|
| `trans(TranslatorInterface $translator, ?string $locale = null)` | Le libellé traduit, dans le domaine de l'enum et la locale donnée, ou celle du traducteur. |
| `translatableDescription()` | Un `TranslatableText` de la clé et du domaine de la description, ou `null` sans description. |

`EnumTranslation` calcule les clés et le domaine (`labelKey()`, `descriptionKey()`, `domain()`) ainsi que
`plainLabel()`, le libellé ou le nom, affiché là où rien ne traduit. `TranslatableText` est une clé dans un domaine,
traduite au rendu.

Une enum qui doit rester libre de toute dépendance à Symfony, dans une couche de domaine qui n'en admet aucune,
n'utilise pas le trait ; les filtres `enum_label` et `enum_description` de Twig affichent alors son `Label` et sa
`Description` tels qu'écrits.

## Extraire les clés

Lorsque `symfony/translation` est installé, le bundle donne à `translation:extract` les clés des enums traduisibles
qu'il trouve dans les chemins que lit la commande, soit `src/` et `templates/` par défaut :

```sh
bin/console translation:extract fr --force --format=yaml
```

Chaque cas ajoute sa clé de libellé et, s'il en a une, sa clé de description, dans le domaine de son enum ; le message
d'une nouvelle clé est la clé avec le préfixe de la commande, comme l'écrivent les extracteurs de Symfony. Sans
l'extracteur, les clés qu'une enum traduit par ses métadonnées n'apparaissent dans aucun catalogue, et
`debug:translation` ne peut pas signaler qu'elles manquent. `debug:enums --locale=fr` vérifie directement les clés d'une
enum ([Commandes](commands.md)).
