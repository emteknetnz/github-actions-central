<?php

include('modules.php');

$modules = MODULES['regular']['silverstripe'];
$module = 'silverstripe-campaign-admin'

// hit github api
$token = $argv[1];

function fetch($path, $token) {
    $ch = curl_init();
    curl_setopt($ch ,CURLOPT_URL, 'https://api.github.com' . $path);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/vnd.github.v3+json',
        "Authorization: $token"
    ];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($tuCurl);
    curl_close($ch);
    return $res;
}

# old standards is probably all that's needed
# https://github.com/emteknetnz/silverstripe-community-info/blob/master/standards.php

# otherwise newer standards
https://github.com/emteknetnz/rhino/blob/main/app/src/Processors/StandardsProcessor.php
