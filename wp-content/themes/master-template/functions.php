<?php
add_action('after_setup_theme', function () { add_theme_support('title-tag'); });

function master_social_url($value, $network) {
    $url = esc_url_raw(trim((string)$value));
    if ($url === '' || wp_parse_url($url, PHP_URL_SCHEME) !== 'https') return '';
    $host = strtolower(rtrim((string)wp_parse_url($url, PHP_URL_HOST), '.'));
    $domains = ['telegram'=>['t.me','telegram.me'], 'vk'=>['vk.com','vk.ru','vk.me'], 'avito'=>['avito.ru']];
    if (!$host || !isset($domains[$network])) return '';
    foreach ($domains[$network] as $domain) if ($host === $domain || str_ends_with($host, '.'.$domain)) return $url;
    return '';
}

function master_config() {
    $default = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
    $saved = get_option('master_site_config');
    $config = array_replace_recursive($default, is_array($saved) ? $saved : []);
    $config['copy'] = array_replace(master_default_copy(), (array)get_option('master_copy', []));
    $config['badge'] = ['rating'=>(float)get_option('master_avito_rating', 4.9), 'count'=>get_option('master_avito_count', 118)];
    $social_links = get_option('master_social_links');
    foreach (['telegram','vk','avito'] as $network) $config['social'][$network] = master_social_url(is_array($social_links) ? ($social_links[$network] ?? '') : ($config['social'][$network] ?? ''), $network);
    $base = get_template_directory_uri();
    foreach (['photo'] as $key) {
        if (!empty($config[$key]) && str_starts_with($config[$key], '/assets/')) $config[$key] = $base . $config[$key];
    }
    foreach ($config['works'] ?? [] as &$work) {
        foreach (['before','after'] as $key) if (!empty($work[$key]) && str_starts_with($work[$key], '/assets/')) $work[$key] = $base . $work[$key];
    }
    if (get_option('master_content_initialized')) {
        $config['services'] = master_content_items('master_service');
        $config['works'] = master_content_items('master_work');
        $config['reviews']['items'] = array_values(array_filter(master_content_items('master_review'), fn($review) => empty($review['example'])));
        $distribution = array_fill_keys([1,2,3,4,5], 0);
        foreach ($config['reviews']['items'] as $review) $distribution[(int)$review['rating']]++;
        $config['reviews']['distribution'] = $distribution;
        $config['reviews']['example'] = false;
    }
    if (get_option('master_faq_initialized')) $config['faq'] = master_content_items('master_faq');
    $profile_photo = (int)get_option('master_profile_photo');
    if ($profile_photo) $config['photo'] = wp_get_attachment_image_url($profile_photo, 'large') ?: $config['photo'];
    $logo = (int)get_option('master_site_logo');
    $avito_logo = (int)get_option('master_avito_logo');
    $config['logo'] = $logo ? (wp_get_attachment_image_url($logo, 'medium') ?: '') : '';
    $config['avitoLogo'] = $avito_logo ? (wp_get_attachment_image_url($avito_logo, 'thumbnail') ?: '') : '';
    $config['icons'] = [];
    foreach (['telegram','vk','avito'] as $network) {
        $icon = (int)get_option('master_icon_'.$network);
        $config['icons'][$network] = $icon ? (wp_get_attachment_image_url($icon, 'thumbnail') ?: '') : '';
    }
    return $config;
}

require_once __DIR__ . '/admin-settings.php';

add_action('init', function () {
    register_post_type('master_lead', ['label'=>'Заявки мастера','public'=>false,'show_ui'=>true,'show_in_menu'=>'master-site','supports'=>['title']]);
});
add_filter('manage_master_lead_posts_columns', function ($columns) {
    return ['cb'=>$columns['cb'], 'title'=>'Заявка', 'master_status'=>'Доставка', 'date'=>'Дата'];
});
add_action('manage_master_lead_posts_custom_column', function ($column, $id) {
    if ($column === 'master_status') echo esc_html(get_post_meta($id, '_master_status', true) ?: 'pending');
}, 10, 2);
add_action('add_meta_boxes_master_lead', function () {
    add_meta_box('master_lead_details', 'Детали заявки', function ($post) {
        $lead = get_post_meta($post->ID, '_master_lead', true);
        if (!is_array($lead)) return;
        foreach ($lead as $key=>$value) echo '<p><strong>' . esc_html($key) . ':</strong> ' . esc_html($value) . '</p>';
        echo '<p><strong>Доставка:</strong> ' . esc_html(get_post_meta($post->ID, '_master_status', true)) . '</p>';
        echo '<p><strong>Попыток:</strong> ' . esc_html(get_post_meta($post->ID, '_master_attempts', true)) . '</p>';
        echo '<p><strong>Ошибка:</strong> ' . esc_html(get_post_meta($post->ID, '_master_error', true)) . '</p>';
    }, 'master_lead');
});
add_action('rest_api_init', function () {
    register_rest_route('master/v1', '/config', ['methods'=>'GET','callback'=>fn()=>rest_ensure_response(master_config()),'permission_callback'=>'__return_true']);
    register_rest_route('master/v1', '/leads', ['methods'=>'POST','callback'=>'master_receive_lead','permission_callback'=>'__return_true']);
});

