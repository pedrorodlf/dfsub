<?php
define('MAX_FILE_SIZE', 6000000);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', '0');

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use RequirementsChecker\ProjectRequirements;

if(array_key_exists(\App\ProxyController::COOKIE_CLOUD_SESSION_ID_NAME, $_REQUEST)){
    $_SERVER['HTTP_USER_AUTHORIZATION'] = $_REQUEST[\App\ProxyController::COOKIE_CLOUD_SESSION_ID_NAME];
}else{
    $_SERVER['HTTP_USER_AUTHORIZATION'] = 'USER_LOGGED_OUT';
}

if(!ProjectRequirements::isApplicationInstalled()){
    require __DIR__ . '/requirements-checker/requirements-checker.php';
}

$kernel = new \App\Kernel('prod', false);
$request = Request::createFromGlobals();

if(!ProjectRequirements::isApplicationConnectedWithCloud() && !str_contains($request->getUri(),'cloud/connect')) {
    $kernel->boot();
    $kernel->getContainer()->get('cloud_connection_service')->checkIfProjectIsRootProject($request);
    $kernel->getContainer()->get('cloud_connection_service')->establishConnection($request);
}

header("cache-control:no-store, no-cache, must-revalidate, max-age=0");
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);