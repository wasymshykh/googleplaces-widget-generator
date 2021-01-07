<?php

require_once '../config/init.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: access");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// checking if the request has uuid and place
if (isset($_GET['uuid']) && isset($_GET['template']) && is_string($_GET['uuid']) && is_string($_GET['template']) && !empty(normal_text($_GET['uuid'])) && !empty(normal_text($_GET['template']))) {

    $uuid = normal_text($_GET['uuid']);
    $template_id = normal_text($_GET['template']);

    $w = new Widget($db);
    
    // checking user's information
    $user = $w->get_customer_by('customer_uuid', $uuid);

    if ($user) {
        
        // checking if the template is available
        $template = $w->get_template_by('template_id', $template_id);
        
        if ($template) {

            // checking if user's subscription allow template access
            if ($user['customer_subscription'] === $template['template_subscription'] || $template['template_subscription'] === 'B') {

                // cache table query
                $widget_cache = $w->get_widget_template($uuid, $template_id);
            
                if (!$widget_cache) {
                    // cache not found, create a new widget cache
                    
                    // getting place rating records
                    $rating = $w->get_rating_by('rating_uuid', $user['customer_uuid']);

                    if ($rating) {
                        // check for refreshing data
                        $customer = $user['customer_interval'];
                        
                    } else {
                        // getting new data
                        $rating = $w->update_place_data($user['customer_uuid'], $user['customer_place_id'], $settings->get('google_api_key'), false);
                    }

                    die();
            
                } else {
                    
                    // cache found, check for cache expiry if it is older than setting's defined cache limit
                    $cache_lifetime = $settings->get('cache_lifetime');
                    
                    $widget_expiry = strtotime($cache_lifetime, $widget_cache['cache_created']);
                    $current_time = strtotime('now');

                    if (($widget_expiry <= $current_time)) {

                        echo 'Yeah';
                        die(var_dump($widget_cache));

                    } else {
                        echo 'Whoops expired';
                        die();
                    }
                }

            } else {
                put_response(403, 'error', 'Widget template cannot be requested in current subscription');
            }
        } else {
            put_response(403, 'error', 'Invalid template');
        }
    } else {
        put_response(403, 'error', 'Invalid customer');
    }
}

put_response(400, 'error', 'Invalid Request.');
