// A step run once the changes stop: each `schedule()` puts it off by `wait` milliseconds; `flush()` runs it at once
// when it waits, `cancel()` drops it.

export function debounce(step, wait, timers = globalThis) {
    let handle = null;

    const run = () => {
        handle = null;
        step();
    };

    return {
        schedule() {
            if (null !== handle) {
                timers.clearTimeout(handle);
            }

            handle = timers.setTimeout(run, wait);
        },
        flush() {
            if (null === handle) {
                return false;
            }

            timers.clearTimeout(handle);
            run();

            return true;
        },
        cancel() {
            if (null !== handle) {
                timers.clearTimeout(handle);
                handle = null;
            }
        },
        get pending() {
            return null !== handle;
        },
    };
}
