var allocationData;
var sectorData;
var symbolDataHTML;
var symbolDetailHTML;

// websocket variables
var webSocketPreviousSymbol = '';
var quoteData;

// iex parameters
const iexURL = 'https://cloud.iexapis.com/stable/';
const iexPK = 'pk_5e475fb86046444a94016bf6270a7434';

const url = 'https://ws-api.iextrading.com/1.0/last';
const socket = io.connect(url);

numeral.register('locale', 'us', {
    delimiters: {
        thousands: ',',
        decimal: '.',
    },
    abbreviations: {
        thousand: 'k',
        million: 'M',
        billion: 'B',
        trillion: 'T',
    },
    currency: {
        symbol: '$',
    },
});
numeral.locale('us'); //init local numeral configuration

Highcharts.setOptions({
    lang: {
        thousandsSep: ',',
    },
});

$(document).ready(function () {
    var intraDayTimer = 0;
    displayIntraday(function (timer) {
        // load intraday page, graph and table
        intraDayTimer = timer;
    });

    $('a[href="#"]').click(function (event) {
        event.preventDefault();
        socket.emit('unsubscribe', webSocketPreviousSymbol); // unsubscribe from any websocket
        $('.navbar-nav li').removeClass('active'); // remove active class from every menu item

        switch ($(this).attr('id')) {
            case 'heatMap':
                $('.navbar-nav li#heatMap').addClass('active');
                displayIntraday(function (timer) {
                    intraDayTimer = timer;
                });
                break;

            case 'sectorAllocation':
                $('.navbar-nav li#sectorAllocation').addClass('active');
                clearInterval(intraDayTimer);
                changeFavIcon(true, true, true);
                displaySectorAllocation();
                updateAllocationDetails();
                break;

            case 'tradeHistory':
                $('.navbar-nav li#tradeHistory').addClass('active');
                clearInterval(intraDayTimer);
                changeFavIcon(true, true, true);
                displayTradeHistory();
                break;

            case 'earningsToday':
                $('.navbar-nav li#earningsToday').addClass('active');
                clearInterval(intraDayTimer);
                changeFavIcon(true, true, true);
                displayEarnings();
                break;

            case 'sectorTimeframe':
                $('.navbar-nav li#charts').addClass('active');
                clearInterval(intraDayTimer);
                changeFavIcon(true, true, true);
                displaySectorTimeframeCharts();
                break;

            case 'aboveBelow':
                $('.navbar-nav li#charts').addClass('active');
                clearInterval(intraDayTimer);
                changeFavIcon(true, true, true);
                displayAboveBelow();
                break;

            case 'technicalCharts':
                $('.navbar-nav li#charts').addClass('active');
                clearInterval(intraDayTimer);
                changeFavIcon(true, true, true);
                displayTechnicalCharts();
                break;

            case 'sp500':
                $('.navbar-nav li#rawData').addClass('active');
                clearInterval(intraDayTimer);
                changeFavIcon(true, true, true);
                displaySP500();
                break;

            case 'basket':
                $('.navbar-nav li#rawData').addClass('active');
                clearInterval(intraDayTimer);
                changeFavIcon(true, true, true);
                displayBasket();
                break;

            case 'performanceData':
                $('.navbar-nav li#rawData').addClass('active');
                clearInterval(intraDayTimer);
                changeFavIcon(true, true, true);
                displayPerformanceData();
                break;
        }
    });
});

// intraday heatmap chart and table

// load intraday page, graph and table
function displayIntraday(callback) {
    $('#realtime').load('./pages/heatMap.html', function () {
        // load main page template
        $('#portfolio').load('./damwidiMain.php?mode=buildPortfolioTable', () => {
            // build out the portfolio table
            heatMapChart = newHeatMapChart(); // create new chart
            updateIntraday(heatMapChart); // update heat map chart with latest data
        });

        // var intraDayTimer = setInterval(function(){                             // refresh data every 60 sec
        //     updateIntraday(heatMapChart);
        // },60000);
        // callback(intraDayTimer);
    });
}

// create new heat map bar chart
function newHeatMapChart() {
    var options = {
        scales: {
            xAxes: [
                {
                    categoryPercentage: 1.0,
                    barPercentage: 0.4,
                    ticks: {
                        fontSize: 10,
                    },
                },
            ],
            yAxes: [
                {
                    ticks: {
                        beginAtZero: true,
                        maxTicksLimit: 5,
                        fontSize: 10,
                        callback: function (value) {
                            return value.toFixed(1) + '%';
                        },
                    },
                },
            ],
        },
        title: {
            position: 'top',
            display: true,
            text: '',
        },
        legend: {
            display: false,
        },
        tooltips: {
            titleFontSize: 0,
            titleMarginBottom: 0,
            titleFontStyle: 'normal',
            backgroundColor: 'rgba(0,0,0,0)',
            titleFontColor: '#000',
            bodyFontColor: '#000',
            displayColors: false,
            callbacks: {
                label: function (tooltipItem) {
                    return tooltipItem.yLabel.toFixed(2) + '%';
                },
            },
        },
        animation: {
            duration: 0,
            easing: 'easeOutQuart',
        },
        annotation: {
            annotations: [
                {
                    id: 'vline',
                    type: 'line',
                    mode: 'vertical',
                    scaleID: 'x-axis-0',
                    value: 'SPY',
                    borderColor: 'rgba(0, 0, 0, 0.5)',
                    borderWidth: 3,
                    borderDash: [2, 2],
                },
            ],
        },
    };

    return (heatMapChart = new Chart('chart0', {
        type: 'bar',
        options: options,
    }));
}

