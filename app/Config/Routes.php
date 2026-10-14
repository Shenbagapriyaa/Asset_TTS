<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'AuthController::login');
$routes->post('/login', 'AuthController::attemptLogin');
$routes->get('/forgot-password', 'AuthController::forgotPassword');
$routes->post('/forgot-password', 'AuthController::requestPasswordReset');
$routes->get('/reset-password', 'AuthController::resetPassword');
$routes->post('/reset-password', 'AuthController::updatePassword');
$routes->post('/logout', 'AuthController::logout');
$routes->get('/tenant-unavailable', 'AuthController::tenantUnavailable');

$routes->group('', ['filter' => 'tenant'], static function (RouteCollection $routes): void {
    $routes->get('/two-factor/setup', 'TwoFactorController::setup');
    $routes->post('/two-factor/confirm', 'TwoFactorController::confirm');
    $routes->get('/two-factor/verify', 'TwoFactorController::verify');
    $routes->post('/two-factor/verify', 'TwoFactorController::attemptVerify');
    $routes->post('/two-factor/recovery', 'TwoFactorController::useRecovery');

    $routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
        $routes->get('/assets', 'AssetsController::index');
        $routes->get('/assets/new', 'AssetsController::new');
        $routes->post('/assets', 'AssetsController::create');
        $routes->get('/assets/(:num)/edit', 'AssetsController::edit/$1');
        $routes->post('/assets/(:num)', 'AssetsController::update/$1');
        $routes->post('/assets/(:num)/delete', 'AssetsController::delete/$1');
        $routes->post('/assets/bulk-delete', 'AssetsController::bulkDelete');
        $routes->get('/assets/lines/(:num)', 'AssetsController::lines/$1');
        $routes->get('/admin/users', 'UsersController::index', ['filter' => 'role:admin']);
        $routes->post('/admin/users', 'UsersController::create', ['filter' => 'role:admin']);
        $routes->post('/admin/users/(:num)/toggle', 'UsersController::toggle/$1', ['filter' => 'role:admin']);
        $routes->post('/admin/users/(:num)/reset-2fa', 'UsersController::resetTwoFactor/$1', ['filter' => 'role:admin']);
    });
});

$routes->get('/super-admin/login', 'SuperAdminController::login');
$routes->post('/super-admin/login', 'SuperAdminController::attemptLogin');
$routes->group('super-admin', ['filter' => 'superadmin'], static function (RouteCollection $routes): void {
    $routes->get('tenants', 'SuperAdminController::index');
    $routes->post('tenants', 'SuperAdminController::createTenant');
    $routes->post('tenants/(:num)/toggle', 'SuperAdminController::toggle/$1');
    $routes->post('tenants/migrate-all', 'SuperAdminController::migrateAll');
});
