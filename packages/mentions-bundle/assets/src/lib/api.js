// The request of the mention controller to the block editor's member search: a `GET` with the editor's own header,
// which changes nothing and carries no token. The answer is the suggestions that name a handle, `[{handle, label,
// url, reference}]`, or none when the search fails.

import { handleOf } from './query.js';

const HEADERS = { 'X-Phil-Block-Editor': '1', Accept: 'application/json' };

export async function suggestions(url, query, fetcher, signal = undefined) {
    if ('string' !== typeof url || '' === url) {
        return [];
    }

    const separator = url.includes('?') ? '&' : '?';

    try {
        const response = await fetcher(`${url + separator}q=${encodeURIComponent(query)}`, {
            method: 'GET',
            headers: { ...HEADERS },
            credentials: 'same-origin',
            signal,
        });

        if (!response.ok) {
            return [];
        }

        const data = await response.json();
        const items = Array.isArray(data?.items) ? data.items : [];

        return items
            .map((item) => ({
                handle: handleOf(item),
                label: String(item.label ?? ''),
                url: item.url ?? null,
                reference: String(item.reference ?? ''),
            }))
            .filter((item) => null !== item.handle && '' !== item.label);
    } catch {
        return [];
    }
}
