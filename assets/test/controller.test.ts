import { Application } from '@hotwired/stimulus'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import TourController from '../src/controller'

/**
 * Runs the controller against the real driver.js in jsdom: what is asserted is what a user
 * would see (popover title, buttons) and what leaves the page (fetch, Turbo.visit,
 * sessionStorage).
 */

const IDENTIFIER = 'svenpetersen--ux-driver--tour'
const TOUR_ID = 'test-tour'
const SEEN_URL = '/ux-driver/tours/test-tour/seen'

const STEPS = [
  { page: '/dashboard', popover: { title: 'Welcome', description: 'A short tour.', side: 'bottom' } },
  { page: '/dashboard', element: '#nav-statistic', popover: { title: 'Go to statistics', description: 'Click it.', side: 'right' } },
  { page: '/statistic', element: '#filter', popover: { title: 'Filter', description: 'Narrow it down.', side: 'bottom' } },
  { page: '/statistic', element: '#chart', popover: { title: 'Chart', description: 'Per day.', side: 'top' } }
]

const PAGES: Record<string, string> = {
  '/dashboard': '<a id="nav-statistic" href="/statistic">Statistics</a>',
  '/statistic': '<div id="filter">Filter</div><div id="chart">Chart</div>'
}

let application: Application
let fetchMock: ReturnType<typeof vi.fn>
let visitMock: ReturnType<typeof vi.fn>

interface Options {
  autostart?: boolean
  steps?: unknown[]
}

/** Renders a page of the test application with the tour button on it and starts Stimulus. */
async function open (page: string, { autostart = true, steps = STEPS }: Options = {}): Promise<HTMLElement> {
  window.history.replaceState(null, '', page)
  document.body.innerHTML = PAGES[page] ?? ''

  const button = document.createElement('button')
  button.type = 'button'
  button.textContent = 'Tour'
  button.setAttribute('data-controller', IDENTIFIER)
  button.setAttribute('data-action', `click->${IDENTIFIER}#start`)
  button.setAttribute(`data-${IDENTIFIER}-id-value`, TOUR_ID)
  button.setAttribute(`data-${IDENTIFIER}-steps-value`, JSON.stringify(steps))
  button.setAttribute(`data-${IDENTIFIER}-autostart-value`, String(autostart))
  button.setAttribute(`data-${IDENTIFIER}-seen-url-value`, SEEN_URL)
  button.setAttribute(`data-${IDENTIFIER}-csrf-token-value`, 'csrf-token')
  document.body.append(button)

  application = Application.start(document.documentElement)
  application.register(IDENTIFIER, TourController)
  await settle()

  return button
}

const settle = async (ms = 50): Promise<void> => await new Promise((resolve) => setTimeout(resolve, ms))

const title = (): string | null => document.querySelector('.driver-popover-title')?.textContent ?? null

/**
 * Waits for the popover and then for driver.js' transition (400 ms by default): driver.js ignores
 * keys and buttons until it has finished, just as it does for a real user.
 */
async function expectStep (expected: string): Promise<void> {
  await vi.waitFor(() => { expect(title()).toBe(expected) }, { timeout: 2000 })
  await settle(450)
}

async function expectNoTour (): Promise<void> {
  await settle(500)
  expect(document.querySelector('.driver-popover')).toBeNull()
}

function clickButton (selector: string): void {
  const button = document.querySelector<HTMLElement>(selector)
  expect(button, selector).not.toBeNull()
  button!.click()
}

function press (key: string): void {
  window.dispatchEvent(new KeyboardEvent('keyup', { key, bubbles: true }))
}

function isHidden (selector: string): boolean {
  const element = document.querySelector<HTMLElement>(selector)

  return element === null || element.style.display === 'none'
}

beforeEach(() => {
  fetchMock = vi.fn(async () => await Promise.resolve({ ok: true, status: 204 }))
  visitMock = vi.fn()
  vi.stubGlobal('fetch', fetchMock)
  vi.stubGlobal('Turbo', { visit: visitMock })
  window.sessionStorage.clear()
})

/**
 * Removes the button while Stimulus still observes the DOM, so the controller disconnects and
 * tears its tour down. `Application.stop()` alone does not disconnect anything — a tour left
 * running would react to the next test's key presses.
 */
afterEach(async () => {
  document.body.innerHTML = ''
  await settle()
  application.stop()
  vi.unstubAllGlobals()
})

