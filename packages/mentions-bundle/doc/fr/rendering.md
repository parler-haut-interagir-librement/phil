<!-- phil-dev source-sha256=a87dbbf4b7bb87ccd90dfd2321ccb2b8bf69bc7d0c266b97783863ad7bcc7112 -->
# Rendu

## Le filtre

```twig
{{ comment.body|mentions }}
{{ proposal.body|markdown|mentions }}
```

`mentions` change les identifiants d'un texte en liens :

```html
<a class="mention" href="/members/ada" data-mention="user:1">@ada</a>
```

| Entrée | Lue comme |
|---|---|
| Une chaîne brute | Échappée d'abord, puis changée en liens |
| Du HTML marqué sûr, comme la sortie d'un filtre Markdown | Changé en liens tel quel |

Le filtre est déclaré par `#[AsTwigFilter]` sur `MentionsExtension::link()`, avec `preEscape: 'html'` et sa sortie
marquée sûre ; TwigBundle ne charge le service que lorsqu'un gabarit appelle le filtre.

Un membre sans profil, ou dont l'adresse n'est ni relative ni en `http` ou `https`, reçoit
`<span class="mention" data-mention="user:3">@chloé</span>`. Un identifiant que personne ne porte reste tel quel.

## Ce qui est lu

`MentionLinker::link(string $html): string` ne lit que le texte entre les balises, et jamais à l'intérieur de `a`,
`code`, `pre`, `script`, `style` ou `textarea` : un lien déjà fait et le code restent tels quels. L'annuaire est
interrogé une fois par texte, pour ses premiers identifiants distincts jusqu'à `limits.per_text` ; les autres restent
tels quels. Un texte entre balises qui n'est pas de l'UTF-8 valide est laissé tel quel.

L'identifiant garde la casse avec laquelle il a été tapé ; le lien et la référence sont ceux du membre.

## Documents UJO

Un document UJO dont `phil/ujo` produit le rendu contient déjà ses marques sous forme de liens : il n'a besoin d'aucun
filtre. Un identifiant tapé dans un document sans être choisi dans le menu de l'éditeur reste du texte ; le filtre en
fait aussi un lien quand l'application l'applique au HTML rendu.

## La feuille de style

`phil-mentions/mentions.css` met en forme `.mention`, à partir des jetons de PHIL UX quand ils sont chargés et des
couleurs de la page sinon.
