<!-- phil-dev source-sha256=45ee4df7cdd16c3ddccaaef3111ae44061ee3eb4aecb85408191339e9dafe3bf -->
# Service Mermaid

Mermaid met en page ses diagrammes dans un moteur de navigateur, ce que PHP ne peut pas faire. La bibliothèque définit
un port et un adaptateur, comme le fait un client Gotenberg :

```php
interface MermaidService
{
    /** @throws RenderingFailed */
    public function toSvg(string $code): string;
}
```

`HttpMermaidService` appelle le service que `phil/svg-blocks-bundle` fournit sous forme d'image de conteneur :

```php
new HttpMermaidService(HttpClientInterface $client, string $endpoint, float $timeout = 10.0, int $maxBytes = 1_048_576)
```

| Requête | Réponse |
|---|---|
| `POST {endpoint}/render`, `{"code": text}` | `200 image/svg+xml` : le diagramme |
| | `422 {"error": text}` : Mermaid refuse le diagramme ; la raison parvient à l'auteur |
| | tout autre statut ou type : un échec |

L'appel a une échéance, ne suit aucune redirection et lit au plus `$maxBytes` ; une erreur de transport, un délai
dépassé ou une réponse plus grande est une `RenderingFailed` avec sa raison. L'adresse doit être `http://` ou
`https://`, sans chaîne de requête.

## La réponse est assainie

`MermaidRenderer` fait passer ce que retourne le service par `phil/svg-sanitizer` avant de le conserver, sous la
politique par défaut avec les éléments `<style>` conservés, `new Policy(styleElements: true)` : les scripts, les
gestionnaires d'événements, `foreignObject` et les références externes sont retirés, les attributs `style` passent par
`phil/css-sanitizer`, et le diagramme peut être écrit sans risque dans la page. Ses identifiants, avec les références
vers eux, sont préfixés par `svg-`. Son élément `<style>`, où Mermaid écrit les couleurs et les polices du thème, et
où le code d'un diagramme peut porter son propre CSS (`themeCSS`), est conservé, chaque règle placée sous la racine du
diagramme, dont l'identifiant est écrit à partir du contenu du diagramme : les règles de Mermaid, écrites sous
l'identifiant du diagramme, gardent leur sens, deux diagrammes d'une même page ne partagent jamais une règle même si
Mermaid nomme leurs racines de la même façon, et aucune règle n'atteint le reste de la page — voir la politique de `phil/svg-sanitizer`. Le service dessine les libellés en texte
SVG, jamais en HTML dans un `foreignObject` : ils sont donc conservés. Une réponse que l'assainisseur refuse en entier
est un échec. Un moteur de rendu qui reçoit un autre `SvgSanitizer` utilise plutôt sa politique.
