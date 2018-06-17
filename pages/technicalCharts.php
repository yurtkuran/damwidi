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

    <div class="technicalCharts">
        <div id="progressImage"></div>
        <div class="row">
            <div id="chartPrice" style="height: 600px; width: 100%"></div>
        </div>
    </div>

</div>

<script>
    $('#form-new').on('submit', function(event) {
        event.preventDefault();

        var symbol = document.getElementById("symbol").value;
        if (symbol) {
            $(".errorMessage").empty();
            retrievePriceDataAlpha(symbol.toUpperCase())
            $("#symbol").blur();
        }
    });
</script>