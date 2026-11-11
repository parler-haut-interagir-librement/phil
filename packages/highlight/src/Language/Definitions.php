<?php

declare(strict_types=1);

namespace Phil\Highlight\Language;

/**
 * The syntaxes of the programming languages the library ships, one method each. The word lists were written for this
 * library from each language's reference; a list holds what a reader expects coloured, not every reserved word of
 * every version.
 */
final class Definitions
{
    private const array C_KEYWORDS = ['auto', 'break', 'case', 'const', 'continue', 'default', 'do', 'else', 'enum', 'extern', 'for', 'goto', 'if', 'inline', 'register', 'restrict', 'return', 'sizeof', 'static', 'struct', 'switch', 'typedef', 'union', 'volatile', 'while', '_Alignas', '_Alignof', '_Atomic', '_Generic', '_Noreturn', '_Static_assert', '_Thread_local'];

    private const array C_TYPES = ['char', 'double', 'float', 'int', 'long', 'short', 'signed', 'unsigned', 'void', 'bool', '_Bool', '_Complex', 'size_t', 'ptrdiff_t', 'int8_t', 'int16_t', 'int32_t', 'int64_t', 'uint8_t', 'uint16_t', 'uint32_t', 'uint64_t', 'FILE'];

    private const array JS_KEYWORDS = ['as', 'async', 'await', 'break', 'case', 'catch', 'class', 'const', 'continue', 'debugger', 'default', 'delete', 'do', 'else', 'export', 'extends', 'finally', 'for', 'from', 'function', 'get', 'if', 'import', 'in', 'instanceof', 'let', 'new', 'of', 'return', 'set', 'static', 'super', 'switch', 'this', 'throw', 'try', 'typeof', 'var', 'void', 'while', 'with', 'yield'];

    private const array JS_CONSTANTS = ['true', 'false', 'null', 'undefined', 'NaN', 'Infinity'];

    private const array JS_BUILTINS = ['console', 'window', 'document', 'globalThis', 'require', 'module', 'process'];

