<?php
define('MAX_FILE_SIZE', 6000000);
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

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

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$kernel = new \App\Kernel('prod', false);
$request = Request::createFromGlobals();

if(!ProjectRequirements::isApplicationConnectedWithCloud() && !str_contains($request->getUri(),'cloud/connect')) {
    $kernel->boot();
    $kernel->getContainer()->get('cloud_connection_service')->checkIfProjectIsRootProject($request);
    $kernel->getContainer()->get('cloud_connection_service')->establishConnection($request);
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
try {
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Erro interno do servidor:\n";
    echo $e->getMessage() . "\n\n";
    echo $e->getTraceAsString();
    exit(1);
}
