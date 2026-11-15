import { Controller } from '@hotwired/stimulus';

/*
 * Passkeys, through WebAuthn: `register` asks the server for the options of a new passkey, lets the browser create
 * it and posts its JSON back; `signIn` asks for the options of a sign-in, lets the browser sign with a passkey of the
 * site and posts the answer to the passkey authenticator, which says where to go next. The options and answers are
 * the JSON forms WebAuthn Level 3 defines, read and written by the browser itself.
 */
export default class extends Controller {
    static targets = ['button', 'status', 'name'];

    static values = {
        optionsUrl: String,
        registerUrl: String,
        loginUrl: String,
        reloadUrl: String,
        token: String,
        unsupportedMessage: { type: String, default: 'This browser does not support passkeys.' },
        cancelledMessage: { type: String, default: 'The passkey was not used.' },
        failedMessage: { type: String, default: 'The passkey could not be verified.' },
    };

    async register() {
        if (!this.supported()) {
            return;
        }

        await this.run(async () => {
            const options = await this.post(this.optionsUrlValue, null);
            const credential = await navigator.credentials.create({
                publicKey: PublicKeyCredential.parseCreationOptionsFromJSON(options),
            });
            const input = this.element.querySelector('input[name="passkey_name"]');
            const name = input ? input.value : '';
            const url = `${this.registerUrlValue}?name=${encodeURIComponent(name)}`;
            await this.post(url, credential.toJSON());
            window.location.assign(this.reloadUrlValue);
        });
    }

    async signIn() {
        if (!this.supported()) {
            return;
        }

        await this.run(async () => {
            const options = await this.post(this.optionsUrlValue, null);
            const credential = await navigator.credentials.get({
                publicKey: PublicKeyCredential.parseRequestOptionsFromJSON(options),
            });
            const answer = await this.post(this.loginUrlValue, credential.toJSON());
            window.location.assign(answer.redirect);
        });
    }

    supported() {
        const supported =
            window.PublicKeyCredential !== undefined &&
            typeof PublicKeyCredential.parseCreationOptionsFromJSON === 'function';

        if (!supported) {
            this.say(this.unsupportedMessageValue);
        }

        return supported;
    }

    async run(ceremony) {
        this.busy(true);

        try {
            await ceremony();
        } catch (error) {
            this.say(error && error.name === 'NotAllowedError' ? this.cancelledMessageValue : this.failedMessageValue);
        } finally {
            this.busy(false);
        }
    }

    async post(url, body) {
        const headers = { Accept: 'application/json', 'Content-Type': 'application/json' };

        if (this.hasTokenValue && this.tokenValue !== '') {
            headers['X-CSRF-Token'] = this.tokenValue;
        }

        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers,
            body: body === null ? '{}' : JSON.stringify(body),
        });

        if (!response.ok) {
            throw new Error(`The server refused the passkey: ${response.status}`);
        }

        return response.json();
    }

    busy(busy) {
        if (this.hasButtonTarget) {
            this.buttonTarget.toggleAttribute('disabled', busy);
            this.buttonTarget.setAttribute('aria-busy', busy ? 'true' : 'false');
        }
    }

    say(message) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = message;
        }
    }
}
