import { getAuthConfig, requireAuthOrRedirect, initWebSocket } from './shared/api.js';
import { formatMinutes, tzOffsetMinutes } from './shared/format.js';

export function analyticsPage() {
    const API_BASE = window.API_BASE;

    return {
        // ─── State ───────────────────────────────────────────
        loading: true,
        period: '30d',
        categories: [],
        data: {
            overview: { total_done: 0, productive_done: 0, unproductive_done: 0, total_minutes: 0, productive_minutes: 0, unproductive_minutes: 0, streak: 0, completion_rate: 0 },
            heatmap: {},
            categories: [],
            tags: [],
            live: { productive_minutes: 0, unproductive_minutes: 0, by_category: [] },
        },
        // Cached min/max for tag cloud sizing — recomputed when data.tags changes
        tagBounds: { min: 0, max: 0 },

        // ─── Shared helpers ───────────────────────────────────
        getAuthConfig,
        formatMinutes,

        // ─── Init ─────────────────────────────────────────────
        async init() {
            const token = requireAuthOrRedirect();
            if (!token) return;

            await this.loadCategories();
            await this.load();

            this.ws = initWebSocket(API_BASE, token, (payload) => {
                if (['create', 'update', 'delete'].includes(payload.action)) {
                    this.load();
                }
            });
        },

        async loadCategories() {
            try {
                const res = await axios.get(`${API_BASE}/categories`, this.getAuthConfig());
                this.categories = res.data;
            } catch (e) {
                console.error('Error loading categories', e);
            }
        },

        // ─── Data ─────────────────────────────────────────────
        async load() {
            this.loading = true;
            try {
                const res = await axios.get(`${API_BASE}/analytics`, {
                    ...this.getAuthConfig(),
                    params: { period: this.period, tz_offset: tzOffsetMinutes() },
                });
                this.data = res.data;
                this.updateTagBounds();
                this.$nextTick(() => {
                    this.renderHeatmap();
                    this.renderCategoryChart();
                });
            } catch (e) {
                console.error('Error loading analytics', e);
            } finally {
                this.loading = false;
            }
        },

        updateTagBounds() {
            const counts = this.data.tags.map(t => t.count);
            this.tagBounds = counts.length
                ? { min: Math.min(...counts), max: Math.max(...counts) }
                : { min: 0, max: 0 };
        },

        async setPeriod(p) {
            this.period = p;
            await this.load();
        },

        // ─── Heatmap ──────────────────────────────────────────
        renderHeatmap() {
            const canvas = document.getElementById('heatmap-canvas');
            if (!canvas) return;

            const heatmap = this.data.heatmap;
            const ctx = canvas.getContext('2d');
            let days = this.getHeatmapDays();
            const cellSize = 20;
            const cellGap = 4;
            const paddingLeft = 30; // for week marks
            const paddingTop = 20;  // for month marks

            // trim days to start from the week containing the first activity
            const heatmapKeys = Object.keys(heatmap).sort();
            if (heatmapKeys.length > 0) {
                const firstActivity = heatmapKeys[0];
                const idx = days.findIndex(d => d >= firstActivity);
                if (idx > 0) {
                    const weekStart = Math.floor(idx / 7) * 7;
                    days = days.slice(weekStart);
                }
            }

            const weekCount = Math.ceil(days.length / 7);
            const logicalWidth = paddingLeft + weekCount * (cellSize + cellGap);
            const logicalHeight = paddingTop + 7 * (cellSize + cellGap);
            const dpr = window.devicePixelRatio || 1;
            canvas.width = logicalWidth * dpr;
            canvas.height = logicalHeight * dpr;
            canvas.style.width = logicalWidth + 'px';
            canvas.style.height = logicalHeight + 'px';
            ctx.scale(dpr, dpr);

            const maxCount = Math.max(1, ...Object.values(heatmap));

            const accentRgb = [127, 180, 202]; // --accent
            const bg = '#2d2c3c';              // --surface-2
            const border = '#43436c';          // --border

            this.heatmapCells = [];

            // week marks
            const dayLabels = ['Mon', '', 'Wed', '', 'Fri', '', 'Sun'];
            ctx.fillStyle = '#717c7c';
            ctx.font = '13px monospace';
            dayLabels.forEach((label, i) => {
                if (label) {
                    ctx.fillText(label, 0, paddingTop + i * (cellSize + cellGap) + cellSize - 3);
                }
            });

            // month marks
            let lastMonth = null;
            let lastLabelX = -Infinity;
            ctx.fillStyle = '#717c7c';
            ctx.font = '13px monospace';
            ctx.textBaseline = 'top';
            days.forEach((day, i) => {
                const week = Math.floor(i / 7);
                const month = day.slice(5, 7);
                if (month !== lastMonth) {
                    lastMonth = month;
                    const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    const label = monthNames[parseInt(month) - 1];
                    // if month starts mid-week, shift label to next column so it aligns with cells fully in new month
                    const dayOfWeek = i % 7;
                    const labelWeek = dayOfWeek === 0 ? week : week + 1;
                    const x = paddingLeft + labelWeek * (cellSize + cellGap);
                    if (x - lastLabelX >= 32 && x < canvas.width) {
                        ctx.fillText(label, x, 4);
                        lastLabelX = x;
                    }
                }
            });
            ctx.textBaseline = 'alphabetic';

            // cells
            days.forEach((day, i) => {
                const week = Math.floor(i / 7);
                const weekDay = i % 7;
                const x = paddingLeft + week * (cellSize + cellGap);
                const y = paddingTop + weekDay * (cellSize + cellGap);
                const count = heatmap[day] || 0;
                const intensity = count / maxCount;

                // Store for tooltip
                this.heatmapCells.push({ x, y, width: cellSize, height: cellSize, day, count });

                if (count === 0) {
                    ctx.fillStyle = bg;
                    ctx.strokeStyle = border;
                    ctx.lineWidth = 0.5;
                    ctx.beginPath();
                    ctx.roundRect(x, y, cellSize, cellSize, 2);
                    ctx.fill();
                    ctx.stroke();
                } else {
                    const alpha = 0.25 + intensity * 0.75;
                    ctx.fillStyle = `rgba(${accentRgb[0]}, ${accentRgb[1]}, ${accentRgb[2]}, ${alpha})`;
                    ctx.beginPath();
                    ctx.roundRect(x, y, cellSize, cellSize, 2);
                    ctx.fill();
                }
            });
        },

        heatmapMouseMove(e) {
            const canvas = document.getElementById('heatmap-canvas');
            if (!canvas || !this.heatmapCells) return;

            const rect = canvas.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;
            const mouseY = e.clientY - rect.top;

            const cell = this.heatmapCells.find(c =>
                mouseX >= c.x && mouseX <= c.x + c.width &&
                mouseY >= c.y && mouseY <= c.y + c.height
            );

            const tooltip = document.getElementById('heatmap-tooltip');
            if (cell) {
                const date = new Date(cell.day);
                const formatted = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                const tasks = cell.count === 1 ? '1 activity' : `${cell.count} activities`;
                tooltip.textContent = `${formatted}: ${tasks}`;
                tooltip.style.display = 'block';
                tooltip.style.left = `${e.clientX + 10}px`;
                tooltip.style.top = `${e.clientY + 10}px`;
            } else {
                tooltip.style.display = 'none';
            }
        },

        heatmapMouseLeave() {
            const tooltip = document.getElementById('heatmap-tooltip');
            if (tooltip) tooltip.style.display = 'none';
        },

        tagMouseEnter(e, tag, count) {
            const tooltip = document.getElementById('heatmap-tooltip');
            if (!tooltip) return;
            const times = count === 1 ? '1 time' : `${count} times`;
            tooltip.textContent = `#${tag} — ${times}`;
            tooltip.style.display = 'block';
            tooltip.style.left = `${e.clientX + 10}px`;
            tooltip.style.top = `${e.clientY + 10}px`;
        },

        tagMouseMove(e) {
            const tooltip = document.getElementById('heatmap-tooltip');
            if (!tooltip || tooltip.style.display === 'none') return;
            tooltip.style.left = `${e.clientX + 10}px`;
            tooltip.style.top = `${e.clientY + 10}px`;
        },

        tagMouseLeave() {
            const tooltip = document.getElementById('heatmap-tooltip');
            if (tooltip) tooltip.style.display = 'none';
        },

        getHeatmapDays() {
            const days = [];
            const periodDays = this.period === '7d' ? 7 : this.period === '30d' ? 30 : this.period === '90d' ? 90 : 365;
            const today = new Date();

            // starting from monday
            const startDate = new Date(today);
            startDate.setDate(today.getDate() - periodDays);
            // revert to previous monday
            const dow = startDate.getDay();
            const diff = dow === 0 ? 6 : dow - 1;
            startDate.setDate(startDate.getDate() - diff);

            const current = new Date(startDate);
            while (current <= today) {
                const y = current.getFullYear();
                const m = String(current.getMonth() + 1).padStart(2, '0');
                const d = String(current.getDate()).padStart(2, '0');
                days.push(`${y}-${m}-${d}`);
                current.setDate(current.getDate() + 1);
            }
            return days;
        },

        // ─── Category chart (canvas bars) ─────────────────────
        renderCategoryChart() {
            const canvas = document.getElementById('category-canvas');
            if (!canvas || !this.data.categories.length) return;

            const ctx = canvas.getContext('2d');
            const categories = this.data.categories.slice(0, 8); // max 8
            const maxMinutes = Math.max(1, ...categories.map(c => c.minutes));

            const barHeight = 32;
            const barGap = 12;
            const labelWidth = 120;
            const valueWidth = 70;
            const padding = 10;

            const logicalWidth = canvas.parentElement.clientWidth || 400;
            const logicalHeight = categories.length * (barHeight + barGap) + padding * 2;
            const dpr = window.devicePixelRatio || 1;
            canvas.width = logicalWidth * dpr;
            canvas.height = logicalHeight * dpr;
            canvas.style.width = logicalWidth + 'px';
            canvas.style.height = logicalHeight + 'px';
            ctx.scale(dpr, dpr);

            ctx.clearRect(0, 0, logicalWidth, logicalHeight);

            const barMaxWidth = canvas.width - labelWidth - valueWidth - padding * 2;

            categories.forEach((cat, i) => {
                const y = padding + i * (barHeight + barGap);
                const barWidth = (cat.minutes / maxMinutes) * barMaxWidth;

                // mark
                ctx.fillStyle = '#717c7c';
                ctx.font = '16px monospace';
                ctx.textBaseline = 'middle';
                ctx.fillText(
                    cat.name.length > 12 ? cat.name.slice(0, 12) + '…' : cat.name,
                    padding,
                    y + barHeight / 2
                );

                // background bar
                ctx.fillStyle = '#2d2c3c';
                ctx.beginPath();
                ctx.roundRect(labelWidth, y, barMaxWidth, barHeight, 4);
                ctx.fill();

                // color bar
                ctx.fillStyle = cat.color;
                if (barWidth > 0) {
                    ctx.beginPath();
                    ctx.roundRect(labelWidth, y, barWidth, barHeight, 4);
                    ctx.fill();
                }

                // value
                ctx.fillStyle = '#c8c093';
                ctx.font = '16px monospace';
                ctx.textBaseline = 'middle';
                const timeStr = this.formatMinutes(cat.minutes);
                ctx.fillText(timeStr, labelWidth + barMaxWidth + padding, y + barHeight / 2);
            });
        },

        // ─── Tag cloud (CSS-based) ─────────────────────────────
        tagRatio(count) {
            const { min, max } = this.tagBounds;
            return max === min ? 1 : (count - min) / (max - min);
        },

        getTagSize(count) {
            // 0.75rem — 1.5rem
            return 0.75 + this.tagRatio(count) * 0.75;
        },

        getTagOpacity(count) {
            return 0.5 + this.tagRatio(count) * 0.5;
        },

        // ─── Utilities ────────────────────────────────────────
        hasData() {
            return this.data.overview.total_done > 0;
        },

        liveMaxMinutes() {
            if (!this.data.live.by_category.length) return 1;
            return Math.max(...this.data.live.by_category.map(c => c.productive + c.unproductive));
        },

        getCategoryName(categoryId) {
            if (!categoryId) return 'Uncategorized';
            const cat = this.categories.find(c => c.id == categoryId);
            return cat ? cat.name : `Category ${categoryId}`;
        },
    };
}
