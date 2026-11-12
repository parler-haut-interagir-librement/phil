import { Controller } from '@hotwired/stimulus';
import { fillForm } from './lib/csrf.js';
import { formatBytes, percent, splitBySize } from './lib/files.js';

/**
 * Sends the chosen or dropped files one request each, with a new CSRF token each, showing each one's progress, then
 * reloads the listing. A file larger than the bundle accepts is refused before it is sent. Without JavaScript, the
 * form posts them all at once.
 */
export default class extends Controller {
    static targets = ['input', 'list'];
    static values = { maxBytes: Number };

    over(event) {
        event.preventDefault();
    }

    drop(event) {
        event.preventDefault();

        if (event.dataTransfer && event.dataTransfer.files.length > 0) {
            this.inputTarget.files = event.dataTransfer.files;
            this.upload(Array.from(event.dataTransfer.files));
        }
    }

    send(event) {
        event.preventDefault();
        this.upload(Array.from(this.inputTarget.files));
    }

    async upload(files) {
        const { accepted, refused } = splitBySize(files, this.maxBytesValue);
        this.listTarget.replaceChildren();

        refused.forEach((file) => {
            this.item(file).status.textContent = `larger than ${formatBytes(this.maxBytesValue)}`;
        });

        const results = await Promise.all(accepted.map((file) => this.post(file, this.item(file))));

        if (results.some(Boolean)) {
            window.location.reload();
        }
    }

    item(file) {
        const li = document.createElement('li');
        const progress = document.createElement('progress');
        const status = document.createElement('span');
        progress.max = 100;
        progress.value = 0;
        li.append(`${file.name} (${formatBytes(file.size)}) `, progress, ' ', status);
        this.listTarget.append(li);

        return { progress, status };
    }

    post(file, { progress, status }) {
        fillForm(this.element, this.element.ownerDocument);
        const data = new FormData(this.element);
        data.delete('files[]');
        data.append('files[]', file);

        return new Promise((resolve) => {
            const request = new XMLHttpRequest();
            request.open('POST', this.element.action);
            request.setRequestHeader('Accept', 'application/json');
            request.upload.addEventListener('progress', (event) => {
                progress.value = percent(event.loaded, event.total);
            });
            request.addEventListener('load', () => {
                let answer = {};

                try {
                    answer = JSON.parse(request.responseText);
                } catch {
                    answer = { error: request.statusText };
                }

                progress.value = 100;
                status.textContent = answer.error ?? 'uploaded';
                resolve(200 === request.status);
            });
            request.addEventListener('error', () => {
                status.textContent = 'the upload failed';
                resolve(false);
            });
            request.send(data);
        });
    }
}
