<?php

include('modules.php');

$modules = MODULES['regular']['silverstripe'];
$module = 'silverstripe-campaign-admin';

$account = 'silverstripe';
$repo = 'silverstripe-campaign-admin';

// hit github api
$token = $argv[1];

function fetch($path) {
    global $token;
    $ch = curl_init();
    curl_setopt($ch ,CURLOPT_URL, 'https://api.github.com' . $path);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/vnd.github.v3+json',
        "Authorization: $token"
    ];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    echo "Fetching from $path\n";
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res);
}

# otherwise newer standards
https://github.com/emteknetnz/rhino/blob/main/app/src/Processors/StandardsProcessor.php

# work out the highest next-minor branch e.g. '4'
$ref = 0;
$json = $requester->fetch("/repos/$account/$repo/branches");
foreach ($json->root ?? [] as $branch) {
    if (!$branch) {
        continue;
    }
    $name = $branch->name;
    if (!preg_match('#^([1-9])$#', $name)) {
        continue;
    }
    if ((int) $name > (int) $ref) {
        $ref = $name;
    }
}
if (!$ref) {
    $ref = 'master';
}

$json = fetch("/repos/$account/$repo/contents/.travis.yml?ref=$ref");
var_dump($json);
