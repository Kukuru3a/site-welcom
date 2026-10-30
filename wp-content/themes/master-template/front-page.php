<?php
$html = file_get_contents(__DIR__ . '/template.html');
$base = esc_url(get_template_directory_uri());
$html = str_replace('href="/style.css"', 'href="' . $base . '/site.css?v=' . filemtime(__DIR__ . '/site.css') . '"', $html);
$html = str_replace('src="/app.js"', 'src="' . $base . '/app.js?v=' . filemtime(__DIR__ . '/app.js') . '"', $html);
$html = str_replace('src="/assets/avito.png"', 'src="' . $base . '/assets/avito.png"', $html);
$html = str_replace('href="/policy"', 'href="' . esc_url(home_url('/?master_policy=1')) . '"', $html);
$html = str_replace('</head>', '<script>window.masterSite=' . wp_json_encode(['api' => esc_url_raw(rest_url('master/v1/')), 'home' => esc_url_raw(home_url('/')), 'logo' => esc_url_raw($base . '/assets/electrician-logo.jpg'), 'assets' => esc_url_raw($base . '/assets/')]) . ';</script></head>', $html);
echo $html; // Static template controlled by the theme; dynamic content is escaped in app.js.