function updateIntraday(heatMapChart, callback) {
    loadIntraDayData(function (heatMapData) {
        // load intraday data
        niceTime('Data complete: '); // display time
        $('#intraDayTitle').text(formatTime(heatMapData['time'])); // update graph title
        heatMapChart.data = heatMapData['graphHeatMap']; // load data into graph
        heatMapChart.update(); // redraw graph
        updatePortfolioTable(heatMapData['portfolioTable']); // update portfolio table

        profit = heatMapData.intraDay.DAM.gain >= 0;
        ahead = heatMapData.intraDay.DAM.gain >= heatMapData.intraDay.SPY.gain;
        changeFavIcon(profit, ahead, false);
    });
}

// update portfolio with realtime data
function updatePortfolioTable(data) {
    // console.log(data);

    Object.keys(data).forEach(function (symbol) {
        $('#last' + symbol).text(data[symbol]['last']);
        $('#value' + symbol).text(data[symbol]['value']);

        if (symbol != 'DAM') {
            $('#change' + symbol).text(data[symbol]['changePercent'] + '% / ' + data[symbol]['change']);
        } else {
            $('#change' + symbol).text(data[symbol]['changePercent'] + '%');
        }

        $('#valueChange' + symbol).text(data[symbol]['valueChange']);

        // format cells based on change (up, down or flat)
        $('#change' + symbol).removeClass();
        $('#change' + symbol).addClass('text-center');
        $('#valueChange' + symbol).removeClass();
        $('#valueChange' + symbol).addClass('text-right');
        switch (data[symbol]['tick']) {
            case 'UP':
                $('#change' + symbol).addClass('table-success');
                $('#change' + symbol).css('color', 'green');
                $('#valueChange' + symbol).addClass('table-success');
                $('#valueChange' + symbol).css('color', 'green');
                break;
            case 'DOWN':
                $('#change' + symbol).addClass('table-danger');
                $('#change' + symbol).css('color', 'red');
                $('#valueChange' + symbol).addClass('table-danger');
                $('#valueChange' + symbol).css('color', 'red');
                break;
            case 'ZERO':
                $('#change' + symbol).addClass('table-default');
                $('#change' + symbol).css('color', 'black');
                $('#valueChange' + symbol).addClass('table-default');
                $('#valueChange' + symbol).css('color', 'black');
                break;
        }
    });
}

// sector allocation page

// load and display page template
function displaySectorAllocation() {
    $('#realtime').load('./pages/sectorAllocation.html', function () {
        $('#allocation').load('./damwidiMain.php?mode=buildAllocationTable', function () {
            // complete build out of allocation table
            loadIntraDayData(function (heatMapData) {
                $('#intraDayTitle').html(formatTime(heatMapData['time']));
                allocationData = heatMapData['allocationTable'];
                sectorData = parseSectorData(allocationData);
                updateAllocationTable(allocationData);
            });
        });
    });
}

// add data to allocation table
function updateAllocationTable(data, mode) {
    if (mode === undefined) mode = 'relative';
    Object.keys(data).forEach(function (symbol) {
        $('#value' + symbol).text(data[symbol]['currentValue']);
        $('#change' + symbol).text(data[symbol]['change']);
        $('#allocation' + symbol).text(data[symbol]['allocation']);

        if (mode == 'relative') {
            $('#weight' + symbol).text(data[symbol]['weightPercent']);
            $('#implied' + symbol).text(data[symbol]['impliedPercent']);
            $('#impliedOverUnder' + symbol).text(data[symbol]['impliedOverUnderPercent']);
        } else {
            $('#weight' + symbol).text(data[symbol]['weight']);
            $('#implied' + symbol).text(data[symbol]['implied']);
            $('#impliedOverUnder' + symbol).text(data[symbol]['impliedOverUnder']);
        }

        // format change text color based on value
        $('#change' + symbol).css('color', changeColor(numeral(data[symbol]['change']).value()));
    });
}

function updateAllocationDetails() {
    loadDateDetails(function (dateData) {
        $.each(dateData, function (key) {
            $('#' + key).text(dateData[key]);
        });
    });
}

// sector vs timeframe page

// load and display page template
function displaySectorTimeframeCharts() {
    $('#realtime').load('./pages/sectorTimeframeCharts.html', function () {
        buildSectorTimeframeCharts();
    });
}

