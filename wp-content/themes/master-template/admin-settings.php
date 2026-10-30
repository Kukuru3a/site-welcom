<?php
/** Editing controls for every site-wide value shown in the theme. */
function master_copy_fields() {
    return [
        'Навигация и первый экран' => [
            'navServices'=>['Услуги в меню','Услуги'], 'navWorks'=>['Работы в меню','Работы'],
            'navAbout'=>['Обо мне в меню','Обо мне'], 'navContacts'=>['Контакты в меню','Контакты'],
            'headerButton'=>['Кнопка в шапке','Заказать звонок'], 'heroButton'=>['Кнопка на первом экране','Контакты'],
            'heroNote'=>['Подпись под кнопкой','Обычно отвечаю в течение рабочего дня'],
        ],
        'Разделы' => [
            'servicesKicker'=>['Надпись над услугами','ЧЕМ МОГУ ПОМОЧЬ'], 'servicesTitle'=>['Заголовок услуг','Услуги и цены'],
            'serviceButton'=>['Кнопка в карточках услуг','Оставить заявку'],
            'aboutKicker'=>['Надпись над рассказом','ЗНАКОМСТВО'], 'aboutTitle'=>['Заголовок рассказа','Мастер, который отвечает за результат'],
            'experienceCaption'=>['Подпись к стажу','опыт работы'], 'areaCaption'=>['Подпись к городу','город и районы выезда'],
            'worksKicker'=>['Надпись над работами','ПРИМЕРЫ'], 'worksTitle'=>['Заголовок работ','Примеры работ'],
            'faqKicker'=>['Надпись над вопросами','ОТВЕТЫ'], 'faqTitle'=>['Заголовок вопросов','Частые вопросы'],
            'faqIntro'=>['Текст рядом с вопросами','Если остались вопросы, расскажу всё по телефону.'],
        ],
        'Контакты и форма' => [
            'contactKicker'=>['Надпись над контактами','НА СВЯЗИ'], 'contactTitle'=>['Заголовок контактов','Закажите обратный звонок'],
            'contactIntro'=>['Текст контактов','Оставьте номер, и я свяжусь с вами.'],
            'formTitle'=>['Заголовок формы','Заказать обратный звонок'], 'formIntro'=>['Текст формы','Перезвоню в рабочее время.'],
            'formSubmit'=>['Кнопка отправки','Жду звонка'],
            'phoneLabel'=>['Подпись поля телефона','Номер телефона'], 'phonePlaceholder'=>['Подсказка телефона','+7 (999) 000-00-00'],
            'nameLabel'=>['Подпись поля имени','Ваше имя'], 'nameOptional'=>['Подпись необязательного поля','необязательно'],
            'namePlaceholder'=>['Подсказка имени','Как к вам обращаться'],
        ],
    ];
}

function master_default_copy() {
    $defaults = [];
    foreach (master_copy_fields() as $fields) foreach ($fields as $key=>$meta) $defaults[$key] = $meta[1];
    return $defaults;
}

add_action('admin_menu', function () {
    add_menu_page('Сайт мастера', 'Сайт мастера', 'manage_options', 'master-site', 'master_settings_page', 'dashicons-admin-home', 25);
    add_submenu_page('master-site', 'Профиль и настройки', 'Профиль и настройки', 'manage_options', 'master-site', 'master_settings_page');
    add_submenu_page('master-site', 'Ссылки соцсетей', 'Ссылки соцсетей', 'manage_options', 'master-links', 'master_links_page');
}, 9);

add_action('admin_menu', function () {
    foreach (['edit.php','edit.php?post_type=page','edit-comments.php','themes.php','tools.php'] as $slug) remove_menu_page($slug);
    remove_submenu_page('master-site', 'post-new.php?post_type=master_review');
}, 999);

function master_settings_input($name, $label, $value, $type='text') {
    $id = 'master-setting-' . sanitize_key($name);
    echo '<p><label for="' . esc_attr($id) . '"><strong>' . esc_html($label) . '</strong></label><br>';
    if ($type === 'textarea') echo '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" rows="3" style="width:100%;max-width:720px">' . esc_textarea($value) . '</textarea>';
    else echo '<input id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" type="' . esc_attr($type) . '" value="' . esc_attr($value) . '" style="width:100%;max-width:600px"' . ($type === 'number' ? ' min="0" step="1"' : '') . '>';
    echo '</p>';
}

