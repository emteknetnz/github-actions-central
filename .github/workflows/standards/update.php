<?php

// this needs to create a pull-request with the relevant changes
// - git clone the module that needs updating?
// - or can i just send raw files to the API without first cloning the repo?
// - file_get_contents static templates that should copied in
// - for scanned things like .travis.yml, need to create a new github actions ci.yml workflow file
// - which i'll copy in from templates, and add in composer_require_extra
// - also delete .travis.yml while I'm at it

// exists | missing | up-to-date | template-missing | <template-path>

function handleTravisYml($val) {
    if ($val == 'missing') {
        return;
    }
    
}

function update($account, $repo, $scan) {

    foreach ($scan as $filename => $val) {
        switch($filename) {
            case ('.travis.yml'):
                handleTravisYml($val);
                // delete file
            default:
                break;
        }
    }
    // ci.yml + .travis.yml
    $path = '.github/workflows/ci.yml';
    if ($scan[$path] == 'missing') {
        $contents = file_get_contents('templates/ci.yml');
    } else {
        $contents = file_get_contents($path);
    }
    $travisReqs = $scan['.travis.yml']['reqs'] ?? [];
    if (!empty($travisReqs)) {
        $ciReqs = $scan[$path]['reqs'] ?? [];
        $reqs = array_merge($travisReqs, $ciReqs); // TODO ensure that travisreqs get overridden if same key exists
        
    }
}