// build bar charts to compare sector returns vs timeframe
function buildSectorTimeframeCharts() {
    var periods = ['1wk', '2wk', '4wk', '1qtr', 'ytd', '1yr'];

    $(periods).each(function (i, val) {
        // var ctx = $("#chart"+i);
        $.ajax({
            type: 'POST',
            url: './damwidiMain.php?mode=returnSectorTimeframePerformanceData&timeframe=' + val,
        }).done(function (data) {
            // newTimeframeChart(ctx, data, val);
            newTimeframeHighChart('chart' + i, data, val);
        });
    });
}

// create timeframe-vs-sector bar chart
function newTimeframeChart(ctx, data, period) {
    var chartData = JSON.parse(data);

    var chartOptions = {
        scales: {
            xAxes: [
                {
                    ticks: {
                        beginAtZero: true,
                        fontSize: 10,
                        callback: function (value) {
                            return value + '%';
                        },
                    },
                },
            ],
            yAxes: [
                {
                    categoryPercentage: 1.0,
                    barPercentage: 0.5,
                    ticks: {
                        beginAtZero: true,
                        fontSize: 10,
                    },
                },
            ],
        },
        title: {
            position: 'top',
            display: true,
            text: period,
        },
        legend: {
            display: false,
        },
        tooltips: {
            titleFontSize: 0,
            titleMarginBottom: 0,
            titleFontStyle: 'normal',
            backgroundColor: 'rgba(0,0,0,0)',
            titleFontColor: '#000',
            bodyFontColor: '#000',
            displayColors: false,
            callbacks: {
                label: function (tooltipItem) {
                    return tooltipItem.xLabel.toFixed(2) + '%';
                },
            },
        },
        animation: {
            duration: 200,
            easing: 'easeOutQuart',
        },
        annotation: {
            annotations: [
                {
                    id: 'vline',
                    type: 'line',
                    mode: 'vertical',
                    scaleID: 'x-axis-0',
                    value: chartData['SPY'],
                    borderColor: 'rgba(0, 0, 0, 0.5)',
                    borderWidth: 1,
                    borderDash: [2, 2],
                },
            ],
        },
    };

    window.myChart = new Chart(ctx, {
        type: 'horizontalBar',
        data: chartData,
        options: chartOptions,
    });
}

// create timeframe-vs-sector bar chart using HighCharts API
function newTimeframeHighChart(chart, data, period) {
    data = JSON.parse(data);
    valueDAM = numeral(data.datasets[0].data[0]).value();
    valueSPY = numeral(data.datasets[0].data[1]).value();
    arrowType = valueDAM > valueSPY ? 'fa-arrow-circle-up' : 'fa-arrow-circle-down';
    arrowColor = valueDAM >= 0 ? 'arrowGreen' : 'arrowRed';

    $('#' + chart).css('height', 35 * data.labels.length + 'px');

    xAxisPlotLines = [
        {
            color: 'rgba(0,0,0,0.3)',
            dashStyle: 'ShortDash',
            width: 2,
            value: 1.5,
        },
    ];

    if (data.labels.length > 12) {
        xAxisPlotLines.push({
            color: 'rgba(0,0,0,0.3)',
            dashStyle: 'ShortDash',
            width: 2,
            value: 11.5,
        });
    }

    chartOptions = {
        chart: {
            type: 'bar',
        },
        title: {
            text: period + ' ' + "<span class='fa " + arrowType + " fa-lg' id='" + arrowColor + "'></span>",
            useHTML: true,
            align: 'center',
            x: 20,
            style: {
                color: '#666',
                fontSize: '12px',
                fontWeight: 'bold',
            },
        },
        legend: {
            enabled: false,
        },
        tooltip: {
            animation: false,
            backgroundColor: 'rgba(255,255,255,0)',
            borderWidth: 0,
            headerFormat: '',
            pointFormat: '<b>{point.y:.2f}%</b><br/>',
            shadow: false,
        },
        credits: {
            enabled: false,
        },
        xAxis: {
            categories: data.labels,
            gridLineWidth: 1,
            labels: {
                style: {
                    fontSize: '10px',
                },
            },
            plotLines: xAxisPlotLines,
        },
        yAxis: {
            title: {
                text: null,
            },
            labels: {
                format: '{value}%',
                style: {
                    fontSize: '10px',
                },
            },
            plotLines: [
                {
                    // color: '#FF0000',
                    color: 'rgba(0,0,0,0.5)',
                    dashStyle: 'ShortDash',
                    width: 1,
                    value: data.SPY,
                    zIndex: 4,
                },
                {
                    color: 'rgba(0,0,0,0.5)',
                    // dashStyle: 'ShortDash',
                    width: 1,
                    value: 0,
                    zIndex: 4,
                },
            ],
            allowDecimals: false,
        },
        plotOptions: {
            series: {
                animation: false,
            },
        },
        series: [
            {
                data: [],
                maxPointWidth: 14,
            },
        ],
    };

    $.each(data.datasets[0].data, function (i, value) {
        chartOptions.series[0].data[i] = {
            y: parseFloat(value),
            color: data.datasets[0].backgroundColor[i],
            borderColor: data.datasets[0].borderColor[i],
        };
    });
    Highcharts.chart(chart, chartOptions);
}

