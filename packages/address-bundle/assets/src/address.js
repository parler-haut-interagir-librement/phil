/*
 * What an address form shows of a country, from the format endpoint's answer: for each field, whether it is shown,
 * its label and whether it is required; the states or provinces to choose from. No DOM here: the controller applies it.
 */

/**
 * @param {{fields: {name: string, label: string, required: boolean}[], subdivisions: {code: string, name: string}[]}} format
 * @param {string[]} names the fields the form has
 * @returns {{name: string, shown: boolean, label: string|null, required: boolean}[]}
 */
export function plan(format, names) {
    const fields = new Map(format.fields.map((field) => [field.name, field]));

    return names.map((name) => {
        const field = fields.get(name);

        return { name, shown: field !== undefined, label: field?.label ?? null, required: field?.required ?? false };
    });
}

/**
 * The URL of a country's format, from the endpoint's URL with its place for the country.
 */
export function formatUrl(url, country) {
    return url.replace('__COUNTRY__', encodeURIComponent(country));
}
