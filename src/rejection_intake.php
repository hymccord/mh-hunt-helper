<?php

define('not_direct_access', TRUE);
require_once "check-cors.php";
require_once "uuid.php";
require_once "check-ban.php";
require_once "check-time.php";
require_once "config.php";
require_once "check-version.php";
require_once "db-connect.php";
require_once "send_response.php";

foreach(['pre', 'post', 'hunter_id_hash', 'entry_timestamp', 'extension_version'] as $field) {
    if (empty($_POST[$field])) {
        error_log("Missing $field in rejection_intake.");
        sendResponse('success', "Thanks for the hunt info!");
    }
}

recordRejectionsInFile();
sendResponse('success', "Thanks for the hunt info!");

function recordRejectionsInFile($limit = 250) {
    $timestamp = $_POST['entry_timestamp'];
    $pre = $_POST['pre'];
    $post = $_POST['post'];

    $file_name = 'rejections.json';

    $data = file_get_contents($file_name);

    if (!empty($data)) {
        $data = json_decode($data, true);
        krsort($data, 1); // sort it by timestamp descending

        if (count($data) >= $limit) {
            $data = array_slice($data, 0, $limit - 1, true); // Limit to last limit - 1 entries
        }
    }

    $data[$timestamp] = [
        'date' => date('Y-m-d\TH:i:s', $timestamp),
        'extension_version' => $_POST['extension_version'],
        'mouse' => $pre['mouse'],
        'pre' => getEnvironmentData($pre),
        'post' => getEnvironmentData($post),
    ];
    krsort($data, 1); // sort it by timestamp descending

    file_put_contents($file_name, json_encode($data));
}

function getEnvironmentData($rejectionData) {
    return [
        'location' => $rejectionData['location'],
        'stage' => $rejectionData['stage'],
    ];
}

?>
