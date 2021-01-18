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
    
    // checking company's information
    $user = $w->get_company_by('company_uuid', $uuid);

    if ($user) {

        // language filter
        $filter_language = 'en';
        if (isset($_GET['lang']) && !empty($_GET['lang'])) {
            $lang = normal_text($_GET['lang']);
            if (in_array($lang, $allowed_lang)) {
                $filter_language = $lang;
            }
        }
        $t = new Translation($db, $filter_language);
        
        // checking if the template is available
        $template = $w->get_template_by('template_id', $template_id);
        
        if ($template) {
            // gzip compression start
            ob_start("ob_gzhandler");

            // stars filter only if widget type is 'C'
            $filter_stars = false;
            if ($template['template_type'] === 'C' && isset($_GET['stars']) && !empty($_GET['stars']) && is_string($_GET['stars'])) {
                // matching data pattern
                $stars_arr = explode(',', $_GET['stars']);
                foreach ($stars_arr as $star) {
                    $star = normal_text($star);
                    if (is_numeric($star) && $star > 0 && $star <= 5) {
                        if (!$filter_stars) {
                            $filter_stars = [$star];
                        } else {
                            array_push($filter_stars, $star);
                        }
                    }
                }

                // sorting stars lowest to highest
                if($filter_stars !== false && count($filter_stars) > 1) {
                    sort($filter_stars);
                }
            }
            
            // mode filter
            $filter_mode = '';
            if (isset($_GET['theme']) && !empty($_GET['theme']) && is_string($_GET['theme'])) {
                $filter_mode = normal_text($_GET['theme']);
            }

            // checking if user's subscription allow template access
            if ($user['company_subscription'] !== $template['template_subscription'] && ($template['template_subscription'] === 'P' && $user['company_subscription'] === 'F')) {
                // user's subscription is F while template is P then choosing the first found free template
                $template = $w->get_template_by('template_subscription', 'F');

                if (!$template) {
                    put_response(400, 'error', 'no free template found.');
                }
            }

            // cache table query
            $widget_cache = $w->get_widget_template($uuid, $template['template_id'], $filter_language, $filter_mode, filter_stars_to_text($filter_stars));
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
            $rating = $w->get_rating_by('rating_uuid', $user['company_uuid']);
            $reviews = $w->get_reviews_by('review_uuid', $user['company_uuid'], 'review_author_id', true);

            if ($rating) {
                // check the company interval for refreshing place data
                $company_interval = $user['company_interval'];
                if (empty($company_interval)) {
                    $company_interval = '30 days';
                }
                $interval_expiry = strtotime($company_interval, strtotime($rating['rating_last_update']));
                $current_time = strtotime('now');
                if ($interval_expiry <= $current_time) {
                    $rating = $w->update_place_data($user['company_uuid'], $user['company_place_id'], $reviews, $filter_language, $settings->get('google_api_key'), true);
                }
            } else {
                // getting new place data
                $rating = $w->update_place_data($user['company_uuid'], $user['company_place_id'], $reviews, $filter_language, $settings->get('google_api_key'), false);
            }

            // checking if there's any change in reviews data
            if (isset($rating['review_change']) && $rating['review_change'] === true) {
                $reviews = $w->get_reviews_by('review_uuid', $user['company_uuid'], 'review_author_id', true);
            }
            
            // cache not found, create a new widget cache

            $branding = '';
            if ($user['company_subscription'] === 'F') {
                $branding = $settings->get('branding_html');
            }
            
            // replacing placeholders in the html
            if ($template['template_type'] === 'S') {
                // if widget template is without reviews 
                $template['template_html'] = htmlspecialchars_decode($template['template_html'], ENT_QUOTES);
                $replaced_html = $w->replace_placeholders($template['template_html'], $rating, $user['company_place_id'], $theme_class[$filter_mode], $branding);
            } else {
                // with comment reviews
                $template['template_html'] = htmlspecialchars_decode($template['template_html'], ENT_QUOTES);

                // applying filter on review comments
                if ($filter_stars && count($reviews) > 0) {
                    $filtered_reviews = [];
                    foreach ($reviews as $review) {
                        if (in_array($review['review_rating'], $filter_stars)) {
                            array_push($filtered_reviews, $review);
                        }
                    }
                    $reviews = $filtered_reviews;
                }
                
                $replaced_html = $w->replace_placeholders_reviews($template['template_html'], $rating, $reviews, $user['company_place_id'], $theme_class[$filter_mode], $branding);
            }
            $replaced_html = $w->minify_html($replaced_html);

            // adding/updating cache
            $widget_cache = $w->insert_widget_cache($user['company_uuid'], $template['template_id'], $replaced_html, $widget_cache_expired, $filter_language, $filter_mode, filter_stars_to_text($filter_stars));
            // if cache insertion failed
            if (!$widget_cache) {
                put_response(500, 'error', 'Server cannot cache the request');
            }

            put_response(200, 'success', $widget_cache['cache_html']);

        } else {
            put_response(403, 'error', 'Invalid template');
        }
    } else {
        put_response(403, 'error', 'Invalid company');
    }
}

put_response(400, 'error', 'Invalid Request.');
