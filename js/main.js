var allocationData;

$(document).ready(function(){

    var intraDayTimer = 0;
    displayIntraday(function(timer){                                // load intraday page, graph and table
        intraDayTimer = timer;
    });

    $('a[href="#"]').click(function (event) {
        event.preventDefault();
        $('.navbar-nav li').removeClass("active");                  // remove active class from every menu item

        switch($(this).attr('id')){
            case 'heatMap':
                $(".navbar-nav li#heatMap").addClass('active');
                displayIntraday(function(timer){
                    intraDayTimer = timer;
                });
                break;

            case 'sectorAllocation':
                $(".navbar-nav li#sectorAllocation").addClass('active');
                clearInterval(intraDayTimer);
                displaySectorAllocation();
                updateAllocationDetails();
                break;

            case 'tradeHistory':
                $(".navbar-nav li#tradeHistory").addClass('active');
                clearInterval(intraDayTimer);
                displayTradeHistory();
                break;

            case 'sectorTimeframe':
                clearInterval(intraDayTimer);
                displaySectorTimeframeCharts();
                break;
        }
    });
});


//
// intraday heatmap chart and table

// load intraday page, graph and table
function displayIntraday(callback){
    $("#realtime").load("./pages/heatMap.html", function(){                     // load main page template
        $('#portfolio').load('./damwidiMain.php?mode=buildPortfolioTable');     // build out the portfolio table
        heatMapChart = newHeatMapChart();                                       // create new chart
        updateIntraday(heatMapChart);                                           // update heat map chart with latest data

        var intraDayTimer = setInterval(function(){                             // refresh data every 60 sec
            updateIntraday(heatMapChart);
        },60000);
        callback(intraDayTimer);
    });
};

// create new heat map bar chart
function newHeatMapChart(){
    var options = {
        scales: {
            xAxes: [{
                categoryPercentage: 1.0,
                barPercentage: 0.4,
                ticks: {
                    fontSize: 12,
                    }
            }],
            yAxes: [{
                ticks: {
                    beginAtZero: true,
                    fontSize: 12,
                    callback: function(value) {
                        return value.toFixed(1)+"%";
                    }
                }
            }]
        },
        title: {
            position: 'top',
            display: true,
            text: "",
        },
        legend: {
            display: false
        },
        tooltips: {
            titleFontSize:     0,
            titleMarginBottom: 0,
            titleFontStyle:    'normal',
            backgroundColor:   'rgba(0,0,0,0)',
            titleFontColor:    '#000',
            bodyFontColor:     '#000',
            displayColors:     false,
            callbacks: {
                label: function(tooltipItem) {
                    return tooltipItem.yLabel.toFixed(2)+'%';
                }
            }
        },
        animation: {
            duration: 0,
            easing: "easeOutQuart",
        },
        annotation: {
            annotations: [{
                id:      'vline',
                type:    'line',
                mode:    'vertical',
                scaleID: 'x-axis-0',
                value:   'SPY',
                borderColor: 'rgba(0, 0, 0, 0.5)',
                borderWidth:  3,
                borderDash:   [2, 2],
            }]
        }
    };

    return heatMapChart = new Chart("chart0", {
        type: 'bar',
        options: options
    });
}

function updateIntraday(heatMapChart, callback){
    loadIntraDayData(function(heatMapData) {                                    // load intraday data
        niceTime('Data complete: ');                                            // display time
        $("#intraDayTitle").text( formatTime(heatMapData['time']));             // update graph title
        heatMapChart.data = heatMapData['graphHeatMap'];                        // load data into graph
        heatMapChart.update();                                                  // redraw graph
        updatePortfolioTable(heatMapData['portfolioTable']);                    // update portfolio table
    });
};

// update portfolio with realtime data
function updatePortfolioTable(data){
    // console.log(data);

    Object.keys(data).forEach(function(symbol){
        $("#last"+symbol).text(data[symbol]['last']);
        $("#value"+symbol).text(data[symbol]['value']);

        if (symbol != 'DAM'){
            $("#change"+symbol).text(data[symbol]['changePercent']+"% / "+data[symbol]['change']);
        } else {
            $("#change"+symbol).text(data[symbol]['changePercent']+"%");
        }

        $("#valueChange"+symbol).text(data[symbol]['valueChange']);

        // format cells based on change (up, down or flat)
        $("#change"+symbol).removeClass();
        $("#change"+symbol).addClass("text-center");
        $("#valueChange"+symbol).removeClass();
        $("#valueChange"+symbol).addClass("text-right");
        switch (data[symbol]['tick']){
            case 'UP':
                $("#change"+symbol).addClass("table-success");
                $("#change"+symbol).css("color","green");
                $("#valueChange"+symbol).addClass("table-success");
                $("#valueChange"+symbol).css("color","green");
                break;
            case 'DOWN':
                $("#change"+symbol).addClass("table-danger");
                $("#change"+symbol).css("color","red");
                $("#valueChange"+symbol).addClass("table-danger");
                $("#valueChange"+symbol).css("color","red");
                break;
            case 'ZERO':
                $("#change"+symbol).addClass("table-default");
                $("#change"+symbol).css("color","black");
                $("#valueChange"+symbol).addClass("table-default");
                $("#valueChange"+symbol).css("color","black");
                break;
        }
    });
}


//
// sector allocation page

// load and display page template
function displaySectorAllocation(){
    $("#realtime").load("./pages/sectorAllocation.html", function(){
        $('#allocation').load('./damwidiMain.php?mode=buildAllocationTable', function(){    // complete build out of allocation table
            loadIntraDayData(function(heatMapData) {
                $("#intraDayTitle").html( formatTime(heatMapData['time']));
                allocationData = heatMapData['allocationTable'];
                updateAllocationTable(allocationData);
            });
        });
    });
}

