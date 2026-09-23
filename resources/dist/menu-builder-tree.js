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
            event.dataTransfer.effectAllowed = 'move'
            event.dataTransfer.setData('text/plain', this.draggingKey)
            row.closest('.mb-node').classList.add('mb-dragging')
        },

        onDragEnd(event) {
            const row = event.target.closest?.('.mb-row')

            row?.removeAttribute('draggable')
            row?.closest('.mb-node')?.classList.remove('mb-dragging')
            this.clearDropTarget()
            this.draggingKey = null
        },

        onDragOver(event) {
            const row = this.dropRow(event)

            if (!row) {
                return
            }

            event.preventDefault()
            event.dataTransfer.dropEffect = 'move'

            const position = this.positionFor(event, row)

            if (this.dropTarget?.row === row && this.dropTarget.position === position) {
                return
            }

            this.clearDropTarget()
            this.dropTarget = { row, position }
            row.classList.add(`mb-drop-${position}`)
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
            const row = this.dropRow(event)
            const draggingKey = this.draggingKey

            this.clearDropTarget()

            if (!row) {
                return
            }

            event.preventDefault()

            // The drop event itself decides the position, so a stale
            // highlight can never send the wrong operation.
            const key = this.keyOf(row)
            const position = this.positionFor(event, row)

            if (position === 'inside') {
                delete this.collapsed[key]
                this.persist()
            }

            this.$wire.moveItem(draggingKey, key, position)
        },

        // The row under the pointer, if the dragged item may be dropped on it.
        // An item can never be dropped on itself or inside its own subtree;
        // the server enforces this (and all placement rules) as well.
        dropRow(event) {
            if (!this.draggingKey) {
                return null
            }

            const row = event.target.closest?.('.mb-row')

            if (!row || !this.$root.contains(row)) {
                return null
            }

            const dragged = this.$root.querySelector(`.mb-node[data-key="${CSS.escape(this.draggingKey)}"]`)

            return dragged?.contains(row) ? null : row
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
            this.dropTarget = null
        },
    }
}
