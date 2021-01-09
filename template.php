<?php

require_once 'config/init.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: access");
header("Access-Control-Allow-Methods: GET,POST");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$t = new Template($db);

if (empty($_GET) && empty($_POST)) {
    $templates = $t->get_templates();
    put_response(200, 'success', $templates);
}

if (isset($_GET['id'])) {
    // return the template with id
    if (!is_string($_GET['id']) || empty(normal_text($_GET['id']))) {
        put_response(400, 'error', 'template id cannot be empty');
    } else {
        $id = normal_text($_GET['id']);
        $template = $t->get_template_by('template_id', $id);
        if ($template) {
            put_response(200, 'success', $template);
        } else {
            put_response(403, 'error', 'No template found.');
        }
    }
}

if (isset($_POST['delete'])) {

    if (!is_string($_POST['delete']) || empty($_POST['delete'])) {
        put_response(403, 'error', 'provide template id in delete key.');
    } else {
        $id = normal_text($_POST['delete']);
        $template = $t->get_template_by('template_id', $id);
        if (!$template) {
            put_response(403, 'error', 'template cannot be found.');
        }
    }

    $result = $t->delete_template_data($template['template_id']);
    if ($result) {
        put_response(200, 'success', 'Template and linked data is successfully deleted.');
    } else {
        put_response(500, 'error', 'Cannot delete the template.');
    }

}


if (isset($_POST['create'])) {

    $errors = [];

    if (!isset($_POST['id']) || !is_string($_POST['id']) || empty($_POST['id'])) {
        array_push($errors, 'Template id cannot be empty.');
    } else {
        $id = normal_text($_POST['id']);
        $template = $t->get_template_by('template_id', $id);
        if ($template) {
            array_push($errors, 'Template with same id exists');
        }
    }

    if (!isset($_POST['html']) || !is_string($_POST['html']) || empty(normal_text($_POST['html']))){
        array_push($errors, 'Html cannot be empty.');
    } else {
        $html = normal_text($_POST['html']);
    }

    if (!isset($_POST['subscription']) || !is_string($_POST['subscription']) || empty(normal_text($_POST['subscription']))){
        array_push($errors, 'Subscription cannot be empty.');
    } else {
        $subscription = normal_text($_POST['subscription']);
        if ($subscription !== 'F' && $subscription !== 'P') {
            array_push($errors, 'Subscription value can only be F or P');
        }
    }

    if (!isset($_POST['type']) || !is_string($_POST['type']) || empty(normal_text($_POST['type']))){
        array_push($errors, 'Widget type cannot be empty.');
    } else {
        $type = normal_text($_POST['type']);
        if ($type !== 'S' && $type !== 'C') {
            array_push($errors, 'Widget type value can only be S or C');
        }
    }

    if (empty($errors)) {
        $result = $t->create_template($id, $html, $subscription, $type);
        if ($result) {
            put_response(200, 'success', 'Template is successfully added.');
        } else {
            put_response(500, 'error', 'Cannot add the template.');
        }
    } else {
        put_response(403, 'error', $errors);
    }

}

if (isset($_POST['update'])) {

    if (!is_string($_POST['update']) || empty($_POST['update'])) {
        put_response(403, 'error', 'provide template id in update key.');
    } else {
        $id = normal_text($_POST['update']);
        $template = $t->get_template_by('template_id', $id);
        if (!$template) {
            put_response(403, 'error', 'template cannot be found.');
        }
    }
    
    $errors = [];
    $update = [];

    if (isset($_POST['id']) && isset($_POST['id']) && !empty($_POST['id'])) {
        $n_id = normal_text($_POST['id']);
        $n_template = $t->get_template_by('template_id', $n_id);
        if ($n_template) {
            array_push($errors, 'Template with same id exists');
        } else {
            if ($template['template_id'] !== $n_id) {
                $update['template_id'] = $n_id;
            }
        }
    }

    if (isset($_POST['html']) && isset($_POST['html']) && !empty($_POST['html'])) {
        $n_html = normal_text($_POST['html']);
        if ($template['template_html'] !== $n_html) {
            $update['template_html'] = $n_html;
        }
    }

    if (isset($_POST['subscription']) && isset($_POST['subscription']) && !empty($_POST['subscription'])) {
        $n_subscription = normal_text($_POST['subscription']);
        if ($template['template_subscription'] !== $n_subscription) {
            if ($n_subscription !== 'F' && $n_subscription !== 'P') {
                array_push($errors, 'Subscription value can only be F or P');
            } else {
                $update['template_subscription'] = $n_subscription;
            }
        }
    }

    if (isset($_POST['type']) && isset($_POST['type']) && !empty($_POST['type'])) {
        $n_type = normal_text($_POST['type']);
        if ($template['template_type'] !== $n_type) {
            if ($n_type !== 'S' && $n_type !== 'C') {
                array_push($errors, 'Widget type value can only be S or C');
            } else {
                $update['template_type'] = $n_type;
            }
        }
    }

    if (!empty($errors)) {
        put_response(403, 'error', $errors);
    }

    if (!empty($update)) {
        $result = $t->update_template($update, $template['template_id']);
        if ($result) {
            put_response(200, 'success', 'Template is successfully updated.');
        } else {
            put_response(500, 'error', 'Cannot update the template.');
        }
    } else {
        put_response(200, 'success', 'No changes made.');
    }

}



put_response(400, 'error', 'no response is returned.');
