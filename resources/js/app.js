/**
 * Admin UI behaviour. Alpine ships with Livewire, so components are
 * registered on `alpine:init` instead of importing Alpine a second time.
 * Heavy libraries (ApexCharts) are imported on demand by the pages that use them.
 */

const THEME_KEY = 'theme';
const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');

function storedTheme() {
    try {
        return localStorage.getItem(THEME_KEY) || 'system';
    } catch {
        return 'system';
    }
}

function applyTheme(mode, notify = true) {
    const dark = mode === 'dark' || (mode === 'system' && prefersDark.matches);
    document.documentElement.classList.toggle('dark', dark);
    // The layout reads this cookie, so the next page is rendered in the same theme.
    document.cookie = `theme=${dark ? 'dark' : 'light'};path=/;max-age=31536000;SameSite=Lax`;
    if (notify) window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark } }));
}

prefersDark.addEventListener('change', () => {
    if (storedTheme() === 'system') applyTheme('system');
});

// How long a modal's exit animation takes (resources/views/components/ui/modal.blade.php).
const MODAL_LEAVE_MS = 180;

document.addEventListener('livewire:init', () => {
    // When the server stops rendering a modal (closed after a save, or after the
    // close button already started the animation), keep it on the page until its
    // exit animation has played, then remove it.
    window.Livewire.hook('morph.removing', ({ el, skip }) => {
        if (!el.hasAttribute?.('data-ui-modal')) return;
        skip();
        const modal = window.Alpine.$data(el);
        if (!modal.closedAt) {
            modal.closedAt = Date.now();
            modal.show = false;
        }
        const left = Math.max(0, MODAL_LEAVE_MS - (Date.now() - modal.closedAt));
        setTimeout(() => el.remove(), left + 20);
    });

    // A failed background progress check (checkSync) must not pop up Livewire's error
    // screen: the banner just tries again a few seconds later.
    window.Livewire.hook('request', ({ payload, fail }) => {
        if (typeof payload === 'string' && payload.includes('"method":"checkSync"')) {
            fail(({ preventDefault }) => {
                preventDefault();
                // The pending $wire call never settles; tell the banner to carry on.
                window.dispatchEvent(new CustomEvent('sync-check-failed'));
            });
        }
    });
});

// wire:navigate copies the new page's <html> attributes; make sure the theme sticks.
document.addEventListener('livewire:navigated', () => applyTheme(storedTheme(), false));

/** 1234 → "1.2K". */
export function compact(n) {
    if (n === null || n === undefined || Number.isNaN(n)) return '—';
    return new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(n);
}

/** Download the visible rows of a table as CSV (cells with data-no-export are skipped). */
window.exportTable = (table, filename = 'export') => {
    const rows = [...table.querySelectorAll('tr')].filter((tr) => tr.offsetParent !== null);
    const csv = rows
        .map((tr) =>
            [...tr.querySelectorAll('th, td')]
                .filter((cell) => !cell.hasAttribute('data-no-export'))
                .map((cell) => `"${cell.innerText.trim().replace(/\s+/g, ' ').replace(/"/g, '""')}"`)
                .join(','),
        )
        .join('\n');
    const link = Object.assign(document.createElement('a'), {
        href: URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' })),
        download: `${filename}.csv`,
    });
    link.click();
    URL.revokeObjectURL(link.href);
};

// --------------------------------------------------------------- Charts

const PLATFORM_COLORS = {
    facebook: ['#1877F2', '#0B5FCC', '#5B9BF5'],
    instagram: ['#E1306C', '#A1338F', '#F77737'],
    google_ads: ['#34A853', '#188038', '#81C995'],
};

function chartTheme() {
    const dark = document.documentElement.classList.contains('dark');
    return {
        theme: { mode: dark ? 'dark' : 'light' },
        grid: { borderColor: dark ? '#1e293b' : '#e2e8f0' },
        tooltip: { theme: dark ? 'dark' : 'light' },
    };
}

/** One colour per series: the platform's colour, shifted for a second account of the same platform. */
export function seriesColors(series) {
    const used = {};
    return series.map((s) => {
        const list = PLATFORM_COLORS[s.platform] || ['#009DFF', '#0062A3', '#6CC6FF'];
        used[s.platform] = (used[s.platform] ?? -1) + 1;
        return list[used[s.platform] % list.length];
    });
}

function trendOptions(cfg) {
    const theme = chartTheme();
    return {
        chart: {
            type: 'area',
            height: 300,
            fontFamily: 'inherit',
            background: 'transparent',
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches },
        },
        series: cfg.series.map((s) => ({ name: s.name, data: s.data })),
        // Local midnight, so a day never shifts because of the time zone.
        xaxis: {
            type: 'datetime',
            categories: cfg.dates.map((d) => new Date(`${d}T00:00:00`).getTime()),
            labels: { datetimeUTC: false },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: { labels: { formatter: (v) => compact(v) } },
        // Fixed colours when the series mean something (e.g. delivered / failed), platform colours otherwise.
        colors: cfg.colors || seriesColors(cfg.series),
        // Different line styles too, so series aren't told apart by colour alone.
        stroke: { curve: 'smooth', width: 2, dashArray: cfg.series.map((_, i) => [0, 6, 2][i % 3]) },
        fill: { type: 'gradient', gradient: { opacityFrom: 0.25, opacityTo: 0.02 } },
        dataLabels: { enabled: false },
        legend: { show: false },
        markers: { size: 0, hover: { size: 4 } },
        grid: { borderColor: theme.grid.borderColor, strokeDashArray: 4 },
        theme: theme.theme,
        tooltip: {
            theme: theme.tooltip.theme,
            shared: true,
            x: { format: 'dd MMM yyyy' },
            y: { formatter: (v) => (v === null || v === undefined ? '—' : v.toLocaleString()) },
        },
    };
}

