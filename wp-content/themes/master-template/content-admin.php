<?php
/** Editable services, portfolio and Avito reviews. */
add_action('init', function () {
    foreach ([
        'master_service' => 'Услуги',
        'master_work' => 'Работы',
        'master_faq' => 'Вопросы',
        'master_review' => 'Отзывы',
    ] as $type => $label) {
        register_post_type($type, [
            'labels' => ['name'=>$label, 'singular_name'=>rtrim($label,'ы'), 'add_new_item'=>'Добавить', 'edit_item'=>'Редактировать', 'all_items'=>$label],
            'public'=>false, 'show_ui'=>true, 'show_in_menu'=>$type === 'master_review' ? false : 'master-site',
            'supports'=>['title','editor','page-attributes'],
            'capability_type'=>'post',
        ]);
    }
    if (!get_option('master_content_initialized')) master_seed_content();
    if (!get_option('master_content_v2')) master_expand_content();
    if (!get_option('master_content_v3')) master_polish_examples();
    if (!get_option('master_faq_initialized')) master_seed_faq();
}, 20);

function master_seed_faq() {
    $config = get_option('master_site_config');
    if (!is_array($config)) $config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
    foreach ($config['faq'] ?? [] as $index=>$item) {
        wp_insert_post(['post_type'=>'master_faq','post_status'=>'publish','menu_order'=>$index,'post_title'=>$item['q'] ?? '', 'post_content'=>$item['a'] ?? '']);
    }
    update_option('master_faq_initialized', 1, false);
}

function master_polish_examples() {
    $service = get_page_by_title('Монтаж освещения', OBJECT, 'master_service');
    if ($service && get_post_meta($service->ID,'_master_unit',true) === 'точка') update_post_meta($service->ID,'_master_unit','точку');
    foreach (['Новая электрика на кухне','Замена щита'] as $title) {
        $post = get_page_by_title($title, OBJECT, 'master_work');
        if (!$post || !get_post_meta($post->ID,'_master_example',true)) continue;
        wp_update_post(['ID'=>$post->ID,'post_content'=>'Иллюстрация для оформления карточки. Замените изображение и описание на фотографию выполненной работы.']);
        update_post_meta($post->ID,'_master_duration',''); update_post_meta($post->ID,'_master_cost','');
    }
    update_option('master_content_v3',1,false);
}

function master_expand_content() {
    foreach ([
        'Замена розетки или выключателя'=>'Замена розеток и выключателей',
        'Монтаж светильника'=>'Установка люстр и светильников',
        'Поиск неисправности'=>'Поиск неисправностей',
    ] as $old=>$new) {
        $post = get_page_by_title($old, OBJECT, 'master_service');
        if ($post && !get_page_by_title($new, OBJECT, 'master_service')) wp_update_post(['ID'=>$post->ID,'post_title'=>$new]);
    }
    $services = [
        ['Установка люстр и светильников','Подключение и аккуратный монтаж','1800','шт.'],
        ['Монтаж проводки','Прокладка новых линий с проверкой','450','м'],
        ['Сборка электрощита','Компоновка, сборка и маркировка линий','12000','щит'],
        ['Установка автоматов и УЗО','Подбор и подключение защитной автоматики','1600','шт.'],
        ['Подключение электроплиты и духового шкафа','Подключение техники к подготовленной линии','2500','шт.'],
        ['Монтаж освещения','Монтаж группы светильников и проверка','2200','точку'],
    ];
    foreach ($services as [$title,$description,$price,$unit]) {
        if (get_page_by_title($title, OBJECT, 'master_service')) continue;
        $id = wp_insert_post(['post_type'=>'master_service','post_status'=>'publish','post_title'=>$title,'post_content'=>$description]);
        if (!is_wp_error($id)) { update_post_meta($id,'_master_price',$price); update_post_meta($id,'_master_unit',$unit); update_post_meta($id,'_master_example','1'); }
    }
    foreach (get_posts(['post_type'=>'master_service','post_status'=>'publish','numberposts'=>-1]) as $post) {
        if (in_array($post->post_title,['Замена розеток и выключателей','Установка люстр и светильников','Поиск неисправностей','Прокладка кабеля'],true) && !metadata_exists('post',$post->ID,'_master_example')) update_post_meta($post->ID,'_master_example','1');
    }
    foreach (get_posts(['post_type'=>'master_work','post_status'=>'publish','numberposts'=>-1]) as $post) {
        if (in_array($post->post_title,['Новая электрика на кухне','Замена щита'],true) && !metadata_exists('post',$post->ID,'_master_example')) update_post_meta($post->ID,'_master_example','1');
    }
    $works = [
        ['Установка освещения','Заготовка карточки: замените изображение и описание на реальный проект.','/assets/work-after.svg'],
        ['Монтаж кабельной линии','Заготовка карточки: добавьте фотографии выполненной работы.','/assets/work-before.svg'],
    ];
    foreach ($works as [$title,$description,$image]) {
        if (get_page_by_title($title, OBJECT, 'master_work')) continue;
        $id = wp_insert_post(['post_type'=>'master_work','post_status'=>'publish','post_title'=>$title,'post_content'=>$description]);
        if (!is_wp_error($id)) { update_post_meta($id,'_master_after',$image); update_post_meta($id,'_master_example','1'); }
    }
    update_option('master_content_v2',1,false);
}

