/*
 * Menu builder: accordion toggles for <x-menu-builder::menu variant="tree">.
 *
 * Dropdowns are Filament dropdown components and need no code from this
 * package. No dependencies.
 */
(() => {
    if (window.menuBuilderMenus) {
        return
    }

    window.menuBuilderMenus = true

    const toggleOf = (entry) => entry.querySelector(':scope > .mb-row > [data-mb-toggle]')

    const setOpen = (entry, open) => {
        entry.toggleAttribute('data-open', open)
        toggleOf(entry)?.setAttribute('aria-expanded', open ? 'true' : 'false')

        if (!open) {
            entry.querySelectorAll('.mb-entry[data-open]').forEach((child) => setOpen(child, false))
        }
    }

    document.addEventListener('click', (event) => {
        const toggle = event.target.closest?.('[data-mb-toggle]')

        if (!toggle) {
            return
        }

        const entry = toggle.closest('.mb-entry')

        setOpen(entry, !entry.hasAttribute('data-open'))
    })

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
