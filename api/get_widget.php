<?php

require_once '../config/init.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: access");
header("Access-Control-Allow-Methods: GET");
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
            if ($user['customer_subscription'] !== $template['template_subscription'] && ($template['template_subscription'] === 'P' && $user['customer_subscription'] === 'F')) {
                // user's subscription is F while template is P then choosing the first found free template
                $template = $w->get_template_by('template_subscription', 'F');

                if (!$template) {
                    put_response(400, 'error', 'no free template found.');
                }
            }

            // cache table query
            $widget_cache = $w->get_widget_template($uuid, $template['template_id']);
            $widget_cache_expired = false;

            if ($widget_cache) {
                $cache_lifetime = $settings->get('cache_lifetime');
                $widget_expiry = strtotime($cache_lifetime, strtotime($widget_cache['cache_created']));
                $current_time = strtotime('now');
                if ($widget_expiry > $current_time) {
                    put_response(200, 'success', $widget_cache['cache_html']);
                } else {
                    $widget_cache_expired = true;
                }
            }
            
            // getting place rating records
            $rating = $w->get_rating_by('rating_uuid', $user['customer_uuid']);
            if ($rating) {
                // check the customer interval for refreshing place data
                $customer_interval = $user['customer_interval'];
                if (empty($customer_interval)) {
                    $customer_interval = '30 days';
                }
                $interval_expiry = strtotime($customer_interval, strtotime($rating['rating_last_update']));
                $current_time = strtotime('now');
                if ($interval_expiry <= $current_time) {
                    $rating = $w->update_place_data($user['customer_uuid'], $user['customer_place_id'], $settings->get('google_api_key'), true);
                }
            } else {
                // getting new place data
                $rating = $w->update_place_data($user['customer_uuid'], $user['customer_place_id'], $settings->get('google_api_key'), false);
            }
            
            // cache not found, create a new widget cache
            
            // replacing placeholders in the html
            $template['template_html'] = htmlspecialchars_decode($template['template_html'], ENT_QUOTES);
            $replaced_html = $w->replace_placeholders($template['template_html'], $rating);
            
            // adding/updating cache
            $widget_cache = $w->insert_widget_cache($user['customer_uuid'], $template['template_id'], $replaced_html, $widget_cache_expired);
            // if cache insertion failed
            if (!$widget_cache) {
                put_response(500, 'error', 'Server cannot cache the request');
            }

            put_response(200, 'success', $widget_cache['cache_html']);

        } else {
            put_response(403, 'error', 'Invalid template');
        }
    } else {
        put_response(403, 'error', 'Invalid customer');
    }
}

put_response(400, 'error', 'Invalid Request.');