// display earnings today
function displayEarnings() {
    var count = 0;
    var weight = 0;

    // load and display page template
    $('#realtime').load('./pages/earnings.html', function () {
        // retrieve data from IEX trading
        $.ajax({
            type: 'GET',
            url: 'https://cloud.iexapis.com/stable/stock/market/today-earnings?token=pk_5e475fb86046444a94016bf6270a7434',
        }).done(function (data) {
            // console.log(data);
            var earnings = data.bto.concat(data.amc);
            // console.log(earnings);
            $('#datatable').DataTable({
                language: {
                    emptyTable: 'No earnings reported today',
                },
                searching: false,
                info: false,
                order: [
                    [6, 'desc'],
                    [0, 'asc'],
                ],
                orderMulti: true,
                paging: false,
                data: earnings,
                columns: [
                    {
                        data: 'quote.symbol',
                        width: '5%',
                        render: function (data, type, row) {
                            return '<a class="earnings" target="_yahoo" href="https://finance.yahoo.com/quote/' + row.quote.symbol + '">' + row.quote.symbol + '</a>';
                        },
                    },
                    { data: 'quote.companyName', width: '20%' },
                    { defaultContent: '', width: '15%' },
                    { data: 'consensusEPS', width: '10%', render: $.fn.dataTable.render.number(',', '.', 2) },
                    { data: 'quote.latestPrice', width: '10%' },
                    { data: 'quote.week52High', width: '10%' },
                    { data: 'quote.week52Low', width: '10%' },
                    { data: 'announceTime', width: '5%' },
                ],
                columnDefs: [
                    { className: 'text-center', targets: [0, 2, 3, 4, 5, 6, 7] },
                    { className: 'text-left', targets: [1] },
                ],
                createdRow: function (row, data, dataIndex) {
                    isSP500(data.symbol, function (data) {
                        if (data.isSP500) {
                            $('#countSP500').html(++count);
                            $('#weightSP500').html(numeral((weight += data.weight)).format('0.00'));
                            $(row).addClass('table-primary');
                        }
                    });
                },
            });
        });
    });
}

// trade history page
function displayTradeHistory() {
    // load and display page template
    $('#realtime').load('./pages/tradeHistory.html', function () {
        $('#datatable').DataTable({
            info: false,
            orderMulti: false,
            paging: false,
            ajax: './damwidiMain.php?mode=returnTransactions',
            columns: [{ data: 'transaction_date' }, { data: 'symbol' }, { data: 'type' }, { data: 'amount' }, { data: 'shares' }, { data: 'description' }],
            order: [[0, 'desc']],
            columnDefs: [
                { orderable: false, targets: [1, 2, 3, 4, 5] },
                { className: 'text-center', targets: [0, 1, 2] },
                { className: 'text-right', targets: [3, 4] },
            ],
        });
    });
}

// S&P500 data page
function displaySP500() {
    // load and display page template
    $('#realtime').load('./pages/sp500.html', function () {
        $.ajax({
            type: 'GET',
            url: 'http://192.241.146.131:3000/getspy/',
        }).done((sp500) => {
            $('#datatable').DataTable({
                searching: true,
                info: false,
                orderMulti: true,
                paging: false,
                data: sp500,
                order: [4, 'desc'],
                columns: [
                    { data: 'id' },
                    {
                        data: 'symbol',
                        render: function (data, type, row) {
                            return '<a class="earnings" target="_yahoo" href="https://finance.yahoo.com/quote/' + row.symbol + '">' + row.symbol + '</a>';
                        },
                    },
                    { data: 'companyName' },
                    { data: 'sector' },
                    { data: 'weight', render: $.fn.dataTable.render.number(',', '.', 2, '', '%') },
                ],
                columnDefs: [{ targets: [0, 1, 4], className: 'text-center' }],
            });
        });
    });
}

// basket data page

// load and display page template
function displayBasket() {
    $('#realtime').load('./pages/basket.html', function () {
        $('#datatable').DataTable({
            searching: false,
            info: false,
            orderMulti: false,
            paging: false,
            ajax: './damwidiMain.php?mode=returnBasket',
            columns: [
                {
                    data: 'symbol',
                    render: function (data, type, row) {
                        return '<a class="earnings" target="_yahoo" href="https://finance.yahoo.com/quote/' + row.symbol + '">' + row.symbol + '</a>';
                    },
                },
                { data: 'description' },
                { data: 'dateAdded' },
                { data: 'dateLastVisited' },
                { data: 'visitCount' },
            ],
            order: [[2, 'desc']],
            columnDefs: [
                { width: 100, className: 'text-center', targets: [0, 2, 3] },
                { width: 200, className: 'text-left', targets: [1] },
                { width: 50, className: 'text-center', targets: [4] },
            ],
            rowCallback: function (row, data) {
                $(row).addClass('');
            },
            createdRow: function (row, data, dataIndex) {
                isSP500(data.symbol, function (data) {
                    if (data.isSP500) {
                        $(row).addClass('table-primary');
                    }
                });
            },
        });
    });
}

// performance data page

