<?php

// filter the text to stay a bit safe from potiential threats
function normal_text($data)
{
    if (gettype($data) !== "array") {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    return '';
}

// returing the original text back
function normal_text_back($text)
{
    if (gettype($text) !== "array") {
        return htmlspecialchars_decode(trim($text), ENT_QUOTES);
    }
    return '';
}

// returns the date into more of a human readable
function normal_date($date, $format = 'M d, Y h:i A')
{
    $d = date_create($date);
    return date_format($d, $format);
}

// returns the current date based on set timezone
function current_date($format = 'Y-m-d H:i:s')
{
    return date($format);
}

// whenever to redirect :)
function go($url)
{
    header("location: " . $url);
    die();
}

function put_response ($status_code, $type, $message)
{
    http_response_code($status_code);
    echo json_encode(['code' => $status_code, 'type' => $type, 'message' => $message]);
    die();
}

function filter_stars_to_text ($stars_arr)
{
    if ($stars_arr === false) {
        return '';
    }
    return implode(',', $stars_arr);
}
