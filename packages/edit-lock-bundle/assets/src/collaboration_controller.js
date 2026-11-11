// Writing together, block by block, in the block editor of phil/block-editor-bundle: the block a writer enters is
// leased to them and kept by heartbeats; what they change in it is saved and pushed to the others; the blocks the
// others hold are closed and named; their saves are applied as they arrive from Mercure. No character-level merge:
// one writer per block.
//
//     <div data-controller="phil--edit-lock-bundle--collaboration"
//          data-phil--edit-lock-bundle--collaboration-config-value="{{ config|json_encode }}">
//         {{ form_row(form.body) }}
//         <aside data-phil--edit-lock-bundle--collaboration-target="presence"></aside>
//         <p data-phil--edit-lock-bundle--collaboration-target="status" role="status"></p>
//     </div>

import { Controller } from '@hotwired/stimulus';

import { translator } from './lib/i18n.js';
import { LeaseClient } from './lib/leases.js';
import { active, locks, roster, sameLocks, withDeadlines } from './lib/presence.js';
import { changes, parseBlocks, snapshot } from './lib/snapshot.js';
import { readStreams } from './lib/streams.js';

const EDITOR = 'phil--block-editor-bundle--editor';

export default class extends Controller {
    static targets = ['presence', 'status'];

    static values = { config: Object };

    connect() {
        const config = this.configValue ?? {};
        const document = this.element.ownerDocument;
        this.me = config.holder?.id ?? null;
        this.takeOverOffered = true === config.takeOver;
        this.live = 'string' === typeof config.hub && 'function' === typeof globalThis.EventSource;
        this.t = translator(config.locale ?? document.documentElement.lang, config.messages ?? {});
        this.client = new LeaseClient(config, (url, init) => globalThis.fetch(url, init));
        this.editor = this.element.querySelector('[data-controller~="' + EDITOR + '"]') ?? this.element;
        this.known = snapshot(
            parseBlocks(this.editor.querySelector('[data-' + EDITOR + '-target="input"]')?.value ?? ''),
        );
        this.held = null;
        this.focused = null;
        this.queue = Promise.resolve();
        this.presence = [];
        this.locked = {};
        this.listeners = [];
        this.listen(this.editor, EDITOR + ':focus', (event) => this.enter(event.detail?.id ?? null));
        this.listen(this.editor, EDITOR + ':change', (event) => this.changed(event.detail?.json ?? ''));
        this.listen(this.element, 'click', (event) => this.click(event));
        this.listen(document.defaultView, 'pagehide', () => this.client.leave(true));

        if (this.live) {
            this.source = new EventSource(config.hub, { withCredentials: true });
            this.source.addEventListener('message', (event) => this.receive(event.data));
        }

        const seconds = Math.max(5, Number(config.seconds) || 30);
        this.beat = setInterval(() => this.heartbeat(), (seconds * 1000) / 3);
        this.tick = setInterval(() => this.show(), 1000);
        this.refresh();
    }

    disconnect() {
        clearInterval(this.beat);
        clearInterval(this.tick);
        this.source?.close();
        this.listeners.forEach(([target, type, handler]) => {
            target.removeEventListener(type, handler);
        });
        this.listeners = [];
        this.client.leave(true);
    }

    listen(target, type, handler) {
        target.addEventListener(type, handler);
        this.listeners.push([target, type, handler]);
    }

    /** Runs the requests one after the other, in the order the writer acted. */
    enqueue(step) {
        this.queue = this.queue.then(step).catch(() => null);

        return this.queue;
    }

    // The writer ------------------------------------------------------------------------------------------------

    /** The writer entered a block, or left the editor: the lease follows them. */
    enter(id) {
        this.focused = id;

        return this.enqueue(async () => {
            if (id === this.held) {
                return;
            }

            if (null !== this.held) {
                await this.client.release(this.held);
                this.held = null;
            }

            if (null === id || undefined !== this.locked[id]) {
                return;
            }

            const answer = await this.client.acquire(id);

            if (answer.ok) {
                this.held = id;
            } else if (409 === answer.status) {
                this.announce(this.t('held', { name: answer.body?.lease?.holder?.name ?? '' }));
            }

            if (!this.live) {
                await this.refresh();
            }
        });
    }

