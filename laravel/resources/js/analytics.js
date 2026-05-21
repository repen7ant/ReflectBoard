export function analyticsPage() {
    const API_BASE = window.API_BASE;

    return {
        // ─── State ───────────────────────────────────────────
        loading: true,
        period: '30d',
        data: {
            overview: { total_done: 0, total_minutes: 0, streak: 0, completion_rate: 0 },
            heatmap: {},
            categories: [],
            tags: [],
            live: { total_minutes: 0, by_category: [] },
        },

        // ─── Auth ─────────────────────────────────────────────
        getAuthConfig() {
            const token = document.querySelector('meta[name="api-token"]')?.content;
            return { headers: { Authorization: `Bearer ${token}` } };
        },

        // ─── Init ─────────────────────────────────────────────
        async init() {
            const token = document.querySelector('meta[name="api-token"]')?.content;
            if (!token) {
                window.location.href = '/login';
                return;
            }
            await this.load();
        },

        // ─── Data ─────────────────────────────────────────────
        async load() {
            this.loading = true;
            try {
                const res = await axios.get(`${API_BASE}/analytics?period=${this.period}`, this.getAuthConfig());
                this.data = res.data;
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
            const days = this.getHeatmapDays();
            const cellSize = 14;
            const cellGap = 3;
            const weekCount = Math.ceil(days.length / 7);
            const paddingLeft = 30; // for week marks
            const paddingTop = 20;  // for month marks

            canvas.width = paddingLeft + weekCount * (cellSize + cellGap);
            canvas.height = paddingTop + 7 * (cellSize + cellGap);

            const maxCount = Math.max(1, ...Object.values(heatmap));

            const accentRgb = [127, 180, 202]; // --accent
            const bg = '#2d2c3c';              // --surface-2
            const border = '#43436c';          // --border

            // week marks
            const dayLabels = ['Mon', '', 'Wed', '', 'Fri', '', 'Sun'];
            ctx.fillStyle = '#717c7c';
            ctx.font = '9px monospace';
            dayLabels.forEach((label, i) => {
                if (label) {
                    ctx.fillText(label, 0, paddingTop + i * (cellSize + cellGap) + cellSize - 3);
                }
            });

            // month marks
            let lastMonth = null;
            days.forEach((day, i) => {
                const week = Math.floor(i / 7);
                const month = day.slice(5, 7);
                if (month !== lastMonth) {
                    lastMonth = month;
                    const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    const label = monthNames[parseInt(month) - 1];
                    ctx.fillStyle = '#717c7c';
                    ctx.font = '9px monospace';
                    ctx.fillText(label, paddingLeft + week * (cellSize + cellGap), 10);
                }
            });

            // cells
            days.forEach((day, i) => {
                const week = Math.floor(i / 7);
                const weekDay = i % 7;
                const x = paddingLeft + week * (cellSize + cellGap);
                const y = paddingTop + weekDay * (cellSize + cellGap);
                const count = heatmap[day] || 0;
                const intensity = count / maxCount;

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
                days.push(current.toISOString().split('T')[0]);
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

            const barHeight = 28;
            const barGap = 10;
            const labelWidth = 100;
            const valueWidth = 60;
            const padding = 10;

            canvas.width = canvas.parentElement.clientWidth || 400;
            canvas.height = categories.length * (barHeight + barGap) + padding * 2;

            ctx.clearRect(0, 0, canvas.width, canvas.height);

            const barMaxWidth = canvas.width - labelWidth - valueWidth - padding * 2;

            categories.forEach((cat, i) => {
                const y = padding + i * (barHeight + barGap);
                const barWidth = (cat.minutes / maxMinutes) * barMaxWidth;

                // Метка
                ctx.fillStyle = '#717c7c';
                ctx.font = '11px monospace';
                ctx.textBaseline = 'middle';
                ctx.fillText(
                    cat.name.length > 10 ? cat.name.slice(0, 10) + '…' : cat.name,
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
                ctx.font = '10px monospace';
                ctx.textBaseline = 'middle';
                const timeStr = this.formatMinutes(cat.minutes);
                ctx.fillText(timeStr, labelWidth + barMaxWidth + padding, y + barHeight / 2);
            });
        },

        // ─── Tag cloud (CSS-based) ─────────────────────────────
        getTagSize(count) {
            const max = Math.max(...this.data.tags.map(t => t.count));
            const min = Math.min(...this.data.tags.map(t => t.count));
            if (max === min) return 1;
            const ratio = (count - min) / (max - min);
            // 0.75rem — 1.5rem
            return 0.75 + ratio * 0.75;
        },

        getTagOpacity(count) {
            const max = Math.max(...this.data.tags.map(t => t.count));
            const min = Math.min(...this.data.tags.map(t => t.count));
            if (max === min) return 1;
            const ratio = (count - min) / (max - min);
            return 0.5 + ratio * 0.5;
        },

        // ─── Utilities ────────────────────────────────────────
        formatMinutes(minutes) {
            if (!minutes) return '0m';
            const h = Math.floor(minutes / 60);
            const m = minutes % 60;
            if (h > 0 && m > 0) return `${h}h ${m}m`;
            if (h > 0) return `${h}h`;
            return `${m}m`;
        },

        hasData() {
            return this.data.overview.total_done > 0;
        },

        liveMaxMinutes() {
            if (!this.data.live.by_category.length) return 1;
            return Math.max(...this.data.live.by_category.map(c => c.minutes));
        },
    };
}
