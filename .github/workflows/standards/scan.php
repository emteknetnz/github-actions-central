<?php

include('modules.php');

$modules = MODULES['regular']['silverstripe'];
$module = 'silverstripe-campaign-admin';

$account = 'silverstripe';
$repo = 'silverstripe-asset-admin';

// github token
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
    curl_close($ch);
    $json = json_decode($res);
    // files contents
    if (strpos($path, '?ref') !== false) {
        if ($json && ($json->message ?? '') != 'Not Found' && $json->content) {
            return base64_decode($json->content);
        }
        return '';
    }
    // non file
    return $json;
}

// get the highest next-minor branch e.g. '4' which is assumed to be the default branch
function getDefaultRef($account, $repo) {
    $ref = 0;
    $json = fetch("/repos/$account/$repo/branches");
    var_dump($json);
    foreach ($json ?? [] as $branch) {
        if (!$branch) {
            continue;
        }
        $name = $branch->name;
        if (!preg_match('#^([1-9]+)$#', $name)) {
            continue;
        }
        if ((int) $name > (int) $ref) {
            $ref = $name;
        }
    }
    if (!$ref) {
        $ref = 'master';
    }
    return $ref;
}

// https://github.com/emteknetnz/rhino/blob/main/app/src/Processors/StandardsProcessor.php

// get a list of composer requirements from old .travis files
function getTravisComposerReqs($contents) {
    $reqs = [];
    $contents = str_replace(['"', "'"], '', $contents);
    $arrs = [
        ['REQUIRE_RECIPE_CORE', 'silverstripe/recipe-core'],
        ['REQUIRE_INSTALLER', 'silverstripe/installer'],
        ['REQUIRE_CWP_CWP_RECIPE_CMS', 'cwp/cwp-recipe-cms'],
        ['REQUIRE_RECIPE_TESTING', 'silverstripe/recipe-testing'],
        ['REQUIRE_FRAMEWORKTEST', 'silverstripe/frameworktest'],
        ['REQUIRE_CWP_STARTER_THEME', 'silverstripe/cwp-starter-theme'],
        ['REQUIRE_GRAPHQL', 'silverstripe/graphql'], // TODO ensure not in silverstripe/graphql now
    ];
    foreach (array_values($arrs) as $arr) {
        list($var, $repo) = $arr;
        if (preg_match("#$var=(.+?)(\n|$)#", $contents, $m)) {
            $reqs[$repo] = $m[1];
        }
    }
    if (preg_match("#REQUIRE_EXTRA=(.+?)(\n|$)#", $contents, $m)) {
        foreach (explode(' ', $m[1]) as $s) {
            list($repo, $ver) = preg_split('#[: ]#', $s);
            $reqs[$repo] = $ver;
        }
    }
    return $reqs;
}

// SECURITY.md
function getSecurityPolicy($contents) {
    // TODO - probably compare with a template file in the repo
    return 'exists';
}

// contributing.md
function getContributing($contents) {
    // TODO - probably compare with a template file in the repo
    return 'exists';
}

// LICENSE
function getLicense($contents) {
    // TODO - probably compare with a template file in the repo
    return 'exists';
}


$ref = getDefaultRef($account, $repo);
$res = [];
$arrs = [
    ['.travis.yml', 'getTravisComposerReqs'],
    ['.SECURITY.md', 'getSecurityPolicy'],
    ['.contributing.md', 'getContributing'],
    ['LICENSE', 'getLicense'],
];
foreach ($arrs as $arr) {
    list($filename, $fn) = $arr;
    $key = strtolower(substr($fn, 3, 1)) . substr($fn, 4);
    if ($contents = fetch("/repos/$account/$repo/contents/$filename?ref=$ref")) {
        $res[$key] = call_user_func($fn, $contents);
    } else {
        $res[$key] = 'missing';
    }
}

print_r($res);