// load and display page template
function displayPerformanceData() {
    $('#realtime').load('./pages/performanceData.html', function () {
        $.ajax({
            type: 'POST',
            url: './damwidiMain.php?mode=returnPerformanceData',
        }).done(function (data) {
            console.log(JSON.parse(data));
        });
    });
}

// technical charts

// load and display technical chart template
// https://icons8.com/preloaders/en/filtered-search/all/free/
function displayTechnicalCharts() {
    $('#realtime').load('./pages/technicalCharts.php', function () {
        symbolDetailHTML = $('#symbolDetail').html();
        symbolDataHTML = $('#symbolData').html();

        $('#symbolDetail').html('');
        $('#symbolData').html('');
    });
}

function processSymbol(symbol) {
    $('#progressImage').html('<img id="progress" src="progress.svg"/>');
    retrievePriceDataAlpha(symbol)
        .then(function (data) {
            retrieveSymbolDescription(symbol)
                .then(function (description) {
                    displayCandleChart(data, symbol, description);
                    displaySymbolDetails(symbol);
                    saveBasket(symbol, description);
                })
                .catch(function (error) {
                    description = symbol == 'DAM' ? 'damwidi investments' : symbol;
                    displayCandleChart(data, symbol, description);
                    $('#symbolDetail').html('');
                    $('#symbolData').html('');
                });
        })
        .catch(function (error) {
            $('.errorMessage').text('symbol not found');
        })
        .finally(function () {
            $('#progress').hide();
            $('#progressImage').empty();
        });
}

function retrievePriceDataAlpha(symbol) {
    if (symbol != 'DAM') {
        var url = 'https://www.alphavantage.co/query?function=TIME_SERIES_DAILY&symbol=' + symbol + '&apikey=WWQO&outputsize=full';
    } else {
        var url = './damwidiMain.php?mode=returnDamwidiOHLC';
    }

    $('#progressImage').html('<img id="progress" src="progress.svg"/>');

    return new Promise(function (resolve, reject) {
        $.getJSON(url, function (data) {})
            .fail(function () {
                reject('error loading data');
            })
            .done(function (data) {
                if (!data.hasOwnProperty('Error Message')) {
                    resolve(data['Time Series (Daily)']);
                    // displayCandleChart(data['Time Series (Daily)'], description);
                } else {
                    // $(".errorMessage").text('symbol not found');
                    reject('symbol not found');
                }
            })
            .always(function () {
                // hide progress spinner
                $('#progress').hide();
                $('#progressImage').empty();
            });
    });
}

function retrieveSymbolDescription(symbol) {
    return new Promise(function (resolve, reject) {
        $.ajax({
            url: 'https://sandbox.tradier.com/v1/markets/quotes?symbols=' + symbol,
            beforeSend: function (xhr) {
                xhr.setRequestHeader('Accept', 'application/json'), xhr.setRequestHeader('Authorization', 'Bearer ata5xeDkGRVybQMZbiSn0rQH8IgR');
            },
            success: function (data) {
                if (!data.quotes.hasOwnProperty('unmatched_symbols')) {
                    resolve(data.quotes.quote.description);
                } else {
                    reject('symbol not found');
                }
            },
        })
            .always(function () {})
            .fail(function () {
                reject('error loading data');
            });
    });
}

