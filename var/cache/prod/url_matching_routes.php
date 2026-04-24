<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    false, // $matchHost
    [ // $staticRoutes
        '/robots.txt' => [[['_route' => 'robots_txt', '_controller' => 'App\\ProxyController::robotsTxtAction'], null, null, null, false, false, null]],
        '/sitemap.xml' => [[['_route' => 'sitemap', '_controller' => 'App\\ProxyController::sitemapAction'], null, null, null, false, false, null]],
        '/login' => [[['_route' => 'login', '_controller' => 'App\\Controller\\AuthController::loginAction'], null, ['GET' => 0, 'POST' => 1], null, false, false, null]],
        '/register' => [[['_route' => 'register', '_controller' => 'App\\Controller\\AuthController::registerAction'], null, ['GET' => 0, 'POST' => 1], null, false, false, null]],
        '/logout' => [[['_route' => 'logout', '_controller' => 'App\\Controller\\AuthController::logoutAction'], null, ['GET' => 0], null, false, false, null]],
        '/dashboard' => [[['_route' => 'dashboard', '_controller' => 'App\\Controller\\AuthController::dashboardAction'], null, ['GET' => 0, 'POST' => 1], null, false, false, null]],
        '/dashboard/carteira.pdf' => [[['_route' => 'dashboard_pdf', '_controller' => 'App\\Controller\\AuthController::downloadCardAction'], null, ['GET' => 0], null, false, false, null]],
        '/admin' => [[['_route' => 'admin', '_controller' => 'App\\Controller\\AdminController::indexAction'], null, ['GET' => 0, 'POST' => 1], null, false, false, null]],
        '/consulta' => [[['_route' => 'consulta', '_controller' => 'App\\Controller\\PublicController::consultaAction'], null, ['GET' => 0], null, false, false, null]],
    ],
    [ // $regexpList
        0 => '{^(?'
                .'|/(.*)cloud/connect(*:25)'
                .'|/(.*)cloud/cache\\-clear(*:55)'
                .'|/(.*)cloud/reconnect(*:82)'
                .'|/admin/editar/([^/]++)(*:111)'
                .'|/validar/([^/]++)(*:136)'
                .'|/((?!.*\\.(?:.+)).*)(*:163)'
            .')/?$}sD',
    ],
    [ // $dynamicRoutes
        25 => [[['_route' => 'cloud_connect_route', '_controller' => 'App\\CloudController::indexAction'], ['prefix'], null, null, false, false, null]],
        55 => [[['_route' => 'cloud_clear_cache', '_controller' => 'App\\CloudController::clearCacheAction'], ['prefix'], null, null, false, false, null]],
        82 => [[['_route' => 'cloud_reconnect_route', '_controller' => 'App\\CloudController::cloudReconnectAction'], ['prefix'], null, null, false, false, null]],
        111 => [[['_route' => 'admin_edit', '_controller' => 'App\\Controller\\AdminController::editAction'], ['id'], ['GET' => 0, 'POST' => 1], null, false, true, null]],
        136 => [[['_route' => 'validate_user', '_controller' => 'App\\Controller\\PublicController::validateAction'], ['token'], ['GET' => 0], null, false, true, null]],
        163 => [
            [['_route' => 'proxy', '_controller' => 'App\\ProxyController::indexAction'], ['dynamicRoute'], ['GET' => 0, 'POST' => 1], null, false, true, null],
            [null, null, null, null, false, false, 0],
        ],
    ],
    null, // $checkCondition
];