    public static function php(): Syntax
    {
        return new Syntax(
            'php',
            aliases: ['php8', 'phtml'],
            keywords: ['abstract', 'and', 'as', 'break', 'case', 'catch', 'class', 'clone', 'const', 'continue', 'declare', 'default', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'enum', 'extends', 'final', 'finally', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include', 'include_once', 'instanceof', 'insteadof', 'interface', 'isset', 'list', 'match', 'namespace', 'new', 'or', 'print', 'private', 'protected', 'public', 'readonly', 'require', 'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var', 'while', 'xor', 'yield'],
            types: ['array', 'bool', 'callable', 'float', 'int', 'iterable', 'mixed', 'never', 'object', 'parent', 'self', 'string', 'void'],
            constants: ['true', 'false', 'null', 'TRUE', 'FALSE', 'NULL', '__CLASS__', '__DIR__', '__FILE__', '__FUNCTION__', '__LINE__', '__METHOD__', '__NAMESPACE__', '__TRAIT__', 'PHP_EOL', 'PHP_VERSION'],
            lineComments: ['//', '#'],
            blockComments: ['/*' => '*/'],
            quotes: [new Quote('"', multiline: true), new Quote("'", multiline: true)],
            variablePrefixes: '$',
            metaMarks: ['<?php', '<?=', '?>', '#['],
            capitalizedTypes: true,
        );
    }

    public static function javascript(): Syntax
    {
        return new Syntax(
            'javascript',
            aliases: ['js', 'mjs', 'cjs', 'jsx', 'node'],
            keywords: self::JS_KEYWORDS,
            constants: self::JS_CONSTANTS,
            builtins: self::JS_BUILTINS,
            lineComments: ['//'],
            blockComments: ['/*' => '*/'],
            quotes: [new Quote('"'), new Quote("'"), new Quote('`', multiline: true)],
            annotation: '@',
            capitalizedTypes: true,
            regex: true,
            shebang: true,
            wordChars: '$',
        );
    }

    public static function typescript(): Syntax
    {
        return new Syntax(
            'typescript',
            aliases: ['ts', 'tsx', 'mts', 'cts'],
            keywords: [...self::JS_KEYWORDS, 'abstract', 'asserts', 'declare', 'enum', 'implements', 'infer', 'interface', 'is', 'keyof', 'namespace', 'override', 'private', 'protected', 'public', 'readonly', 'satisfies', 'type', 'unique'],
            types: ['any', 'bigint', 'boolean', 'never', 'number', 'object', 'string', 'symbol', 'unknown'],
            constants: self::JS_CONSTANTS,
            builtins: self::JS_BUILTINS,
            lineComments: ['//'],
            blockComments: ['/*' => '*/'],
            quotes: [new Quote('"'), new Quote("'"), new Quote('`', multiline: true)],
            annotation: '@',
            capitalizedTypes: true,
            regex: true,
            shebang: true,
            wordChars: '$',
        );
    }

    /**
     * JSON, comments of JSONC allowed.
     */
    public static function json(): Syntax
    {
        return new Syntax(
            'json',
            aliases: ['jsonc', 'json5', 'geojson', 'webmanifest'],
            constants: ['true', 'false', 'null'],
            lineComments: ['//'],
            blockComments: ['/*' => '*/'],
            quotes: [new Quote('"')],
            propertyKeys: true,
            operators: '+-',
            punctuation: '{}[],:',
        );
    }

    public static function python(): Syntax
    {
        return new Syntax(
            'python',
            aliases: ['py', 'py3', 'python3', 'gyp'],
            keywords: ['and', 'as', 'assert', 'async', 'await', 'break', 'case', 'class', 'continue', 'def', 'del', 'elif', 'else', 'except', 'finally', 'for', 'from', 'global', 'if', 'import', 'in', 'is', 'lambda', 'match', 'nonlocal', 'not', 'or', 'pass', 'raise', 'return', 'try', 'while', 'with', 'yield'],
            types: ['bool', 'bytearray', 'bytes', 'complex', 'dict', 'float', 'frozenset', 'int', 'list', 'object', 'set', 'str', 'tuple', 'type'],
            constants: ['True', 'False', 'None', 'Ellipsis', 'NotImplemented', '__name__'],
            builtins: ['abs', 'all', 'any', 'enumerate', 'filter', 'getattr', 'hasattr', 'input', 'isinstance', 'iter', 'len', 'map', 'max', 'min', 'next', 'open', 'print', 'range', 'repr', 'reversed', 'round', 'setattr', 'sorted', 'sum', 'super', 'zip'],
            lineComments: ['#'],
            quotes: [new Quote('"""', multiline: true), new Quote("'''", multiline: true), new Quote('"'), new Quote("'")],
            stringPrefixes: 'rRbBfFuU',
            annotation: '@',
            capitalizedTypes: true,
            shebang: true,
            operators: '+-*/%=<>!&|^~:@',
        );
    }

    public static function ruby(): Syntax
    {
        return new Syntax(
            'ruby',
            aliases: ['rb', 'gemspec', 'podspec', 'rake'],
            keywords: ['BEGIN', 'END', 'alias', 'and', 'begin', 'break', 'case', 'class', 'def', 'defined?', 'do', 'else', 'elsif', 'end', 'ensure', 'for', 'if', 'in', 'module', 'next', 'not', 'or', 'redo', 'rescue', 'retry', 'return', 'self', 'super', 'then', 'undef', 'unless', 'until', 'when', 'while', 'yield'],
            constants: ['true', 'false', 'nil', '__FILE__', '__LINE__', '__method__'],
            builtins: ['attr_accessor', 'attr_reader', 'attr_writer', 'extend', 'include', 'lambda', 'loop', 'p', 'prepend', 'print', 'private', 'proc', 'protected', 'public', 'puts', 'raise', 'require', 'require_relative'],
            lineComments: ['#'],
            blockComments: ['=begin' => '=end'],
            quotes: [new Quote('"', multiline: true), new Quote("'", multiline: true), new Quote('`', multiline: true)],
            variablePrefixes: '@$',
            capitalizedTypes: true,
            regex: true,
            shebang: true,
            symbols: true,
            wordSuffixes: '?!',
        );
    }

    public static function go(): Syntax
    {
        return new Syntax(
            'go',
            aliases: ['golang'],
            keywords: ['break', 'case', 'chan', 'const', 'continue', 'default', 'defer', 'else', 'fallthrough', 'for', 'func', 'go', 'goto', 'if', 'import', 'interface', 'map', 'package', 'range', 'return', 'select', 'struct', 'switch', 'type', 'var'],
            types: ['any', 'bool', 'byte', 'comparable', 'complex64', 'complex128', 'error', 'float32', 'float64', 'int', 'int8', 'int16', 'int32', 'int64', 'rune', 'string', 'uint', 'uint8', 'uint16', 'uint32', 'uint64', 'uintptr'],
            constants: ['true', 'false', 'nil', 'iota'],
            builtins: ['append', 'cap', 'clear', 'close', 'complex', 'copy', 'delete', 'imag', 'len', 'make', 'max', 'min', 'new', 'panic', 'print', 'println', 'real', 'recover'],
            lineComments: ['//'],
            blockComments: ['/*' => '*/'],
            quotes: [new Quote('"'), new Quote('`', escape: '', multiline: true), new Quote("'", character: true)],
        );
    }

    public static function rust(): Syntax
    {
        return new Syntax(
            'rust',
            aliases: ['rs'],
            keywords: ['as', 'async', 'await', 'break', 'const', 'continue', 'crate', 'dyn', 'else', 'enum', 'extern', 'fn', 'for', 'if', 'impl', 'in', 'let', 'loop', 'match', 'mod', 'move', 'mut', 'pub', 'ref', 'return', 'self', 'Self', 'static', 'struct', 'super', 'trait', 'type', 'unsafe', 'use', 'where', 'while'],
            types: ['bool', 'char', 'f32', 'f64', 'i8', 'i16', 'i32', 'i64', 'i128', 'isize', 'str', 'u8', 'u16', 'u32', 'u64', 'u128', 'usize'],
            constants: ['true', 'false', 'None', 'Some', 'Ok', 'Err'],
            lineComments: ['//'],
            blockComments: ['/*' => '*/'],
            quotes: [new Quote('"', multiline: true), new Quote("'", character: true)],
            stringPrefixes: 'br',
            metaMarks: ['#![', '#['],
            capitalizedTypes: true,
            macros: true,
        );
    }

    public static function java(): Syntax
    {
        return new Syntax(
            'java',
            aliases: ['jsp'],
            keywords: ['abstract', 'assert', 'break', 'case', 'catch', 'class', 'const', 'continue', 'default', 'do', 'else', 'enum', 'extends', 'final', 'finally', 'for', 'goto', 'if', 'implements', 'import', 'instanceof', 'interface', 'native', 'new', 'package', 'permits', 'private', 'protected', 'public', 'record', 'return', 'sealed', 'static', 'strictfp', 'super', 'switch', 'synchronized', 'this', 'throw', 'throws', 'transient', 'try', 'var', 'volatile', 'while', 'yield'],
            types: ['boolean', 'byte', 'char', 'double', 'float', 'int', 'long', 'short', 'void'],
            constants: ['true', 'false', 'null'],
            lineComments: ['//'],
            blockComments: ['/*' => '*/'],
            quotes: [new Quote('"""', multiline: true), new Quote('"'), new Quote("'", character: true)],
            annotation: '@',
            capitalizedTypes: true,
        );
    }

    public static function c(): Syntax
    {
        return new Syntax(
            'c',
            aliases: ['h'],
            keywords: self::C_KEYWORDS,
            types: self::C_TYPES,
            constants: ['NULL', 'true', 'false', 'EOF', 'stdin', 'stdout', 'stderr'],
            lineComments: ['//'],
            blockComments: ['/*' => '*/'],
            quotes: [new Quote('"'), new Quote("'", character: true)],
            stringPrefixes: 'LuU8',
            preprocessor: true,
        );
    }

    public static function cpp(): Syntax
    {
        return new Syntax(
            'cpp',
            aliases: ['c++', 'cc', 'cxx', 'hpp', 'hh', 'hxx'],
            keywords: [...self::C_KEYWORDS, 'alignas', 'alignof', 'and', 'and_eq', 'asm', 'bitand', 'bitor', 'catch', 'class', 'co_await', 'co_return', 'co_yield', 'compl', 'concept', 'const_cast', 'consteval', 'constexpr', 'constinit', 'decltype', 'delete', 'dynamic_cast', 'explicit', 'export', 'final', 'friend', 'mutable', 'namespace', 'new', 'noexcept', 'not', 'not_eq', 'operator', 'or', 'or_eq', 'override', 'private', 'protected', 'public', 'reinterpret_cast', 'requires', 'static_assert', 'static_cast', 'template', 'this', 'thread_local', 'throw', 'try', 'typeid', 'typename', 'using', 'virtual', 'xor', 'xor_eq'],
            types: [...self::C_TYPES, 'char8_t', 'char16_t', 'char32_t', 'wchar_t', 'string', 'vector', 'map', 'unique_ptr', 'shared_ptr'],
            constants: ['nullptr', 'NULL', 'true', 'false'],
            lineComments: ['//'],
            blockComments: ['/*' => '*/'],
            quotes: [new Quote('"'), new Quote("'", character: true)],
            stringPrefixes: 'LuU8',
            preprocessor: true,
        );
    }

    public static function csharp(): Syntax
    {
        return new Syntax(
            'csharp',
            aliases: ['cs', 'c#', 'dotnet'],
            keywords: ['abstract', 'as', 'async', 'await', 'base', 'break', 'case', 'catch', 'checked', 'class', 'const', 'continue', 'default', 'delegate', 'do', 'else', 'enum', 'event', 'explicit', 'extern', 'finally', 'fixed', 'for', 'foreach', 'get', 'goto', 'if', 'implicit', 'in', 'init', 'interface', 'internal', 'is', 'lock', 'nameof', 'namespace', 'new', 'operator', 'out', 'override', 'params', 'partial', 'private', 'protected', 'public', 'readonly', 'record', 'ref', 'return', 'sealed', 'set', 'sizeof', 'stackalloc', 'static', 'struct', 'switch', 'this', 'throw', 'try', 'typeof', 'unchecked', 'unsafe', 'using', 'value', 'var', 'virtual', 'volatile', 'when', 'where', 'while', 'yield'],
            types: ['bool', 'byte', 'char', 'decimal', 'double', 'dynamic', 'float', 'int', 'long', 'nint', 'nuint', 'object', 'sbyte', 'short', 'string', 'uint', 'ulong', 'ushort', 'void'],
            constants: ['true', 'false', 'null'],
            lineComments: ['//'],
            blockComments: ['/*' => '*/'],
            quotes: [new Quote('"""', multiline: true), new Quote('@"', '"', escape: '', multiline: true), new Quote('"'), new Quote("'", character: true)],
            stringPrefixes: '$',
            preprocessor: true,
            capitalizedTypes: true,
        );
    }

    public static function sql(): Syntax
    {
        return new Syntax(
            'sql',
            aliases: ['mysql', 'postgresql', 'postgres', 'pgsql', 'sqlite', 'plsql', 'tsql', 'mariadb'],
            keywords: ['add', 'all', 'alter', 'and', 'as', 'asc', 'begin', 'between', 'by', 'cascade', 'case', 'cast', 'check', 'column', 'commit', 'constraint', 'create', 'cross', 'default', 'delete', 'desc', 'distinct', 'drop', 'else', 'end', 'except', 'exists', 'fetch', 'first', 'foreign', 'from', 'full', 'grant', 'group', 'having', 'if', 'ilike', 'in', 'index', 'inner', 'insert', 'intersect', 'into', 'is', 'join', 'key', 'lateral', 'left', 'like', 'limit', 'natural', 'next', 'not', 'offset', 'on', 'only', 'or', 'order', 'outer', 'over', 'partition', 'primary', 'recursive', 'references', 'rename', 'returning', 'revoke', 'right', 'rollback', 'rows', 'select', 'set', 'table', 'then', 'to', 'transaction', 'truncate', 'union', 'unique', 'update', 'using', 'values', 'view', 'when', 'where', 'window', 'with'],
            types: ['bigint', 'blob', 'boolean', 'bytea', 'char', 'date', 'decimal', 'double', 'float', 'int', 'integer', 'interval', 'json', 'jsonb', 'numeric', 'precision', 'real', 'serial', 'smallint', 'text', 'time', 'timestamp', 'uuid', 'varchar'],
            constants: ['true', 'false', 'null'],
            builtins: ['abs', 'avg', 'coalesce', 'count', 'length', 'lower', 'max', 'min', 'now', 'nullif', 'round', 'substring', 'sum', 'trim', 'upper'],
            lineComments: ['--'],
            blockComments: ['/*' => '*/'],
            quotes: [new Quote("'", escape: '', multiline: true), new Quote('"', escape: ''), new Quote('`', escape: '')],
            variablePrefixes: '@$',
            caseInsensitive: true,
            punctuation: '()[];,.',
        );
    }

    public static function bash(): Syntax
    {
        return new Syntax(
            'bash',
            aliases: ['sh', 'shell', 'zsh', 'ksh'],
            keywords: ['alias', 'break', 'case', 'continue', 'declare', 'do', 'done', 'elif', 'else', 'esac', 'exit', 'export', 'fi', 'for', 'function', 'if', 'in', 'local', 'readonly', 'return', 'select', 'shift', 'source', 'then', 'time', 'trap', 'typeset', 'unset', 'until', 'while'],
            builtins: ['bg', 'builtin', 'cd', 'command', 'echo', 'eval', 'exec', 'false', 'fg', 'getopts', 'hash', 'jobs', 'kill', 'let', 'mapfile', 'popd', 'printf', 'pushd', 'pwd', 'read', 'set', 'test', 'true', 'type', 'umask', 'wait'],
            lineComments: ['#'],
            quotes: [new Quote('"', multiline: true), new Quote("'", escape: '', multiline: true), new Quote('`', multiline: true)],
            variablePrefixes: '$',
            specialVariables: '#?@*!$-',
            shebang: true,
            commentAfterSpace: true,
            operators: '|&;<>=!',
            punctuation: '()[]{}',
        );
    }

    /**
     * The expressions between Twig's delimiters, for `Twig`.
     */
    public static function twigExpression(): Syntax
    {
        return new Syntax(
            'twig-expression',
            keywords: ['and', 'apply', 'as', 'autoescape', 'block', 'cache', 'deprecated', 'do', 'else', 'elseif', 'embed', 'endapply', 'endautoescape', 'endblock', 'endcache', 'endembed', 'endfor', 'endif', 'endmacro', 'endsandbox', 'endset', 'endverbatim', 'endwith', 'ends', 'extends', 'flush', 'for', 'from', 'guard', 'if', 'import', 'in', 'include', 'is', 'macro', 'matches', 'not', 'only', 'or', 'sandbox', 'set', 'starts', 'use', 'verbatim', 'with', 'defined', 'empty', 'even', 'odd', 'iterable', 'same', 'divisible'],
            constants: ['true', 'false', 'null', 'none', 'TRUE', 'FALSE', 'NULL', 'NONE'],
            quotes: [new Quote('"', multiline: true), new Quote("'", multiline: true)],
            callAfter: '|',
            operators: '+-*/%=<>!?:~|',
            punctuation: '()[]{},.',
        );
    }
}
