<!-- phil-dev source-sha256=9fcca26b4bf1c0833945c0b3644aeee99eadad9f43375c4bc2f894557efcbd0b -->
# JavaScript

## Le contrôleur

`assets/src/checkin_desk_controller.js` est un contrôleur Stimulus, enregistré sous
`phil--checkin-bundle--checkin-desk`. Sa feuille de style, `phil-checkin/checkin-desk.css`, colore le résultat selon
l'issue. Le contrôleur est déclaré `"fetch": "lazy"` : StimulusBundle le charge, avec ses bibliothèques, seulement sur
une page où un élément s'en sert, pour que les autres pages du site ne le téléchargent pas.

```twig
<form data-controller="phil--checkin-bundle--checkin-desk"
      data-phil--checkin-bundle--checkin-desk-config-value="{{ desk|json_encode }}"
      data-action="phil--checkin-bundle--checkin-desk#submit">
    <input data-phil--checkin-bundle--checkin-desk-target="code" autocomplete="off" autofocus>
    <select data-phil--checkin-bundle--checkin-desk-target="direction">
        <option value="in">Entry</option>
        <option value="out">Exit</option>
    </select>
    <output data-phil--checkin-bundle--checkin-desk-target="result" role="status"></output>
    <p data-phil--checkin-bundle--checkin-desk-target="state"></p>
</form>
```

```php
// in the page's controller
$desk = $deskConfig->for(CheckpointId::of('main-door'));
```

| Cible | Rôle |
|---|---|
| `code` | Le champ où tape un lecteur en mode clavier, ou le personnel ; vidé après chaque lecture. |
| `direction` | Facultatif : `out` envoie des sorties ; des entrées sinon. |
| `result` | L'issue, le libellé du titre d'accès, et s'il a été décidé hors ligne. |
| `state` | Facultatif : en ligne ou hors ligne, et combien de lectures attendent d'être envoyées. |

La valeur `config` est ce que donne `DeskConfig::for()`, plus des clés facultatives :

| Clé | Sens |
|---|---|
| `checkpoint` | Le point de contrôle du poste. |
| `urls` | Les points de terminaison ; `manifest` et `sync` sont nuls sans catalogue. |
| `offline` | Si le poste télécharge un manifeste et tient un journal. |
| `csrfCookie` | Le nom du témoin et de l'en-tête du jeton CSRF à double soumission. |
| `device` | Facultatif : l'identifiant de l'appareil ; sinon, un identifiant est tiré une fois et gardé dans le magasin de l'appareil. |
| `locale`, `messages` | La langue des libellés — le `lang` de la page par défaut — et des libellés remplacés par clé. |

## Hors ligne

Quand le poste peut fonctionner hors ligne, il télécharge le manifeste de son point de contrôle à la connexion, puis
chaque minute, et le garde sur l'appareil. Une lecture qui ne peut pas joindre le serveur — pas de réseau, ou une
erreur du serveur autre que 503 — est décidée à partir du manifeste. Le HMAC-SHA256 du code, calculé avec WebCrypto
sous la clé du manifeste, trouve son titre d'accès. La même politique que celle du serveur décide. La copie des
présences de l'appareil est mise à jour. La lecture rejoint le journal sous l'identifiant avec lequel elle a d'abord
été envoyée. Un code absent du manifeste est proposé aux écouteurs de `phil-checkin:lookup` de la page, et est inconnu
sinon ; un manifeste expiré ne décide rien.

Une lecture répondue en ligne met aussi à jour la copie des présences du titre d'accès sur l'appareil, pour qu'un
titulaire admis en ligne il y a une minute ne soit pas admis de nouveau hors ligne avant l'arrivée du prochain
manifeste.

Quand le réseau revient — l'événement `online`, ou la minute suivante —, le journal est envoyé, au plus 500 lectures à
la fois, et les lectures que nomme le serveur sont oubliées. Un manifeste frais remplace alors l'ancien, et les
lectures encore dans le journal — celles que le serveur n'a pas encore enregistrées — lui sont appliquées de nouveau.
WebCrypto exige un contexte sécurisé : servez le poste en HTTPS.

Chaque appareil enregistre hors ligne par lui-même, à partir de ce qu'il garde : aucun serveur sur le site, aucun
relais entre les appareils. Deux appareils hors ligne en même temps ne voient pas les lectures l'un de l'autre : un
billet peut donc être admis à deux portes pendant une panne. Le serveur décide de nouveau chaque lecture à l'arrivée
des journaux, enregistre la décision que le titulaire a rencontrée à la porte et signale la lecture comme un conflit
quand sa propre décision diffère, pour que le personnel y donne suite. Plus la panne est courte, moins il y a de
conflits : chaque appareil envoie son journal dès qu'il rejoint de nouveau le serveur.

## Où l'appareil garde ses données

Le contrôleur garde son identifiant, le manifeste, les présences qu'il a modifiées et le journal dans le premier
magasin que le navigateur accorde, que l'élément nomme dans `data-store` :

| Magasin | `data-store` | Quand |
|---|---|---|
| IndexedDB, base `phil-checkin` | `indexeddb` | Chaque fois que le navigateur l'ouvre : la plupart des navigateurs, la plupart du temps. |
| `localStorage` | `local` | IndexedDB est absent ou refuse de s'ouvrir. |
| La mémoire de la page | `memory` | Ni l'un ni l'autre n'est accordé, comme dans certaines fenêtres privées : perdu au rechargement. |

