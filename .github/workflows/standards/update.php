<?php


// TODO: Really should combine the 'update' logic with 'scan' and 'doUpdate'
// .. so that scan also creates the new content.
// then based on the scan we have enough info to know if we update things
// right now there's lots of duplicate logic

// looks at the result of scan, decides if should clone repo and create pull-request
// this needs to create a pull-request with the relevant changes
// - git clone the module that needs updating?
// - run git commands via shell_exec()
// - file_get_contents static templates that should copied in
// - for scanned things like .travis.yml, need to create a new github actions ci.yml workflow file
// - which i'll copy in from templates, and add in composer_require_extra
// - also delete .travis.yml while I'm at it

// exists | missing | up-to-date | template-missing | <template-path>

$phpReq = '^7.3 || ^8.0';
$phpunitReq = '^9.5';
$frameworkReq = '^4.10'; // minimum required for phpunit9 support
$recipeTestingReq = '^2';
$phpcsReq = '^3';

// TODO: should be shared array with scan.php:scan().$arrs
$shouldExist = [
    '.github/workflows/ci.yml'
];

$shouldBeMissing = [
    '.travis.yml'
];

$shouldBeUpToDate = [
    'SECURITY.md',
    'contributing.md',
    'LICENSE'
];

// work out if we should create a pull-request to update the repo
function getDoUpdate($scan) {
    global $shouldExist, $shouldBeMissing, $shouldByUpToDate, $phpReq, $phpunitReq, $recipeTestingReq;
    foreach ($scan as $filename => $val) {
        if (
            in_array($filename, $shouldExist) && $val != 'exists' ||
            in_array($filename, $shouldBeMissing) && $val != 'missing' ||
            in_array($filename, $shouldByUpToDate) && $val != 'update-to-date'
        ) {
            return true;
        }
    }
    // composer.json
    if (
        ($scan['composer.json']['reqs']['php'] ?? '') != $phpReq ||
        ($scan['composer.json']['reqs']['phpunit'] ?? '') != $phpunitReq ||
        ($scan['composer.json']['reqs']['recipe-testing'] ?? $recipeTestingReq) != $recipeTestingReq
    ) {
        return true;
    }
    // note: don't need special logic to check if .travis.yml reqs are diff to ci.yml reqs
    // because the mere existance of a .travis.yml file means we need to update to delete it
    return false;
}

function getRepoDir($repo) {
    $dir = __DIR__;
    return "$dir/repos/$repo";
}

function initRepo($account, $repo) {
    $dir = __DIR__;
    $time = time();
    $defaultRef = getDefaultRef($account, $repo);
    if (!file_exists("$dir/repos")) {
        mkdir("$dir/repos");
    }
    $repoDir = getRepoDir($repo);
    if (!file_exists($repoDir)) {
        echo shell_exec(<<<BASH
            git clone https://github.com/$account/$repo.git $repoDir
            cd $repoDir
            git checkout $defaultRef
            git checkout -b pulls/$defaultRef/update-$time
        BASH);
    }
}

function getRepoFileContents($repo, $path) {
    $repoDir = getRepoDir($repo);
    $fullPath = str_replace('//', '/', "$repoDir/$path");
    return file_get_contents($fullPath);
}

function updateRepoFile($repo, $path, $contents) {
    $repoDir = getRepoDir($repo);
    $fullPath = str_replace('//', '/', "$repoDir/$path");
    $dir = dirname($fullPath);
    if (!file_exists($dir)) {
        mkdir($dir, 0775, true);
    }
    file_put_contents($fullPath, $contents);
}

// update .github/workflows/ci.yml
// if .travis.yml is still present will copy REQUIRE_EXTRA to composer_requre_extra
function updateCiYml($repo, $scan) {
    // ci.yml + .travis.yml
    $path = '.github/workflows/ci.yml';
    if ($scan[$path] == 'missing') {
        $contents = file_get_contents(__DIR__ . '/templates/ci.yml');
    } else {
        $contents = getRepoFileContents($repo, $path);
    }
    $travisReqs = $scan['.travis.yml']['reqs'] ?? [];
    if (!empty($travisReqs)) {
        $ciReqs = $scan[$path]['reqs'] ?? [];
        // ciReqs will take precedence for duplicate requirements
        $reqs = array_merge($travisReqs, $ciReqs);
        $arr = [];
        foreach ($reqs as $req => $val) {
            $arr[] = "$req:$val";
        }
        $str = implode(' ', $arr);
        $extra = "composer_require_extra: $str";
        if (strpos($contents, 'with:') === false) {
            $contents = trim($contents) . "\n    with:\n";
        }
        if (strpos($contents, 'composer_require_extra:') !== false) {
            $contents = preg_replace('#composer_require_extra:.+#', $extra, $contents);
        } else {
            $contents = str_replace("with:\n", "with:\n      $extra", $contents);
        }
        $contents = trim($contents) . "\n";
    }
    updateRepoFile($repo, $path, $contents);
}

// update if framework <=4.9
function doUpdateFramework($req) {
    $v = str_replace(['^', '~', '.x-dev'], '', $req);
    return (bool) preg_match('#^4\.[0-9]$#', $v);
}

function updateComposerJson($repo) {
    // assume that composer.json is always present
    global $phpReq, $phpunitReq, $phpcsReq, $recipeTestingReq, $frameworkReq;
    $path = 'composer.json';
    $contents = getRepoFileContents($repo, $path);
    $json = json_decode($contents);
    $json->require->php = $phpReq;
    $json->{'require-dev'}->{'phpunit/phpunit'} = $phpunitReq;
    if (isset($json->{'require'}->{'silverstripe/framework'})) {
        if (doUpdateFramework($json->{'require'}->{'silverstripe/framework'})) {
            $json->{'require'}->{'silverstripe/framework'} = $frameworkReq;
        }
    } else {
        $json->{'require-dev'}->{'silverstripe/framework'} = $frameworkReq;
    }
    if (isset($json->{'require-dev'}->{'silverstripe/recipe-testing'})) {
        $json->{'require-dev'}->{'silverstripe/recipe-testing'} = $recipeTestingReq;
    } else {
        $json->{'require-dev'}->{'squizlabs/php_codesniffer'} = $phpcsReq;
    }
    $json->{'minimum-stability'} = 'dev';
    $json->{'prefer-stable'} = 'true';
    $contents = json_encode($contents, JSON_PRETTY_PRINT);
    updateRepoFile($repo, $path, $contents);
}

// update SECURITY.md, etc
function updateShouldBeUpToDate($repo, $filename) {
    $templatePath = "templates/$filename";
    if (!file_exists($templatePath)) {
        return;
    }
    $contents = file_get_contents($templatePath);
    updateRepoFile($repo, $filename, $contents);
}

function update($account, $repo, $scan) {
    global $shouldBeUpToDate;
    $doUpdate = getDoUpdate($scan);
    if (!$doUpdate) {
        echo "Everything up to date in $account/$repo, not creating pull-request\n";
        return;
    }
    initRepo($account, $repo);
    updateCiYml($repo, $scan);
    updateComposerJson($repo);
    foreach ($shouldBeUpToDate as $filename) {
        updateShouldBeUpToDate($repo, $filename);
    }
}
