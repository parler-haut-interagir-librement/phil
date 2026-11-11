// The inline HTML of a block's text, cleaned in the browser by the rules phil/ujo applies on the server: the same
// tools of its `rich` profile, written back in the same canonical form, so that what the editor saves is what the
// server keeps. Pure functions, without the DOM.

/** Elements whose content is dropped with them. */
export const DROPPED = [
    'script',
    'style',
    'iframe',
    'object',
    'embed',
    'template',
    'noscript',
    'textarea',
    'select',
    'svg',
    'math',
    'title',
    'head',
    'frameset',
    'frame',
    'applet',
    'audio',
    'video',
    'canvas',
    'form',
    'xml',
];

const VOID = ['br', 'hr', 'img', 'wbr', 'input', 'meta', 'link', 'col', 'area', 'base', 'source', 'track', 'param'];

const RELATIONS = ['nofollow', 'noopener', 'noreferrer', 'ugc', 'sponsored', 'external'];

/**
 * The inline tools of phil/ujo's `rich` profile the editor writes, in the profile's order: a narrower pattern comes
 * before a wider one. `accepts` are `tag`, `tag.class` or `tag[attribute]`.
 */
export const INLINE_TOOLS = [
    { name: 'bold', tag: 'b', accepts: ['b', 'strong'] },
    { name: 'italic', tag: 'i', accepts: ['i', 'em'] },
    { name: 'underline', tag: 'u', accepts: ['u'], className: 'cdx-underline' },
    { name: 'strikethrough', tag: 's', accepts: ['s', 'strike', 'del'], className: 'cdx-strikethrough' },
    { name: 'marker', tag: 'mark', accepts: ['mark'], className: 'cdx-marker' },
    { name: 'inlineCode', tag: 'code', accepts: ['code', 'span.inline-code'], className: 'inline-code' },
    {
        name: 'mention',
        tag: 'a',
        accepts: ['a[data-mention]'],
        attributes: { href: 'link', 'data-mention': 'reference' },
        required: ['data-mention'],
    },
    {
        name: 'wikiLink',
        tag: 'a',
        accepts: ['a[data-ref]'],
        attributes: { href: 'link', 'data-ref': 'reference' },
        required: ['data-ref'],
    },
    {
        name: 'link',
        tag: 'a',
        accepts: ['a'],
        attributes: { href: 'link', target: 'target', rel: 'rel' },
        required: ['href'],
    },
    { name: 'superscript', tag: 'sup', accepts: ['sup'] },
    { name: 'subscript', tag: 'sub', accepts: ['sub'] },
    { name: 'lineBreak', tag: 'br', accepts: ['br'] },
    {
        name: 'textColor',
        tag: 'span',
        accepts: ['span.text-color'],
        className: 'text-color',
        attributes: { 'data-color': 'token' },
        required: ['data-color'],
    },
];

/** The marks a pasted element's style stands for, as Word and Google Docs write them. */
const STYLE_MARKS = [
    ['bold', /font-weight\s*:\s*(bold|bolder|[6-9]00)\b/i],
    ['italic', /font-style\s*:\s*italic\b/i],
    ['underline', /text-decoration(-line)?\s*:[^;]*\bunderline\b/i],
    ['strikethrough', /text-decoration(-line)?\s*:[^;]*\bline-through\b/i],
    ['superscript', /vertical-align\s*:\s*super\b/i],
    ['subscript', /vertical-align\s*:\s*sub\b/i],
];

