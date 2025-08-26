<?php
define('SITEPATH', __DIR__);
include_once '../dawn/entry.php';

DEFINE('SITENETWORK', OURNETWORK);

runFrameworkFile('site');