function displayCandleChart(data, symbol, title) {
    var ohlc = [];
    var sma3 = [];
    var dataLength = Object.keys(data).length;
    var groupingUnits = [
        ['week', [1]],
        ['month', [1, 2, 3, 4, 6]],
    ];
    var i, j, sum;

    $.each(data, function (key, value) {
        ohlc.push([
            Date.parse(key), // date
            parseFloat(value['1. open']), // open
            parseFloat(value['2. high']), // high
            parseFloat(value['3. low']), // low
            parseFloat(value['4. close']), // close
        ]);
    });

    // calculate sma(3,3)
    for (i = 0; i < dataLength - 4; i++) {
        sum = 0;
        for (j = 0; j < 3; j++) {
            sum += ohlc.slice(i + j + 2, i + j + 3)[0][4];
        }
        sma3.push([ohlc.slice(i, i + 1)[0][0], sum / 3]);
    }

    // reverse order
    ohlc.sort(function (a, b) {
        return a[0] - b[0];
    });

    sma3.sort(function (a, b) {
        return a[0] - b[0];
    });

    var titleText = symbol == 'DAM' ? symbol + '<br>' + title : '';

    // create the chart
    Highcharts.stockChart('chartPrice', {
        chart: {
            className: 'candleChart',
        },

        rangeSelector: {
            selected: 2,
        },

        title: {
            text: titleText,
            margin: 0,
            useHTML: false,
            align: 'center',
        },

        xAxis: {
            type: 'datetime',
            dateTimeLabelFormats: {
                second: '%Y-%m-%d<br/>%H:%M:%S',
                minute: '%Y-%m-%d<br/>%H:%M',
                hour: '%Y-%m-%d<br/>%H:%M',
                day: '%Y<br/>%m-%d',
                week: '%Y<br/>%m-%d',
                month: '%Y-%m',
                year: '%Y',
            },
            crosshair: {
                snap: true,
            },
        },

        yAxis: [
            {
                labels: {
                    align: 'right',
                    x: -3,
                },
                title: {
                    text: 'OHLC',
                },
                height: '100%',
                lineWidth: 2,
                resize: {
                    enabled: true,
                },
                crosshair: {
                    snap: false,
                    color: '#5b5b5b',
                },
            },
        ],

        tooltip: {
            split: true,
        },

        plotOptions: {
            candlestick: {
                color: '#ff0000',
            },
            bb: {
                color: '#0040ff',
                lineWidth: 1,
                dashStyle: 'ShortDash',
                bottomLine: {
                    styles: {
                        lineWidth: 3,
                    },
                },
                topLine: {
                    styles: {
                        lineWidth: 3,
                    },
                },
                enableMouseTracking: false,
            },
            sma: {
                index: 3,
                enableMouseTracking: false,
                lineWidth: 3,
                marker: {
                    enabled: false,
                },
            },
        },

        series: [
            {
                type: 'candlestick',
                name: symbol,
                id: 'aapl',
                data: ohlc,
                dataGrouping: {
                    units: groupingUnits,
                },
                pointRange: 5,
            },
            {
                type: 'bb',
                linkedTo: 'aapl',
            },
            {
                type: 'sma',
                linkedTo: 'aapl',
                color: '#FF8040',
                params: {
                    period: 50,
                    index: 3,
                },
            },
            {
                type: 'sma',
                linkedTo: 'aapl',
                color: '#000000',
                params: {
                    period: 200,
                    index: 3,
                },
            },
            {
                type: 'sma',
                linkedTo: 'aapl',
                color: '#ff0000',
                params: {
                    period: 2,
                    index: 3,
                },
            },
            {
                type: 'line',
                linkedTo: 'aapl',
                data: sma3,
                color: '#26a833',
                lineWidth: 3,
                enableMouseTracking: false,
            },
        ],
    });
}

function displaySymbolDetails(symbol) {
    $('#symbolData').html(symbolDataHTML);
    $('#symbolDetail').html(symbolDetailHTML);
    var boxPlotOptions = {
        chart: {
            type: 'boxplot',
            inverted: true,
        },

        plotOptions: {
            series: {
                animation: false,
            },
        },

        navigation: {
            buttonOptions: {
                enabled: false,
            },
        },

        credits: {
            enabled: false,
        },

        title: {
            text: undefined,
        },

        legend: {
            enabled: false,
        },

        tooltip: {
            useHTML: true,
            enabled: true,
            headerFormat: '<table class="week52ToolTip">',
            pointFormat:
                '<tr><td>52wk High:</td><td>${point.high:.2f}</td></tr><tr><td> 2wk High:</td><td>${point.q3:.2f}</td></tr><tr><td>Current:</td><td>${point.median:.2f}</td></tr><tr><td>2wk Low:</td><td>${point.q1:.2f}</td></tr><tr><td>52wk Low:</td><td>${point.low:.2f}</td></tr>',
            footerFormat: '</table>',
        },

        xAxis: {
            categories: '',
            visible: false,
        },

        yAxis: {
            title: {
                text: '52 Week Performance',
            },
            labels: {
                format: '${value:,.f}',
            },
            gridLineWidth: 0,
        },

        series: [
            {
                name: '52 Week',
                pointWidth: 20,
                data: [[142, 150, 157, 164, 233]],
            },
        ],
    };

    $.ajax({
        type: 'GET',
        url: iexURL + 'stock/' + symbol + '/logo' + '?token=' + iexPK,
    }).done(function (logo) {
        $('#companyLogo').attr('src', logo.url);
    });

    $.ajax({
        type: 'GET',
        url: iexURL + 'stock/' + symbol + '/quote' + '?token=' + iexPK,
    })
        .done(function (data) {
            quoteData = data;

            // update headers
            $('#tickerSymbol').html(symbol.toUpperCase());
            $('#companyName').html(quoteData.companyName);

            // update detail table
            $('#previousClose').html(numeral(quoteData.previousClose).format('$0,0.00'));
            $('#marketCap').html(numeral(quoteData.marketCap).format('$0,0.0a'));
            $('#peRatio').html(quoteData.peRatio);

            $('#week52High').html(numeral(quoteData.week52High).format('$0,0.00'));
            $('#week52Low').html(numeral(quoteData.week52Low).format('$0,0.00'));
            $('#ytdChange').html(numeral(quoteData.ytdChange).format('0.00%'));

            $('#latestVolume').html(numeral(quoteData.latestVolume).format('0,0'));
            $('#avgTotalVolume').html(numeral(quoteData.avgTotalVolume).format('0,0'));
            $('#sector').html(quoteData.sector);
            // $('#latestUpdate').html(moment(quoteData[symbol].quote.latestUpdate).format('hh:mm:ss dddd YYYY-MM-DD'));

            // make connection
            if (socket.connected) {
                console.log('websocket connected');
                socket.emit('unsubscribe', webSocketPreviousSymbol);
                socket.emit('subscribe', symbol);
                webSocketPreviousSymbol = symbol;
                count = 0;
                previousTick = 0;
            }
        })
        .then(function (quoteData) {
            $.ajax({
                type: 'GET',
                // url: "https://api.iextrading.com/1.0/stock/"+symbol+"/chart?chartLast=10",
                // url: "https://api.iextrading.com/1.0/stock/market/batch?symbols="+symbol+"&types=quote,news&last=10",
                url: iexURL + 'stock/' + symbol + '/chart?chartLast=10&token=' + iexPK,

                // https://cloud.iexapis.com/stable/stock/amzn/chart?chartLast=10&token=pk_5e475fb86046444a94016bf6270a7434
            }).done(function (historicalData) {
                boxPlotOptions.series[0].data = [
                    [
                        quoteData.week52Low,
                        Math.min.apply(
                            Math,
                            historicalData.map(function (o) {
                                return o.low;
                            })
                        ),
                        quoteData.latestPrice,
                        Math.max.apply(
                            Math,
                            historicalData.map(function (o) {
                                return o.high;
                            })
                        ),
                        quoteData.week52High,
                    ],
                ];
                Highcharts.chart('containerBoxPlot', boxPlotOptions);
            });
        });
}

