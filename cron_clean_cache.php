<?php

require_once 'config/init.php';

if (!isset($_GET['key']) || empty($_GET['key'])) {
    put_response(403, 'error', 'you donot have permission to access this route');
}

if ($settings->get('cron_secret_key') !== $_GET['key']) {
    put_response(403, 'error', 'you provided invalid key');
}

$w = new Widget($db);

// getting all the cache widgets
$widgets = $w->get_widget_templates();

$delete_widgets = [];
foreach ($widgets as $widget) {
    
    $expiring = strtotime($settings->get('cache_lifetime'), strtotime($widget['cache_created']));
    $current = strtotime('now');
    
    date('Y-m-d h:i:s', $expiring);

    if ($expiring <= $current) {
        array_push($delete_widgets, ['uuid'=>$widget['cache_uuid'], 'template_id'=>$widget['cache_template_id'], 'lang' => $widget['cache_lang']]);
    }
}

if (count($delete_widgets) > 0) {
    $w->delete_widgets_caches($delete_widgets);
}