function master_settings_media($name, $label, $id, $fallback='') {
    $url = $id ? wp_get_attachment_image_url($id, 'medium') : $fallback;
    echo '<p><strong>' . esc_html($label) . '</strong></p><div class="master-media-field" data-name="' . esc_attr($name) . '">';
    echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($id) . '">';
    echo '<img src="' . esc_url($url ?: '') . '" alt="Предпросмотр" style="max-width:200px;max-height:160px;display:block;margin-bottom:8px">';
    echo '<button type="button" class="button master-media-select">Выбрать фото</button> <button type="button" class="button master-media-clear">Вернуть исходное</button></div>';
}

function master_links_page() {
    if (!current_user_can('manage_options')) return;
    $stored = get_option('master_social_links');
    $values = is_array($stored) ? $stored : [];
    foreach (['telegram','vk','avito'] as $network) $values[$network] = master_social_url($values[$network] ?? '', $network);
    $notice = '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['master_save_links'])) {
        check_admin_referer('master_save_links');
        $errors = []; $clean = [];
        foreach (['telegram'=>'Telegram','vk'=>'ВКонтакте','avito'=>'Авито'] as $network=>$label) {
            $raw = trim((string)wp_unslash($_POST['social_'.$network] ?? ''));
            $values[$network] = $raw;
            $clean[$network] = master_social_url($raw, $network);
            if ($raw !== '' && $clean[$network] === '') $errors[] = $label;
        }
        if ($errors) $notice = '<div class="notice notice-error"><p>Нужна полная HTTPS-ссылка на соответствующий сервис. Проверьте: ' . esc_html(implode(', ', $errors)) . '.</p></div>';
        else {
            update_option('master_social_links', $clean, false);
            $values = $clean;
            $notice = '<div class="notice notice-success"><p>Ссылки сохранены. Проверьте иконки в блоке обратного звонка на сайте.</p></div>';
        }
    }
    echo '<div class="wrap"><h1>Ссылки соцсетей</h1><p>Укажите адреса профилей. Пустое поле оставляет иконку без перехода.</p>' . $notice;
    echo '<form method="post">'; wp_nonce_field('master_save_links');
    foreach (['telegram'=>['Telegram','https://t.me/username'], 'vk'=>['ВКонтакте','https://vk.com/username'], 'avito'=>['Авито','https://www.avito.ru/user/...']] as $network=>$meta) {
        $id = 'master-social-' . $network;
        echo '<p><label for="' . esc_attr($id) . '"><strong>' . esc_html($meta[0]) . '</strong></label><br><input type="url" id="' . esc_attr($id) . '" name="social_' . esc_attr($network) . '" value="' . esc_attr($values[$network] ?? '') . '" placeholder="' . esc_attr($meta[1]) . '" style="width:100%;max-width:650px"></p>';
    }
    echo '<p><button type="submit" name="master_save_links" value="1" class="button button-primary button-large">Сохранить ссылки</button></p></form></div>';
}

