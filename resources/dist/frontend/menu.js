/*
 * Menu builder: dropdown behavior for <x-menu-builder::menu>.
 *
 * Toggle buttons open and close submenus (tap on touch screens, click or
 * keyboard everywhere). On wide screens dropdown submenus also open on hover,
 * and each one flips to the inline start when it would leave the viewport,
 * which works the same way for LTR and RTL. No dependencies.
 */
(() => {
    if (window.menuBuilderMenus) {
        return
    }

    window.menuBuilderMenus = true

    const wide = window.matchMedia('(min-width: 48rem)')

    const menuOf = (element) => element.closest('[data-mb-menu]')

    const isDropdown = (element) => menuOf(element)?.dataset.mbMenu === 'dropdown'

    const toggleOf = (entry) => entry.querySelector(':scope > .mb-row > [data-mb-toggle]')

    const place = (entry) => {
        const submenu = entry.querySelector(':scope > .mb-submenu')

        entry.classList.remove('mb-flip')

        if (!submenu || !wide.matches || !isDropdown(entry)) {
            return
        }

        const rect = submenu.getBoundingClientRect()

        if (rect.width === 0) {
            return
        }

        const rtl = getComputedStyle(entry).direction === 'rtl'
        const overflows = rtl ? rect.left < 0 : rect.right > document.documentElement.clientWidth

        entry.classList.toggle('mb-flip', overflows)
    }

    const setOpen = (entry, open) => {
        entry.toggleAttribute('data-open', open)
        toggleOf(entry)?.setAttribute('aria-expanded', open ? 'true' : 'false')

        if (!open) {
            entry.querySelectorAll('.mb-entry[data-open]').forEach((child) => setOpen(child, false))
        }
    }

    const closeDropdowns = (except) => {
        document.querySelectorAll('[data-mb-menu="dropdown"] .mb-entry[data-open]').forEach((entry) => {
            if (!entry.contains(except)) {
                setOpen(entry, false)
            }
        })
    }

    document.addEventListener('click', (event) => {
        const toggle = event.target.closest?.('[data-mb-toggle]')

        if (!toggle) {
            if (wide.matches) {
                closeDropdowns(event.target)
            }

            return
        }

        const entry = toggle.closest('.mb-entry')
        const open = !entry.hasAttribute('data-open')

        if (open && wide.matches && isDropdown(entry)) {
            closeDropdowns(entry)
        }

        setOpen(entry, open)

        if (open) {
            requestAnimationFrame(() => place(entry))
        }
    })

    const onEnter = (event) => {
        const entry = event.target.closest?.('[data-mb-menu="dropdown"] .mb-has-children')

        if (entry && !entry.contains(event.relatedTarget)) {
            requestAnimationFrame(() => place(entry))
        }
    }

    document.addEventListener('pointerover', onEnter)
    document.addEventListener('focusin', onEnter)

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return
        }

        const entry = event.target.closest?.('.mb-entry[data-open]')

        if (entry) {
            setOpen(entry, false)
            toggleOf(entry)?.focus()
        }
    })
})()