const ENTITIES = {
    amp: '&',
    lt: '<',
    gt: '>',
    quot: '"',
    apos: "'",
    nbsp: ' ',
    shy: '­',
    copy: '©',
    reg: '®',
    trade: '™',
    hellip: '…',
    mdash: '—',
    ndash: '–',
    lsquo: '‘',
    rsquo: '’',
    sbquo: '‚',
    ldquo: '“',
    rdquo: '”',
    bdquo: '„',
    laquo: '«',
    raquo: '»',
    lsaquo: '‹',
    rsaquo: '›',
    bull: '•',
    middot: '·',
    deg: '°',
    euro: '€',
    pound: '£',
    yen: '¥',
    cent: '¢',
    sect: '§',
    para: '¶',
    times: '×',
    divide: '÷',
    plusmn: '±',
    frac12: '½',
    frac14: '¼',
    frac34: '¾',
    iexcl: '¡',
    iquest: '¿',
    dagger: '†',
    Dagger: '‡',
    permil: '‰',
    prime: '′',
    Prime: '″',
    thinsp: ' ',
    ensp: ' ',
    emsp: ' ',
    zwj: '‍',
    zwnj: '‌',
    agrave: 'à',
    aacute: 'á',
    acirc: 'â',
    atilde: 'ã',
    auml: 'ä',
    aring: 'å',
    aelig: 'æ',
    ccedil: 'ç',
    egrave: 'è',
    eacute: 'é',
    ecirc: 'ê',
    euml: 'ë',
    igrave: 'ì',
    iacute: 'í',
    icirc: 'î',
    iuml: 'ï',
    ntilde: 'ñ',
    ograve: 'ò',
    oacute: 'ó',
    ocirc: 'ô',
    otilde: 'õ',
    ouml: 'ö',
    oslash: 'ø',
    oelig: 'œ',
    ugrave: 'ù',
    uacute: 'ú',
    ucirc: 'û',
    uuml: 'ü',
    yacute: 'ý',
    yuml: 'ÿ',
    szlig: 'ß',
    Agrave: 'À',
    Aacute: 'Á',
    Acirc: 'Â',
    Atilde: 'Ã',
    Auml: 'Ä',
    Aring: 'Å',
    AElig: 'Æ',
    Ccedil: 'Ç',
    Egrave: 'È',
    Eacute: 'É',
    Ecirc: 'Ê',
    Euml: 'Ë',
    Igrave: 'Ì',
    Iacute: 'Í',
    Icirc: 'Î',
    Iuml: 'Ï',
    Ntilde: 'Ñ',
    Ograve: 'Ò',
    Oacute: 'Ó',
    Ocirc: 'Ô',
    Otilde: 'Õ',
    Ouml: 'Ö',
    Oslash: 'Ø',
    OElig: 'Œ',
    Ugrave: 'Ù',
    Uacute: 'Ú',
    Ucirc: 'Û',
    Uuml: 'Ü',
    Yacute: 'Ý',
    Yuml: 'Ÿ',
};

/** Text decoded: named entities of the table above, decimal and hexadecimal references. */
export function decodeEntities(text) {
    return text.replace(/&(#[0-9]{1,7}|#[xX][0-9a-fA-F]{1,6}|[A-Za-z][A-Za-z0-9]{1,31});/g, (whole, name) => {
        if ('#' !== name[0]) {
            return ENTITIES[name] ?? whole;
        }

        const code = 'x' === name[1].toLowerCase() ? parseInt(name.slice(2), 16) : parseInt(name.slice(1), 10);

        return code > 0 && code <= 0x10ffff && (code < 0xd800 || code > 0xdfff) ? String.fromCodePoint(code) : '�';
    });
}