function master_seed_content() {
    $config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
    foreach ($config['services'] as $item) {
        $id = wp_insert_post(['post_type'=>'master_service','post_status'=>'publish','post_title'=>$item['title'],'post_content'=>$item['description']]);
        update_post_meta($id,'_master_price',$item['price']); update_post_meta($id,'_master_unit',$item['unit']);
    }
    foreach ($config['works'] as $item) {
        $id = wp_insert_post(['post_type'=>'master_work','post_status'=>'publish','post_title'=>$item['title'],'post_content'=>$item['description']]);
        foreach (['before','after','duration','cost'] as $field) update_post_meta($id,'_master_'.$field,$item[$field] ?? '');
    }
    foreach ($config['reviews']['items'] as $item) {
        $id = wp_insert_post(['post_type'=>'master_review','post_status'=>'publish','post_title'=>$item['name'],'post_content'=>$item['text']]);
        foreach (['date','meta','rating','deal'] as $field) update_post_meta($id,'_master_'.$field,$item[$field] ?? '');
        update_post_meta($id,'_master_example','1');
    }
    update_option('master_content_initialized', 1, false);
}

function master_media_url($value) {
    if (is_numeric($value)) return wp_get_attachment_image_url((int)$value, 'large') ?: '';
    if (str_starts_with((string)$value, '/assets/')) return get_template_directory_uri() . $value;
    return esc_url_raw($value);
}
function master_content_items($type) {
    $posts = get_posts(['post_type'=>$type,'post_status'=>'publish','numberposts'=>-1,'orderby'=>'menu_order date','order'=>'ASC']);
    return array_map(function ($post) use ($type) {
        $item = ['title'=>get_the_title($post),'description'=>wp_strip_all_tags($post->post_content)];
        if ($type === 'master_faq') return ['q'=>$item['title'],'a'=>$item['description']];
        if ($type === 'master_service') return $item + ['price'=>(float)get_post_meta($post->ID,'_master_price',true),'unit'=>get_post_meta($post->ID,'_master_unit',true),'example'=>(bool)get_post_meta($post->ID,'_master_example',true)];
        if ($type === 'master_work') return $item + [
            'before'=>master_media_url(get_post_meta($post->ID,'_master_before',true)),
            'after'=>master_media_url(get_post_meta($post->ID,'_master_after',true)),
            'duration'=>get_post_meta($post->ID,'_master_duration',true),
            'cost'=>get_post_meta($post->ID,'_master_cost',true),
            'example'=>(bool)get_post_meta($post->ID,'_master_example',true),
        ];
        return ['name'=>get_the_title($post),'text'=>wp_strip_all_tags($post->post_content),
            'date'=>get_post_meta($post->ID,'_master_date',true),
            'meta'=>get_post_meta($post->ID,'_master_meta',true),
            'rating'=>max(1,min(5,(int)get_post_meta($post->ID,'_master_rating',true))),
            'deal'=>get_post_meta($post->ID,'_master_deal',true),
            'avatar'=>master_media_url(get_post_meta($post->ID,'_master_avatar',true)),
            'example'=>(bool)get_post_meta($post->ID,'_master_example',true)];
    }, $posts);
}

