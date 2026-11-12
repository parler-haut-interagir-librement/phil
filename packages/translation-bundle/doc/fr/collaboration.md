<!-- phil-dev source-sha256=2258dea80b21db2a1c9795e65402a17b4d2859701ff843010a883e34de0c307c -->
# Commentaires et crédits

## Une cible par segment

Un segment contient une seule cible. Une proposition est cette cible à l'état `suggested` ; une meilleure proposition la
remplace, et un traducteur en désaccord en écrit une autre ou explique pourquoi dans un commentaire. Il n'y a pas de
suggestions concurrentes à départager par un vote : la conversation sur un segment (une question à l'auteur, un choix
expliqué, une autre formulation) va dans ses commentaires.

## Commentaires

`Phil\TranslationBundle\Application\Comments` conserve les commentaires des segments d'un document dans une langue
cible, dans la table `phil_translation_comment`.

```php
$comments->add('article:42', LanguageTag::of('fr'), 'b1/text', 'Formal or informal “you” here?');
$bySegment = $comments->forDocument('article:42', LanguageTag::of('fr'));   // array<key, list<CommentRecord>>
$comments->remove('article:42', LanguageTag::of('fr'), $id);
```

Un commentaire compte de 1 à 4000 caractères UTF-8, sans les espaces blancs à ses deux extrémités, et il est signé par
l'utilisateur courant : l'identifiant que donne le service `Translators` de l'application, ou personne s'il n'y en a
pas. `forDocument()` les donne par clé de segment, du plus ancien au plus récent. `remove()` lance `NotFound` pour un
commentaire qui n'appartient pas à ce document et à cette langue. À l'écran, tous les traducteurs commentent et seuls
les réviseurs suppriment des commentaires.

## Qui a fait quoi

Chaque ligne de segment garde qui a écrit sa cible (`translated_by`) et qui l'a révisée (`reviewed_by`), sous forme des
identifiants du service `Translators` de l'application :

| Action | Écrite par | Révisée par |
|---|---|---|
| Traduire, proposer | l'utilisateur courant | personne |
| Réviser | conservé | l'utilisateur courant |
| Confirmer une cible périmée | conservé | personne |
| Vider | personne | personne |
| Importer depuis du XLIFF | l'utilisateur courant | l'utilisateur courant, pour une unité révisée, s'il peut réviser |
| Traduction automatique | personne, et signalée comme produite par une machine | personne |

Une unité qu'un fichier XLIFF marque `final` ou `signed-off` n'est fusionnée comme révisée que si l'utilisateur peut
réviser : pour un utilisateur qui peut seulement traduire, elle est fusionnée comme traduite, comme s'il l'avait écrite
à l'écran, de sorte qu'un fichier n'accorde jamais un droit que l'écran refuse.

Le bundle ne conserve que des identifiants : les noms, les courriels et les profils restent à l'application.

## Crédit public

Les traducteurs sont remerciés par un crédit public, et jamais payés par l'entremise du bundle.
`credits($document, $language)` donne les personnes qui ont écrit ou révisé les cibles utilisables du document et qui
acceptent d'être créditées : le nom que `Translators` donne pour chacune, avec le nombre de segments qu'elle a traduits
et révisés, ceux qui en ont le plus d'abord, puis par nom. Une personne dont `credit()` est `null`, qui a demandé de ne
pas être créditée, est omise, et la sortie d'une machine ne crédite personne.

```twig
{{ include('@PhilTranslation/credits.html.twig', {document: 'article:42', language: 'fr-CA'}) }}
{# Translated by Ana, Ben and Cléo. Thank you! #}

{% for credit in phil_translation_credits('article:42', app.request.locale) %}
    {{ credit.name }} ({{ credit.translated }} translated, {{ credit.reviewed }} reviewed)
{% endfor %}
```

La fonction Twig `phil_translation_credits()` accepte une étiquette BCP 47 ou une locale comme `fr_CA`, et ne donne
aucun crédit pour une langue qui n'en est pas une.

## Le service `Translators`

```php
use Phil\TranslationBundle\Application\Contract\Translators;

final readonly class UserTranslators implements Translators
{
    public function __construct(private Security $security, private UserRepository $users) {}

    public function current(): ?string
    {
        return $this->security->getUser()?->getUserIdentifier();
    }

    public function credit(string $translator): ?string
    {
        $user = $this->users->findOneByIdentifier($translator);

        return null !== $user && $user->acceptsCredit() ? $user->publicName() : null;
    }
}
```

```yaml
phil_translation:
    translators: App\Translation\UserTranslators
```

Sans lui, les cibles et les commentaires sont signés par personne et nul n'est crédité.