/** Text escaped as PHP's htmlspecialchars() with ENT_QUOTES does: what phil/ujo writes. */
export function escapeHtml(text) {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * Whether an address may be a link (`http`, `https`, `mailto`, `tel` or relative) or, with `media`, a source
 * (`http`, `https` or relative): never `javascript:`, never protocol-relative, never with spaces or controls.
 */
export function isSafeUrl(url, { media = false } = {}) {
    // biome-ignore lint/suspicious/noControlCharactersInRegex: the controls are what an address may not hold.
    if ('' === url || /[\x00-\x20\x7f\\]/.test(url)) {
        return false;
    }

    const scheme = /^([A-Za-z][A-Za-z0-9+.-]*):/.exec(url);

    if (null === scheme) {
        return !url.startsWith('//');
    }

    const name = scheme[1].toLowerCase();
    const allowed = media ? ['http', 'https'] : ['http', 'https', 'mailto', 'tel'];

    return (
        allowed.includes(name) && (!['http', 'https'].includes(name) || /^https?:\/\/[^/?#@]+(?:[/?#]|$)/i.test(url))
    );
}

/** An attribute value cleaned as phil/ujo's AttributeValue does, or null when refused. */
export function cleanAttribute(kind, value) {
    switch (kind) {
        case 'link':
            return isSafeUrl(value) ? value : null;
        case 'token':
            return /^[A-Za-z0-9_-]{1,64}$/.test(value) ? value : null;
        case 'reference':
            return /^[a-z][a-z0-9_-]{0,31}:[A-Za-z0-9_./-]{1,128}$/.test(value) ? value : null;
        case 'target':
            return '_blank' === value.toLowerCase() ? '_blank' : null;
        default: {
            const words = value.toLowerCase().match(/[a-z]+/g) ?? [];
            const kept = RELATIONS.filter((relation) => words.includes(relation));

            return 0 === kept.length ? null : kept.join(' ');
        }
    }
}

/**
 * The tokens of a fragment: `{kind: 'text', text}`, `{kind: 'start', name, attributes}`, `{kind: 'end', name}`,
 * names in lower case, text decoded. Comments, `<!…>` declarations and the content of dropped elements are left out;
 * with `conditions`, Word's `<![if …]>` and `<![endif]>` markers are kept as `{kind: 'if', condition}`.
 */
export function tokenize(html, { conditions = false } = {}) {
    const tokens = [];
    const pattern =
        /<!--[\s\S]*?(?:-->|$)|<!\[(endif|if[^\]]*)\]>|<![^>]*>|<(\/?)([A-Za-z][A-Za-z0-9:-]*)((?:[^>"']|"[^"]*"|'[^']*')*)>/g;
    let at = 0;
    let dropping = null;
    for (const match of html.matchAll(pattern)) {
        const text = html.slice(at, match.index);
        at = match.index + match[0].length;
        const name = (match[3] ?? '').toLowerCase();
        const end = '/' === match[2];

        if (null !== dropping) {
            dropping = end && name === dropping ? null : dropping;
            continue;
        }

        if ('' !== text) {
            tokens.push({ kind: 'text', text: decodeEntities(text) });
        }

        if (undefined !== match[1]) {
            if (conditions) {
                tokens.push({ kind: 'if', condition: match[1].toLowerCase() });
            }
        } else if ('' === name) {
            // A comment or a declaration.
        } else if (DROPPED.includes(name)) {
            dropping = end || /\/\s*$/.test(match[4]) ? null : name;
        } else {
            tokens.push(end ? { kind: 'end', name } : { kind: 'start', name, attributes: parseAttributes(match[4]) });
        }
    }

    const rest = html.slice(at);

    if (null === dropping && '' !== rest) {
        tokens.push({ kind: 'text', text: decodeEntities(rest) });
    }

    return tokens;
}

/** The attributes of a start tag, names in lower case, values decoded; the first of a name wins. */
export function parseAttributes(source) {
    const attributes = {};
    const pattern = /([^\s"'>/=]+)(?:\s*=\s*("[^"]*"|'[^']*'|[^\s"'=<>`]+))?/g;
    for (const match of source.matchAll(pattern)) {
        const name = match[1].toLowerCase();
        const value = match[2] ?? '';

        if (!(name in attributes)) {
            attributes[name] = decodeEntities('"' === value[0] || "'" === value[0] ? value.slice(1, -1) : value);
        }
    }

    return attributes;
}

/** The tools of the `rich` profile among those named, in the profile's order. */
export function profile(names = INLINE_TOOLS.map((tool) => tool.name)) {
    return INLINE_TOOLS.filter((tool) => names.includes(tool.name));
}

function accepts(tool, name, attributes) {
    return tool.accepts.some((pattern) => {
        const [, tag, className, attribute] = /^([a-z][a-z0-9]*)(?:\.([A-Za-z0-9_-]+)|\[([a-z-]+)])?$/.exec(pattern);

        return (
            tag === name &&
            (undefined === className || (attributes.class ?? '').split(' ').includes(className)) &&
            (undefined === attribute || attribute in attributes)
        );
    });
}

function clean(tool, attributes) {
    const kept = undefined === tool.className ? {} : { class: tool.className };

    for (const [name, kind] of Object.entries(tool.attributes ?? {})) {
        const value = name in attributes ? cleanAttribute(kind, attributes[name]) : null;

        if (null !== value) {
            kept[name] = value;
        } else if ((tool.required ?? []).includes(name)) {
            return null;
        }
    }

    return kept;
}

/** The first tool of a profile that writes an element, with the attributes it keeps; null when none does. */
export function match(tools, name, attributes) {
    for (const tool of tools) {
        const kept = accepts(tool, name, attributes) ? clean(tool, attributes) : null;

        if (null !== kept) {
            return { tool, attributes: kept };
        }
    }

    return null;
}

function startTag(tag, attributes) {
    return (
        '<' +
        tag +
        Object.entries(attributes)
            .map(([name, value]) => ' ' + name + '="' + escapeHtml(value) + '"')
            .join('') +
        '>'
    );
}

/** The tools an element's inline style stands for, those of the profile only. */
function styleMarks(tools, attributes) {
    const style = attributes.style ?? '';

    return STYLE_MARKS.filter(([name, pattern]) => pattern.test(style))
        .map(([name]) => tools.find((tool) => tool.name === name))
        .filter((tool) => undefined !== tool);
}

/**
 * A fragment with only what the tools allow, in phil/ujo's canonical form: text decoded and escaped again, elements
 * no tool writes unwrapped, dropped elements removed with their content, attributes cleaned, elements closed in
 * order, never a link in a link. With `styles`, the inline styles of pasted text become marks, and Google Docs' bold
 * wrapper of normal weight is unwrapped.
 */
export function sanitize(html, tools = INLINE_TOOLS, { styles = false } = {}) {
    let out = '';
    const open = [];

    for (const token of tokenize(html)) {
        if ('text' === token.kind) {
            out += escapeHtml(token.text);
            continue;
        }

        if ('end' === token.kind) {
            const at = open.map((entry) => entry.name).lastIndexOf(token.name);
            out += -1 === at ? '' : close(open.splice(at));
            continue;
        }

        const written = [];
        const normalWeight = styles && /font-weight\s*:\s*(normal|[1-5]00)\b/i.test(token.attributes.style ?? '');
        const found =
            normalWeight && ['b', 'strong'].includes(token.name) ? null : match(tools, token.name, token.attributes);
        const insideLink = open.some((entry) => entry.tags.includes('a'));

        if (null !== found && ('a' !== found.tool.tag || !insideLink)) {
            written.push(found.tool.tag);
            out += startTag(found.tool.tag, found.attributes);
        }

        for (const tool of styles && !VOID.includes(token.name) ? styleMarks(tools, token.attributes) : []) {
            if (!written.includes(tool.tag) && !open.some((entry) => entry.tags.includes(tool.tag))) {
                written.push(tool.tag);
                out += startTag(tool.tag, undefined === tool.className ? {} : { class: tool.className });
            }
        }

        if (!VOID.includes(token.name)) {
            open.push({ name: token.name, tags: written });
        }
    }

    return out + close(open);
}

function close(entries) {
    return entries
        .reverse()
        .map((entry) =>
            entry.tags
                .slice()
                .reverse()
                .map((tag) => '</' + tag + '>')
                .join(''),
        )
        .join('');
}

/** The text of a fragment, decoded, without elements; a line break becomes a new line. */
export function textOf(html) {
    return tokenize(html)
        .map((token) =>
            'text' === token.kind ? token.text : 'start' === token.kind && 'br' === token.name ? '\n' : '',
        )
        .join('');
}

/** Whether a fragment shows nothing: no text but spaces, no line break. */
export function isBlank(html) {
    return '' === textOf(html).replace(/[\s ]+/g, '');
}

/** Plain text as inline HTML: escaped, new lines as line breaks. */
export function fromText(text) {
    return escapeHtml(text).replace(/\r\n?|\n/g, '<br>');
}
