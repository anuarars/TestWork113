<?php
/**
 * Template Name: Cities Table
 */

get_header();

do_action('before_cities_table'); // кастомный хук
?>
<div class="cities-table-wrapper">
    <h2>Список стран и городов</h2>

    <input type="text" id="city-search" placeholder="Поиск города..." />

    <table id="cities-table" border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr>
                <th>Страна</th>
                <th>Город</th>
                <th>Температура</th>
            </tr>
        </thead>
        <tbody>
            <?php
            global $wpdb;

            $api_key = 'ff405e40423b2a822bc7ee4e5c83776a';

            function cities_get_temperature($post_id, $api_key) {
                $cache_key = 'city_temp_' . $post_id;
                $cached = get_transient($cache_key);
                if ($cached !== false) {
                    return $cached;
                }

                $lat = get_post_meta($post_id, '_latitude', true);
                if ($lat === '') $lat = get_post_meta($post_id, 'latitude', true);
                if ($lat === '') $lat = get_post_meta($post_id, '_city_latitude', true);

                $lon = get_post_meta($post_id, '_longitude', true);
                if ($lon === '') $lon = get_post_meta($post_id, 'longitude', true);
                if ($lon === '') $lon = get_post_meta($post_id, '_city_longitude', true);

                $city_name = get_the_title($post_id);

                if (!empty($lat) && !empty($lon)) {
                    $url = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lon}&units=metric&appid={$api_key}";
                } else {
                    $url = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($city_name) . "&units=metric&appid={$api_key}";
                }

                $response = wp_remote_get($url, ['timeout' => 10]);
                if (is_wp_error($response)) {
                    return 'N/A';
                }

                $code = wp_remote_retrieve_response_code($response);
                if ($code !== 200) {
                    // Для дебага можно включить лог:
                    // error_log('OWM error '.$code.': '.wp_remote_retrieve_body($response));
                    return 'N/A';
                }

                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($data['main']['temp'])) {
                    $temp = round(floatval($data['main']['temp']), 1) . ' °C';
                    set_transient($cache_key, $temp, 30 * MINUTE_IN_SECONDS);
                    return $temp;
                }

                return 'N/A';
            }

            // Получаем города с привязкой к таксономии "countries"
            $results = $wpdb->get_results("
                SELECT p.ID,
                       p.post_title AS city,
                       GROUP_CONCAT(t.name SEPARATOR ', ') AS countries
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                LEFT JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE p.post_type = 'cities'
                  AND p.post_status = 'publish'
                  AND (tt.taxonomy = 'countries' OR tt.taxonomy IS NULL)
                GROUP BY p.ID
                ORDER BY p.post_title ASC
            ");

            if ($results) {
                foreach ($results as $row) {
                    $temperature = cities_get_temperature($row->ID, $api_key);

                    echo '<tr>
                        <td>' . esc_html($row->countries ?: '-') . '</td>
                        <td>' . esc_html($row->city) . '</td>
                        <td>' . esc_html($temperature) . '</td>
                    </tr>';
                }
            } else {
                echo '<tr><td colspan="3">Нет данных</td></tr>';
            }
            ?>
        </tbody>
    </table>
</div>
<?php
do_action('after_cities_table'); // кастомный хук
get_footer();
