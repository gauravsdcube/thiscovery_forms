humhub.module('thiscoveryForms.dashboard', function (module, require, $) {
    var charts = [];

    var palette = [
        '#2563eb', '#0ea5e9', '#14b8a6', '#22c55e', '#eab308',
        '#f97316', '#ef4444', '#a855f7', '#6366f1', '#64748b'
    ];

    var colors = function (n) {
        var out = [];
        for (var i = 0; i < n; i++) {
            out.push(palette[i % palette.length]);
        }
        return out;
    };

    var labelSubmissions = function () {
        return (module.config && module.config.submissions) ? module.config.submissions : 'Submissions';
    };

    var readPayload = function (el, configKey) {
        if (configKey && module.config && module.config[configKey] !== undefined) {
            return module.config[configKey];
        }
        var raw = el.getAttribute('data-cf-payload');
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    };

    var destroyAll = function () {
        charts.forEach(function (c) {
            try { c.destroy(); } catch (e) {}
        });
        charts = [];
    };

    var renderTimeline = function (canvas, payload) {
        if (!canvas || typeof window.Chart === 'undefined' || !payload) {
            return;
        }
        charts.push(new window.Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: payload.labels || [],
                datasets: [{
                    label: labelSubmissions(),
                    data: payload.data || [],
                    backgroundColor: 'rgba(37, 99, 235, 0.75)',
                    borderRadius: 4,
                    maxBarThickness: 28
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        ticks: {
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 7,
                            callback: function (value) {
                                var label = this.getLabelForValue(value);
                                if (!label || typeof label !== 'string') {
                                    return label;
                                }
                                return label.length > 5 ? label.slice(5) : label;
                            }
                        },
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: 'rgba(148, 163, 184, 0.25)' }
                    }
                }
            }
        }));
    };

    var renderStructured = function (canvas, payload) {
        if (!canvas || typeof window.Chart === 'undefined' || !payload) {
            return;
        }

        if (payload.chartType === 'ranking' && payload.datasets) {
            charts.push(new window.Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: payload.labels || [],
                    datasets: (payload.datasets || []).map(function (dataset, index) {
                        return {
                            label: dataset.label || '',
                            data: dataset.data || [],
                            backgroundColor: palette[index % palette.length],
                            borderRadius: 4,
                            maxBarThickness: 36
                        };
                    })
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: { boxWidth: 12, font: { size: 11 } }
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                            grid: { display: false }
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    }
                }
            }));
            return;
        }

        var type = payload.chartType === 'bar' ? 'bar' : 'doughnut';
        charts.push(new window.Chart(canvas.getContext('2d'), {
            type: type,
            data: {
                labels: payload.labels || [],
                datasets: [{
                    data: payload.data || [],
                    backgroundColor: colors((payload.labels || []).length),
                    borderWidth: type === 'doughnut' ? 2 : 0,
                    borderColor: '#fff',
                    borderRadius: type === 'bar' ? 4 : 0,
                    maxBarThickness: 36
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: type === 'doughnut',
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    }
                },
                scales: type === 'bar' ? {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                } : undefined
            }
        }));
    };

    var renderOverviewBars = function (canvas, payload) {
        if (!canvas || typeof window.Chart === 'undefined' || !payload || !payload.length) {
            return;
        }
        charts.push(new window.Chart(canvas.getContext('2d'), {
            type: 'bar',
            data: {
                labels: payload.map(function (r) { return r.title; }),
                datasets: [{
                    label: labelSubmissions(),
                    data: payload.map(function (r) { return r.answers; }),
                    backgroundColor: 'rgba(14, 165, 233, 0.8)',
                    borderRadius: 4,
                    maxBarThickness: 32
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 } },
                    y: { grid: { display: false } }
                }
            }
        }));
    };

    module.init = function (root) {
        var $root = $(root || document);
        if (!$root.length) {
            return;
        }

        if (typeof window.Chart === 'undefined') {
            if (module.logError) {
                module.logError('Chart.js is not loaded');
            }
            return;
        }

        destroyAll();

        $root.find('[data-cf-chart="timeline"]').each(function () {
            renderTimeline(this, readPayload(this, 'timeline'));
        });

        var structured = (module.config && module.config.structured) ? module.config.structured : [];
        $root.find('[data-cf-chart="structured"]').each(function (idx) {
            var payload = structured[idx] || readPayload(this, null);
            renderStructured(this, payload);
        });

        $root.find('[data-cf-chart="overview"]').each(function () {
            renderOverviewBars(this, readPayload(this, 'overview'));
        });
    };

    module.export = {
        init: module.init
    };
});
