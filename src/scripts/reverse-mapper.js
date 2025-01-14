$( function() {
    $("#erase_item").click(function () {
        $("#results").html('');
        $("#results_total").html('');
        $("#item").val('').focus();
        window.history.replaceState({}, "MH Hunt Helper", "reverse-mapper.php");
    });

    searchItems('all', firstLoad);

    function searchItems(item_id, callback) {
        if (item_id !== 'all') {
            $("#loader").css( "display", "block" );
            // Every time we search for a item (on reload or ajax) set a history of it.
            window.history.replaceState({}, "MH Hunt Helper", "reverse-mapper.php?item=" + item_id);
        }

        $.ajax({
            url: "searchByItem.php",
            method: "GET",
            data: {
                item_id: item_id,
                item_type: "mousemaps"
            }
        })
        .done(function( data ) {
            callback( JSON.parse(data));
        });
    }

    function firstLoad(items) {
        for (var i = 0; i < items.length; i++) {
            items[i].value = decodeURI(escape(items[i].value));
        }
        // Check and search for previous item (done on reload of whole page)
        var previous_item_id = $("#prev_item").val();
        if (previous_item_id) {
            var previous_item_name = '';
            for (var i = 0; i < items.length; i++) {
                if (items[i].id == previous_item_id) {
                    previous_item_name = items[i].value;
                    $("#item").val(previous_item_name);
                    break;
                }
            }
            searchItems($("#prev_item").val(), renderResultsTable);
        }

        // set autocomplete
        addAutocomplete(items);
    }

    function addAutocomplete(items) {
        $('#item').autocomplete({
            source: function(request, response) {
                var results = $.ui.autocomplete.filter(items, request.term);

                results.sort(function(a, b) {
                    return a.value.toUpperCase().indexOf(request.term.toUpperCase()) - b.value.toUpperCase().indexOf(request.term.toUpperCase());
                });

                response(results.slice(0, 10));
            },
            delay: 0,
            select: function( event, ui ) {
                searchItems(ui.item.id, renderResultsTable);
            }
        });

        // Fix for double click on IOS
        if (navigator.userAgent.match(/(iPod|iPhone|iPad)/)) {
            $('#item').autocomplete('widget').off('mouseenter');
        }
    }

    function renderResultsTable(data) {
        var final_html = '<table id="results_table" class="table table-striped table-hover" style="width:100%"><thead><tr><th>Map</th><th>Seen Maps</th><th>Rate per map</th></tr></thead><tbody>';

        var all_stages = '';
        let total_seen = 'Did not find this mouse on maps';
        data.forEach(function(row) {
            total_seen = 'Seen on ' + row.total_maps + ' maps';
            final_html += '<tr><td>'
                + row.map + '</td><td>'
                + row.seen_maps + '</td><td>'
                + parseFloat(((row.rate)/100).toFixed(2)) + '%</td></tr>';
        });
        final_html += '</tbody></table>';

        total_seen = '<h4>' + total_seen + '</h4>';
        $('#results_total').html(total_seen);

        $("#results").html(final_html);
        $('#results_table').DataTable( {
            "paging":   false,
            "searching": false,
            "info": false,
            "order": [[2, 'desc']]
        });

        var table = $('#results_table').DataTable();
        table.columns().iterator( 'column', function (ctx, idx) {
            $( table.column(idx).header() ).append('<span class="sort-icon"/>');
        });

        $("#loader").css( "display", "none" );
    }
});
