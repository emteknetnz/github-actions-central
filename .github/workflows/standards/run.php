<?php

// github token
$token = $argv[1];

include('modules.php');
include('scan.php');
include('update.php');

$modules = MODULES['regular']['silverstripe'];
$module = 'silverstripe-campaign-admin';

$account = 'silverstripe';
$repo = 'silverstripe-asset-admin'; // has custom .travis reqs

// delete dir if it already exists (only relevant for local dev)
$dir = __DIR__;
if (file_exists("$dir/repos")) {
    shell_exec("rm -rf $dir/repos");
}

$scan = scan($account, $repo);
update($account, $repo, $scan);
