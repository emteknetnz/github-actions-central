<?php

// github token
$token = $argv[1];

include('modules.php');
include('scan.php');
include('update.php');

$modules = MODULES['regular']['silverstripe'];
$module = 'silverstripe-campaign-admin';

$account = 'silverstripe';
$repo = 'silverstripe-asset-admin';

$scan = scan($account, $repo);
update($account, $repo, $scan);
