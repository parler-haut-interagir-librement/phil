# Pages

## Four layers

The metadata of the page being rendered is built by `ShareMetadataFactory` from four layers, each over the one
before, a null value leaving the value below it:

1. the defaults of the configuration: `site_name`, `locale`, `twitter_site`, `fediverse_creator`, `default_image`;
2. the sources of the application, each service that implements `ShareMetadataSource`, in the order of their tags;
3. what the request set, through `SharePage::set()` in a controller or `social_page()` in a template;
4. what the template passes to `social_meta()`.

The keys are those of `ShareMetadata::KEYS`, plus `card_template`, the template of the page's card, and `card`,
false for no card. Then:

- the address is the request's — its scheme, host, base path and path, without its query — when no layer gives one;
- the locale is the request's when no layer gives one;
- an image that is a medium of phil/media-bundle becomes its absolute signed address, its replacement text, its size
  and, for an image, its media type;
- a page without an image gets its card, unless `card` is false: its title, its description or the site's name as its
  subtitle, its locale, drawn from `card_template` or the default template.

A page without a title has no tags; neither has one rendered outside a request without an address. A value the tags
cannot carry — an address that is not absolute, a locale that is not one — throws `InvalidShareMetadata`, so that it
is fixed where it comes from.

## In Twig

```twig
{# templates/blog/post.html.twig #}
{% do social_page({
    title: post.title,
    description: post.summary,
    type: 'article',
    author: post.author.name,
    published_time: post.publishedAt,
    modified_time: post.updatedAt,
    alternate_locales: post.translations|keys,
}) %}

{# templates/base.html.twig, in <head> #}
{{ social_meta() }}
{{ social_meta({card_template: 'event'}) }}
```

## In a controller

```php
public function show(Post $post, SharePage $page): Response
{
    $page->set(['title' => $post->title, 'image' => $post->cover]);
    // ...
}
```

`SharePage` is reset between requests, so a worker never carries a page's values into the next one.

## Sources

```php
final readonly class ProductSource implements ShareMetadataSource
{
    public function __construct(private RequestStack $requests) {}

    public function values(): array
    {
        $product = $this->requests->getCurrentRequest()?->attributes->get('product');

        return $product instanceof Product ? ['title' => $product->name, 'description' => $product->summary, 'type' => 'website'] : [];
    }
}
```

A source is autoconfigured. A model the application shares between its pages, its search and its structured data
gives its title, description and image once, through its source.
