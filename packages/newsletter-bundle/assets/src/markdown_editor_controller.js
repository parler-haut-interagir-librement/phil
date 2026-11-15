import { Controller } from '@hotwired/stimulus';

/**
 * The Markdown editor of a campaign: buttons that wrap the selection in Markdown, and a preview the server lays out
 * as the message, shown in a frame sandboxed without scripts. The text area stays the field the form sends, so the
 * editor works without this controller too.
 */
const FORMATS = {
    heading: { before: '## ', after: '', line: true },
    bold: { before: '**', after: '**' },
    italic: { before: '_', after: '_' },
    link: { before: '[', after: '](https://)' },
    list: { before: '- ', after: '', line: true },
    quote: { before: '> ', after: '', line: true },
};

export default class extends Controller {
    static targets = ['source', 'frame'];

    static values = { previewUrl: String };

    format(event) {
        const format = FORMATS[event.params.format];

        if (!format) {
            return;
        }

        const area = this.sourceTarget;
        const start = format.line ? area.value.lastIndexOf('\n', area.selectionStart - 1) + 1 : area.selectionStart;
        const end = area.selectionEnd;
        const selected = area.value.slice(start, end);

        area.setRangeText(format.before + selected + format.after, start, end, 'end');
        area.dispatchEvent(new Event('input', { bubbles: true }));
        area.focus();
    }

    async preview() {
        if (!this.previewUrlValue) {
            return;
        }

        const body = new FormData();
        body.append('markdown', this.sourceTarget.value);

        const response = await fetch(this.previewUrlValue, { method: 'POST', body, credentials: 'same-origin' });

        this.frameTarget.srcdoc = response.ok ? await response.text() : '';
        this.frameTarget.hidden = !response.ok;
    }
}
