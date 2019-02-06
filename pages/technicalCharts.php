<?php include_once('../php-includes/functions.php'); ?>

<div class="container-fluid">

    <form id="form-new">
        <div class="row">
            <div class="col-2">
                <input id="symbol" name="symbol" class="form-control input-sm" placeholder="Enter symbol...">
            </div>

            <div class="col-1">
                <button type="submit" name="submit" class="btn btn-success"> <?=i('play')?> </button>
            </div>
        </div>
        <div class="row errorMessage col-2"></div>
    </form>

    <div id="symbolDetail">
        <div class="row mt-0 company">
            <div>
                <h4 class="text-center pr-2" id="tickerSymbol"></h4>
            </div>
        </div>

        <div class="row mt-2 company">
            <h6 class="text-center" id="companyName"></h6>
        </div>

        <div class="row mt-0 company">
            <div class="realTime ">
                <span class="quote quote-bg" id="iexPrice"></span>
                <span class="supsub up">
                    <sup class='' id="iexChange"></sup>
                    <sub class='pt-1' id="iexPerct"></sub>
                </span>
            </div>
        </div>

        <div class="row justify-content-center">
            <small class="quoteTime mt-0" id="realTimeUpdate"></small>
        </div>
    </div>

    <div class="technicalCharts">
        <div id="progressImage"></div>
        <div class="row">
            <div id="chartPrice" style="height: 600px; width: 100%"></div>
        </div>
    </div>

    <div class="row pt-5">
        <div class="col-sm"></div>
        <div class="col-6" id="symbolData">
            <table class="table table-sm">
                <tr>
                    <td class="text-center w-25"><p class="text-info quoteDetail m-0">Previous Close</p><span id="previousClose"></span></td>
                    <td class="text-center w-25"><p class="text-info quoteDetail m-0">Market Cap</p><span id="marketCap"></span></td>
                    <td class="text-center w-25"><p class="text-info quoteDetail m-0">P/E Ratio</p><span id="peRatio"></span></td>
                </tr>
                <tr>
                    <td class="text-center"><p class="text-info quoteDetail m-0">52wk High</p><span id="week52High"></span></td>
                    <td class="text-center"><p class="text-info quoteDetail m-0">52wk Low</p><span id="week52Low"></span></td>
                    <td class="text-center"><p class="text-info quoteDetail m-0">YTD Change</p><span id="ytdChange"></span></td>
                </tr>
                <tr class="border-bottom">
                    <td class="text-center"><p class="text-info quoteDetail m-0">Volume</p><span id="latestVolume"></span></td>
                    <td class="text-center"><p class="text-info quoteDetail m-0">Avg Total Vol</p><span id="avgTotalVolume"></span></td>
                    <td class="text-center"><p class="text-info quoteDetail m-0">Sector</p><span id="sector"></span></td>
                </tr>
            </table>

            <div id="containerBoxPlot" class=" border-bottom mt-0" style="height: 150px; margin: auto; min-width: 310px; max-width: 600px"></div>

            <div class="row justify-content-center">
                <small class="quoteTime mt-4" id="latestUpdate"></small>
            </div>

            <div class="row justify-content-center p-4">
                <img class="rounded float-right p-0" height="80px" id="companyLogo" src="" alt="">
            </div>
        </div>
        <div class="col-sm"></div>
    </div>
</div>

<script>
    $('#form-new').on('submit', function(event) {
        event.preventDefault();

        var symbol = document.getElementById("symbol").value;
        if (symbol) {
            $(".errorMessage").empty();
            processSymbol(symbol.toUpperCase())
            $("#symbol").blur();
        }
    });
</script>