add_action('add_meta_boxes', function () {
    foreach (['master_service'=>'Цена и единица расчёта','master_work'=>'Фотографии и детали','master_review'=>'Данные отзыва'] as $type=>$title)
        add_meta_box('master_fields', $title, 'master_render_fields', $type, 'normal', 'high');
});
function master_field($post, $key, $label, $kind='text') {
    $value = get_post_meta($post->ID,'_master_'.$key,true);
    echo '<p><label for="master-' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label><br>';
    if ($kind === 'media') {
        $url = master_media_url($value);
        echo '<span class="master-media-field"><input type="hidden" id="master-' . esc_attr($key) . '" name="master_' . esc_attr($key) . '" value="' . esc_attr($value) . '"><img src="' . esc_url($url) . '" alt="" style="max-width:220px;max-height:160px;display:block;margin:8px 0"><button type="button" class="button master-media-select">Выбрать фото</button> <button type="button" class="button master-media-clear">Удалить фото</button></span>';
    } elseif ($kind === 'checkbox') {
        echo '<input type="checkbox" id="master-' . esc_attr($key) . '" name="master_' . esc_attr($key) . '" value="1" ' . checked($value,'1',false) . '>';
    } elseif ($kind === 'rating') {
        echo '<select id="master-' . esc_attr($key) . '" name="master_' . esc_attr($key) . '">';
        foreach ([5,4,3,2,1] as $n) echo '<option value="'.$n.'" ' . selected((int)$value ?: 5,$n,false) . '>'.$n.'</option>';
        echo '</select>';
    } else {
        echo '<input style="width:100%;max-width:480px" type="' . ($kind === 'number' ? 'number' : 'text') . '" min="0" step="' . ($kind === 'number' ? '0.01' : '1') . '" id="master-' . esc_attr($key) . '" name="master_' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
    }
    echo '</p>';
}
function master_render_fields($post) {
    wp_nonce_field('master_save_fields','master_fields_nonce');
    if ($post->post_type === 'master_service') { master_field($post,'price','Цена от, ₽','number'); master_field($post,'unit','Единица расчёта: шт., м, час, выезд'); master_field($post,'example','Пример цены, не подтверждён мастером','checkbox'); }
    if ($post->post_type === 'master_work') { master_field($post,'before','Фото до (необязательно)','media'); master_field($post,'after','Фото после / основное','media'); master_field($post,'duration','Срок'); master_field($post,'cost','Стоимость'); master_field($post,'example','Заготовка, не выполненная работа','checkbox'); }
    if ($post->post_type === 'master_review') { master_field($post,'date','Дата отзыва'); master_field($post,'meta','Дополнительная информация'); master_field($post,'rating','Оценка','rating'); master_field($post,'deal','Статус сделки'); master_field($post,'avatar','Аватар','media'); master_field($post,'example','Демонстрационный отзыв','checkbox'); }
}
add_action('save_post', function ($id, $post) {
    $fields = [
        'master_service'=>['price','unit','example'],
        'master_work'=>['before','after','duration','cost','example'],
        'master_review'=>['date','meta','rating','deal','avatar','example'],
    ];
    if (!isset($fields[$post->post_type]) || !isset($_POST['master_fields_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['master_fields_nonce'])),'master_save_fields') || !current_user_can('edit_post',$id) || wp_is_post_revision($id)) return;
    foreach ($fields[$post->post_type] as $field) {
        $raw = wp_unslash($_POST['master_'.$field] ?? '');
        $value = in_array($field,['before','after','avatar'],true) ? (is_numeric($raw) ? absint($raw) : esc_url_raw($raw)) : sanitize_text_field($raw);
        if ($field === 'price') $value = max(0,(float)$value);
        if ($field === 'rating') $value = max(1,min(5,(int)$value));
        if ($field === 'example') $value = $raw ? '1' : '';
        update_post_meta($id,'_master_'.$field,$value);
    }
}, 10, 2);

add_action('admin_enqueue_scripts', function ($hook) {
    if (!str_contains($hook, 'master') && !in_array($hook,['post.php','post-new.php'],true)) return;
    wp_enqueue_media();
    wp_enqueue_script('master-admin-media', get_template_directory_uri() . '/admin-media.js', ['jquery'], '1.0', true);
});
