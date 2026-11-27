<!-- phil-dev source-sha256=6576824e89877a779937f362b34dcfd49b4412b2e4df311c13a087481e63e302 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/svg-blocks` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.1-alpha.

Développement seulement.

### Modifié

- Les prérequis de développement demandent la dernière mineure de chaque dépendance (`symfony/http-client-contracts`
  3.8).

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `BlockMarkup` : les blocs `math`, `mermaid`, `chart` et `qrCode` d'un document UJO dessinés à son enregistrement,
  blocs imbriqués compris, chaque dessin gardé sous `rendered` avec sa clé ; les dessins à jour gardés, ceux périmés ou
  forgés retirés et redessinés, les types différés listés comme en attente, les échecs listés avec leur raison, les
  dessins bornés à 1 Mio ; `preview()` et `draws()`.
- `Rendered`, le résultat d'une passe.
- `Signer` : des clés HMAC-SHA256 versionnées du type, de la source et du balisage, un secret de 32 octets ou plus,
  vérifiées en temps constant ; `trusts()` est la fermeture de `HtmlRenderer::withStoredMarkup()`.
- `Source` : ce à partir de quoi chaque type est dessiné.
- `QrCodeRenderer`, `ChartRenderer` et `MathRenderer` par-dessus `phil/qr-code`, `phil/svg-chart` et
  `phil/latex-mathml`.
- Le port `MermaidService`, `HttpMermaidService` avec une échéance, sans redirection et avec une limite de taille, et
  `MermaidRenderer`, dont la réponse passe par `phil/svg-sanitizer` avec les éléments `<style>` gardés, chaque règle du
  thème de Mermaid placée sous la racine du diagramme, nommée d'après son contenu pour que deux diagrammes d'une même
  page ne partagent jamais une règle.
- `RenderingFailed` et `InvalidConfiguration`, sous l'interface `SvgBlocksException`.
- Des tests unitaires, un test d'intégration contre le service Mermaid à `PHIL_MERMAID_URL`, et un banc d'essai de la
  lecture.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README ; `CONTRIBUTING.md`, `SECURITY.md` et
  `CODE_OF_CONDUCT.md`.
