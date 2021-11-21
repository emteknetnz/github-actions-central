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
function scanTravis($contents) {
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
    return ['reqs' => $reqs];
}

// ensure that comopser requires correct php version, phpunit, etc
function scanComposerJson($contents) {
    $reqs = [];
    $json = json_decode($contents);
    $reqs['phpunit'] = $json->{'require-dev'}->{'phpunit/phpunit'} ?? '';
    $reqs['recipe-testing'] = $json->{'require-dev'}->{'silverstripe/recipe-testing'} ?? '';
    $reqs['php'] = $json->{'require'}->{'php'} ?? '';
    return ['reqs' => $reqs]
}

function compareToTemplate($contents, $filename) {
    $contents = trim($contents);
    $path = "templates/$filename";
    if (!file_exists($path)) {
        return 'template-missing';
    }
    return $contents == trim(file_get_contents($path)) ? 'up-to-date' : 'different';
}

// SECURITY.md
function getSecurityPolicy($contents) {
    return compareToTemplate('SECURITY.MD', $contents);
}

// contributing.md
function getContributing($contents) {
    return compareToTemplate('contributing.md', $contents);
}

// LICENSE
function getLicense($contents) {
    return compareToTemplate('LICENSE', $contents);
}

$ref = getDefaultRef($account, $repo);
$res = [];
$arrs = [
    ['.travis.yml', 'scanTravis'],
    ['composer.json', 'scanComposerJson'],
    ['SECURITY.md', 'compareToTemplate'],
    ['contributing.md', 'compareToTemplate'],
    ['LICENSE', 'compareToTemplate'],
];
foreach ($arrs as $arr) {
    list($filename, $fn) = $arr;
    if ($contents = fetch("/repos/$account/$repo/contents/$filename?ref=$ref")) {
        if ($fn == 'compareToTemplate') {
            $res[$filename] = call_user_func($fn, $contents, $filename);
        } else {
            $res[$filename] = call_user_func($fn, $contents);
        }
    } else {
        $res[$filename] = 'missing';
    }
}

print_r($res);

# list of repos, loop
# list of files to scan
# templates to compare against
# check if they're the same, add status to output
# for other files, get info e.g. travis deps
# also copy in template that should be used if chages need to be made
# 2nd process, take use previous output as input
# loop input
# for everything that needs updating, hit the API with POST/PATCH
# pull-request to update everything as needed in one go as gha user
# 

# files that are the same no matter what - add in latest template
# if missing or doesn't match tempalte
# SECURITY.md
# LICENSE
# contributing.md

# files that require introspection
# .travis.yml
# - deps - will use for gha composer_require_extra
# - provision? relevant if self and means don't use installer
# composer.json
# - phpunit, recipe-testing version, etc
# phpcs.xml.dist
# phpunit.xml.dist
# package.json?

# files that we're checking if they exist just for auditing
# - scrutinezer.json
# - codecov

# files that we're checking if they exist and if so delete
# - composer.lock
# - package.shrinkwrap
# - whatever that old code checker thing was on framework

