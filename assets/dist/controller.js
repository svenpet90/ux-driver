import { Controller } from '@hotwired/stimulus';
import { driver } from 'driver.js';
/**
 * Plays a tour (driver.js), across several pages if need be.
 *
 * `ux_driver_tour('<id>')` renders the attributes. Every page involved renders the whole tour;
 * the controller only shows the steps whose `page` is the current page. It sits on the button
 * that restarts the tour. Whether the tour starts by itself is decided on the server
 * (`autostart`): only as long as the user has not seen it.
 *
 * Seen is seen: every ending counts — clicked through, closed with X or Escape — and is
 * reported with a POST to `seenUrl`. Leaving the page does not count as an ending. The tab also
 * remembers the ending in `sessionStorage`: when navigating back, Turbo first shows a cached
 * copy of the page whose `autostart` still stems from the first visit.
 *
 * Changing pages: when the next step lives on another page, the controller remembers it in
 * `sessionStorage` (per tab) and navigates — via "Next", or the user clicks the highlighted
 * element if it is a link to that page. The next page that loads in this tab always takes the
 * marker out; it only resumes if it is the expected page. There is no "Previous" across a page
 * change.
 *
 * The controller is only loaded once an element carries it (`fetch: lazy` in package.json);
 * driver.js' stylesheet comes in via `autoimport`.
 */
