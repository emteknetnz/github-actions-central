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
    $url = 'https://api.github.com' . $path;
    echo "Fetching from $url\n";
    curl_setopt($ch ,CURLOPT_URL, $url);
    $headers = [
        'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:28.0) Gecko/20100101 Firefox/28.0',
        'Accept: application/vnd.github.v3+json'
    ];
    if (strpos($token, ':') !== false) {
        // user:token style
        curl_setopt($ch, CURLOPT_USERPWD, $token);
    } else {
        // token only
        $headers[] = "Authorization: $token";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    var_dump($res);
    curl_close($ch);
    return json_decode($res);
}

# otherwise newer standards
https://github.com/emteknetnz/rhino/blob/main/app/src/Processors/StandardsProcessor.php

# work out the highest next-minor branch e.g. '4'
$ref = 0;
$json = fetch("/repos/$account/$repo/branches");
var_dump($json);
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
