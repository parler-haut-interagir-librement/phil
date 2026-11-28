<!-- phil-dev source-sha256=57b78811b8434d2fef4277cf87dbe86d16e5b4b75b4aedf13ea61bb809027d76 -->
# Aperçu et confirmation

## L'aperçu

```php
$preview = $previewer->preview($user, SelectionToken::fromString($token), $changes, new DateTimeImmutable());
```

L'aperçu lit la sélection sans utiliser le jeton, redemande le droit de lancer l'action, vérifie la modification par
rapport aux champs de l'action et compte les éléments. Il ne change rien, de sorte qu'une requête GET peut l'afficher.

| Propriété ou méthode | Ce qu'elle donne |
|---|---|
| `count` | les éléments que la sélection nomme maintenant, avant les voters |
| `sample` | jusqu'à `Limits::sampleSize` éléments, chacun un `SampleItem` avec son libellé, `granted` et un `ValueChange` par champ |
| `deniedInSample()` | combien d'éléments de l'échantillon le voter refuse |
| `destructive` | si l'action avertit |
| `changes` | l'ensemble de modifications montré |
| `expiresAt` | le moment où le jeton cesse de fonctionner |

`ValueChange::changes()` distingue un champ qui prend une autre valeur d'un champ qui l'a déjà.

## Le dialogue

La confirmation est un `<dialog>` natif, modal avec un script et affiché dans la page sans script. Le formulaire qu'il
contient renvoie le jeton et la modification ; rien dans la page ne nomme un élément.

```twig
<dialog id="bulk-confirm" open aria-labelledby="bulk-confirm-title" {{ stimulus_controller('dialog') }}>
    <form method="post" action="{{ path('admin_bulk_confirm') }}">
        <h2 id="bulk-confirm-title">{{ 'bulk.confirm.title'|trans({count: preview.count}) }}</h2>

        {% if preview.destructive %}
            <p role="alert">{{ 'bulk.confirm.destructive'|trans }}</p>
        {% endif %}

        <table>
            <caption>{{ 'bulk.confirm.sample'|trans({shown: preview.sample|length, count: preview.count}) }}</caption>
            {% for item in preview.sample %}
                {% for change in item.changes %}
                    <tr>
                        <th scope="row">{{ item.label }}</th>
                        <td>{{ change.field }}</td>
                        <td>{{ change.old ?? '—' }}</td>
                        <td>{{ change.new ?? '—' }}</td>
                    </tr>
                {% endfor %}
            {% endfor %}
        </table>

        {% if preview.deniedInSample() > 0 %}
            <p>{{ 'bulk.confirm.denied'|trans({count: preview.deniedInSample()}) }}</p>
        {% endif %}

        <input type="hidden" name="token" value="{{ token }}">
        <input type="hidden" name="_csrf_token" value="{{ csrf_token('bulk_actions') }}">
        <input type="hidden" name="changes" value="{{ preview.changes.values|json_encode }}">

        <button type="submit" value="confirm">{{ 'bulk.confirm.submit'|trans }}</button>
        <button type="submit" formmethod="dialog" formnovalidate>{{ 'bulk.confirm.cancel'|trans }}</button>
    </form>
</dialog>
```

La modification voyage en JSON, ce qui garde un `null`, un `false` ou un `0` tel quel, alors qu'un champ caché
transformerait chacun en chaîne.

Le contrôleur Stimulus se contente d'appeler `showModal()` à la connexion et de redonner le focus au bouton qui l'a
ouvert à la fermeture ; sans lui, l'attribut `open` affiche le dialogue dans la page.

## La confirmation

```php
#[Route('/admin/bulk/confirm', name: 'admin_bulk_confirm', methods: ['POST'])]
public function confirm(Request $request, BulkConfirmer $confirmer): Response
{
    $this->guard->check($request, 'bulk_actions');      // stateless CSRF and origin, below

    $changes = json_decode($request->request->getString('changes'), true, 4, JSON_THROW_ON_ERROR);

    $run = $confirmer->confirm(
        $this->getUser()->getUserIdentifier(),
        SelectionToken::fromString($request->request->getString('token')),
        ChangeSet::of(\is_array($changes) ? $changes : []),
        new DateTimeImmutable(),
    );

    return $this->redirectToRoute('admin_bulk_report', ['run' => $run->id->value], Response::HTTP_SEE_OTHER);
}
```

`confirm()` lit d'abord la sélection, de sorte que la tentative d'une autre personne n'use pas le jeton de son
propriétaire, puis le prend en une seule étape atomique : de deux confirmations du même jeton, une seule l'obtient. Elle
redemande le droit, revérifie la modification et les limites, range l'exécution, enregistre `AuditEvent::Confirmed` et
envoie le premier lot. Rien ne change dans la requête elle-même.

`BulkConfirmer::start()` fait les mêmes vérifications sans jeton, pour un appelant qui a montré sa propre confirmation ;
voyez [Passerelles](bridges.md).

## Les requêtes qu'envoie une page

| Requête | Méthode | Effet |
|---|---|---|
| Sélection | POST, CSRF et origine vérifiés | range la sélection, redirige vers l'aperçu avec le jeton |
| Aperçu | GET | aucun |
| Confirmation | POST, CSRF et origine vérifiés | range l'exécution et l'envoie |
| Rapport | GET | aucun |
| Nouvelle tentative | POST, CSRF et origine vérifiés | remet les éléments en échec en attente et envoie l'exécution |

Chaque POST vérifie le jeton CSRF sans état de Symfony — l'identifiant `bulk_actions` listé sous
`framework.csrf_protection.stateless_token_ids` — et `Sec-Fetch-Site`, `Origin` ou `Referer` du navigateur, qui doit
nommer le site. Un GET ne change rien : un lien, une prélecture ou un robot n'exécute donc jamais une action. Le jeton
voyage dans le corps du formulaire ou dans l'adresse de l'aperçu ; il est à usage unique et de courte durée, et il ne
sert à personne d'autre que son propriétaire.
