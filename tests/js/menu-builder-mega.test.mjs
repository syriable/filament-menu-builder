import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import menuBuilderMega from '../../resources/dist/menu-builder-mega.js'

const { panelInsetStart, scrollState, scrollDelta, isObscured, revealDelta } = menuBuilderMega.geometry

// A <nav> from x = 100 to x = 1500 (1400px wide).
const container = { left: 100, right: 1500 }

describe('panelInsetStart', () => {
    it('aligns the panel with its trigger when it fits', () => {
        assert.equal(panelInsetStart({ container, trigger: { left: 300, right: 400 }, panelWidth: 634, rtl: false }), 200)
    })

    it('keeps the first category flush with the start edge', () => {
        assert.equal(panelInsetStart({ container, trigger: { left: 100, right: 180 }, panelWidth: 1274, rtl: false }), 0)
    })

    it('clamps a panel that would overflow the end edge', () => {
        // The right edges of these panels all end at the container's end edge.
        for (const panelWidth of [634, 954, 1274]) {
            const start = panelInsetStart({ container, trigger: { left: 1300, right: 1400 }, panelWidth, rtl: false })

            assert.equal(start + panelWidth, 1400)
        }
    })

    it('mirrors the offset in right-to-left menus', () => {
        assert.equal(panelInsetStart({ container, trigger: { left: 1200, right: 1300 }, panelWidth: 634, rtl: true }), 200)
        assert.equal(panelInsetStart({ container, trigger: { left: 120, right: 200 }, panelWidth: 954, rtl: true }), 1400 - 954)
    })

    it('starts at zero when the panel is wider than the container', () => {
        assert.equal(panelInsetStart({ container, trigger: { left: 900, right: 1000 }, panelWidth: 2000, rtl: false }), 0)
    })
})

describe('panelInsetStart with viewport bounds', () => {
    // A 2000px viewport with a 16px gap on each side.
    const bounds = { left: 16, right: 1984 }

    it('lets a panel extend past the container end edge', () => {
        const start = panelInsetStart({ container, bounds, trigger: { left: 1300, right: 1400 }, panelWidth: 634, rtl: false })

        assert.equal(start, 1200)
        assert.equal(container.left + start + 634, 1934)
    })

    it('clamps a panel to the viewport end edge', () => {
        const start = panelInsetStart({ container, bounds, trigger: { left: 1300, right: 1400 }, panelWidth: 1274, rtl: false })

        assert.equal(container.left + start + 1274, 1984)
    })

    it('moves a panel before the container start edge when needed', () => {
        const start = panelInsetStart({ container, bounds, trigger: { left: 1300, right: 1400 }, panelWidth: 1900, rtl: false })

        assert.equal(start, -16)
        assert.equal(container.left + start + 1900, 1984)
    })

    it('starts at the viewport start edge when the panel is wider than the viewport', () => {
        const start = panelInsetStart({ container, bounds, trigger: { left: 900, right: 1000 }, panelWidth: 2500, rtl: false })

        assert.equal(container.left + start, 16)
    })

    it('mirrors the bounds in right-to-left menus', () => {
        // Aligned with the trigger's right edge, extending past the container's left edge.
        const start = panelInsetStart({ container, bounds, trigger: { left: 120, right: 200 }, panelWidth: 150, rtl: true })

        assert.equal(start, 1300)
        assert.equal(container.right - start - 150, 50)

        // Clamped to the viewport's left edge.
        const clamped = panelInsetStart({ container, bounds, trigger: { left: 120, right: 200 }, panelWidth: 1274, rtl: true })

        assert.equal(container.right - clamped - 1274, 16)
    })
})

describe('scrollState', () => {
    it('reports nothing to scroll when everything fits', () => {
        assert.deepEqual(scrollState({ scrollLeft: 0, scrollWidth: 1000, clientWidth: 1000 }), { canScrollStart: false, canScrollEnd: false })
    })

    it('reports the hidden side at the start and at the end', () => {
        assert.deepEqual(scrollState({ scrollLeft: 0, scrollWidth: 1500, clientWidth: 1000 }), { canScrollStart: false, canScrollEnd: true })
        assert.deepEqual(scrollState({ scrollLeft: 500, scrollWidth: 1500, clientWidth: 1000 }), { canScrollStart: true, canScrollEnd: false })
        assert.deepEqual(scrollState({ scrollLeft: 250, scrollWidth: 1500, clientWidth: 1000 }), { canScrollStart: true, canScrollEnd: true })
    })

    it('handles the negative scrollLeft of right-to-left containers', () => {
        assert.deepEqual(scrollState({ scrollLeft: -500, scrollWidth: 1500, clientWidth: 1000 }), { canScrollStart: true, canScrollEnd: false })
    })

    it('ignores subpixel remainders', () => {
        assert.deepEqual(scrollState({ scrollLeft: 499.5, scrollWidth: 1500, clientWidth: 1000 }), { canScrollStart: true, canScrollEnd: false })
    })
})

describe('scrollDelta', () => {
    it('scrolls 80% of the visible width towards the requested side', () => {
        assert.equal(scrollDelta(1, 1000, false), 800)
        assert.equal(scrollDelta(-1, 1000, false), -800)
    })

    it('mirrors the physical direction in right-to-left containers', () => {
        assert.equal(scrollDelta(1, 1000, true), -800)
        assert.equal(scrollDelta(-1, 1000, true), 800)
    })
})

describe('isObscured and revealDelta', () => {
    const strip = { left: 0, right: 1000 }

    it('treats an item under the start arrow as obscured', () => {
        const item = { left: 10, right: 110 }

        assert.equal(isObscured({ item, strip, startInset: 36, endInset: 0, rtl: false }), true)
        assert.equal(revealDelta({ item, strip, startInset: 36, endInset: 0, rtl: false }), -26)
    })

    it('treats a fully visible item as visible', () => {
        const item = { left: 100, right: 200 }

        assert.equal(isObscured({ item, strip, startInset: 36, endInset: 36, rtl: false }), false)
        assert.equal(revealDelta({ item, strip, startInset: 36, endInset: 36, rtl: false }), 0)
    })

    it('puts the start arrow on the right in right-to-left menus', () => {
        const item = { left: 900, right: 990 }

        assert.equal(isObscured({ item, strip, startInset: 36, endInset: 0, rtl: true }), true)
        assert.equal(isObscured({ item, strip, startInset: 0, endInset: 36, rtl: true }), false)
        assert.equal(revealDelta({ item, strip, startInset: 36, endInset: 0, rtl: true }), 26)
    })
})

describe('menuBuilderMega', () => {
    it('returns an Alpine component with lifecycle hooks', () => {
        const component = menuBuilderMega({ openDelay: 0, closeDelay: 0, breakpoint: 1024 })

        assert.equal(typeof component.init, 'function')
        assert.equal(typeof component.destroy, 'function')
        assert.equal(component.openEntry, null)
    })
})
