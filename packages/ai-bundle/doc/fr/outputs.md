<!-- phil-dev source-sha256=80ea303003e1db3634ea8c1c3d701da41765454d44e0e28ed23fff950bbe59fb -->
# Sorties, flux continu et outils

Une tâche demande au modèle l'une de quatre sorties, fixée par son `output` : `text`, la valeur par défaut ;
`structured`, des données façonnées par une classe ; `image` ; ou `speech`. Quoi qu'elle demande, la réponse revient
sous forme de `Suggestion`, journalisée et facturée de la même façon, et rien n'est appliqué tant que l'application ne
le dit pas.

## Sortie structurée

```yaml
phil_ai:
    tasks:
        character.invent:
            platform: main
            model: 'the-model'
            output: structured
            structure: 'App\Ai\Character'
```

La tâche envoie sa classe par l'option `response_format` de Symfony AI. L'abonné de sortie structurée de Symfony AI,
enregistré sur le répartiteur d'événements de la plateforme comme le fait le bundle Symfony AI, transforme la classe en
schéma JSON pour le fournisseur et la réponse en objet : la propriété `data` de la suggestion le contient, et `text`
est vide. Une plateforme sans cet abonné transmet l'option telle quelle au fournisseur.

```php
$suggestion = $tasks->run(new TaskRequest('character.invent', $project, $locale, ['setting' => 'a harbour town']));

$character = $suggestion->data;    // an App\Ai\Character, shown in a form before anything is saved
```

`structure` est obligatoire pour `structured` et refusée pour les autres sorties.

## Outils que le modèle propose

```yaml
phil_ai:
    tasks:
        rules.answer:
            platform: main
            model: 'the-model'
            tools:
                look_up:
                    description: 'Looks a rule up in the game system, by its name.'
                    parameters: { type: object, properties: { rule: { type: string, description: 'The rule' } }, required: [rule], additionalProperties: false }
```

Les outils vont au modèle comme des outils de Symfony AI : un nom de 1 à 64 lettres, chiffres, `_` ou `-`, une
description, et le schéma JSON des arguments. **Le bundle n'exécute jamais un outil.** Un appel que le modèle propose
revient, non exécuté, dans `toolCalls` de la suggestion, une liste de `ProposedToolCall` : l'`id` du fournisseur, le
`name` de l'outil et les `arguments` tels que le modèle les a écrits, sans vérification contre le schéma. L'application
montre ce que le modèle veut faire, ne l'exécute qu'une fois qu'une personne est d'accord, et vérifie les arguments
comme elle vérifie n'importe quelle entrée. Une tâche qui génère une image ou de la parole ne prend aucun outil.

Exécuter les outils et rendre leurs résultats au modèle dans une boucle est le travail d'un agent (`symfony/ai-agent`
de Symfony AI et sa boîte à outils), que l'application construit sur une plateforme à elle quand elle en veut un.

## Images et parole

Une tâche `image` ou `speech` envoie seulement son invite utilisateur, comme les modèles de texte vers image et de texte
vers parole la prennent, et lit les octets que la plateforme retourne : la propriété `media` de la suggestion est un
`Media`, avec ses `bytes` et son `mimeType`, et `toDataUri()` donne une adresse `data:` pour un `<img>` ou un `<audio>`
montré avant que la personne décide. Les octets vivent seulement dans la suggestion ; l'application stocke ce que la
personne accepte, avec `phil/media-bundle` par exemple.

Un modèle qui facture à l'image ou à la requête, quels que soient ses jetons, prend un prix par appel :

```yaml
phil_ai:
    prices:
        'black-forest-labs/flux-schnell': { input: 0, output: 0, per_call: 3000 }    # 0.003 per image
```

Les plateformes sont des adaptateurs, choisis par la configuration :

| Adaptateur | Ce qu'il génère | Par |
|---|---|---|
| Hugging Face | Des images, avec un modèle de texte vers image | Le pont Hugging Face de Symfony AI, `symfony/ai-hugging-face-platform` |
| Replicate | Des images, par le nom d'un modèle | Le `Infrastructure\Replicate\ReplicatePlatformFactory` de ce bundle |
| Un modèle de texte vers parole | De la parole | Le pont Symfony AI du fournisseur qui l'offre |

Le pont Replicate propre à Symfony AI sert des modèles de langage ; pour les images, `ReplicatePlatformFactory` construit
une plateforme Symfony AI (un `Provider` du client de modèle et du convertisseur de résultat du bundle) sur l'API de
prédictions de Replicate. Elle est enregistrée quand un client HTTP est installé, et construit la plateforme avec le
jeton de l'application, ou avec celui d'une personne comme `user_key_factory` :

```yaml
services:
    app.replicate_platform:
        class: Symfony\AI\Platform\PlatformInterface
        factory: ['@Phil\AiBundle\Infrastructure\Replicate\ReplicatePlatformFactory', 'create']
        arguments: ['%env(REPLICATE_API_TOKEN)%']

phil_ai:
    platforms:
        images:
            platform: 'app.replicate_platform'
            user_key_factory: 'Phil\AiBundle\Infrastructure\Replicate\ReplicatePlatformFactory'
            secret: 'replicate-token'
    tasks:
        cover.draw:
            platform: images
            model: 'black-forest-labs/flux-schnell'
            output: image
            options: { aspect_ratio: '2:3' }
```

Le modèle se nomme `owner/name` ; les options de la tâche se joignent à l'invite dans l'entrée du modèle. La requête
demande à Replicate d'attendre la prédiction, 60 secondes au plus ; une prédiction qui n'a pas réussi d'ici là, qui a
échoué ou qui a été annulée fait échouer la tâche. L'image est récupérée seulement sur le réseau de diffusion de
Replicate, en HTTPS, sans suivre de redirection, et refusée au-delà de 20 Mio : une adresse ailleurs dans une réponse
n'envoie jamais le serveur ailleurs.

## Flux continu

```php
$stream = $tasks->stream(new TaskRequest('draft.rewrite', $project, $locale, ['text' => $passage]));

foreach ($stream as $piece) {
    echo $piece;    // or send it as a server-sent event, a Turbo stream, a Mercure update
    flush();
}

$suggestion = $stream->suggestion();    // once the last piece is read
```

`stream()` vérifie tout ce que vérifie `run()` avant de retourner (la tâche, l'invite, la clé, le quota), puis demande à
la plateforme un flux avec l'option `stream` de Symfony AI. Chaque morceau est du texte à montrer tout de suite ; les
appels d'outils que le modèle propose pendant le flux reviennent dans la suggestion. La suggestion entière, avec ses
jetons tirés des métadonnées `token_usage` de la plateforme, est journalisée et facturée une fois le dernier morceau
lu, comme `run()` le fait.

Un flux se lit une seule fois. Un échec en cours de lecture lève `TaskFailed` ; un flux quitté avant sa fin, ou jamais
lu, est journalisé comme échoué : ce qui a été montré n'a jamais été une proposition entière. Dans les deux cas, la
tâche est rendue au quota.