class default_1 extends Controller {
    constructor() {
        super(...arguments);
        this.tour = null;
        this.tearingDown = false;
        this.linkListener = null;
    }
    connect() {
        const resumeAt = this.takeResumeIndex();
        if (resumeAt !== null) {
            this.drive(resumeAt);
        }
        else if (this.autostartValue && !this.seenInThisTab()) {
            this.start();
        }
    }
    disconnect() {
        this.teardown();
    }
    /** Starts the tour at the first step of this page. */
    start() {
        const first = this.stepsValue.findIndex((step) => this.isOnThisPage(step));
        if (first !== -1) {
            this.drive(first);
        }
    }
    drive(index) {
        this.teardown();
        this.tour = driver({
            steps: this.stepsValue.map((step, i) => this.prepared(step, i)),
            showProgress: true,
            progressText: this.progressLabelValue,
            nextBtnText: this.nextLabelValue,
            prevBtnText: this.previousLabelValue,
            doneBtnText: this.doneLabelValue,
            // Not onHighlighted: that only fires once the transition has finished, and a user who
            // clicks the highlighted link right away would lose the tour on the next page.
            onHighlightStarted: (element, _step, { driver }) => {
                this.watchLink(element, driver.getActiveIndex());
            },
            onNextClick: (_element, _step, { driver }) => {
                var _a;
                const next = ((_a = driver.getActiveIndex()) !== null && _a !== void 0 ? _a : 0) + 1;
                if (this.stepsValue[next] !== undefined && !this.isOnThisPage(this.stepsValue[next])) {
                    this.continueOn(next);
                }
                else {
                    driver.moveNext();
                }
            },
            onPrevClick: (_element, _step, { driver }) => {
                var _a;
                const previous = ((_a = driver.getActiveIndex()) !== null && _a !== void 0 ? _a : 0) - 1;
                // The button is hidden in that case; this catches the arrow key.
                if (this.stepsValue[previous] !== undefined && this.isOnThisPage(this.stepsValue[previous])) {
                    driver.movePrevious();
                }
            },
            onDestroyed: () => {
                var _a;
                (_a = this.linkListener) === null || _a === void 0 ? void 0 : _a.abort();
                if (!this.tearingDown) {
                    this.markSeen();
                }
            }
        });
        this.tour.drive(index);
    }
    /** Tears the tour down without counting it as seen. */
    teardown() {
        var _a;
        this.tearingDown = true;
        (_a = this.tour) === null || _a === void 0 ? void 0 : _a.destroy();
        this.tour = null;
        this.tearingDown = false;
    }
    /** The next step lives elsewhere: remember it, tear down here, change pages. */
    continueOn(index) {
        this.rememberResumeIndex(index);
        this.teardown();
        const url = this.stepsValue[index].page;
        const turbo = window.Turbo;
        if (turbo !== undefined && document.body.dataset.turbo !== 'false') {
            turbo.visit(url);
        }
        else {
            window.location.assign(url);
        }
    }
    /**
     * If the highlighted element is a link to the next step's page, the user may click it
     * themselves: the click remembers the next step, the link (or Turbo) navigates as usual.
     */
    watchLink(element, index) {
        var _a;
        (_a = this.linkListener) === null || _a === void 0 ? void 0 : _a.abort();
        const next = this.stepsValue[(index !== null && index !== void 0 ? index : 0) + 1];
        const link = element === null || element === void 0 ? void 0 : element.closest('a[href]');
        if (next === undefined || this.isOnThisPage(next) || !(link instanceof HTMLAnchorElement)) {
            return;
        }
        if (new URL(link.href, window.location.href).pathname !== next.page) {
            return;
        }
        this.linkListener = new AbortController();
        link.addEventListener('click', () => {
            this.rememberResumeIndex((index !== null && index !== void 0 ? index : 0) + 1);
        }, { signal: this.linkListener.signal });
    }
    /**
     * Escapes the texts (driver.js sets them via innerHTML; this keeps any HTML in them as text)
     * and hides "Previous" where the previous step lives on another page.
     */
    prepared(step, index) {
        var _a, _b, _c, _d;
        const previous = this.stepsValue[index - 1];
        const popover = {
            ...step.popover,
            title: escapeHtml((_b = (_a = step.popover) === null || _a === void 0 ? void 0 : _a.title) !== null && _b !== void 0 ? _b : ''),
            description: escapeHtml((_d = (_c = step.popover) === null || _c === void 0 ? void 0 : _c.description) !== null && _d !== void 0 ? _d : '')
        };
        if (previous !== undefined && !this.isOnThisPage(previous)) {
            popover.showButtons = ['next', 'close'];
        }
        return { ...step, popover };
    }
    isOnThisPage(step) {
        return step.page === window.location.pathname;
    }
    /**
     * Reports the ending to the server. `keepalive`, because users often click on at the same
     * moment; without it the page change would cancel the request. If it fails, the tour starts
     * once more on the next visit — annoying, but harmless.
     */
    markSeen() {
        this.rememberSeenInThisTab();
        void fetch(this.seenUrlValue, {
            method: 'POST',
            keepalive: true,
            credentials: 'same-origin',
            headers: { 'X-CSRF-Token': this.csrfTokenValue }
        }).catch(() => undefined);
    }
    /** Always takes the marker out — it only applies to the very next page. */
    takeResumeIndex() {
        let stored;
        try {
            stored = window.sessionStorage.getItem(this.key('resume'));
            window.sessionStorage.removeItem(this.key('resume'));
        }
        catch {
            return null;
        }
        const index = Number(stored);
        const step = this.stepsValue[index];
        return stored !== null && step !== undefined && this.isOnThisPage(step) ? index : null;
    }
    rememberResumeIndex(index) {
        try {
            window.sessionStorage.setItem(this.key('resume'), String(index));
        }
        catch {
            // Without storage the tour ends with the page change.
        }
    }
    seenInThisTab() {
        try {
            return window.sessionStorage.getItem(this.key('seen')) === '1';
        }
        catch {
            return false;
        }
    }
    rememberSeenInThisTab() {
        try {
            window.sessionStorage.setItem(this.key('seen'), '1');
        }
        catch {
            // Then the server alone decides.
        }
    }
    key(purpose) {
        return `ux-driver:${purpose}:${this.idValue}`;
    }
}
default_1.values = {
    id: String,
    steps: { type: Array, default: [] },
    autostart: { type: Boolean, default: false },
    seenUrl: String,
    csrfToken: String,
    nextLabel: { type: String, default: 'Next' },
    previousLabel: { type: String, default: 'Previous' },
    doneLabel: { type: String, default: 'Done' },
    progressLabel: { type: String, default: '{{current}} of {{total}}' }
};
export default default_1;
function escapeHtml(text) {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
