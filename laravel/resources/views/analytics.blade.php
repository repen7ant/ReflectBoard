<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="api-token" content="{{ auth()->check() ? auth()->user()->api_token : '' }}">
    <title>Analytics — ReflectBoard</title>
    <link rel="icon" href="/icon.svg" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="analyticsPage()" x-init="init()">

    <!-- Topbar -->
    <nav class="topbar">
        <div class="topbar-logo">
            <a href="/">
                <img src="/icon.svg" alt="ReflectBoard" class="logo-icon">
            </a>
        </div>
        <div class="nav-links">
            <a href="/board" class="nav-link">Board</a>
            <a href="/done" class="nav-link">Done</a>
            <a href="/analytics" class="nav-link active">Analytics</a>
        </div>
    </nav>

    <!-- Page -->
    <div class="analytics-wrapper">

        <!-- Period switcher -->
        <div class="analytics-periods">
            <button class="period-btn" :class="{ 'active': period === '7d' }"  @click="setPeriod('7d')">7 days</button>
            <button class="period-btn" :class="{ 'active': period === '30d' }" @click="setPeriod('30d')">30 days</button>
            <button class="period-btn" :class="{ 'active': period === '90d' }" @click="setPeriod('90d')">90 days</button>
            <button class="period-btn" :class="{ 'active': period === 'all' }" @click="setPeriod('all')">All time</button>
        </div>

        <!-- Loading -->
        <template x-if="loading">
            <div class="loading-center" style="padding: 4rem;">
                <div class="spinner"></div>
            </div>
        </template>

        <template x-if="!loading">
            <div>

                <!-- Empty state -->
                <template x-if="!hasData()">
                    <div class="analytics-empty">
                        <div class="analytics-empty-icon">◎</div>
                        <div class="analytics-empty-text">Complete your first task to see stats here</div>
                    </div>
                </template>

                <template x-if="hasData()">
                    <div class="analytics-content">

                        <!-- Block 1: Overview cards -->
                        <div class="overview-grid">
                            <div class="overview-card">
                                <div class="overview-value" x-text="data.overview.total_done"></div>
                                <div class="overview-label">Tasks completed</div>
                            </div>
                            <div class="overview-card">
                                <div class="overview-value" x-text="formatMinutes(data.overview.total_minutes)"></div>
                                <div class="overview-label">Total time</div>
                            </div>
                            <div class="overview-card">
                                <div class="overview-value">
                                    <span x-text="data.overview.streak"></span>
                                    <span class="overview-unit">d</span>
                                </div>
                                <div class="overview-label">Current streak</div>
                            </div>
                            <div class="overview-card">
                                <div class="overview-value">
                                    <span x-text="data.overview.completion_rate"></span>
                                    <span class="overview-unit">%</span>
                                </div>
                                <div class="overview-label">Completion rate</div>
                            </div>
                        </div>

                        <!-- Block 2: Heatmap -->
                        <div class="analytics-block">
                            <div class="analytics-block-title">Activity</div>
                            <div class="heatmap-scroll">
                                <canvas id="heatmap-canvas" @mousemove="heatmapMouseMove($event)" @mouseleave="heatmapMouseLeave()"></canvas>
                            </div>
                        </div>

                        <!-- Block 3: Categories + Tags -->
                        <div class="analytics-two-col">

                            <!-- Categories -->
                            <div class="analytics-block">
                                <div class="analytics-block-title">Time by category</div>
                                <template x-if="data.categories.length === 0">
                                    <div class="analytics-block-empty">No categories yet</div>
                                </template>
                                <template x-if="data.categories.length > 0">
                                    <canvas id="category-canvas" style="width: 100%;"></canvas>
                                </template>
                            </div>

                            <!-- Tags -->
                            <div class="analytics-block">
                                <div class="analytics-block-title">Tag cloud</div>
                                <template x-if="data.tags.length === 0">
                                    <div class="analytics-block-empty">No tags with 3+ uses yet</div>
                                </template>
                                <template x-if="data.tags.length > 0">
                                    <div class="tag-cloud">
                                        <template x-for="t in data.tags" :key="t.tag">
                                            <span
                                                class="tag-cloud-item"
                                                :style="`font-size: ${getTagSize(t.count)}rem; opacity: ${getTagOpacity(t.count)};`"
                                                :title="`${t.tag} — ${t.count} times`"
                                            >#<span x-text="t.tag"></span></span>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Block 4: Live (24h) -->
                        <div class="analytics-block">
                            <div class="analytics-block-title">
                                Live — last 24 hours
                                <span class="live-dot"></span>
                            </div>

                            <div class="live-total">
                                <span class="overview-value" x-text="formatMinutes(data.live.total_minutes)"></span>
                                <span class="overview-label" style="margin-left: 0.5rem;">productive time</span>
                            </div>

                            <template x-if="data.live.by_category.length === 0">
                                <div class="analytics-block-empty">No activity in the last 24 hours</div>
                            </template>

                            <template x-if="data.live.by_category.length > 0">
                                <div class="live-categories">
                                    <template x-for="cat in data.live.by_category" :key="cat.category_id">
                                        <div class="live-category-row">
                                            <div class="live-category-label" x-text="getCategoryName(cat.category_id)"></div>
                                            <div class="live-bar-wrap">
                                                <div
                                                    class="live-bar-fill"
                                                    :style="`width: ${Math.round(cat.minutes / liveMaxMinutes() * 100)}%`"
                                                ></div>
                                            </div>
                                            <div class="live-category-value" x-text="formatMinutes(cat.minutes)"></div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                    </div>
                </template>

            </div>
        </template>

    </div>

    <!-- Heatmap tooltip -->
    <div id="heatmap-tooltip" class="heatmap-tooltip"></div>

    <script>
        window.API_BASE = '{{ config("services.api_base.url") }}';
    </script>

    @include('components.fab')
</body>
</html>
