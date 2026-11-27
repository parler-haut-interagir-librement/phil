<!-- phil-dev source-sha256=11b1d26bbf0fa777b809023d94de80c1913b26da65603db7666bf7b43ac44f1f -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/sitemap` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-11

### Sécurité

- Une adresse — d'une page, d'une version alternative, d'une image, d'un fichier de plan du site — est lue par
  `phil/url` comme la lit un navigateur, et non plus par `filter_var(FILTER_VALIDATE_URL)`, qui prenait un nom
  d'utilisateur pour un hôte : `https://example.com@evil.example/`, une page sur evil.example, passait pour une page
  d'example.com. Une adresse avec un nom d'utilisateur ou un mot de passe, ou dont l'hôte est une adresse IP
  (`https://127.0.0.1/`, `https://2130706433/`) ou un nom seul (`http://intranet/`), ou avec le port 0, est désormais
  une `InvalidSitemap`.

### Ajouté

- `allowLocalhost`, `false` par défaut, sur `Url`, `Alternate`, `Image` et `XmlWriter::index()` : `true`, en
  développement et en test, permet à une adresse d'être sur `localhost` ou sous `.localhost` ; une adresse IP reste
  refusée.

### Modifié

- `Url::$loc`, `Alternate::$href` et `Image::$loc` contiennent l'adresse dans sa forme canonique, que le XML écrit : le
  schéma et l'hôte en minuscules, un hôte international dans sa forme ASCII (accepté, alors qu'il était refusé), le port
  par défaut retiré, le chemin racine écrit `/`, les segments `.` et `..` résolus, échappés ou non, et les caractères
  qu'une partie ne peut pas contenir encodés en pourcentage, `"` parmi eux. Les constructeurs gardent leurs arguments,
  `allowLocalhost` en plus.
- Le paquet exige `phil/url` 0.1.3 au lieu d'`ext-filter`.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Url`, `ChangeFrequency`, `Alternate` (les versions alternatives `hreflang`) et `Image`, vérifiés comme l'attendent le
  protocole et les moteurs de recherche ; `InvalidSitemap`.
- `UrlContainer` et `UrlProvider`, le contrat des parties d'un site qui ajoutent des pages.
- `Sitemap` : les pages par section, éventuellement d'une seule section, coupées en fichiers de 50 000 pages au plus.
  Une section ne peut pas finir par un trait de soulignement et des chiffres (`news_1`), les noms des parties numérotées
  d'une section ; `Sitemap::sectionOfFile()` lit la section du nom d'un fichier, et `Sitemap::sectionNamed()` donne à
  une clé qui finit ainsi — `news_2026` — la section `news-2026`.
- `XmlWriter` : l'`urlset` d'un fichier, avec les versions alternatives et les images, et le `sitemapindex` des
  fichiers.
- La documentation dans `doc/`, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
