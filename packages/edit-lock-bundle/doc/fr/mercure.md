<!-- phil-dev source-sha256=84bb87a5cd5ab9adf39c13ed9ef0851b30c62003522ebaa25de559766bee1fda -->
# Mercure

## Sujets

Chaque ressource a un sujet, `topic_prefix` suivi de son nom : `urn:phil:edit-lock:article:42`. Le contrôleur s'y abonne
par l'URL que donne `ClientConfig` — l'URL publique du hub avec le sujet dans `match`, ou dans `topic` pour un hub du
protocole antérieur à 1.0.

Les mises à jour sont privées, sauf si `mercure.private` vaut `false` : le hub ne les envoie qu'aux abonnés dont le
jeton autorise le sujet. L'application l'accorde aux rédacteurs qu'elle laisse modifier, par le témoin d'autorisation de
MercureBundle ou par un jeton qui lui est propre ; le contrôleur ouvre son `EventSource` avec les identifiants de
connexion.

## Les messages

Chaque message est un Turbo Stream :

```html
<!-- presence: the leases active on the resource, after one changed -->
<turbo-stream action="update" target="phil-edit-lock-presence-…">
    <template>
        <ul class="phil-edit-lock-presence">
            <li data-holder="7" data-part="aB3x" data-expires-in="28">Ada</li>
            <li data-holder="9" data-part="" data-expires-in="30">Grace</li>
        </ul>
    </template>
</turbo-stream>

<!-- a block saved: a document of that one block, canonical, and its place -->
<turbo-stream action="replace" target="phil-edit-lock-block-aB3x" data-holder="7">
    <template>
        <div id="phil-edit-lock-block-aB3x" data-list="root" data-after="Zt0q" data-ujo="{&quot;blocks&quot;:[…]}"></div>
    </template>
</turbo-stream>

<!-- a block removed -->
<turbo-stream action="remove" target="phil-edit-lock-block-aB3x" data-holder="7"></turbo-stream>
```

`data-part` est vide pour un bail sur la ressource entière. `data-expires-in` compte les secondes restantes au moment où
le message a été créé, afin que chaque navigateur calcule la fin selon sa propre horloge.
`TurboStreams::presenceTarget($resource)` donne l'identifiant de l'élément de présence : une page sans l'éditeur peut
montrer la liste en laissant Turbo appliquer le flux de présence à un élément qui porte cet identifiant. Les flux de
blocs sont destinés au contrôleur, qui les applique au document de l'éditeur plutôt qu'à la page.

## Sans hub

Lorsque MercureBundle n'est pas enregistré, ou que `mercure.enabled` vaut `false`, le `NullBroadcaster` ne prévient
personne. `ClientConfig` ne donne aucun hub ; le contrôleur demande alors la présence à chaque pulsation : les verrous
s'affichent donc encore, avec quelques secondes de retard, et les enregistrements des autres atteignent un rédacteur
lorsqu'il rouvre la page.

## Un hub injoignable

Une publication qui échoue est journalisée comme avertissement par le service `logger` et ne fait pas échouer la
requête : le bail ou l'enregistrement est conservé, et la prochaine modification transporte de nouveau toute la
présence.
