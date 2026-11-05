import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { initCharts } from './modules/charts';

window.Alpine = Alpine;
Alpine.plugin(collapse);

// Small reusable Alpine helpers --------------------------------------------
Alpine.data('dismissible', (timeout = 5000) => ({
    show: true,
    init() {
        if (timeout > 0) setTimeout(() => (this.show = false), timeout);
    },
}));

Alpine.data('copyToClipboard', (text) => ({
    copied: false,
    async copy() {
        try {
            await navigator.clipboard.writeText(text);
            this.copied = true;
            setTimeout(() => (this.copied = false), 2000);
        } catch (e) {
            window.prompt('Copy this value:', text);
        }
    },
}));

Alpine.start();

document.addEventListener('DOMContentLoaded', () => initCharts());
