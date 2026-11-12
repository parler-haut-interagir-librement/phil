<!-- phil-dev source-sha256=0088364653ce1b88233cdc1959e6a316a4c1c2052c9662fb2e626f5fc8e04865 -->
# vCard, JSContact et Twig

## `VCardResponse`

```php
return new VCardResponse($shop->contact(), 'shop.vcf');
```

La fiche en pièce jointe `.vcf`, `text/vcard; charset=utf-8`. Un nom de fichier hors ASCII reçoit une solution de repli
ASCII pour les clients qui n'en lisent pas d'autre, et une barre oblique ou une barre oblique inverse dans le nom
devient `_`.

## JSContact

`JSContactResponse` sert une fiche en JSContact, `application/jscontact+json`, pour un carnet d'adresses ou un client
JMAP Contacts ; avec un nom de fichier, elle est téléchargée, avec la même solution de repli ASCII et le même `_` pour
une barre oblique ou une barre oblique inverse que `VCardResponse`.

```php
return new JSContactResponse(new JSContactCard('urn:uuid:'.$shop->id, $shop->contact(), [$shop->address()]));
```

Le bundle enregistre le `JSContactReader` de `phil/contact` comme service, ses limites et le pays des numéros de
téléphone sans indicatif de pays dans la configuration. Un point de terminaison d'importation est un contrôleur de
l'application :

```php
#[Route('/contacts/import', methods: ['POST'])]
public function import(Request $request, JSContactReader $reader): Response
{
    try {
        $read = $reader->read($request->getContent());
    } catch (UnreadableJSContact $unreadable) {
        return new Response($unreadable->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    $this->contacts->save($read->card, $read->addresses);

    return $this->json(array_map(static fn (JSContactNotice $notice): array => ['pointer' => $notice->pointer, 'reason' => $notice->reason], $read->notices));
}
```

Les avis disent au client ce qui a été lu sans être gardé ; `$read->unmapped` contient les propriétés que la fiche ne
représente pas, que l'application garde à côté de la fiche quand elle veut les réécrire.

## Twig

| Fonction | Donne |
|---|---|
| `contact_vcard(card)` | La fiche en vCard ; vide pour `null` |
| `person_name(name, format)` | Un `PersonName`, ou le nom d'une fiche, dans un format : `display` (par défaut), tel qu'on l'écrit tous les jours ; `formal`, avec son titre honorifique et son titre de compétence ; `sort`, la clé sous laquelle une liste le trie. Une fiche sans nom structuré donne son nom dans chaque format, la fiche d'une organisation son organisation ; vide pour `null` |
| `contact_json_ld(card)` | Le JSON-LD schema.org de la fiche : un nœud du graphe de la page avec phil/structured-data-bundle, sinon `<script type="application/ld+json">…</script>`, dont le JSON ne peut pas fermer l'élément ; vide pour `null` |

Lorsque phil/structured-data-bundle est installé, `contact_json_ld` n'affiche rien : la fonction ajoute la fiche, une
`Person` ou une `Organization`, au graphe de la page, que `structured_data()` affiche dans un seul élément script avec
les autres nœuds de la page. Produisez le rendu de `structured_data()` après les fiches, à la fin du corps. Sans ce
bundle, chaque fiche affiche son propre élément.

```twig
{{ person_name(card) }}
{{ person_name(card.structuredName, 'formal') }}
```

Un autre format que `display`, `formal` et `sort` lève `InvalidArgumentException`.
