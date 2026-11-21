
define([
    'jquery',
    'chartjs'
], function ($, Chart) {
    'use strict';

    return function (config, element) {

        const $root = $(element);

        // DOM elements
        const $amount = $root.find('#converter-amount');
        const $from = $root.find('#from-currency');
        const $to = $root.find('#to-currency');
        const $swap = $root.find('#swap-currencies');

        const $converted = $root.find('#converted-amount');
        const $exchangeRate = $root.find('#exchange-rate');

        const $loading = $root.find('#converter-loading');
        const $error = $root.find('#converter-error');

        const $historyRange = $root.find('#history-range');
        const $historyLoading = $root.find('#history-loading');
        const $historyError = $root.find('#history-error');

        const canvas = $root.find('#exchange-history-chart')[0];

        let currentRate = null;
        let historyChart = null;

        let rateRequest = null;
        let historyRequest = null;

        let rateRequestId = 0;
        let historyRequestId = 0;

        /**
         * Display an error message.
         */
        function showError(message) {
            $error.text(message).prop('hidden', false);
        }

        /**
         * Hide an error message.
         */
        function hideError() {
            $error.empty().prop('hidden', true);
        }

        /**
         * Load currencies from Magento AJAX controller.
         */
        function loadCurrencies() {
            $loading.prop('hidden', false);

            $.ajax({
                url: config.currenciesUrl,
                type: 'GET',
                dataType: 'json'
            }).done(function (response) {

                if (!response.success || !Array.isArray(response.data)) {
                    showError('Unable to load supported currencies.');
                    return;
                }

                populateCurrencies(response.data);

            }).fail(function () {

                showError('Unable to connect to the currency service.');

            }).always(function () {

                $loading.prop('hidden', true);

            });
        }

        /**
         * Populate currency dropdowns.
         */
        function populateCurrencies(currencies) {

            $from.empty();
            $to.empty();

            currencies.forEach(function (currency) {

                const code = currency.iso_code;

                if (!code) {
                    return;
                }

                const name = currency.name || code;
                const symbol = currency.symbol || '';
                const label = code + ' - ' + name + ' (' + symbol + ')';

                $from.append(
                    $('<option>', {
                        value: code,
                        text: label
                    })
                );

                $to.append(
                    $('<option>', {
                        value: code,
                        text: label
                    })
                );
            });

            if ($from.find('option[value="USD"]').length) {
                $from.val('USD');
            }

            if ($to.find('option[value="INR"]').length) {
                $to.val('INR');
            }

            if ($from.val() === $to.val()) {
                const alternative = $to.find('option').filter(function () {
                    return this.value !== $from.val();
                }).first();

                if (alternative.length) {
                    $to.val(alternative.val());
                }
            }

            loadExchangeRate();
        }

        /**
         * Validate selected currency codes.
         */
        function getCurrencyPair() {

            const from = $from.val();
            const to = $to.val();

            if (!from || !to) {
                throw new Error('Please select both currencies.');
            }

            return {
                from: from,
                to: to
            };
        }

        /**
         * Get and display current exchange rate.
         */
        function loadExchangeRate() {

            let pair;

            try {
                pair = getCurrencyPair();
            } catch (error) {
                showError(error.message);
                return;
            }

            const requestId = ++rateRequestId;

            if (rateRequest) {
                rateRequest.abort();
            }

            if (historyRequest) {
                historyRequest.abort();
            }

            currentRate = null;

            $converted.text('--');
            $exchangeRate.text('Loading exchange rate...');

            hideError();
            $loading.prop('hidden', false);

            if (pair.from === pair.to) {

                currentRate = 1;

                updateConversion();

                $exchangeRate.text(
                    '1 ' + pair.from + ' = 1 ' + pair.to
                );

                $loading.prop('hidden', true);

                loadHistoricalRates();

                return;
            }

            rateRequest = $.ajax({
                url: config.rateUrl,
                type: 'GET',
                dataType: 'json',
                data: {
                    from: pair.from,
                    to: pair.to
                }
            });

            rateRequest.done(function (response) {

                if (requestId !== rateRequestId) {
                    return;
                }

                if (
                    !response.success ||
                    !response.data ||
                    !Number.isFinite(Number(response.data.rate)) ||
                    Number(response.data.rate) <= 0
                ) {
                    showError('Invalid exchange rate response.');
                    return;
                }

                currentRate = Number(response.data.rate);

                updateConversion();

                $exchangeRate.text(
                    '1 ' + pair.from +
                    ' = ' + formatNumber(currentRate) +
                    ' ' + pair.to
                );

            }).fail(function (xhr, status) {

                if (status === 'abort' || requestId !== rateRequestId) {
                    return;
                }

                showError('Unable to retrieve the current exchange rate.');

                $converted.text('--');
                $exchangeRate.text('Exchange rate unavailable.');

            }).always(function () {

                if (requestId === rateRequestId) {
                    $loading.prop('hidden', true);
                }

            });

            loadHistoricalRates();
        }

        /**
         * Format numbers for display.
         */
        function formatNumber(value) {

            return new Intl.NumberFormat(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 6
            }).format(value);
        }

        /**
         * Calculate converted amount.
         */
        function updateConversion() {

            if (currentRate === null) {
                return;
            }

            const amount = Number($amount.val());

            if (!Number.isFinite(amount) || amount < 0) {
                $converted.text('--');
                return;
            }

            const convertedAmount = amount * currentRate;

            $converted.text(
                formatNumber(convertedAmount) + ' ' + $to.val()
            );
        }

        /**
         * Generate date in YYYY-MM-DD format.
         */
        function formatDate(date) {

            return [
                date.getUTCFullYear(),
                String(date.getUTCMonth() + 1).padStart(2, '0'),
                String(date.getUTCDate()).padStart(2, '0')
            ].join('-');
        }

        /**
         * Calculate historical date range.
         */
        function getDateRange(days) {

            const end = new Date();

            const start = new Date(end);

            start.setUTCDate(start.getUTCDate() - days);

            return {
                startDate: formatDate(start),
                endDate: formatDate(end)
            };
        }

        /**
         * Fetch historical exchange rates.
         */
        function loadHistoricalRates() {

            let pair;

            try {
                pair = getCurrencyPair();
            } catch (error) {
                showHistoryError(error.message);
                return;
            }

            const requestId = ++historyRequestId;

            if (historyRequest) {
                historyRequest.abort();
            }

            const days = Number($historyRange.val());

            if (![7, 30, 90, 365].includes(days)) {
                showHistoryError('Invalid historical date range.');
                return;
            }

            const dates = getDateRange(days);

            $historyError.empty().prop('hidden', true);
            $historyLoading.prop('hidden', false);

            if (pair.from === pair.to) {

                renderChart(
                    [{
                        date: dates.endDate,
                        rate: 1
                    }],
                    pair
                );

                $historyLoading.prop('hidden', true);

                return;
            }

            historyRequest = $.ajax({
                url: config.historyUrl,
                type: 'GET',
                dataType: 'json',
                data: {
                    from: pair.from,
                    to: pair.to,
                    start_date: dates.startDate,
                    end_date: dates.endDate
                }
            });

            historyRequest.done(function (response) {

                if (requestId !== historyRequestId) {
                    return;
                }

                if (
                    !response.success ||
                    !Array.isArray(response.data)
                ) {
                    showHistoryError(
                        'Unable to load historical exchange rates.'
                    );
                    return;
                }

                renderChart(response.data, pair);

            }).fail(function (xhr, status) {

                if (status === 'abort' || requestId !== historyRequestId) {
                    return;
                }

                showHistoryError(
                    'Unable to retrieve historical exchange rates.'
                );

            }).always(function () {

                if (requestId === historyRequestId) {
                    $historyLoading.prop('hidden', true);
                }

            });
        }

        /**
         * Display historical chart errors.
         */
        function showHistoryError(message) {

            $historyError.text(message).prop('hidden', false);
        }

        /**
         * Render Chart.js line chart.
         */
        function renderChart(history, pair) {

            const observations = history
                .filter(function (item) {
                    return (
                        item &&
                        typeof item.date === 'string' &&
                        Number.isFinite(Number(item.rate))
                    );
                })
                .sort(function (a, b) {
                    return a.date.localeCompare(b.date);
                });

            if (observations.length === 0) {

                if (historyChart) {
                    historyChart.destroy();
                    historyChart = null;
                }

                showHistoryError(
                    'No historical exchange-rate data is available for this period.'
                );

                return;
            }

            const labels = observations.map(function (item) {
                return item.date;
            });

            const values = observations.map(function (item) {
                return Number(item.rate);
            });

            if (historyChart) {
                historyChart.destroy();
            }

            historyChart = new Chart(canvas, {
                type: 'line',

                data: {
                    labels: labels,

                    datasets: [{
                        label: pair.from + ' to ' + pair.to,

                        data: values,

                        borderColor: '#16a36a',

                        backgroundColor: 'rgba(22, 163, 106, 0.10)',

                        borderWidth: 2,

                        pointRadius: 2,

                        pointHoverRadius: 5,

                        fill: true,

                        tension: 0.3
                    }]
                },

                options: {
                    responsive: true,

                    maintainAspectRatio: false,

                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },

                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },

                        tooltip: {
                            callbacks: {
                                label: function (context) {

                                    return (
                                        ' ' +
                                        formatNumber(context.parsed.y) +
                                        ' ' +
                                        pair.to
                                    );
                                }
                            }
                        }
                    },

                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        },

                        y: {
                            title: {
                                display: true,
                                text: 'Exchange Rate'
                            },

                            beginAtZero: false
                        }
                    }
                }
            });
        }

        /**
         * Swap selected currencies.
         */
        $swap.on('click', function () {

            const from = $from.val();
            const to = $to.val();

            $from.val(to);
            $to.val(from);

            loadExchangeRate();
        });

        /**
         * Refresh rate when currency changes.
         */
        $from.add($to).on('change', function () {
            loadExchangeRate();
        });

        /**
         * Recalculate amount without another API call.
         */
        $amount.on('input', function () {
            updateConversion();
        });

        /**
         * Change historical chart period.
         */
        $historyRange.on('change', function () {
            loadHistoricalRates();
        });

        /**
         * Initialize converter.
         */
        loadCurrencies();

    };
});
