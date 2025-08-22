jQuery(document).ready(function ($) {
    $('#city-search').on('keyup', function () {
        let search = $(this).val();

        $.post(cities_ajax.url, {
            action: 'search_cities',
            search: search
        }, function (response) {
            let tbody = $('#cities-table tbody');
            tbody.empty();

            if (response.length > 0) {
                response.forEach(row => {
                    tbody.append(`
                        <tr>
                            <td>${row.country}</td>
                            <td>${row.city}</td>
                            <td>${row.temperature}</td>
                        </tr>
                    `);
                });
            } else {
                tbody.append('<tr><td colspan="3">Нет результатов</td></tr>');
            }
        });
    });
});
