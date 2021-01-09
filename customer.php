<?php

require_once 'config/init.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: access");
header("Access-Control-Allow-Methods: GET,POST");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$c = new Customer($db);

if (empty($_GET) && empty($_POST)) {
    $customers = $c->get_customers();
    put_response(200, 'success', $customers);
}

if (isset($_GET['uuid'])) {
    // return the customer with uuid
    if (!is_string($_GET['uuid']) || empty(normal_text($_GET['uuid']))) {
        put_response(400, 'error', 'customer uuid cannot be empty');
    } else {
        $uuid = normal_text($_GET['uuid']);
        $customer = $c->get_customer_by('customer_uuid', $uuid);
        if ($customer) {
            put_response(200, 'success', $customer);
        } else {
            put_response(403, 'error', 'No customer found.');
        }
    }
}

if (isset($_POST['create'])) {

    $errors = [];

    if (!isset($_POST['uuid']) || !is_string($_POST['uuid']) || empty($_POST['uuid'])) {
        array_push($errors, 'Customer uuid cannot be empty.');
    } else {
        $uuid = normal_text($_POST['uuid']);
        $customer = $c->get_customer_by('customer_uuid', $uuid);
        if ($customer) {
            array_push($errors, 'Customer with same uuid exists');
        }
    }

    if (!isset($_POST['place']) || !is_string($_POST['place']) || empty(normal_text($_POST['place']))){
        array_push($errors, 'Place cannot be empty.');
    } else {
        $place = normal_text($_POST['place']);
    }

    if (!isset($_POST['subscription']) || !is_string($_POST['subscription']) || empty(normal_text($_POST['subscription']))){
        array_push($errors, 'Subscription cannot be empty.');
    } else {
        $subscription = normal_text($_POST['subscription']);
        if ($subscription !== 'F' && $subscription !== 'P') {
            array_push($errors, 'Subscription value can only be F or P');
        } else {

            if (!isset($_POST['interval']) || !is_string($_POST['interval']) || empty($_POST['interval'])){
                if ($subscription === 'F') {
                    $interval = '30 days';
                } else if ($subscription === 'P') {
                    $interval = '1 day';
                }
            } else {
                $interval = $_POST['interval'];
            }

        }
    }

    if (!isset($_POST['status']) || !is_string($_POST['status']) || empty($_POST['status'])){
        $status = 'A';
    } else {
        $status = normal_text($_POST['status']);
        if ($status !== 'A' || $status !== 'I') {
            array_push($errors, 'Status value can only be A or I');
        }
    }
    
    if (empty($errors)) {
        $result = $c->create_customer($uuid, $place, $subscription, $status, $interval);
        if ($result) {
            put_response(200, 'success', 'Customer is successfully added.');
        } else {
            put_response(500, 'error', 'Cannot add the customer.');
        }

    } else {
        put_response(403, 'error', $errors);
    }

}


if (isset($_POST['delete'])) {

    if (!is_string($_POST['delete']) || empty($_POST['delete'])) {
        put_response(403, 'error', 'provide uuid in delete key.');
    } else {
        $uuid = normal_text($_POST['delete']);
        $customer = $c->get_customer_by('customer_uuid', $uuid);
        if (!$customer) {
            put_response(403, 'error', 'customer cannot be found.');
        }
    }

    $result = $c->delete_customer_data($uuid);
    if ($result) {
        put_response(200, 'success', 'Customer and linked data is successfully deleted.');
    } else {
        put_response(500, 'error', 'Cannot delete the customer.');
    }

}


if (isset($_POST['update'])) {

    if (!is_string($_POST['update']) || empty($_POST['update'])) {
        put_response(403, 'error', 'provide uuid in update key.');
    } else {
        $uuid = normal_text($_POST['update']);
        $customer = $c->get_customer_by('customer_uuid', $uuid);
        if (!$customer) {
            put_response(403, 'error', 'customer cannot be found.');
        }
    }

    $errors = [];
    $update = [];

    if (isset($_POST['uuid']) && isset($_POST['uuid']) && !empty($_POST['uuid'])) {
        $n_uuid = normal_text($_POST['uuid']);
        $n_customer = $c->get_customer_by('customer_uuid', $n_uuid);
        if ($n_customer) {
            array_push($errors, 'Customer with same uuid exists');
        } else {
            if ($customer['customer_uuid'] !== $n_uuid) {
                $update['customer_uuid'] = $n_uuid;
            }
        }
    }

    if (isset($_POST['place']) && isset($_POST['place']) && !empty($_POST['place'])) {
        $n_place = normal_text($_POST['place']);
        if ($customer['customer_place_id'] !== $n_place) {
            $update['customer_place_id'] = $n_place;
        }
    }

    if (isset($_POST['subscription']) && isset($_POST['subscription']) && !empty($_POST['subscription'])) {
        $n_subscription = normal_text($_POST['subscription']);
        if ($customer['customer_subscription'] !== $n_subscription) {
            if ($n_subscription !== 'F' && $n_subscription !== 'P') {
                array_push($errors, 'Subscription value can only be F or P');
            } else {
                $update['customer_subscription'] = $n_subscription;
            }
        }
    }

    if (isset($_POST['status']) && isset($_POST['status']) && !empty($_POST['status'])) {
        $n_status = normal_text($_POST['status']);
        if ($customer['customer_status'] !== $n_status) {
            if ($n_status !== 'A' && $n_status !== 'I') {
                array_push($errors, 'Status value can only be A or I');
            } else {
                $update['customer_status'] = $n_status;
            }
        }
    }

    if (isset($_POST['interval']) && isset($_POST['interval']) && !empty($_POST['interval'])) {
        $n_interval = normal_text($_POST['interval']);
        if ($customer['customer_interval'] !== $n_interval) {
            $update['customer_interval'] = $n_interval;
        }
    }

    if (!empty($errors)) {
        put_response(403, 'error', $errors);
    }

    if (!empty($update)) {
        $result = $c->update_customer($update, $customer['customer_uuid']);
        if ($result) {
            put_response(200, 'success', 'Customer is successfully updated.');
        } else {
            put_response(500, 'error', 'Cannot update the customer.');
        }
    } else {
        put_response(200, 'success', 'No changes made.');
    }

}

put_response(400, 'error', 'no response is returned.');
