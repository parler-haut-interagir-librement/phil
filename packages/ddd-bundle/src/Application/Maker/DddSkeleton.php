<?php

declare(strict_types=1);

namespace Phil\DddBundle\Application\Maker;

use InvalidArgumentException;

/**
 * What the `make:ddd:*` commands write, in the layout of a bounded context:
 *
 * ```text
 * src/<Context>/
 * ├── README.md
 * ├── Domain/                     # <Name>Id, Event/<Name>
 * ├── Application/                # <Name>Command, <Name>Query, <Name>Handler
 * └── Infrastructure/
 *     └── Persistence/Doctrine/Type/  # <Name>IdType
 * ```
 *
 * The files are rendered from the templates of `templates/maker/`, by replacing their placeholders: nothing in a
 * template is executed. The namespace of `src/` is the root namespace (`App` by default), and each folder below it is
 * a namespace segment.
 *
 * **Names** are PascalCase ASCII words — a letter first, then letters and digits — and a class name is never a word PHP
 * reserves. A suffix the command adds is not repeated: `AddProductCommand` and `AddProduct` both give
 * `AddProductCommand` and `AddProductHandler`, `ProductId` and `Product` both give `ProductId`.
 */
final readonly class DddSkeleton
{
    private const string NAME = '/^[A-Z][A-Za-z0-9]*$/D';

    /**
     * The words PHP refuses as a class name, in lower case: PHP compares them without case.
     */
    private const array RESERVED = [
        'abstract', 'and', 'array', 'as', 'bool', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const',
        'continue', 'declare', 'default', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor',
        'endforeach', 'endif', 'endswitch', 'endwhile', 'enum', 'eval', 'exit', 'extends', 'false', 'final', 'finally',
        'float', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include', 'instanceof',
        'insteadof', 'int', 'interface', 'isset', 'iterable', 'list', 'match', 'mixed', 'namespace', 'never', 'new',
        'null', 'object', 'or', 'parent', 'print', 'private', 'protected', 'public', 'readonly', 'require', 'resource',
        'return', 'self', 'static', 'string', 'switch', 'throw', 'trait', 'true', 'try', 'unset', 'use', 'var', 'void',
        'while', 'xor', 'yield',
    ];

    private string $templates;

    /**
     * @param string $rootNamespace   the namespace of the source directory, without a leading or trailing backslash
     * @param string $sourceDirectory the directory of that namespace, relative to the project directory
     */
    public function __construct(
        private string $rootNamespace = 'App',
        private string $sourceDirectory = 'src',
        ?string $templates = null,
    ) {
        $this->templates = $templates ?? \dirname(__DIR__, 3).'/templates/maker';
    }

    /**
     * The three layers of a new context, each kept by an empty `.gitignore`, and its README.
     *
     * @return list<SkeletonFile>
     *
     * @throws InvalidArgumentException when the name is not a PascalCase word
     */
    public function context(string $context): array
    {
        $this->name($context, 'context');
        $directory = $this->directory($context);

        return [
            new SkeletonFile($directory.'/README.md', $this->render('ContextReadme.md.tpl', [
                'context' => $context,
                'namespace' => $this->namespace($context),
            ])),
            new SkeletonFile($directory.'/Domain/.gitignore', ''),
            new SkeletonFile($directory.'/Application/.gitignore', ''),
            new SkeletonFile($directory.'/Infrastructure/.gitignore', ''),
        ];
    }

    /**
     * An identifier in the domain of the context, and its DBAL type in the infrastructure.
     *
     * @return list<SkeletonFile>
     *
     * @throws InvalidArgumentException when a name is not a PascalCase word, or the class name is reserved
     */
    public function identifier(string $context, string $name): array
    {
        $this->name($context, 'context');
        $base = $this->base($name, 'Id');
        $identifier = $base.'Id';
        $type = $identifier.'Type';
        $this->className($identifier);

        $domain = $this->namespace($context, 'Domain');
        $doctrine = $this->namespace($context, 'Infrastructure', 'Persistence', 'Doctrine', 'Type');

        return [
            new SkeletonFile($this->path($context, $identifier, 'Domain'), $this->render('Identifier.php.tpl', [
                'namespace' => $domain,
                'class' => $identifier,
                'subject' => $this->words($base),
            ])),
            new SkeletonFile($this->path($context, $type, 'Infrastructure', 'Persistence', 'Doctrine', 'Type'), $this->render('IdentifierType.php.tpl', [
                'namespace' => $doctrine,
                'class' => $type,
                'identifier' => $identifier,
                'identifier_fqcn' => $domain.'\\'.$identifier,
                'type_name' => $this->typeName($identifier),
            ])),
        ];
    }

    /**
     * The name a DBAL type of this identifier is registered under: its class name in snake case.
     *
     * @throws InvalidArgumentException when the name is not a PascalCase word
     */
    public function typeName(string $identifier): string
    {
        $base = $this->base($identifier, 'Id');

        return strtolower(implode('_', $this->split($base.'Id')));
    }

    /**
     * The class of the DBAL type `identifier()` writes, fully qualified.
     *
     * @throws InvalidArgumentException when a name is not a PascalCase word
     */
    public function typeClass(string $context, string $name): string
    {
        $this->name($context, 'context');

        return $this->namespace($context, 'Infrastructure', 'Persistence', 'Doctrine', 'Type').'\\'.$this->base($name, 'Id').'IdType';
    }

    /**
     * A command of the application layer, and its handler on the command bus.
     *
     * @return list<SkeletonFile>
     *
     * @throws InvalidArgumentException when a name is not a PascalCase word, or a class name is reserved
     */
    public function command(string $context, string $name): array
    {
        return $this->message($context, $name, 'Command');
    }

    /**
     * A query of the application layer, and its handler on the query bus.
     *
     * @return list<SkeletonFile>
     *
     * @throws InvalidArgumentException when a name is not a PascalCase word, or a class name is reserved
     */
    public function query(string $context, string $name): array
    {
        return $this->message($context, $name, 'Query');
    }

    /**
     * A domain event, in the `Event` folder of the domain.
     *
     * @return list<SkeletonFile>
     *
     * @throws InvalidArgumentException when a name is not a PascalCase word, or the class name is reserved
     */
    public function event(string $context, string $name): array
    {
        $this->name($context, 'context');
        $this->name($name, 'event');
        $this->className($name);

        return [
            new SkeletonFile($this->path($context, $name, 'Domain', 'Event'), $this->render('DomainEvent.php.tpl', [
                'namespace' => $this->namespace($context, 'Domain', 'Event'),
                'class' => $name,
                'context' => $context,
                'subject' => ucfirst($this->words($name)),
            ])),
        ];
    }

    /**
     * @param 'Command'|'Query' $kind
     *
     * @return list<SkeletonFile>
     */
    private function message(string $context, string $name, string $kind): array
    {
        $this->name($context, 'context');
        $base = $this->base($name, $kind);
        $message = $base.$kind;
        $handler = $base.'Handler';
        $this->className($message);
        $this->className($handler);
        $namespace = $this->namespace($context, 'Application');

        return [
            new SkeletonFile($this->path($context, $message, 'Application'), $this->render($kind.'.php.tpl', [
                'namespace' => $namespace,
                'class' => $message,
                'handler' => $handler,
                'subject' => ucfirst($this->words($base)),
            ])),
            new SkeletonFile($this->path($context, $handler, 'Application'), $this->render($kind.'Handler.php.tpl', [
                'namespace' => $namespace,
                'class' => $handler,
                'message' => $message,
            ])),
        ];
    }

    /**
     * The name without the suffix the command adds, when it ends with it and has something before it.
     */
    private function base(string $name, string $suffix): string
    {
        $this->name($name, 'class');

        if ($name !== $suffix && str_ends_with($name, $suffix)) {
            return substr($name, 0, -\strlen($suffix));
        }

        return $name;
    }

    private function name(string $name, string $what): void
    {
        if (1 !== preg_match(self::NAME, $name)) {
            throw new InvalidArgumentException(\sprintf('The %s name "%s" is not a PascalCase word: a capital letter, then letters and digits.', $what, $name));
        }
    }

    private function className(string $class): void
    {
        if (\in_array(strtolower($class), self::RESERVED, true)) {
            throw new InvalidArgumentException(\sprintf('"%s" is a word PHP reserves: it cannot name a class.', $class));
        }
    }

    private function directory(string $context): string
    {
        return $this->sourceDirectory.'/'.$context;
    }

    private function path(string $context, string $class, string ...$folders): string
    {
        return implode('/', [$this->directory($context), ...$folders, $class.'.php']);
    }

    private function namespace(string $context, string ...$segments): string
    {
        return implode('\\', [$this->rootNamespace, $context, ...$segments]);
    }

    /**
     * `AddProduct` as `add product`, `HtmlPage` as `html page`, `ISBNNumber` as `isbn number`.
     */
    private function words(string $name): string
    {
        return strtolower(implode(' ', $this->split($name)));
    }

    /**
     * @return list<string>
     */
    private function split(string $name): array
    {
        $words = preg_split('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', $name);

        return false === $words ? [$name] : $words;
    }

    /**
     * The imports in the order of the code style, whatever the root namespace: `use App\…` before `use Override`, and
     * `use Zoo\…` after `use Phil\…`. Only the lines at the start of a line are imports; a trait is used indented.
     */
    private function sortImports(string $source): string
    {
        $sorted = preg_replace_callback('/^(?:use [^;\n]+;\n)+/m', static function (array $block): string {
            $lines = explode("\n", rtrim($block[0], "\n"));
            usort($lines, strcasecmp(...));

            return implode("\n", $lines)."\n";
        }, $source);

        return $sorted ?? $source;
    }

    /**
     * @param array<string, string> $values
     */
    private function render(string $template, array $values): string
    {
        $file = $this->templates.'/'.$template;
        $source = is_file($file) ? file_get_contents($file) : false;

        if (false === $source) {
            throw new InvalidArgumentException(\sprintf('The template "%s" cannot be read.', $template));
        }

        $placeholders = [];

        foreach ($values as $key => $value) {
            $placeholders['{{'.$key.'}}'] = $value;
        }

        return $this->sortImports(strtr($source, $placeholders));
    }
}