function master_receive_lead(WP_REST_Request $request) {
    $data = $request->get_json_params();
    if (!is_array($data)) return new WP_Error('invalid_data','Некорректные данные',['status'=>400]);
    if (!empty($data['website'])) return new WP_Error('spam','Не удалось отправить заявку',['status'=>400]);
    $phone = preg_replace('/[^\d+]/', '', (string)($data['phone'] ?? ''));
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) < 10 || strlen($digits) > 15) return new WP_Error('phone','Введите корректный номер телефона',['status'=>422]);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $key = 'master_rate_' . hash('sha256', $ip . '|' . $phone);
    $count = (int)get_transient($key);
    if ($count >= 2) return new WP_Error('rate','Слишком много заявок. Позвоните мастеру напрямую.',['status'=>429]);
    $lead = [
        'phone'=>$phone,
        'name'=>sanitize_text_field(mb_substr((string)($data['name'] ?? ''),0,80)),
        'page'=>esc_url_raw(mb_substr((string)($data['page'] ?? ''),0,300)),
        'referrer'=>esc_url_raw(mb_substr((string)($data['referrer'] ?? ''),0,300)),
        'created'=>wp_date('d.m.Y H:i:s T', null, new DateTimeZone(getenv('SITE_TIMEZONE') ?: 'Europe/Samara'))
    ];
    $id = wp_insert_post(['post_type'=>'master_lead','post_status'=>'private','post_title'=>'Заявка · ' . $phone . ' · ' . $lead['created']], true);
    if (is_wp_error($id)) return new WP_Error('storage','Не удалось сохранить заявку. Попробуйте ещё раз или позвоните.',['status'=>500]);
    update_post_meta($id, '_master_lead', $lead);
    update_post_meta($id, '_master_status', 'pending');
    update_post_meta($id, '_master_attempts', 0);
    set_transient($key, $count + 1, 5 * MINUTE_IN_SECONDS);
    master_deliver_lead($id);
    return rest_ensure_response(['ok'=>true,'id'=>$id,'queued'=>get_post_meta($id,'_master_status',true) !== 'sent']);
}

function master_deliver_lead($id) {
    $lead = get_post_meta($id, '_master_lead', true);
    if (!is_array($lead)) return false;
    $channel = getenv('LEAD_CHANNEL') ?: '';
    $message = "Новая заявка №$id\nТелефон: {$lead['phone']}\nИмя: {$lead['name']}\nДата: {$lead['created']}\nСтраница: {$lead['page']}\nИсточник: {$lead['referrer']}";
    $response = null;
    if ($channel === 'telegram' && getenv('TELEGRAM_BOT_TOKEN') && getenv('TELEGRAM_CHAT_ID')) {
        $url = 'https://api.telegram.org/bot' . rawurlencode(getenv('TELEGRAM_BOT_TOKEN')) . '/sendMessage';
        $response = wp_remote_post($url, ['timeout'=>10,'body'=>['chat_id'=>getenv('TELEGRAM_CHAT_ID'),'text'=>$message]]);
    } elseif ($channel === 'vk' && getenv('VK_GROUP_TOKEN') && getenv('VK_PEER_ID')) {
        $response = wp_remote_post('https://api.vk.com/method/messages.send', ['timeout'=>10,'body'=>['access_token'=>getenv('VK_GROUP_TOKEN'),'peer_id'=>getenv('VK_PEER_ID'),'random_id'=>$id,'message'=>$message,'v'=>'5.199']]);
    }
    $body = is_wp_error($response) || !$response ? [] : json_decode(wp_remote_retrieve_body($response), true);
    $sent = $channel === 'telegram' ? !empty($body['ok']) : ($channel === 'vk' && !empty($body['response']));
    update_post_meta($id, '_master_attempts', (int)get_post_meta($id,'_master_attempts',true)+1);
    if ($sent) { update_post_meta($id, '_master_status', 'sent'); delete_post_meta($id,'_master_error'); }
    else update_post_meta($id, '_master_error', $channel ? 'Сервис не подтвердил доставку или канал настроен не полностью' : 'Канал уведомлений не настроен');
    return $sent;
}
add_filter('cron_schedules', function ($s) { $s['master_five_minutes'] = ['interval'=>300,'display'=>'Каждые пять минут']; return $s; });
add_action('init', function () { if (!wp_next_scheduled('master_retry_leads')) wp_schedule_event(time()+300,'master_five_minutes','master_retry_leads'); });
add_action('master_retry_leads', function () {
    $ids = get_posts(['post_type'=>'master_lead','post_status'=>'private','numberposts'=>20,'fields'=>'ids','meta_key'=>'_master_status','meta_value'=>'pending']);
    foreach ($ids as $id) if ((int)get_post_meta($id,'_master_attempts',true) < 20) master_deliver_lead($id);
});
add_action('template_redirect', function () {
    if (!isset($_GET['master_policy'])) return;
    header('Content-Type: text/html; charset=utf-8');
    $html = file_get_contents(__DIR__ . '/policy.html');
    $html = str_replace('href="/style.css"', 'href="' . esc_url(get_template_directory_uri() . '/site.css') . '"', $html);
    $html = str_replace('href="/"', 'href="' . esc_url(home_url('/')) . '"', $html);
    echo $html; exit;
});
require_once __DIR__ . '/content-admin.php';
