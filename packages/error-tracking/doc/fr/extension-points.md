<!-- phil-dev source-sha256=c6f30f26831f191824f2fb049dee0c4d21033f0ddfff3216e6ddc41c95c52cc6 -->
# Points d'extension

La bibliothèque a trois ports, tous des interfaces de `Phil\ErrorTracking\Contract`. Leurs adaptateurs appartiennent à
l'application ; `phil/error-tracking-bundle` en fournit un de chaque.

## `ErrorStore`

L'endroit où sont gardés les groupes et leurs événements.

```php
interface ErrorStore
{
    public function find(string $fingerprint): ?ErrorGroup;

    public function save(ErrorGroup $group, ?ErrorEvent $event = null): void;
}
```

- `find()` répond `null` pour une empreinte jamais vue.
- `save()` écrit le groupe, créé ou mis à jour, et garde l'événement avec lui quand il en reçoit un. Un magasin peut
  garder un nombre borné d'événements par groupe, en retirant les plus anciens. Une page d'administration enregistre un
  groupe sans événement quand elle le résout, l'ignore ou le rouvre.
- Tout ce que reçoit un magasin est passé par l'assainisseur. `ErrorEvent::toArray()` donne les valeurs simples à
  écrire et `ErrorEvent::fromArray()` les relit.
- Deux processus peuvent lire le même groupe et l'enregistrer l'un après l'autre, et un compte est alors perdu ; un
  magasin qui veut des comptes exacts les incrémente dans sa propre instruction.

## `CaptureThrottle`

Borne les événements d'une clé (l'empreinte) dans un intervalle de temps : voir
[Échantillonnage et limitation](sampling-and-throttling.md).

## `GroupAlerter`

Apprend un premier événement et une régression, avec le groupe tel qu'il a été enregistré :

```php
interface GroupAlerter
{
    public function alert(ErrorGroup $group, Occurrence $occurrence): void;
}
```

Un alerteur qui envoie un message de clavardage ou un courriel gagne à le mettre en file d'attente plutôt qu'à
l'envoyer dans la requête qui a échoué. Il nomme le groupe par son titre, son coupable et son empreinte ; l'événement
lui-même, même assaini, reste dans le magasin.
