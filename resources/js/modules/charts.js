import {
    Chart, LineController, BarController, DoughnutController, LineElement, BarElement, ArcElement,
    PointElement, CategoryScale, LinearScale, Filler, Tooltip, Legend,
} from 'chart.js';

Chart.register(LineController, BarController, DoughnutController, LineElement, BarElement, ArcElement,
    PointElement, CategoryScale, LinearScale, Filler, Tooltip, Legend);

Chart.defaults.font.family = "'Inter Variable', 'Inter', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.color = '#6b7280';

const PALETTE = ['#4f46e5', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6'];

/**
 * Renders every <canvas data-chart='{"type": "...", "labels": [...], "datasets": [...]}'>.
 * Optional "format": "currency" + "currency": "USD" formats axis ticks and tooltips.
 */
export function initCharts(root = document) {
    root.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        if (canvas.dataset.chartReady) return;
        const config = JSON.parse(canvas.dataset.chart);
        const fmt = config.format === 'currency'
            ? new Intl.NumberFormat(undefined, { style: 'currency', currency: config.currency || 'USD', maximumFractionDigits: 0 })
            : new Intl.NumberFormat();
        const ctx = canvas.getContext('2d');

        const datasets = config.datasets.map((ds, i) => {
            const color = ds.color || PALETTE[i % PALETTE.length];
            if (config.type === 'doughnut') {
                return { ...ds, backgroundColor: ds.colors || PALETTE, borderWidth: 0 };
            }
            let background = color;
            if (config.type === 'line') {
                const gradient = ctx.createLinearGradient(0, 0, 0, canvas.height || 240);
                gradient.addColorStop(0, color + '33');
                gradient.addColorStop(1, color + '00');
                background = gradient;
            }
            return {
                tension: 0.35, pointRadius: 0, pointHoverRadius: 4, borderWidth: 2, fill: config.type === 'line',
                borderRadius: config.type === 'bar' ? 6 : 0, maxBarThickness: 28,
                ...ds, borderColor: color, backgroundColor: background,
            };
        });

        new Chart(ctx, {
            type: config.type,
            data: { labels: config.labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                cutout: config.type === 'doughnut' ? '70%' : undefined,
                plugins: {
                    legend: { display: config.type === 'doughnut' || datasets.length > 1, position: 'bottom', labels: { boxWidth: 8, boxHeight: 8, usePointStyle: true } },
                    tooltip: { callbacks: { label: (c) => `${c.dataset.label || c.label}: ${fmt.format(c.parsed.y ?? c.parsed)}` } },
                },
                scales: config.type === 'doughnut' ? {} : {
                    x: { grid: { display: false }, border: { display: false } },
                    y: { beginAtZero: true, border: { display: false }, grid: { color: '#f3f4f6' }, ticks: { precision: 0, callback: (v) => fmt.format(v) } },
                },
            },
        });
        canvas.dataset.chartReady = '1';
    });
}
