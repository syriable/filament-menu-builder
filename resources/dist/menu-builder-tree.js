/**
 * Drag & drop and expand/collapse for the menu builder tree.
 *
 * The script only handles interaction. A drop sends one semantic operation
 * ("move X before/after/inside Y") to the server, which validates it
 * against the placement rules and re-renders the tree.
 *
 * All listeners are delegated to the (never re-rendered) root element and
 * the collapsed state is applied through a generated stylesheet. The tree
 * rows themselves carry no Alpine directives, because Livewire's morphing
 * may move or re-create rows after every change.
 */
export default function menuBuilderTree({ storageKey, canReorder, unsavedMessage }) {
    return {
        collapsed: {},
        draggingKey: null,
        dropTarget: null,
        onBeforeUnload: null,
        onNavigate: null,

        init() {
            try {
                this.collapsed = JSON.parse(window.localStorage.getItem(storageKey) ?? '{}') ?? {}
            } catch {
                this.collapsed = {}
            }

            this.onBeforeUnload = (event) => {
                if (!this.isDirty()) {
                    return
                }

                event.preventDefault()
                event.returnValue = unsavedMessage
            }

            this.onNavigate = (event) => {
                if (this.isDirty() && !window.confirm(unsavedMessage)) {
                    event.preventDefault()
                }
            }

            window.addEventListener('beforeunload', this.onBeforeUnload)
            document.addEventListener('livewire:navigate', this.onNavigate)
        },

        destroy() {
            window.removeEventListener('beforeunload', this.onBeforeUnload)
            document.removeEventListener('livewire:navigate', this.onNavigate)
        },

        isDirty() {
            return this.$root.dataset.dirty === 'true'
        },

        // Collapsing ------------------------------------------------------

        collapsedCss() {
            return Object.keys(this.collapsed)
                .map((key) => {
                    const node = `.mb-node[data-key="${CSS.escape(key)}"]`

                    return `${node} > .mb-children { display: none; } ${node} > .mb-row .mb-toggle svg { transform: rotate(-90deg); }`
                })
                .join('\n')
        },

        toggleItem(key) {
            if (this.collapsed[key]) {
                delete this.collapsed[key]
            } else {
                this.collapsed[key] = true
            }

            this.persist()
        },

        expandAllItems() {
            this.collapsed = {}
            this.persist()
        },

        collapseAllItems() {
            const collapsed = {}

            this.$root.querySelectorAll('.mb-node').forEach((node) => {
                if (node.querySelector(':scope > .mb-children')) {
                    collapsed[node.dataset.key] = true
                }
            })

            this.collapsed = collapsed
            this.persist()
        },

        persist() {
            try {
                window.localStorage.setItem(storageKey, JSON.stringify(this.collapsed))
            } catch {
                // Storage may be unavailable (private mode); collapsing still works.
            }
        },

        onClick(event) {
            const toggle = event.target.closest('.mb-toggle')

            if (toggle && this.$root.contains(toggle)) {
                this.toggleItem(this.keyOf(toggle))
            }
        },

        // Drag & drop -----------------------------------------------------

        // Rows only become draggable while the handle is pressed, so text
        // selection and the action buttons keep working normally.
        onPointerDown(event) {
            const handle = event.target.closest('.mb-handle')

            if (!canReorder || !handle) {
                return
            }

            const row = handle.closest('.mb-row')
            row.setAttribute('draggable', 'true')

            const disarm = () => {
                if (!this.draggingKey) {
                    row.removeAttribute('draggable')
                }

                window.removeEventListener('pointerup', disarm)
            }

            window.addEventListener('pointerup', disarm)
        },

        onDragStart(event) {
            const row = event.target.closest?.('.mb-row')

            if (!row || !row.hasAttribute('draggable')) {
                return
            }

            this.draggingKey = this.keyOf(row)
            this.$root.classList.add('mb-is-dragging')
            event.dataTransfer.effectAllowed = 'move'
            event.dataTransfer.setData('text/plain', this.draggingKey)
            row.closest('.mb-node').classList.add('mb-dragging')
        },

        onDragEnd(event) {
            const row = event.target.closest?.('.mb-row')

            row?.removeAttribute('draggable')
            row?.closest('.mb-node')?.classList.remove('mb-dragging')
            this.clearDropTarget()
            this.$root.classList.remove('mb-is-dragging')
            this.draggingKey = null
        },

        onDragOver(event) {
            const target = this.dropTargetFor(event)

            if (!target) {
                return
            }

            event.preventDefault()
            event.dataTransfer.dropEffect = 'move'

            if (this.dropTarget?.row === target.row && this.dropTarget.position === target.position && this.dropTarget.key === target.key) {
                return
            }

            this.clearDropTarget()
            this.dropTarget = target
            target.row.classList.add(`mb-drop-${target.position}`)
            target.row.style.setProperty('--mb-drop-indent', `${target.indent}px`)
        },

        // dragleave also fires when moving over child elements (often with a
        // null relatedTarget), so only clear when the pointer left the row.
        onDragLeave(event) {
            const row = this.dropTarget?.row

            if (!row) {
                return
            }

            const rect = row.getBoundingClientRect()
            const inside = event.clientX >= rect.left && event.clientX <= rect.right && event.clientY >= rect.top && event.clientY <= rect.bottom

            if (!inside) {
                this.clearDropTarget()
            }
        },

        onDrop(event) {
            // The drop event itself decides the target, so a stale
            // highlight can never send the wrong operation.
            const target = this.dropTargetFor(event)
            const draggingKey = this.draggingKey

            this.clearDropTarget()

            if (!target) {
                return
            }

            event.preventDefault()

            if (target.key === draggingKey) {
                return
            }

            if (target.position === 'inside') {
                delete this.collapsed[target.key]
                this.persist()
            }

            this.$wire.moveItem(draggingKey, target.key, target.position)
        },

        // Resolves what a drop at the pointer would do: { row, key, position, indent }.
        //
        // - The root drop zone moves the item to the end of the top level.
        // - "after" the last child of a parent, moving the pointer into the
        //   indentation gutter (towards the inline start) moves the item out to
        //   the ancestor's level, so a child can always become a root item.
        dropTargetFor(event) {
            if (!this.draggingKey) {
                return null
            }

            const zone = event.target.closest?.('[data-root-drop]')

            if (zone && this.$root.contains(zone)) {
                const roots = [...this.$root.querySelectorAll('.mb-tree > .mb-node')].filter((node) => node.dataset.key !== this.draggingKey)
                const last = roots.at(-1)

                return last ? { row: zone, key: last.dataset.key, position: 'after', indent: 0 } : null
            }

            const row = this.dropRow(event)

            if (!row) {
                return null
            }

            const position = this.positionFor(event, row)

            if (position !== 'after') {
                return { row, key: this.keyOf(row), position, indent: 0 }
            }

            const rtl = getComputedStyle(this.$root).direction === 'rtl'
            const edgeOf = (node) => {
                const rect = node.querySelector(':scope > .mb-row').getBoundingClientRect()

                return rtl ? rect.right : rect.left
            }
            const isBeforeEdge = (edge) => (rtl ? event.clientX > edge : event.clientX < edge)

            let node = row.closest('.mb-node')

            while (this.isLastSibling(node)) {
                const parent = node.parentElement.closest('.mb-node')

                if (!parent || !this.$root.contains(parent) || !isBeforeEdge(edgeOf(node))) {
                    break
                }

                node = parent
            }

            const indent = (edgeOf(node) - edgeOf(row.closest('.mb-node'))) * (rtl ? -1 : 1)

            return { row, key: node.dataset.key, position: 'after', indent }
        },

        // Whether a node is the last of its siblings, ignoring the dragged item.
        isLastSibling(node) {
            for (let next = node.nextElementSibling; next; next = next.nextElementSibling) {
                if (next.dataset.key !== this.draggingKey) {
                    return false
                }
            }

            return true
        },

        // The row under the pointer, if the dragged item may be dropped on it.
        // An item can never be dropped on itself or inside its own subtree;
        // the server enforces this (and all placement rules) as well.
        dropRow(event) {
            if (!this.draggingKey) {
                return null
            }

            // Over the indentation gutter the pointer is not above a row, so
            // fall back to the row at the pointer's vertical position.
            const row = event.target.closest?.('.mb-row') ?? this.rowAt(event)

            if (!row || !this.$root.contains(row)) {
                return null
            }

            const dragged = this.$root.querySelector(`.mb-node[data-key="${CSS.escape(this.draggingKey)}"]`)

            return dragged?.contains(row) ? null : row
        },

        rowAt(event) {
            if (!event.target.closest?.('.mb-tree')) {
                return null
            }

            return [...this.$root.querySelectorAll('.mb-tree .mb-row')].find((row) => {
                const rect = row.getBoundingClientRect()

                return rect.height > 0 && event.clientY >= rect.top && event.clientY <= rect.bottom
            }) ?? null
        },

        // Top quarter: before, bottom quarter: after, middle: inside.
        positionFor(event, row) {
            const rect = row.getBoundingClientRect()
            const offset = (event.clientY - rect.top) / rect.height

            if (offset < 0.25) {
                return 'before'
            }

            if (offset > 0.75) {
                return 'after'
            }

            return 'inside'
        },

        keyOf(element) {
            return element.closest('.mb-node')?.dataset.key ?? null
        },

        clearDropTarget() {
            this.dropTarget?.row.classList.remove('mb-drop-before', 'mb-drop-after', 'mb-drop-inside')
            this.dropTarget?.row.style.removeProperty('--mb-drop-indent')
            this.dropTarget = null
        },
    }
}
