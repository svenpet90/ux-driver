import { Controller } from '@hotwired/stimulus'
import { driver, type Driver, type DriveStep } from 'driver.js'

/** A step as `SvenPetersen\UX\Driver\Model\Step` serializes it: a driver.js step plus its page. */
type TourStep = DriveStep & { page: string }

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
export default class extends Controller {
  static values = {
    id: String,
    steps: { type: Array, default: [] },
    autostart: { type: Boolean, default: false },
    seenUrl: String,
    csrfToken: String,
    nextLabel: { type: String, default: 'Next' },
    previousLabel: { type: String, default: 'Previous' },
    doneLabel: { type: String, default: 'Done' },
    progressLabel: { type: String, default: '{{current}} of {{total}}' }
  }

  declare idValue: string
  declare stepsValue: TourStep[]
  declare autostartValue: boolean
  declare seenUrlValue: string
  declare csrfTokenValue: string
  declare nextLabelValue: string
  declare previousLabelValue: string
  declare doneLabelValue: string
  declare progressLabelValue: string

  private tour: Driver | null = null
  private tearingDown = false
  private linkListener: AbortController | null = null

  connect (): void {
    const resumeAt = this.takeResumeIndex()

    if (resumeAt !== null) {
      this.drive(resumeAt)
    } else if (this.autostartValue && !this.seenInThisTab()) {
      this.start()
    }
  }

  disconnect (): void {
    this.teardown()
  }

  /** Starts the tour at the first step of this page. */
  start (): void {
    const first = this.stepsValue.findIndex((step) => this.isOnThisPage(step))

    if (first !== -1) {
      this.drive(first)
    }
  }

  private drive (index: number): void {
    this.teardown()
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
        this.watchLink(element, driver.getActiveIndex())
      },
      onNextClick: (_element, _step, { driver }) => {
        const next = (driver.getActiveIndex() ?? 0) + 1

        if (this.stepsValue[next] !== undefined && !this.isOnThisPage(this.stepsValue[next])) {
          this.continueOn(next)
        } else {
          driver.moveNext()
        }
      },
      onPrevClick: (_element, _step, { driver }) => {
        const previous = (driver.getActiveIndex() ?? 0) - 1

        // The button is hidden in that case; this catches the arrow key.
        if (this.stepsValue[previous] !== undefined && this.isOnThisPage(this.stepsValue[previous])) {
          driver.movePrevious()
        }
      },
      onDestroyed: () => {
        this.linkListener?.abort()

        if (!this.tearingDown) {
          this.markSeen()
        }
      }
    })
    this.tour.drive(index)
  }

  /** Tears the tour down without counting it as seen. */
  private teardown (): void {
    this.tearingDown = true
    this.tour?.destroy()
    this.tour = null
    this.tearingDown = false
  }

  /** The next step lives elsewhere: remember it, tear down here, change pages. */
  private continueOn (index: number): void {
    this.rememberResumeIndex(index)
    this.teardown()

    const url = this.stepsValue[index].page
    const turbo = (window as Window & { Turbo?: { visit: (url: string) => void } }).Turbo

    if (turbo !== undefined && document.body.dataset.turbo !== 'false') {
      turbo.visit(url)
    } else {
      window.location.assign(url)
    }
  }

  /**
   * If the highlighted element is a link to the next step's page, the user may click it
   * themselves: the click remembers the next step, the link (or Turbo) navigates as usual.
   */
  private watchLink (element: Element | undefined, index: number | undefined): void {
    this.linkListener?.abort()

    const next = this.stepsValue[(index ?? 0) + 1]
    const link = element?.closest('a[href]')

    if (next === undefined || this.isOnThisPage(next) || !(link instanceof HTMLAnchorElement)) {
      return
    }

    if (new URL(link.href, window.location.href).pathname !== next.page) {
      return
    }

    this.linkListener = new AbortController()
    link.addEventListener('click', () => {
      this.rememberResumeIndex((index ?? 0) + 1)
    }, { signal: this.linkListener.signal })
  }

  /**
   * Escapes the texts (driver.js sets them via innerHTML; this keeps any HTML in them as text)
   * and hides "Previous" where the previous step lives on another page.
   */
  private prepared (step: TourStep, index: number): DriveStep {
    const previous = this.stepsValue[index - 1]
    const popover = {
      ...step.popover,
      title: escapeHtml(step.popover?.title ?? ''),
      description: escapeHtml(step.popover?.description ?? '')
    }

    if (previous !== undefined && !this.isOnThisPage(previous)) {
      popover.showButtons = ['next', 'close']
    }

    return { ...step, popover }
  }

  private isOnThisPage (step: TourStep): boolean {
    return step.page === window.location.pathname
  }

  /**
   * Reports the ending to the server. `keepalive`, because users often click on at the same
   * moment; without it the page change would cancel the request. If it fails, the tour starts
   * once more on the next visit — annoying, but harmless.
   */
  private markSeen (): void {
    this.rememberSeenInThisTab()

    void fetch(this.seenUrlValue, {
      method: 'POST',
      keepalive: true,
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': this.csrfTokenValue }
    }).catch(() => undefined)
  }

  /** Always takes the marker out — it only applies to the very next page. */
  private takeResumeIndex (): number | null {
    let stored: string | null

    try {
      stored = window.sessionStorage.getItem(this.key('resume'))
      window.sessionStorage.removeItem(this.key('resume'))
    } catch {
      return null
    }

    const index = Number(stored)
    const step = this.stepsValue[index]

    return stored !== null && step !== undefined && this.isOnThisPage(step) ? index : null
  }

  private rememberResumeIndex (index: number): void {
    try {
      window.sessionStorage.setItem(this.key('resume'), String(index))
    } catch {
      // Without storage the tour ends with the page change.
    }
  }

  private seenInThisTab (): boolean {
    try {
      return window.sessionStorage.getItem(this.key('seen')) === '1'
    } catch {
      return false
    }
  }

  private rememberSeenInThisTab (): void {
    try {
      window.sessionStorage.setItem(this.key('seen'), '1')
    } catch {
      // Then the server alone decides.
    }
  }

  private key (purpose: 'resume' | 'seen'): string {
    return `ux-driver:${purpose}:${this.idValue}`
  }
}

function escapeHtml (text: string): string {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
}
