<!-- phil-dev source-sha256=388835a522c0cf035ea44309836e3e95a23459ba89b685cef7ad05dc84272cc2 -->
# CSRF

## Ce que le bundle vérifie

Une seule requête du bundle porte un jeton CSRF : la suppression d'un fichier en quarantaine sur la
[page d'administration](quarantine-and-events.md#la-page-dadministration), `POST /{id}/delete`, avec le jeton
`phil_upload_scan_quarantine` (`QuarantineAdminController::CSRF_TOKEN_ID`). Une suppression sans jeton valide répond
`400` et ne supprime rien.

Le bundle ne déclare aucune route de téléversement et ne vérifie aucun jeton sur les téléversements : un fichier arrive à
`#[CleanFile]` ou à l'`UploadScanner` par les contrôleurs et les formulaires de l'application, dont la protection CSRF
est celle de l'application.

## Pourquoi le jeton est exigé par défaut

Une suppression est définitive, et la page est ouverte par un opérateur connecté : sans jeton, la page d'un autre site
pourrait faire envoyer une suppression par le navigateur de cet opérateur. Dès que Twig et `symfony/security-csrf` sont
installés, le bundle enregistre donc la page et vérifie le jeton, sans rien à activer. C'est la valeur par défaut la plus
sûre.

## Sans état, sans session

Le jeton est l'un de ceux, sans état, de FrameworkBundle : le bundle ajoute `phil_upload_scan_quarantine` à
`framework.csrf_protection.stateless_token_ids`. Le gestionnaire de jetons de Symfony accepte alors une suppression quand
l'`Origin` de la requête (ou son `Referer`) nomme l'application, ou quand le témoin de double soumission correspond ; il
ne garde rien dans une session. La page d'un autre site envoie sa propre origine et est refusée.

## Envoyer le jeton

Le gabarit du bundle l'envoie dans un champ caché de chaque formulaire de suppression :

```twig
<form method="post" action="{{ path('phil_upload_scan_quarantine_delete', {id: file.id}) }}">
    <input type="hidden" name="_token" value="{{ csrf_token('phil_upload_scan_quarantine') }}">
    <button type="submit">Delete for good</button>
</form>
```

Un gabarit qui le remplace, sous `templates/bundles/PhilUploadScanBundle/`, garde ce champ. Une page de l'application qui
supprime avec `fetch` envoie le champ dans le corps ; le navigateur ajoute lui-même l'en-tête `Origin` :

```js
const body = new FormData();
body.append('_token', tokenField.value); // rendered with csrf_token('phil_upload_scan_quarantine')

await fetch(deleteUrl, { method: 'POST', body, credentials: 'same-origin' });
```

Avec `XMLHttpRequest`, le même `FormData` va à `send()`. Un champ du composant `FileInput` de `phil/ux-kit` fait partie
du formulaire de l'application : le jeton dont ce formulaire a besoin est celui que l'application lui donne, comme pour
tout autre champ.

## Quand le composant a été installé pour une autre raison

Une application peut installer `symfony/security-csrf` pour ses propres formulaires et ne jamais importer les routes de
l'administration. Avec Twig installé, le bundle enregistre tout de même la page, et la page a besoin du gestionnaire de
jetons de Symfony :

- Avec `framework.csrf_protection` activé, la valeur par défaut une fois le composant installé, rien d'autre n'est
  nécessaire : le jeton est sans état, donc aucune session n'est démarrée pour lui, et la page ne répond à rien tant que
  ses routes ne sont pas importées.
- Avec `framework.csrf_protection: false`, le conteneur ne se construit pas, et le message nomme les deux issues.

Les deux issues :

```yaml
# config/packages/framework.yaml — turn the protection on
framework:
    csrf_protection: true
```

```yaml
# config/packages/phil_upload_scan.yaml — leave the administration, and its check, out
phil_upload_scan:
    admin:
        enabled: false
```

`admin.enabled: false` retire la page et son contrôleur tout à fait ; il ne garde jamais une suppression sans
vérification.