IndexedDB passe en premier parce qu'un manifeste est volumineux : 10 000 titres d'accès, la limite par défaut, pèsent
environ 1,5 Mo en JSON, et 100 000, le maximum, environ 15 Mo — plus que les 5 Mo environ qu'un navigateur accorde à
`localStorage` pour un site, alors qu'IndexedDB reçoit une part du disque. Le manifeste est écrit une fois, à son
téléchargement ; chaque lecture décidée hors ligne n'écrit que l'entrée qu'elle a modifiée et le journal, chacun dans
une transaction, pour que deux onglets sur le même point de contrôle ne perdent jamais la lecture l'un de l'autre.

Un magasin qui refuse une écriture — un quota plein — ne perd que cette écriture : la lecture est quand même décidée et
affichée, avec `not kept on this device` ajouté au résultat et `kept: false` dans l'événement `checked`, pour que le
personnel sache que le journal ne la portera pas. Le magasin est propre à chaque site et à chaque profil du navigateur.

### Ce que `localStorage` gardait

Ce qu'un appareil gardait dans `localStorage` — son identifiant, un manifeste, un journal pas encore envoyé — passe dans
IndexedDB la première fois qu'IndexedDB s'ouvre pour le site : sur un navigateur qui refusait IndexedDB auparavant et
l'accorde maintenant, ou sur un appareil qui exécutait une copie du contrôleur qui gardait tout dans `localStorage`.
Chaque valeur est retirée de `localStorage` une fois qu'IndexedDB la garde, pour qu'une écriture qu'IndexedDB refuse
laisse la valeur où elle était jusqu'à la prochaine ouverture. Un journal est joint à celui qui est déjà dans
IndexedDB, ses lectures d'abord et aucune en double, pour qu'aucune lecture en attente d'envoi ne soit perdue ; toute
autre valeur ne passe que si IndexedDB n'en tient aucune sous sa clé. Une valeur qui n'est pas du JSON est abandonnée.
Seules les clés qui commencent par `phil-checkin:` passent : les valeurs propres de la page restent dans
`localStorage`.

### Limites

| Limite | Valeur | Ce que cela signifie pour le poste |
|---|---|---|
| Titres d'accès dans un manifeste | `offline.max_credentials`, 10 000 par défaut, 100 000 au plus | Environ 150 octets chacun en JSON : 1,5 Mo par défaut, 15 Mo au plus. |
| `localStorage` | Environ 5 Mo par site, partagés avec la page | Contient un manifeste d'environ 30 000 titres d'accès au plus ; un manifeste plus grand est décidé depuis la mémoire de la page, et perdu au rechargement. |
| IndexedDB | Une part du disque que fixe le navigateur, des centaines de mégaoctets au moins | Contient le plus grand manifeste et son journal sur tout appareil où tourne un poste. |
| Lectures envoyées à la fois | 500 par le contrôleur, `offline.batch_limit` sur le serveur | Un journal plus long part en plusieurs requêtes, une par minute ou à chaque événement `online`. |
| Âge d'une lecture envoyée | `offline.max_age_seconds`, deux jours par défaut | Une lecture plus vieille est refusée par le serveur : envoyez le journal avant. |
| Usage d'un manifeste | `offline.manifest_seconds`, douze heures par défaut | Un manifeste expiré ne décide rien : le poste a besoin du réseau une fois dans ce délai. |

Le navigateur peut vider le stockage d'un site quand le disque manque, et Safari le vide après sept jours d'usage sans
visite du site. Un poste ouvert chaque jour garde son magasin ; une application qui veut le garder quoi qu'il arrive le
demande au navigateur avec `navigator.storage.persist()` depuis sa propre page, ce que certains navigateurs n'accordent
qu'à un site installé. Un journal est envoyé dès le retour du réseau : ce qu'un vidage peut perdre, ce sont seulement
les lectures d'une panne.

## Événements

| Événement | Sens | Détail | Rôle |
|---|---|---|---|
| `phil-checkin:code` | vers l'élément du contrôleur | `{code}` | Un lecteur — un lecteur par caméra, un lecteur NFC — transmet un code qu'il a lu. |
| `phil-checkin:lookup` | depuis l'élément du contrôleur, hors ligne | `{code, checkpoint, respond(entry)}` | Un paquet qui reconnaît les codes que le manifeste ne peut pas contenir — des codes qui portent leur titre d'accès signé — répond avec l'entrée que le code représente. |
| `phil--checkin-bundle--checkin-desk:checked` | depuis le contrôleur | `{outcome, offline, kept, credential}` | La page, ou un paquet, réagit à une lecture : un son, un compteur, une récompense. |

L'élément porte la dernière issue dans `data-outcome`, le nombre de lectures en attente dans le journal dans
`data-pending`, et son magasin dans `data-store`.

### Répondre à `phil-checkin:lookup`

L'écouteur appelle `event.detail.respond()` de façon synchrone, avec une entrée ou la promesse d'une entrée — une
signature vérifiée avec WebCrypto est asynchrone. La première réponse compte ; une promesse rejetée, ou une réponse qui
n'est pas une entrée, ne compte pas. Une entrée a la forme de celle du manifeste :

| Clé | Type | Valeur par défaut |
|---|---|---|
| `id` | l'identifiant du titre d'accès, une chaîne non vide | obligatoire |
| `label` | chaîne ou null | null |
| `validFrom`, `validUntil` | horodatages Unix en secondes, ou null | null |
| `maxEntries` | entier, ou null pour aucune limite | 1 |
| `revoked` | booléen | false |

Le contrôleur garde les présences d'un tel titre d'accès sous son identifiant, d'un manifeste à l'autre, et journalise
ses lectures comme les autres : le catalogue de l'application doit le trouver par identifiant, pour que la
synchronisation l'enregistre.

```js
form.addEventListener('phil-checkin:lookup', (event) => {
    event.detail.respond(verifySignedCode(event.detail.code)); // a promise of an entry, or of null
});
```