// add data to allocation table
function updateAllocationTable(data, mode = 'relative'){
    Object.keys(data).forEach(function(symbol){
        $("#value"+symbol).text(data[symbol]['currentValue']);
        $("#change"+symbol).text(data[symbol]['change']);
        $("#allocation"+symbol).text(data[symbol]['allocation']);
        if (mode == 'relative') {
            $("#weight"+symbol).text(data[symbol]['weightPercent']);
            $("#implied"+symbol).text(data[symbol]['impliedPercent']);
            $("#impliedOverUnder"+symbol).text(data[symbol]['impliedOverUnderPercent']);
        } else {
            $("#weight"+symbol).text(data[symbol]['weight']);
            $("#implied"+symbol).text(data[symbol]['implied']);
            $("#impliedOverUnder"+symbol).text(data[symbol]['impliedOverUnder']);
        }

        // format change text color based on value
        var change = parseFloat(data[symbol]['change'].replace(/,/g, ''));  // remove comma from number
        switch (true) {
            case (change == 0):
                var changeColor = 'black';
                break;
            case (change > 0):
                var changeColor = 'green';
                break;
            case (change < 0):
                var changeColor = 'red';
                break;
        };
        $("#change"+symbol).css("color",changeColor);
    });
}

function updateAllocationDetails(){
    loadDateDetails(function(dateData) {
        $.each(dateData, function(key) {
            $("#"+key).text(dateData[key]);
        });
    });
}


//
// sector vs timeframe page

// load and display page template
function displaySectorTimeframeCharts(){
    $("#realtime").load("./pages/sectorTimeframeCharts.html", function(){
        buildSectorTimeframeCharts();
    });
};

// build bar charts to compare sector returns vs timeframe
function buildSectorTimeframeCharts(){
    var periods = ['1wk', '2wk', '4wk', '1qtr', 'ytd', '1yr'];

    var options = {
        scales: {
            xAxes: [{
                ticks: {
                    beginAtZero: true,
                    fontSize: 10,
                    callback: function(value) {
                        return value+"%";
                    }
                }
            }],
            yAxes: [{
                categoryPercentage: 1.0,
                barPercentage: 0.5,
                ticks: {
                    beginAtZero: true,
                    fontSize: 10
                }
            }]
        },
        title: {
            position: 'top',
            display: true,
            text: "1wk",
        },
        legend: {
            display: false
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
                label: function(tooltipItem) {
                    return tooltipItem.xLabel.toFixed(2)+'%';
                }
            }
        },
        animation: {
            duration: 200,
            easing: "easeOutQuart",
        },
        annotation: {
            annotations: [{
                id:      'vline',
                type:    'line',
                mode:    'vertical',
                scaleID: 'x-axis-0',
                value:   0,
                borderColor: 'rgba(0, 0, 0, 0.5)',
                borderWidth:  1,
                borderDash:   [2, 2],
            }]
        }
    };

    $(periods).each(function(i, val) {
        var ctx = $("#chart"+i);
        $.ajax({
            type: "POST",
            url: "./damwidiMain.php?mode=returnSectorTimeframePerformanceData&timeframe="+val,
        }).done(function(data){
            newTimeframeChart(ctx, data, options, val);
        });
    });
};

// create timeframe-vs-sector bar chart
function newTimeframeChart(ctx, data, chartOptions, period){
    var chartData = JSON.parse(data);
    chartOptions.title.text = period; //change title
    chartOptions.annotation.annotations[0].value = chartData['SPY']; //change marker line
    window.myChart = new Chart(ctx, {
        type: 'horizontalBar',
        data: chartData,
        options: chartOptions,
    });

};


//
// trade history page

// load and display page template
function displayTradeHistory(){
    $("#realtime").load("./pages/tradeHistory.html", function(){
        $('#datatable').DataTable( {
            "info":       false,
            "orderMulti": false,
            "paging":     false,
            "ajax":       "./damwidiMain.php?mode=returnTransactions",
            "columns": [
                { "data": "transaction_date" },
                { "data": "ticker" },
                { "data": "type" },
                { "data": "amount" },
                { "data": "shares" },
                { "data": "description" }
            ],
            "order":      [[ 0, 'desc' ]],
            "columnDefs": [
                { orderable: false, targets: [1, 2, 3, 4, 5],  },
                { className: "text-center", targets: [ 0, 1, 2 ] },
                { className: "text-right",  targets: [ 3, 4 ] },
            ]
        } );
    });
}


//
// ajax data handlers

// load intraday data
function loadIntraDayData(callback){
    return $.ajax({
        type: "POST",
        url:  "./damwidiMain.php?mode=returnIntraDayData",
        success: function(data){
            callback(JSON.parse(data));
        },
    });
}

function loadDateDetails(callback){
    return $.ajax({
        type: "POST",
        url:  "./damwidiMain.php?mode=returnDetails",
        success: function(data){
            callback(JSON.parse(data));
        },
    });
}


//
// helper functions

// display time is human readible format
function niceTime(label = '', today = new Date()){
    // var today = new Date();
    var h = numeral(today.getHours()).format('00');
    var m = numeral(today.getMinutes()).format('00');
    var s = numeral(today.getSeconds()).format('00');
    console.log(label+h+":"+m+":"+s);
}

// format time used in heatMap title
function formatTime(time){
    if (moment(time).hour() == 0) {
        var formatTime = 'YYYY-MM-DD';
    } else {
        var formatTime = 'hh:mm:ssA YYYY-MM-DD'
    };
    return moment(time).format(formatTime)
};

function test(){
    console.log('this is a test');
};