function master_settings_page() {
    if (!current_user_can('manage_options')) return;
    $notice = '';
    $default = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
    $current = get_option('master_site_config');
    $current = array_replace_recursive($default, is_array($current) ? $current : []);
    $copy = array_replace(master_default_copy(), (array)get_option('master_copy', []));
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['master_save_site'])) {
        check_admin_referer('master_save_site');
        $posted = wp_unslash($_POST);
        $errors = [];
        $phone = trim((string)($posted['site_phone'] ?? ''));
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) < 10 || strlen($digits) > 15) $errors[] = 'телефон';
        $rating = str_replace(',', '.', trim((string)($posted['avito_rating'] ?? '4.9')));
        if (!is_numeric($rating) || (float)$rating < 0 || (float)$rating > 5) $errors[] = 'оценка Авито';
        $count = trim((string)($posted['avito_count'] ?? ''));
        if ($count !== '' && (!ctype_digit($count) || (int)$count > 100000000)) $errors[] = 'количество отзывов';
        if (!$errors) {
            foreach (['name','profession','professionTitle','city','areas','experience','hours','guarantee'] as $key) $current[$key] = sanitize_text_field($posted['site_'.$key] ?? '');
            foreach (['heroText','about'] as $key) $current[$key] = sanitize_textarea_field($posted['site_'.$key] ?? '');
            $current['phone'] = sanitize_text_field($phone);
            $current['phoneHref'] = '+' . $digits;
            foreach (['primary','accent'] as $key) $current['colors'][$key] = sanitize_hex_color($posted['color_'.$key] ?? '') ?: $default['colors'][$key];
            $new_copy = [];
            foreach (master_default_copy() as $key=>$value) $new_copy[$key] = sanitize_text_field($posted['copy_'.$key] ?? $value);
            update_option('master_site_config', $current, false);
            update_option('master_copy', $new_copy, false);
            foreach (['master_profile_photo','master_site_logo','master_avito_logo','master_icon_telegram','master_icon_vk','master_icon_avito'] as $key) {
                $id = absint($posted[$key] ?? 0);
                update_option($key, $id && wp_attachment_is_image($id) ? $id : 0, false);
            }
            update_option('master_avito_rating', (float)$rating, false);
            update_option('master_avito_count', $count === '' ? '' : (int)$count, false);
            $copy = $new_copy;
            $notice = '<div class="notice notice-success"><p>Настройки сайта сохранены.</p></div>';
        } else $notice = '<div class="notice notice-error"><p>Проверьте поля: ' . esc_html(implode(', ', $errors)) . '.</p></div>';
    }

    echo '<div class="wrap"><h1>Сайт мастера</h1><p>Здесь меняются фото, текст, телефон, оценка и цвета. Ссылки соцсетей, услуги, работы и вопросы редактируются в соседних пунктах меню.</p>' . $notice;
    echo '<p><a href="#master-profile">Профиль</a> · <a href="#master-images">Фото и логотипы</a> · <a href="#master-copy">Надписи</a> · <a href="#master-contacts">Контакты и оценка</a></p>';
    echo '<form method="post">'; wp_nonce_field('master_save_site');
    echo '<h2 id="master-profile">Профиль мастера</h2>';
    foreach (['name'=>'Имя мастера','profession'=>'Профессия','professionTitle'=>'Заголовок на первом экране','city'=>'Город','areas'=>'Районы выезда','experience'=>'Стаж','hours'=>'Время работы','heroText'=>'Текст на первом экране','about'=>'Текст «Обо мне»','guarantee'=>'Гарантия'] as $key=>$label)
        master_settings_input('site_'.$key, $label, $current[$key] ?? '', in_array($key,['heroText','about'],true) ? 'textarea' : 'text');
    echo '<h2 id="master-images">Фото и логотипы</h2><p>Фото мастера показывается на первом экране и в разделе «Обо мне». Фото работ меняются в разделе «Работы».</p>';
    master_settings_media('master_profile_photo','Фото мастера', (int)get_option('master_profile_photo'), get_template_directory_uri() . '/assets/master.svg');
    master_settings_media('master_site_logo','Логотип в шапке', (int)get_option('master_site_logo'), get_template_directory_uri() . '/assets/electrician-logo.jpg');
    master_settings_media('master_avito_logo','Логотип на плашке Авито', (int)get_option('master_avito_logo'), get_template_directory_uri() . '/assets/avito.png');
    master_settings_media('master_icon_telegram','Иконка Telegram в контактах', (int)get_option('master_icon_telegram'), get_template_directory_uri() . '/assets/telegram.png');
    master_settings_media('master_icon_vk','Иконка ВКонтакте в контактах', (int)get_option('master_icon_vk'), get_template_directory_uri() . '/assets/vk.png');
    master_settings_media('master_icon_avito','Иконка Авито в контактах', (int)get_option('master_icon_avito'), get_template_directory_uri() . '/assets/avito.png');
    echo '<h2 id="master-copy">Надписи и кнопки</h2>';
    foreach (master_copy_fields() as $section=>$fields) {
        echo '<h3>' . esc_html($section) . '</h3>';
        foreach ($fields as $key=>$meta) master_settings_input('copy_'.$key, $meta[0], $copy[$key] ?? $meta[1]);
    }
    echo '<h2 id="master-contacts">Контакты и оценка</h2>';
    master_settings_input('site_phone','Телефон', $current['phone'] ?? '');
    echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=master-links')) . '">Изменить ссылки Telegram, ВКонтакте и Авито</a></p>';
    echo '<p>Укажите только реальные показатели профиля Авито. Если число отзывов неизвестно, оставьте поле пустым.</p>';
    master_settings_input('avito_rating','Оценка Авито (от 0 до 5)', get_option('master_avito_rating', 4.9));
    master_settings_input('avito_count','Количество отзывов Авито', get_option('master_avito_count', 118), 'number');
    master_settings_input('color_primary','Основной цвет', $current['colors']['primary'] ?? '', 'color');
    master_settings_input('color_accent','Цвет кнопок', $current['colors']['accent'] ?? '', 'color');
    echo '<p><button type="submit" name="master_save_site" value="1" class="button button-primary button-large">Сохранить изменения</button></p></form></div>';
}