    /**
     * The editor wrote a new value: the held block is saved when it changed, removed when it is gone. A block the
     * writer is in but no longer holds — lost, or freed after a takeover — is asked for again first.
     */
    changed(json) {
        const next = snapshot(parseBlocks(json));
        const { saved, removed } = changes(this.known, next);
        this.known = next;

        return this.enqueue(async () => {
            const focused = this.focused;

            if (
                null === this.held &&
                null !== focused &&
                saved.includes(focused) &&
                undefined === this.locked[focused] &&
                (await this.client.acquire(focused)).ok
            ) {
                this.held = focused;
            }

            const id = this.held;

            if (saved.includes(id)) {
                const entry = next.get(id);
                await this.client.save(entry.block, entry.list, entry.after);
            } else if (removed.includes(id)) {
                await this.client.remove(id);
            }
        });
    }

    heartbeat() {
        return this.enqueue(async () => {
            if (null !== this.held) {
                const answer = await this.client.heartbeat(this.held);

                if (409 === answer.status) {
                    this.held = null;
                    this.announce(this.t('lost'));
                }
            }

            if (!this.live) {
                await this.refresh();
            }
        });
    }

    click(event) {
        const button = event.target.closest?.('button[data-take-over]') ?? null;

        if (null === button) {
            return;
        }

        const part = button.dataset.takeOver;
        this.enqueue(async () => {
            if (null !== this.held && part !== this.held) {
                await this.client.release(this.held);
            }

            const answer = await this.client.takeOver(part);
            this.held = answer.ok ? part : null;
            this.announce(this.t(answer.ok ? 'tookOver' : 'refused'));

            if (answer.ok) {
                this.presence = this.presence.filter((item) => item.part !== part || item.holder === this.me);
                this.show();
            }
        });
    }

    // The others ------------------------------------------------------------------------------------------------

    async refresh() {
        const answer = await this.client.presence();

        if (answer.ok && Array.isArray(answer.body?.leases)) {
            this.present(
                answer.body.leases.map((lease) => ({
                    holder: lease.holder.id,
                    name: lease.holder.name,
                    part: lease.part ?? '',
                    expiresIn: lease.expiresIn,
                })),
            );
        }
    }

    /** A message from the hub: presence, or a block another writer saved or removed. */
    receive(data) {
        const root = new DOMParser().parseFromString(String(data), 'text/html').body;

        for (const action of readStreams(root)) {
            if ('presence' === action.kind) {
                this.present(action.items);
            } else if (action.holder !== this.me) {
                this.editor.dispatchEvent(
                    new CustomEvent('save' === action.kind ? 'phil-block-editor:apply' : 'phil-block-editor:remove', {
                        detail: action,
                    }),
                );
            }
        }
    }

    present(items) {
        this.presence = withDeadlines(items, Date.now());
        this.show();
    }

    /** The blocks of the others closed in the editor, and the roster drawn, as presence stands now. */
    show() {
        const now = active(this.presence, Date.now());
        const held = locks(now, this.me, [...this.known.keys()]);

        if (null !== this.held && undefined !== held[this.held]) {
            this.announce(this.t('takenOver', { name: held[this.held] }));
            this.held = null;
        }

        if (!sameLocks(held, this.locked)) {
            this.locked = held;
            this.editor.dispatchEvent(new CustomEvent('phil-block-editor:lock', { detail: { blocks: held } }));
        }

        const writers = roster(now, this.me);
        const drawn = JSON.stringify(writers);

        if (this.hasPresenceTarget && drawn !== this.drawn) {
            this.drawn = drawn;
            this.drawRoster(writers);
        }
    }

    drawRoster(writers) {
        const document = this.element.ownerDocument;
        const list = document.createElement('ul');
        list.className = 'phil-edit-lock-roster';
        list.setAttribute('aria-label', this.t('presence'));

        for (const writer of writers) {
            const entry = document.createElement('li');
            entry.className =
                'phil-edit-lock-roster__writer' + (writer.mine ? ' phil-edit-lock-roster__writer--mine' : '');
            const name = document.createElement('span');
            name.className = 'phil-edit-lock-roster__name';
            name.textContent = writer.mine ? writer.name + ' (' + this.t('you') + ')' : writer.name;
            const what = document.createElement('span');
            what.className = 'phil-edit-lock-roster__what';
            what.textContent =
                this.t('editing') +
                ' ' +
                (writer.whole
                    ? this.t('whole')
                    : 1 === writer.parts.length
                      ? this.t('block')
                      : this.t('blocks', { count: writer.parts.length }));
            entry.append(name, ' ', what);

            if (this.takeOverOffered && !writer.mine) {
                for (const part of writer.parts) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'phil-edit-lock-roster__take-over';
                    button.dataset.takeOver = part;
                    button.textContent = this.t('takeOver');
                    entry.append(' ', button);
                }
            }

            list.append(entry);
        }

        this.presenceTarget.replaceChildren(list);
    }

    announce(message) {
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = message;
        }
    }
}
