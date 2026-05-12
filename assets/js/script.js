$(document).ready(function () {
    // Toggle Sidebar
    $("#menu-toggle").click(function (e) {
        e.preventDefault();
        $(".wrapper").toggleClass("toggled");
    });

    // Initialize DataTables with a check to prevent re-initialization errors
    $('.datatable').each(function() {
        if (!$.fn.DataTable.isDataTable(this)) {
            $(this).DataTable({
                responsive: true,
                "pageLength": 10,
                "language": {
                    "search": "Filter records:"
                }
            });
        }
    });

    // Global Search Functionality
    $('#globalSearch').on('keyup', function() {
        if ($.fn.DataTable.isDataTable('.datatable')) {
            $('.datatable').DataTable().search(this.value).draw();
        }
    });
});
