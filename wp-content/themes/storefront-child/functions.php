<?php
/**
 * Файл functions.php дочерней темы.
 * Здесь регистрируем:
 * - стили
 * - кастомный тип записей "Cities"
 * - метабоксы для координат
 * - таксономию "Countries"
 * - виджет погоды
 * - AJAX-поиск по городам
 */

/**
 * Подключение стилей родительской темы
 */
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
});

/**
 * Регистрируем кастомный тип записи (CPT) "Cities"
 */
function create_cities_cpt() {
    $labels = array(
        'name'               => 'Cities',
        'singular_name'      => 'City',
        'menu_name'          => 'Cities',
        'name_admin_bar'     => 'City',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New City',
        'new_item'           => 'New City',
        'edit_item'          => 'Edit City',
        'view_item'          => 'View City',
        'all_items'          => 'All Cities',
        'search_items'       => 'Search Cities',
        'not_found'          => 'No cities found.',
        'not_found_in_trash' => 'No cities found in Trash.',
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,                 // Доступен на фронте
        'show_ui'            => true,                 // Отображается в админке
        'show_in_menu'       => true,                 // В админ-меню
        'rewrite'            => array('slug' => 'cities'),
        'has_archive'        => true,                 // Архивная страница
        'menu_icon'          => 'dashicons-location', // Иконка в админке
        'supports'           => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),
        'show_in_rest'       => true,                 // Поддержка Gutenberg + REST API
    );

    register_post_type('cities', $args);
}
add_action('init', 'create_cities_cpt');

/**
 * Добавляем метабокс для координат (широта/долгота)
 */
