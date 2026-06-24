import cmsEditor from './editor.js'
import { MediaImage } from './extensions/MediaImage.js'

/**
 * Package entry point. Registers the Alpine component used by the Blade view.
 *
 * In a host app's app.js:
 *   import { registerCmsEditor } from '@degrinthorst/livewire-cms-editor'
 *   document.addEventListener('alpine:init', () => registerCmsEditor(Alpine))
 *
 * Or just include the pre-built dist bundle (ADR-007), which self-registers
 * against window.Alpine.
 */
export function registerCmsEditor(Alpine) {
    Alpine.data('cmsEditor', cmsEditor)
}

export { cmsEditor, MediaImage }

// Self-register when a global Alpine is present (pre-built bundle path).
if (typeof window !== 'undefined' && window.Alpine) {
    registerCmsEditor(window.Alpine)
} else if (typeof document !== 'undefined') {
    document.addEventListener('alpine:init', () => {
        if (window.Alpine) registerCmsEditor(window.Alpine)
    })
}