// above the below and RS charts

// load and display page template
function displayAboveBelow() {
    $('#realtime').load('./pages/sectorAboveBelow.html', function () {});
}

function loadChartData(timeframe, update) {
    var url = './damwidiMain.php?mode=returnAboveBelow&timeframe=' + timeframe;
    $.getJSON(url, function (data) {
        // console.log(data);
        displayLineCharts(data, update);
    }).fail(function () {
        console.log('error');
    });
}

function displayLineCharts(data, update) {
    var options = {
        scales: {
            xAxes: [
                {
                    ticks: {
                        fontSize: 10,
                    },
                    gridLines: {
                        zeroLineColor: '#ff0000',
                    },
                },
            ],
            yAxes: [
                {
                    ticks: {
                        max: 1.02,
                        min: 0.98,
                        beginAtZero: true,
                        fontSize: 10,
                    },
                },
            ],
        },
        title: {
            position: 'top',
            display: true,
            text: 'Above The Line',
        },
        legend: {
            display: true,
            position: 'bottom',
            labels: {
                boxWidth: 15,
            },
        },
        tooltips: {
            titleFontSize: 0,
            titleMarginBottom: 0,
            titleFontStyle: 'normal',
            backgroundColor: 'rgba(0,0,0,0)',
            titleFontColor: '#000',
            bodyFontColor: '#000',
            displayColors: false,
            callbacks: {
                label: function (tooltipItem, data) {
                    var i = tooltipItem.datasetIndex;
                    return data.datasets[tooltipItem.datasetIndex].label;
                },
            },
        },
        animation: {
            duration: 0,
        },
        annotation: {
            annotations: [
                {
                    type: 'line',
                    mode: 'horizontal',
                    scaleID: 'y-axis-0',
                    value: 1,
                    borderColor: '#000000',
                    borderWidth: 2,
                    borderDash: [4, 2],
                },
            ],
        },
    };

    var charts = {
        above: {
            div: 'aboveChart',
            html: '<canvas id="above" height="500" width="1500"></canvas>',
            title: 'Above the Line',
        },
        below: {
            div: 'belowChart',
            html: '<canvas id="below" height="500" width="1500"></canvas>',
            title: 'Below the Line',
        },
        rs: {
            div: 'rsChart',
            html: '<canvas id="rs" height="500" width="1500"></canvas>',
            title: 'Relative Strength',
        },
    };

    $.each(charts, function (type, value) {
        if (update) {
            //remove previus chart
            $('#' + value.div).html('');
            $('#' + value.div).html(value.html);
        }

        options.title.text = value.title;
        var ctx = $('#' + type);

        var lineChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data['labels'],
                datasets: data[type],
            },
            options: options,
        });

        //autoscale
        var min = type != 'rs' ? 1 : 100;
        var max = type != 'rs' ? 1 : 100;
        $(data[type]).each(function (i, val) {
            max = Math.max(max, Math.max.apply(Math, val.data));
            min = Math.min(min, Math.min.apply(Math, val.data));
        });
        if (type != 'rs') {
            max = roundTo(max, 2) + 0.01;
            min = roundTo(min, 2) - 0.01;
        } else {
            max = roundTo(max, 0) + 1;
            min = roundTo(min, 0) - 1;
        }

        lineChart.options.scales.yAxes[0].ticks.max = max;
        lineChart.options.scales.yAxes[0].ticks.min = min;
        lineChart.update();
    });
}

// ajax data handlers

// load intraday data
function loadIntraDayData(callback) {
    return $.ajax({
        type: 'POST',
        url: './damwidiMain.php?mode=returnIntraDayData',
        success: function (data) {
            callback(JSON.parse(data));
        },
    });
}

function loadDateDetails(callback) {
    return $.ajax({
        type: 'POST',
        url: './damwidiMain.php?mode=returnDetails',
        success: function (data) {
            callback(JSON.parse(data));
        },
    });
}