function cities_add_metaboxes() {
    add_meta_box(
        'cities_coordinates',          // ID метабокса
        'Coordinates',                 // Заголовок
        'cities_coordinates_callback', // Callback для отображения HTML
        'cities',                      // Для какого CPT
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'cities_add_metaboxes');

/**
 * Callback для отображения метабокса
 */
function cities_coordinates_callback($post) {
    // Получаем сохраненные значения
    $latitude  = get_post_meta($post->ID, '_latitude', true);
    $longitude = get_post_meta($post->ID, '_longitude', true);

    // Защита nonce
    wp_nonce_field('cities_save_coordinates', 'cities_coordinates_nonce');
    ?>
    <p>
        <label for="latitude"><strong>Latitude (Широта):</strong></label><br>
        <input type="text" name="latitude" id="latitude"
               value="<?php echo esc_attr($latitude); ?>" style="width: 100%;">
    </p>
    <p>
        <label for="longitude"><strong>Longitude (Долгота):</strong></label><br>
        <input type="text" name="longitude" id="longitude"
               value="<?php echo esc_attr($longitude); ?>" style="width: 100%;">
    </p>
    <?php
}

/**
 * Сохранение координат при сохранении записи
 */
function cities_save_coordinates($post_id) {
    // Проверяем nonce
    if (!isset($_POST['cities_coordinates_nonce']) ||
        !wp_verify_nonce($_POST['cities_coordinates_nonce'], 'cities_save_coordinates')) {
        return;
    }

    // Не сохраняем при автосохранении
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Проверяем права пользователя
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Сохраняем latitude
    if (isset($_POST['latitude'])) {
        update_post_meta($post_id, '_latitude', sanitize_text_field($_POST['latitude']));
    }

    // Сохраняем longitude
    if (isset($_POST['longitude'])) {
        update_post_meta($post_id, '_longitude', sanitize_text_field($_POST['longitude']));
    }
}
add_action('save_post', 'cities_save_coordinates');

/**
 * Регистрируем таксономию "Countries" для Cities
 */
function cities_register_taxonomy_countries() {
    $labels = array(
        'name'          => 'Countries',
        'singular_name' => 'Country',
        'search_items'  => 'Search Countries',
        'all_items'     => 'All Countries',
        'edit_item'     => 'Edit Country',
        'add_new_item'  => 'Add New Country',
        'menu_name'     => 'Countries',
    );

    $args = array(
        'hierarchical'      => true,                // Работает как категории
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,                // Колонка в админке
        'rewrite'           => array('slug' => 'country'),
        'show_in_rest'      => true,                // REST + Gutenberg
    );

    register_taxonomy('countries', array('cities'), $args);
}
add_action('init', 'cities_register_taxonomy_countries');

/**
 * Виджет "Погода по городам"
 */
class Cities_Weather_Widget extends WP_Widget {

    // Конструктор
    function __construct() {
        parent::__construct(
            'cities_weather_widget',
            'City Weather Widget',
            array('description' => 'Показывает погоду для выбранного города (CPT Cities)')
        );
    }

    // Форма настроек в админке
    public function form($instance) {
        $selected_city = !empty($instance['city_id']) ? $instance['city_id'] : '';
        
        // Получаем список городов
        $cities = get_posts(array(
            'post_type'   => 'cities',
            'numberposts' => -1,
            'orderby'     => 'title',
            'order'       => 'ASC'
        ));
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('city_id'); ?>">Выберите город:</label>
            <select class="widefat" id="<?php echo $this->get_field_id('city_id'); ?>"
                    name="<?php echo $this->get_field_name('city_id'); ?>">
                <option value="">-- Выберите --</option>
                <?php foreach ($cities as $city): ?>
                    <option value="<?php echo $city->ID; ?>" <?php selected($selected_city, $city->ID); ?>>
                        <?php echo esc_html($city->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <?php
    }

    // Сохраняем настройки
    public function update($new_instance, $old_instance) {
        $instance = [];
        $instance['city_id'] = (!empty($new_instance['city_id']))
            ? strip_tags($new_instance['city_id']) : '';
        return $instance;
    }

    // Получение данных погоды из API OpenWeatherMap
    private function get_weather_data($city_id) {
        $api_key = defined('OPENWEATHER_API_KEY') ? OPENWEATHER_API_KEY : '';
        $lat = get_post_meta($city_id, '_latitude', true);
        $lon = get_post_meta($city_id, '_longitude', true);
        $city_name = get_the_title($city_id);

        // Формируем URL в зависимости от наличия координат
        if ($lat && $lon) {
            $url = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lon}&units=metric&appid={$api_key}";
        } else {
            $url = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($city_name) . "&units=metric&appid={$api_key}";
        }

        $cache_key = 'city_weather_' . $city_id;
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $temp = $data['main']['temp'] ?? null;

        // Сохраняем в кэш на 15 минут
        if ($temp !== null) {
            set_transient($cache_key, $temp, 15 * MINUTE_IN_SECONDS);
        }

        return $temp;
    }

    // Вывод на сайте
    public function widget($args, $instance) {
        $city_id = $instance['city_id'] ?? '';
        if (!$city_id) return;

        $city = get_post($city_id);

        echo $args['before_widget'];
        echo $args['before_title'] . esc_html($city->post_title) . $args['after_title'];

        $temp = $this->get_weather_data($city_id);

        if ($temp !== null) {
            echo "<p>Температура: <strong>{$temp}°C</strong></p>";
        } else {
            echo "<p>Не удалось получить данные о погоде.</p>";
        }

        echo $args['after_widget'];
    }
}

// Регистрируем виджет
function register_cities_weather_widget() {
    register_widget('Cities_Weather_Widget');
}
add_action('widgets_init', 'register_cities_weather_widget');

/**
 * Подключаем JS для AJAX-поиска
 */
add_action('wp_enqueue_scripts', function () {
    if (is_page_template('page-cities-table.php')) {
        wp_enqueue_script('cities-search',
            get_stylesheet_directory_uri() . '/cities-search.js',
            ['jquery'], null, true);

        wp_localize_script('cities-search', 'cities_ajax', [
            'url' => admin_url('admin-ajax.php')
        ]);
    }
});

/**
 * AJAX обработчик поиска городов
 */
add_action('wp_ajax_search_cities', 'search_cities');
add_action('wp_ajax_nopriv_search_cities', 'search_cities');

function search_cities() {
    global $wpdb;

    $search = sanitize_text_field($_POST['search'] ?? '');

    // SQL-запрос по названию города
    $results = $wpdb->get_results($wpdb->prepare("
        SELECT p.ID, p.post_title AS city, t.name AS country
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->term_relationships} tr ON (p.ID = tr.object_id)
        LEFT JOIN {$wpdb->term_taxonomy} tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
        LEFT JOIN {$wpdb->terms} t ON (tt.term_id = t.term_id)
        WHERE p.post_type = 'cities'
          AND p.post_status = 'publish'
          AND p.post_title LIKE %s
    ", '%' . $wpdb->esc_like($search) . '%'));

    $rows = [];
    foreach ($results as $row) {
        $latitude  = get_post_meta($row->ID, '_latitude', true);
        $longitude = get_post_meta($row->ID, '_longitude', true);

        $temperature = 'N/A';
        if ($latitude && $longitude) {
            $cache_key = 'city_weather_' . $row->ID;
            $cached = get_transient($cache_key);

            if ($cached !== false) {
                $temperature = $cached . ' °C';
            } else {
                $api_key = defined('OPENWEATHER_API_KEY') ? OPENWEATHER_API_KEY : '';
                $weather_url = "https://api.openweathermap.org/data/2.5/weather?lat={$latitude}&lon={$longitude}&units=metric&appid={$api_key}";
                $response = wp_remote_get($weather_url);

                if (!is_wp_error($response)) {
                    $data = json_decode(wp_remote_retrieve_body($response), true);
                    if (!empty($data['main']['temp'])) {
                        $temperature = round($data['main']['temp'], 1) . ' °C';
                        // Сохраняем температуру в кэш на 15 минут
                        set_transient($cache_key, round($data['main']['temp'], 1), 15 * MINUTE_IN_SECONDS);
                    }
                }
            }
        }

        $rows[] = [
            'country'     => $row->country,
            'city'        => $row->city,
            'temperature' => $temperature
        ];
    }

    wp_send_json($rows);
}