describe('starting', () => {
  it('starts by itself when the server says the user has not seen the tour', async () => {
    await open('/dashboard')

    await expectStep('Welcome')
    expect(document.querySelector('.driver-popover-progress-text')?.textContent).toBe('1 of 4')
  })

  it('does not start by itself when the user has seen the tour', async () => {
    await open('/dashboard', { autostart: false })

    await expectNoTour()
  })

  it('starts on click, at the first step of the current page', async () => {
    const button = await open('/statistic', { autostart: false })

    button.click()

    await expectStep('Filter')
    expect(document.querySelector('.driver-popover-progress-text')?.textContent).toBe('3 of 4')
  })

  it('hides "Previous" where the previous step lives on another page', async () => {
    const button = await open('/statistic', { autostart: false })
    button.click()
    await expectStep('Filter')

    expect(isHidden('.driver-popover-prev-btn')).toBe(true)
  })

  it('does not go back to another page with the arrow key either', async () => {
    const button = await open('/statistic', { autostart: false })
    button.click()
    await expectStep('Filter')

    press('ArrowLeft')
    await settle(500)

    expect(title()).toBe('Filter')
    expect(visitMock).not.toHaveBeenCalled()
  })

  it('shows titles and descriptions as text, not as HTML', async () => {
    await open('/dashboard', {
      steps: [{ page: '/dashboard', popover: { title: '<img src=x onerror="alert(1)">', description: '<b>bold</b>' } }]
    })

    await expectStep('<img src=x onerror="alert(1)">')
    expect(document.querySelector('.driver-popover img')).toBeNull()
    expect(document.querySelector('.driver-popover-description')?.textContent).toBe('<b>bold</b>')
  })
})

describe('seen', () => {
  it('reports closing with Escape as seen', async () => {
    await open('/dashboard')
    await expectStep('Welcome')

    press('Escape')

    await vi.waitFor(() => { expect(fetchMock).toHaveBeenCalledTimes(1) })
    expect(fetchMock).toHaveBeenCalledWith(SEEN_URL, expect.objectContaining({
      method: 'POST',
      keepalive: true,
      headers: { 'X-CSRF-Token': 'csrf-token' }
    }))
  })

  it('reports finishing the tour as seen', async () => {
    window.sessionStorage.setItem(`ux-driver:resume:${TOUR_ID}`, '3')
    await open('/statistic', { autostart: false })
    await expectStep('Chart')

    clickButton('.driver-popover-next-btn')

    await vi.waitFor(() => { expect(fetchMock).toHaveBeenCalledTimes(1) })
  })

  it('does not count leaving the page as seen', async () => {
    const button = await open('/dashboard')
    await expectStep('Welcome')

    button.remove()
    await settle()

    expect(fetchMock).not.toHaveBeenCalled()
    expect(document.querySelector('.driver-popover')).toBeNull()
  })

  /**
   * Turbo shows a cached copy of a page first when navigating back; its `autostart` still stems
   * from the first visit.
   */
  it('does not start again in the same tab after it was closed, whatever the page says', async () => {
    await open('/dashboard')
    await expectStep('Welcome')
    press('Escape')
    await vi.waitFor(() => { expect(fetchMock).toHaveBeenCalled() })
    application.stop()

    await open('/dashboard', { autostart: true })

    await expectNoTour()
  })
})

describe('across pages', () => {
  /** Users do not wait for the animation; the click must count while it is still running. */
  it('remembers the step when the highlighted link is clicked while the step is still animating', async () => {
    await open('/dashboard')
    await expectStep('Welcome')
    clickButton('.driver-popover-next-btn')
    await vi.waitFor(() => { expect(title()).toBe('Go to statistics') })

    document.addEventListener('click', (event) => { event.preventDefault() }, { once: true })
    document.querySelector<HTMLElement>('#nav-statistic')!.click()

    expect(window.sessionStorage.getItem(`ux-driver:resume:${TOUR_ID}`)).toBe('2')
  })

  it('continues on the next page via "Next" and remembers the step', async () => {
    await open('/dashboard')
    await expectStep('Welcome')
    clickButton('.driver-popover-next-btn')
    await expectStep('Go to statistics')

    clickButton('.driver-popover-next-btn')

    await vi.waitFor(() => { expect(visitMock).toHaveBeenCalledWith('/statistic') })
    expect(window.sessionStorage.getItem(`ux-driver:resume:${TOUR_ID}`)).toBe('2')
    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('remembers the step when the user clicks the highlighted link instead', async () => {
    await open('/dashboard')
    await expectStep('Welcome')
    clickButton('.driver-popover-next-btn')
    await expectStep('Go to statistics')

    // jsdom cannot navigate; the link's own click is what matters here.
    document.addEventListener('click', (event) => { event.preventDefault() }, { once: true })
    document.querySelector<HTMLElement>('#nav-statistic')!.click()

    expect(window.sessionStorage.getItem(`ux-driver:resume:${TOUR_ID}`)).toBe('2')
    expect(visitMock).not.toHaveBeenCalled()
  })

  it('resumes on the expected page and consumes the marker', async () => {
    window.sessionStorage.setItem(`ux-driver:resume:${TOUR_ID}`, '2')

    await open('/statistic', { autostart: false })

    await expectStep('Filter')
    expect(window.sessionStorage.getItem(`ux-driver:resume:${TOUR_ID}`)).toBeNull()
  })

  it('drops the marker on any other page instead of resuming there', async () => {
    window.sessionStorage.setItem(`ux-driver:resume:${TOUR_ID}`, '2')

    await open('/dashboard', { autostart: false })

    await expectNoTour()
    expect(window.sessionStorage.getItem(`ux-driver:resume:${TOUR_ID}`)).toBeNull()
  })
})