// ------------------------------------------------------- Alpine pieces

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    Alpine.store('theme', {
        mode: storedTheme(),
        set(mode) {
            this.mode = mode;
            try {
                mode === 'system' ? localStorage.removeItem(THEME_KEY) : localStorage.setItem(THEME_KEY, mode);
            } catch {
                // Private mode: the choice just isn't remembered.
            }
            applyTheme(mode);
        },
    });

    /** Toasts for the swal:* browser events Livewire components dispatch (listeners live in the Blade view). */
    Alpine.data('toasts', (initial = []) => ({
        items: [],
        init() {
            initial.forEach((t) => this.push(t.type, t));
        },
        push(type, detail) {
            const payload = Array.isArray(detail) ? detail[0] || {} : detail || {};
            const id = `${Date.now()}-${Math.random()}`;
            this.items.push({ id, type, title: payload.title || '', text: payload.text || '', visible: true });
            setTimeout(() => this.dismiss(id), type === 'success' || type === 'info' ? 5000 : 10000);
        },
        dismiss(id) {
            const toast = this.items.find((t) => t.id === id);
            if (toast) toast.visible = false;
            setTimeout(() => (this.items = this.items.filter((t) => t.id !== id)), 250);
        },
    }));

    /**
     * Confirmation dialog. Open it from anywhere with
     * $dispatch('confirm', { title, text, confirm: 'Delete', onConfirm: () => $wire.delete(id) }).
     */
    Alpine.data('confirmDialog', () => ({
        show: false,
        title: '',
        text: '',
        confirmText: 'Confirm',
        danger: true,
        onConfirm: null,
        open(detail) {
            this.title = detail.title || 'Are you sure?';
            this.text = detail.text || '';
            this.confirmText = detail.confirm || 'Confirm';
            this.danger = detail.danger ?? true;
            this.onConfirm = detail.onConfirm || null;
            this.show = true;
            this.$nextTick(() => this.$refs.confirmButton?.focus());
        },
        confirm() {
            this.show = false;
            if (this.onConfirm) this.onConfirm();
        },
    }));

    /** Client-side search over the rows of a table (rows carry data-row). */
    Alpine.data('tableSearch', () => ({
        q: '',
        matches(el) {
            return !this.q || el.textContent.toLowerCase().includes(this.q.trim().toLowerCase());
        },
        get anyMatch() {
            return [...this.$root.querySelectorAll('[data-row]')].some((row) => this.matches(row));
        },
    }));

    /**
     * Horizontal strip that overflows: arrow buttons, faded edges, and the
     * mouse wheel scrolls it sideways (until an end, then the page scrolls).
     * Markup: x-data="hscroll" with a child x-ref="track" that scrolls.
     */
    Alpine.data('hscroll', () => ({
        canLeft: false,
        canRight: false,
        init() {
            this.$nextTick(() => this.update());
            this.observer = new ResizeObserver(() => this.update());
            this.observer.observe(this.$refs.track);
        },
        destroy() {
            this.observer?.disconnect();
        },
        update() {
            const t = this.$refs.track;
            this.canLeft = t.scrollLeft > 2;
            this.canRight = t.scrollLeft + t.clientWidth < t.scrollWidth - 2;
        },
        by(direction) {
            const t = this.$refs.track;
            const max = t.scrollWidth - t.clientWidth;
            const target = Math.max(0, Math.min(max, t.scrollLeft + direction * t.clientWidth * 0.75));
            const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            t.scrollTo({ left: target, behavior: reduce ? 'auto' : 'smooth' });
            // Some browsers skip smooth scrolling (e.g. background tabs): make sure we get there.
            setTimeout(() => {
                if (Math.abs(t.scrollLeft - target) > 4) t.scrollLeft = target;
                this.update();
            }, 450);
        },
        wheel(e) {
            if (Math.abs(e.deltaY) <= Math.abs(e.deltaX)) return; // trackpads already scroll sideways
            if ((e.deltaY > 0 && !this.canRight) || (e.deltaY < 0 && !this.canLeft)) return;
            e.preventDefault();
            this.$refs.track.scrollLeft += e.deltaY;
            this.update();
        },
    }));

    /** Daily trend chart (Social Media overview). */
    Alpine.data('trendChart', (cfg) => ({
        chart: null,
        destroyed: false,
        async init() {
            const { default: ApexCharts } = await import('apexcharts');
            if (this.destroyed) return;
            this.chart = new ApexCharts(this.$refs.canvas, trendOptions(cfg));
            this.chart.render();
            this.onTheme = () => this.chart?.updateOptions(chartTheme());
            window.addEventListener('theme-changed', this.onTheme);
        },
        destroy() {
            this.destroyed = true;
            window.removeEventListener('theme-changed', this.onTheme);
            this.chart?.destroy();
        },
    }));
});
