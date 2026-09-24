/**
 * Mega menu: behavior of <x-menu-builder::menu variant="mega">.
 *
 * - The strip of categories scrolls horizontally. An arrow is shown on each
 *   side that has more categories, and categories that are not fully visible
 *   ignore the pointer so their panel does not open from under an arrow.
 * - From the configured breakpoint up, a category's panel opens on hover
 *   with a short delay, and closes with a short delay so the pointer can
 *   travel into it. Moving to another category switches panels at once.
 * - A panel is aligned with the inline start of its category and kept inside
 *   the <nav>, which is its containing block. The panel width follows from
 *   its column count in CSS, so it is placed in the same frame it opens.
 * - Touch: the first tap on a category opens its panel, the second one
 *   follows the link. Keyboard: ArrowDown opens the panel of the focused
 *   category, Escape closes it, and focus leaving the panel closes it.
 *
 * All listeners are delegated to the root element and the markup only has
 * data-mb-mega-* hooks, so custom item components need no Alpine code. The
 * <nav> dispatches `mb-mega-open` and `mb-mega-close` with `detail.id`, the
 * menu item id of the category.
 */

const ENTRY = '[data-mb-mega-entry]'
const PANEL = ':scope > [data-mb-mega-panel]'
const TRIGGER = ':scope > .mb-row :is(a[href], button, [tabindex]:not([tabindex="-1"]))'
const FOCUSABLE = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'

const clamp = (value, min, max) => Math.min(Math.max(value, min), max)

/**
 * Distance in pixels from the container's inline start edge to the panel's
 * inline start edge: aligned with the trigger, kept inside the container.
 */
function panelInsetStart({ container, trigger, panelWidth, rtl }) {
    const width = container.right - container.left
    const start = rtl ? container.right - trigger.right : trigger.left - container.left

    return clamp(start, 0, Math.max(0, width - panelWidth))
}

/**
 * Which sides of a scroll container have hidden content. scrollLeft is
 * negative in right-to-left containers, so only its magnitude is used.
 */
function scrollState({ scrollLeft, scrollWidth, clientWidth }) {
    const position = Math.abs(scrollLeft)
    const max = Math.max(0, scrollWidth - clientWidth)

    return { canScrollStart: position > 1, canScrollEnd: position < max - 1 }
}

/**
 * The scrollLeft change for one arrow click: 80% of the visible width,
 * towards the inline end (direction 1) or start (direction -1).
 */
function scrollDelta(direction, clientWidth, rtl) {
    return direction * Math.round(clientWidth * 0.8) * (rtl ? -1 : 1)
}

/**
 * Whether an item is not fully inside the visible part of the strip, that
 * is the strip minus the arrows shown at its start and end.
 */
function visibleBounds({ strip, startInset, endInset, rtl }) {
    return {
        left: strip.left + (rtl ? endInset : startInset),
        right: strip.right - (rtl ? startInset : endInset),
    }
}

function isObscured({ item, strip, startInset, endInset, rtl }) {
    const bounds = visibleBounds({ strip, startInset, endInset, rtl })

    return item.left < bounds.left - 0.5 || item.right > bounds.right + 0.5
}

/**
 * The scrollLeft change that brings an item fully into view. scrollLeft is
 * physical in both directions, so no mirroring is needed.
 */
function revealDelta({ item, strip, startInset, endInset, rtl }) {
    const bounds = visibleBounds({ strip, startInset, endInset, rtl })

    if (item.left < bounds.left) {
        return item.left - bounds.left
    }

    if (item.right > bounds.right) {
        return item.right - bounds.right
    }

    return 0
}

const normalizeUrl = (url) => {
    const parsed = new URL(url, window.location.href)

    return parsed.origin + (parsed.pathname.replace(/\/+$/, '') || '/')
}