function isSP500(symbol, callback) {
    return $.ajax({
        type: 'GET',
        url: 'http://192.241.146.131/getspy/' + symbol,
        success: function (data) {
            callback(data);
        },
    });
}

function saveBasket(symbol, description) {
    $.ajax({
        type: 'POST',
        url: './damwidiMain.php?mode=updateDamwidiBasket&symbol=' + symbol + '&description=' + escape(description),
    });
}

//
// helper functions
// return color based on based value
function changeColor(value) {
    switch (true) {
        case value == 0:
            var changeColor = 'black';
            break;
        case value > 0:
            var changeColor = 'green';
            break;
        case value < 0:
            var changeColor = 'red';
            break;
    }
    return changeColor;
}

function changeFavIcon(profit, ahead, defaultIcon) {
    if (defaultIcon === undefined) defaultIcon = false;
    // profit (boolean):      true = DAM making money, false = DAM losing money
    // ahead (boolean):       true = DAM beating SPY,  false = DAM lagging SPY
    // defaultIcon (boolean): true = use default Icon
    //
    // profit ahead  favicon
    // true   true   green up arrow
    // true   false  green down arrow
    // false  true   red up arrow
    // false  false  red down arrow

    var favicon;

    if (!defaultIcon) {
        if (profit && ahead) {
            favicon = './faviconGreenUp.ico';
        } else if (profit && !ahead) {
            favicon = './faviconGreenDown.ico';
        } else if (!profit && ahead) {
            favicon = './faviconRedUp.ico';
        } else if (!profit && !ahead) {
            favicon = './faviconRedDown.ico';
        }
    } else {
        favicon = './faviconDefault.ico';
    }

    $('#favicon').attr('href', favicon);
}

// display time is human readible format
// function niceTime(label = '', today = new Date()){
function niceTime(label, today) {
    if (label === undefined) label = '';
    if (today === undefined) today = new Date();
    // var today = new Date();
    var h = numeral(today.getHours()).format('00');
    var m = numeral(today.getMinutes()).format('00');
    var s = numeral(today.getSeconds()).format('00');
    console.log(label + h + ':' + m + ':' + s);
}

// crate array of only sectors
function parseSectorData(data) {
    var sectorData = [];

    Object.keys(data).forEach(function (symbol, index) {
        if (data[symbol].type == 'S') {
            sectorData.push(data[symbol]);
        }
    });
    return sectorData;
}

// sort sector data
function sortSectorData(field) {
    switch (field) {
        case 'description':
        case 'sector':
            // alpha sort ascending
            sectorData.sort(function (a, b) {
                return a[field].localeCompare(b[field]);
            });
            break;
        case 'implied':
        case 'impliedOverUnder':
        case 'weight':
            // numeric sort descending
            sectorData.sort(function (a, b) {
                return numeral(b[field]).value() - numeral(a[field]).value();
            });
            break;
        default:
            console.log('default');
    }
}

// format time used in heatMap title
function formatTime(time) {
    if (moment(time).hour() == 0) {
        var formatTime = 'YYYY-MM-DD';
    } else {
        var formatTime = 'hh:mm:ssA YYYY-MM-DD';
    }
    return moment(time).format(formatTime);
}

function roundTo(n, digits) {
    if (digits === undefined) {
        digits = 0;
    }

    var multiplicator = Math.pow(10, digits);
    n = parseFloat((n * multiplicator).toFixed(11));
    var test = Math.round(n) / multiplicator;
    return +test.toFixed(2);
}

function test() {
    console.log('this is a test');
}

// websocket event handlers
socket.on('message', function (data) {
    quote = JSON.parse(data);
    symbol = quote.symbol;
    console.log(count++ + ' ' + quote.symbol + ': ' + moment(quote.time).format('YY-MM-DD, hh:mm:ss') + ' $' + quote.price);

    var changePrice = quote.price - quoteData.previousClose;
    var changePerct = changePrice / quoteData.previousClose;

    $('#iexPrice').html(numeral(quote.price).format('$0.00'));
    $('#realTimeUpdate').html(moment(quote.time).format('h:mm:ssA') + ' - ' + moment(quote.time).format('dddd') + ' - ' + moment(quote.time).format('YYYY-MM-DD'));

    if (changePrice >= 0) {
        $('.supsub').addClass('up').removeClass('down');
    } else {
        $('.supsub').addClass('down').removeClass('up');
    }

    if (previousTick !== 0) {
        if (quote.price >= previousTick) {
            // up tick, flash green backgroud
            $('#iexPrice').addClass('quoteGreen');
            setTimeout(() => {
                $('#iexPrice').removeClass('quoteGreen');
            }, 200);
        } else if (quote.price < previousTick) {
            // down tick, flash red background
            $('#iexPrice').addClass('quoteRed');
            setTimeout(() => {
                $('#iexPrice').removeClass('quoteRed');
            }, 200);
        }
    }
    previousTick = quote.price;

    $('#iexChange').html(numeral(changePrice).format('0.00'));
    $('#iexPerct').html(numeral(changePerct).format('0.0%'));
});
