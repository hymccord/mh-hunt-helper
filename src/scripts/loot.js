$(function() {
    $("#erase_item").click(function() {
        $("#results").html('');
        $("#item").val('').focus();
        $('#timefilter').val('all_time');
        $("#prev_item").val('');
        $("#prev_timefilter").val('');
        $("#rate_per_hunt").val('');
        window.history.replaceState({}, "MH Hunt Helper", "loot.php");
    });

    searchItems('all', firstLoad, $('#timefilter').val());

    function searchItems(item_id, callback, timefilter) {
        if (!item_id) return;

        if (item_id !== 'all') {
            $("#loader").css("display", "block");
            // Every time we search for a item (on reload or ajax) set a history of it.
            window.history.replaceState({}, "MH Hunt Helper", "loot.php?item=" + item_id + "&timefilter=" + timefilter);
            $("#prev_item").val(item_id);
            $("#prev_timefilter").val(timefilter);
        }

        $.ajax({
            url: "searchByItem.php",
            method: "GET",
            data: {
                item_id: item_id,
                item_type: "loot",
                timefilter: timefilter
            }
        }).done(function(data) {
            callback(JSON.parse(data));
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
            if ($('#prev_timefilter').val()) {
                $('#timefilter').val($('#prev_timefilter').val());
            }
            searchItems($("#prev_item").val(), renderResultsTable, $('#timefilter').val());
        }

        // set autocomplete
        addAutocomplete(items);
        $('#timefilter').change(function() {
            searchItems($('#prev_item').val(), renderResultsTable, $('#timefilter').val());
        });
        $('#rate_per_hunt').change(function() {
            searchItems($('#prev_item').val(), renderResultsTable, $('#timefilter').val());
        });
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
            select: function(event, ui) {
                searchItems(ui.item.id, renderResultsTable, $('#timefilter').val());
            }
        });

        // Fix for double click on IOS
        if (navigator.userAgent.match(/(iPod|iPhone|iPad)/)) {
            $('#item').autocomplete('widget').off('mouseenter');
        }
    }

    function renderResultsTable(data) {
        var show_per_hunt = $('#rate_per_hunt').is(':checked');
        var final_html = '<table id="results_table" class="table table-striped table-hover table-bordered" style="width:100%" />';

        var all_stages = '';

        var dataSet = []
        data.forEach(function(row) {
            var stage = (row.stage ? row.stage : '');
            all_stages += stage;

            const numEvents = show_per_hunt ? row.total_hunts : row.total_catches;
            const quantityPerEvent = parseFloat(row.total_drops / numEvents).toPrecision(3);

            var drop_chance = parseFloat(row.drop_pct / 100);
            var moe = (1.96 * Math.sqrt(drop_chance * (1 - drop_chance) / row.total_catches) * 100).toFixed(2);
            drop_chance = (drop_chance * 100).toFixed(2);
            const range = `${row.min_amt} - ${row.max_amt}`;

            dataSet.push([row.location, stage, row.cheese, quantityPerEvent, numEvents, `${drop_chance}%`, `${moe}%`, range]);
        })

        $("#results").html(final_html);
        var table = $('#results_table').DataTable({
            data: dataSet,
            dom: 'Pfrtip',
            paging: false,
            searching: false,
            fixedHeader: true,
            searchPanes: {
                initCollapsed: true
            },
            info: false,
            order: [[3, 'desc']],
            columns: [
                { title: 'Location' },
                { title: 'Stage' },
                { title: 'Cheese' },
                { title: show_per_hunt ? 'Qty / Hunt' : 'Qty / Catch' },
                { title: show_per_hunt ? 'Hunts' : 'Catches' },
                { title: 'Drop Chance', name: '95% CI Margin of Error for Drop Chance' },
                { title: '± Error' },
                { title: 'Qty Range' },
            ],
            columnDefs: [
                {
                    "targets": [1],
                    "visible": (all_stages.length === 0 ? false : true)
                }
            ],
            // initComplete: () => {
            //     this.api()
            //         .columns()
            //         .every(() => {
            //             var that = this;
            //             $('input', this.footer()).on('keyup change clear', () => {
            //                 if (that.search() !== this.value) {
            //                     that.search(this.value).draw();
            //                 }
            //             });
            //         });
            // },
        });

        // $.fn.dataTable.SearchPanes(table, {});
        // table.searchPanes.container().prependTo(table.table().container());
        // table.searchPanes.resizePanes();

        // Set 7th table header title for MoE
        $('#results_table th:eq(6)').attr('title', '95% CI Margin of Error for Drop Chance');

        $("#loader").css("display", "none");
    }
});