export default function menuBuilderMega({ openDelay = 100, closeDelay = 150, breakpoint = 1160 } = {}) {
    return {
        entries: [],
        openEntry: null,
        openTimer: null,
        closeTimer: null,
        frame: null,
        media: null,
        resizeObserver: null,
        inputType: 'mouse',
        listeners: [],

        init() {
            this.strip = this.$el.querySelector('[data-mb-mega-strip]')
            this.arrows = {
                start: this.$el.querySelector('[data-mb-mega-scroll="start"]'),
                end: this.$el.querySelector('[data-mb-mega-scroll="end"]'),
            }
            this.entries = Array.from(this.$el.querySelectorAll(ENTRY))
            this.media = window.matchMedia(`(min-width: ${breakpoint}px)`)

            this.entries.filter((entry) => this.hasPanel(entry)).forEach((entry) => {
                const trigger = this.triggerOf(entry)

                trigger?.setAttribute('aria-expanded', 'false')
                trigger?.setAttribute('aria-controls', entry.dataset.mbMegaPanelId)
            })

            this.listen(this.$el, 'pointerdown', (event) => (this.inputType = event.pointerType || 'mouse'), { capture: true, passive: true })
            this.listen(this.$el, 'keydown', (event) => this.onKeydown(event))
            this.listen(this.$el, 'pointerover', (event) => this.onPointerOver(event))
            this.listen(this.$el, 'pointerleave', (event) => this.onPointerLeave(event))
            this.listen(this.$el, 'click', (event) => this.onClick(event))
            this.listen(this.$el, 'focusin', (event) => this.onFocusIn(event))
            this.listen(this.$el, 'focusout', (event) => this.onFocusOut(event))
            this.listen(this.strip, 'scroll', () => this.onScroll(), { passive: true })
            this.listen(document, 'pointerdown', (event) => this.$el.contains(event.target) || this.close(), { passive: true })
            this.listen(document, 'livewire:navigated', () => this.syncActive())
            this.listen(this.media, 'change', () => this.close())

            this.resizeObserver = new ResizeObserver(() => this.scheduleUpdate())
            this.resizeObserver.observe(this.strip)
            this.resizeObserver.observe(this.strip.firstElementChild ?? this.strip)

            this.update()
        },

        destroy() {
            this.cancelOpen()
            this.cancelClose()
            cancelAnimationFrame(this.frame)
            this.resizeObserver?.disconnect()
            this.listeners.forEach((remove) => remove())
            this.listeners = []
        },

        listen(target, type, handler, options) {
            target.addEventListener(type, handler, options)
            this.listeners.push(() => target.removeEventListener(type, handler, options))
        },

        // Queries

        isRtl() {
            return getComputedStyle(this.$el).direction === 'rtl'
        },

        panelsEnabled() {
            return this.media.matches
        },

        entryFrom(target) {
            const entry = target instanceof Element ? target.closest(ENTRY) : null

            return entry && this.$el.contains(entry) ? entry : null
        },

        hasPanel(entry) {
            return entry.hasAttribute('data-mb-mega-panel-id')
        },

        panelOf(entry) {
            return entry.querySelector(PANEL)
        },

        triggerOf(entry) {
            return entry.querySelector(TRIGGER)
        },

        insets() {
            const width = (arrow) => (arrow && !arrow.hidden ? arrow.offsetWidth : 0)

            return { startInset: width(this.arrows.start), endInset: width(this.arrows.end) }
        },

        // Pointer

        onPointerOver(event) {
            if (event.pointerType === 'touch' || !this.panelsEnabled()) {
                return
            }

            const entry = this.entryFrom(event.target)

            if (!entry || !this.hasPanel(entry)) {
                this.cancelOpen()

                if (this.openEntry) {
                    this.scheduleClose()
                }

                return
            }

            this.cancelClose()

            if (entry === this.openEntry) {
                this.cancelOpen()
            } else if (this.openEntry) {
                this.open(entry)
            } else {
                this.scheduleOpen(entry)
            }
        },

        onPointerLeave(event) {
            if (event.pointerType === 'touch') {
                return
            }

            this.cancelOpen()

            if (this.openEntry) {
                this.scheduleClose()
            }
        },

        onClick(event) {
            const arrow = event.target instanceof Element ? event.target.closest('[data-mb-mega-scroll]') : null

            if (arrow) {
                this.scrollStrip(arrow.dataset.mbMegaScroll === 'end' ? 1 : -1)

                return
            }

            if (!['touch', 'pen'].includes(this.inputType) || !this.panelsEnabled()) {
                return
            }

            const entry = this.entryFrom(event.target)
            const trigger = entry && this.hasPanel(entry) ? this.triggerOf(entry) : null

            if (!trigger?.contains(event.target) || entry === this.openEntry) {
                return
            }

            event.preventDefault()
            this.open(entry, { touch: true })
        },

        // Keyboard and focus

        onKeydown(event) {
            this.inputType = 'keyboard'

            if (event.key === 'Escape' && this.openEntry) {
                const trigger = this.triggerOf(this.openEntry)

                this.close()
                trigger?.focus()

                return
            }

            if (event.key !== 'ArrowDown' || !this.panelsEnabled()) {
                return
            }

            const entry = this.entryFrom(event.target)

            if (!entry || !this.hasPanel(entry) || this.triggerOf(entry) !== event.target) {
                return
            }

            event.preventDefault()
            this.open(entry)

            Array.from(this.panelOf(entry).querySelectorAll(FOCUSABLE))
                .find((element) => element.getClientRects().length > 0)
                ?.focus()
        },

        onFocusIn(event) {
            const entry = this.entryFrom(event.target)

            if (this.openEntry && entry !== this.openEntry) {
                this.close()
            }

            if (entry && event.target === this.triggerOf(entry)) {
                this.reveal(entry)
            }
        },

        onFocusOut(event) {
            if (
                this.inputType === 'keyboard' &&
                this.openEntry?.contains(event.target) &&
                !this.openEntry.contains(event.relatedTarget)
            ) {
                this.close()
            }
        },

        // Panels

        scheduleOpen(entry) {
            this.cancelOpen()
            this.openTimer = setTimeout(() => {
                this.openTimer = null
                this.open(entry)
            }, openDelay)
        },

        scheduleClose() {
            if (this.closeTimer !== null) {
                return
            }

            this.closeTimer = setTimeout(() => {
                this.closeTimer = null
                this.close()
            }, closeDelay)
        },

        cancelOpen() {
            clearTimeout(this.openTimer)
            this.openTimer = null
        },

        cancelClose() {
            clearTimeout(this.closeTimer)
            this.closeTimer = null
        },

        open(entry, { touch = false } = {}) {
            this.cancelOpen()
            this.cancelClose()

            const panel = this.panelOf(entry)

            if (!panel || entry === this.openEntry) {
                return
            }

            this.close()

            this.$el.toggleAttribute('data-mb-mega-touch', touch)
            entry.setAttribute('data-open', '')
            this.place(entry, panel)
            this.triggerOf(entry)?.setAttribute('aria-expanded', 'true')
            this.openEntry = entry
            this.dispatch('mb-mega-open', entry)
        },

        close() {
            this.cancelOpen()
            this.cancelClose()

            const entry = this.openEntry

            if (!entry) {
                return
            }

            entry.removeAttribute('data-open')
            this.triggerOf(entry)?.setAttribute('aria-expanded', 'false')
            this.$el.removeAttribute('data-mb-mega-touch')
            this.openEntry = null
            this.dispatch('mb-mega-close', entry)
        },

        /**
         * The panel is displayed already, so its width is known; reading it
         * and writing the offset in the same task means no frame is painted
         * at the wrong position.
         */
        place(entry, panel) {
            const nav = this.$el.getBoundingClientRect()
            const left = nav.left + this.$el.clientLeft

            panel.style.insetInlineStart = `${panelInsetStart({
                container: { left, right: left + this.$el.clientWidth },
                trigger: entry.getBoundingClientRect(),
                panelWidth: panel.offsetWidth,
                rtl: this.isRtl(),
            })}px`
        },

        dispatch(name, entry) {
            this.$el.dispatchEvent(new CustomEvent(name, { bubbles: true, detail: { id: entry.dataset.mbMegaEntry } }))
        },

        // Strip

        onScroll() {
            this.close()
            this.scheduleUpdate()
        },

        scrollStrip(direction) {
            const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches

            this.strip.scrollBy({
                left: scrollDelta(direction, this.strip.clientWidth, this.isRtl()),
                behavior: reduceMotion ? 'auto' : 'smooth',
            })
        },

        reveal(entry) {
            const left = revealDelta({
                item: entry.getBoundingClientRect(),
                strip: this.strip.getBoundingClientRect(),
                ...this.insets(),
                rtl: this.isRtl(),
            })

            if (left !== 0) {
                this.strip.scrollBy({ left })
            }
        },

        scheduleUpdate() {
            if (this.frame !== null) {
                return
            }

            this.frame = requestAnimationFrame(() => {
                this.frame = null
                this.update()
            })
        },

        update() {
            const { canScrollStart, canScrollEnd } = scrollState(this.strip)
            const rtl = this.isRtl()

            this.arrows.start.hidden = !canScrollStart
            this.arrows.end.hidden = !canScrollEnd

            const strip = this.strip.getBoundingClientRect()
            const insets = this.insets()

            this.entries.forEach((entry) => {
                entry.toggleAttribute(
                    'data-mb-mega-obscured',
                    isObscured({ item: entry.getBoundingClientRect(), strip, ...insets, rtl }),
                )
            })

            if (this.openEntry) {
                this.place(this.openEntry, this.panelOf(this.openEntry))
            }
        },

        /**
         * Pages loaded with wire:navigate may keep the menu (@persist). The
         * active state is then updated here from the current URL, query
         * string ignored, like Menu::build() does on the server.
         */
        syncActive() {
            const here = normalizeUrl(window.location.href)

            this.entries.forEach((entry) => {
                let trail = false

                entry.querySelectorAll('a[href]:not(.mb-parent-link)').forEach((link) => {
                    const current = normalizeUrl(link.href) === here

                    current ? link.setAttribute('aria-current', 'page') : link.removeAttribute('aria-current')
                    trail ||= current && link !== this.triggerOf(entry)
                })

                const trigger = this.triggerOf(entry)
                const current = trigger?.getAttribute('aria-current') === 'page'

                entry.classList.toggle('mb-current', current)
                entry.classList.toggle('mb-active-trail', trail)
                trigger?.classList.toggle('mb-active', current || trail)
            })
        },
    }
}

menuBuilderMega.geometry = { panelInsetStart, scrollState, scrollDelta, isObscured, revealDelta }
