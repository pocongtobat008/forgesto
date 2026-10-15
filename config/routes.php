<?php

/**
 * StockForge - Route definitions.
 *
 * Static/specific routes are registered before dynamic {id} routes so the
 * first match wins.
 *
 * @var App\Core\Router $router
 */

use App\Controllers\ActivityController;
use App\Controllers\AuditController;
use App\Controllers\BookYearController;
use App\Controllers\DashboardController;
use App\Controllers\DeviceController;
use App\Controllers\DiscrepancyController;
use App\Controllers\GroupController;
use App\Controllers\ImportController;
use App\Controllers\ItemController;
use App\Controllers\LocationController;
use App\Controllers\NotificationController;
use App\Controllers\ProfileController;
use App\Controllers\UserController;
use App\Controllers\ReconciliationController;
use App\Controllers\WarehouseController;

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/
$router->get('/', 'DashboardController@index');
$router->get('/dashboard', 'DashboardController@index');

/*
|--------------------------------------------------------------------------
| Warehouses (Gudang / Situs)
|--------------------------------------------------------------------------
*/
$router->get('/warehouses', 'WarehouseController@index');
$router->get('/warehouses/distinct', 'WarehouseController@distinct');
$router->get('/warehouses/create', 'WarehouseController@create');
$router->post('/warehouses', 'WarehouseController@store');
$router->post('/warehouses/switch', 'WarehouseController@switch');
$router->get('/warehouses/edit/{id}', 'WarehouseController@edit');
$router->post('/warehouses/{id}', 'WarehouseController@update');
$router->post('/warehouses/{id}/delete', 'WarehouseController@destroy');

/*
|--------------------------------------------------------------------------
| Groups
|--------------------------------------------------------------------------
*/
$router->get('/groups', 'GroupController@index');
$router->get('/groups/distinct', 'GroupController@distinct');
$router->get('/groups/create', 'GroupController@create');
$router->post('/groups', 'GroupController@store');
$router->get('/groups/{id}', 'GroupController@show');
$router->get('/groups/{id}/berita-acara', 'GroupController@beritaAcara');
$router->get('/groups/edit/{id}', 'GroupController@edit');
$router->post('/groups/{id}', 'GroupController@update');
$router->post('/groups/{id}/delete', 'GroupController@destroy');

/*
|--------------------------------------------------------------------------
| Users & roles
|--------------------------------------------------------------------------
*/
$router->get('/users', 'UserController@index');
$router->get('/users/create', 'UserController@create');
$router->post('/users', 'UserController@store');
$router->get('/users/edit/{id}', 'UserController@edit');
$router->post('/users/{id}', 'UserController@update');
$router->post('/users/{id}/delete', 'UserController@destroy');

// User Rules & Permissions (Power Admin Maintenance)
$router->get('/user-rules', 'UserRuleController@index');
$router->post('/user-rules/save', 'UserRuleController@save');
$router->post('/user-rules/reset', 'UserRuleController@reset');
$router->post('/user-rules/custom/add', 'UserRuleController@addCustom');
$router->post('/user-rules/custom/delete', 'UserRuleController@deleteCustom');
$router->get('/api/menu-rule', 'UserRuleController@apiGetMenuRule');
$router->post('/api/menu-rule/save', 'UserRuleController@apiSaveMenuRule');

/*
|--------------------------------------------------------------------------
| Locations
|--------------------------------------------------------------------------
*/
$router->get('/locations', 'LocationController@index');
$router->get('/locations/distinct', 'LocationController@distinct');
$router->get('/locations/create', 'LocationController@create');
$router->post('/locations', 'LocationController@store');
$router->get('/locations/edit/{id}', 'LocationController@edit');
$router->post('/locations/{id}', 'LocationController@update');
$router->post('/locations/{id}/delete', 'LocationController@destroy');

/*
|--------------------------------------------------------------------------
| Devices
|--------------------------------------------------------------------------
*/
$router->get('/devices', 'DeviceController@index');
$router->get('/devices/distinct', 'DeviceController@distinct');
$router->get('/devices/create', 'DeviceController@create');
$router->post('/devices', 'DeviceController@store');
$router->get('/devices/edit/{id}', 'DeviceController@edit');
$router->post('/devices/{id}', 'DeviceController@update');
$router->post('/devices/{id}/delete', 'DeviceController@destroy');

/*
|--------------------------------------------------------------------------
| Items (data STO) - TAB-BASED ROUTES (New System)
|--------------------------------------------------------------------------
| Each tab has its own route and controller for physical data separation
*/

// Items management (main endpoint)
$router->get("/items", "ItemController@index");
$router->post("/items", "ItemController@store");

// Tab 1: Belum dihitung (Pending)

// Tab 2: Sudah dihitung (Counted) - POSTING FEATURE HERE

// Tab 3: Terposting (Posted) - Read-only

// Tab 4: Harus Recount (Recount)

// Shared routes (work across all tabs)
$router->get('/items/distinct', 'ItemController@distinct');
$router->get('/items/lookup', 'ItemController@lookup');
$router->get('/users/distinct', 'UserController@distinct');
$router->get('/items/pending/export.xlsx', 'ItemController@exportFiltered');
$router->get('/items/counted/export.xlsx', 'ItemController@exportFiltered');
$router->get('/items/posted/export.xlsx', 'ItemController@exportFiltered');
$router->get('/items/recount/export.xlsx', 'ItemController@exportFiltered');
$router->get('/items/create', 'ItemController@create');
$router->get('/items/memo', 'ItemController@memo');
$router->get('/items/memo.pdf', 'ItemController@memoPdf');
$router->get('/items/memo/preview', 'ItemController@memoPreview');
$router->post('/items', 'ItemController@store');
$router->post('/items/bulk-post', 'ItemController@bulkPost');
$router->post('/items/posting', 'ItemPostingController@posting');
$router->post('/items/recount', 'ItemPostingController@recount');

// Item detail routes (ID-based, work from any tab)
$router->get('/items/{id}', 'ItemController@show');
$router->get('/items/{id}/edit', 'ItemController@edit');
$router->post('/items/{id}', 'ItemController@update');
$router->post('/items/{id}/count', 'ItemController@count');
$router->post('/items/{id}/post', 'ItemController@post');
$router->post('/items/{id}/unlock', 'ItemController@unlock');
$router->post('/items/{id}/delete', 'ItemController@destroy');

// Items index (legacy redirect)
$router->get('/items/index', 'ItemController@index');

/*
|--------------------------------------------------------------------------
| Master Data (Seluruh data barang, tanpa tunggu submit audit)
|--------------------------------------------------------------------------
*/
$router->get('/master-data', 'MasterDataController@index');
$router->get('/master-data/distinct', 'MasterDataController@distinct');
$router->get('/master-data/export.xlsx', 'MasterDataController@exportFiltered');
$router->get('/master-data/{id}', 'MasterDataController@show');

/*
|--------------------------------------------------------------------------
| Audit
|--------------------------------------------------------------------------
*/
$router->get('/audit', 'AuditController@index');
$router->get('/audit/history', 'AuditController@history');
$router->get('/audit/history/distinct', 'AuditController@historyDistinct');
$router->post('/audit/period', 'AuditController@savePeriod');
$router->post('/audit/period/close', 'AuditController@closePeriod');
$router->post('/audit/period/new', 'AuditController@startNewPeriod');
$router->post('/audit/period/{id}/view', 'AuditController@viewPeriod');
$router->post('/audit/report/{id}', 'AuditController@report');
$router->get('/audit/{id}/check', 'AuditController@check');
$router->post('/audit/{id}/check', 'AuditController@submit');

/*
|--------------------------------------------------------------------------
| Tahun Buku (periode + selisih close buku)
|--------------------------------------------------------------------------
*/
$router->get('/tahun-buku', 'BookYearController@index');
$router->post('/tahun-buku/{id}/buka', 'BookYearController@openPeriod');
$router->get('/tahun-buku/kembali', 'BookYearController@resetView');
$router->post('/tahun-buku/kembali', 'BookYearController@resetView');
$router->get('/tahun-buku/{id}', 'BookYearController@detail');
$router->get('/tahun-buku/{id}/worksheet', 'BookYearController@worksheet');
$router->get('/tahun-buku/{id}/export', 'BookYearController@exportSnapshot');
$router->post('/tahun-buku/reset', 'BookYearController@resetPeriod');
$router->get('/tahun-buku/hapus/{id}', 'BookYearController@deleteConfirm');
$router->post('/tahun-buku/hapus/{id}', 'BookYearController@deleteExecute');

/*
|--------------------------------------------------------------------------
| Discrepancies
|--------------------------------------------------------------------------
*/
$router->get('/discrepancies', 'DiscrepancyController@index');
$router->get('/discrepancies/distinct', 'DiscrepancyController@distinct');
$router->post('/discrepancies', 'DiscrepancyController@store');
$router->get('/discrepancies/{id}', 'DiscrepancyController@show');
$router->post('/discrepancies/{id}/search', 'DiscrepancyController@search');
$router->post('/discrepancies/{id}/resolve', 'DiscrepancyController@resolve');

/*
|--------------------------------------------------------------------------
| Reconciliation (Hasil Hitung vs Qty Stock)
|--------------------------------------------------------------------------
*/
$router->get('/reconciliation', 'ReconciliationController@index');
$router->get('/reconciliation/distinct', 'ReconciliationController@distinct');
$router->post('/reconciliation/sync', 'ReconciliationController@sync');
$router->post('/reconciliation/bulk-recheck', 'ReconciliationController@bulkRecheck');
$router->get('/reconciliation/audit', 'ReconciliationController@auditTrail');
$router->post('/reconciliation/export-sap', 'ReconciliationController@exportSap');
$router->get('/exports', 'ExportController@index');
$router->get('/exports/distinct', 'ExportController@distinct');
$router->get('/exports/{id}/download', 'ExportController@download');
$router->get('/reconciliation/{id}', 'ReconciliationController@show');
$router->post('/reconciliation/{id}', 'ReconciliationController@update');
$router->post('/reconciliation/{id}/verify', 'ReconciliationController@verify');
$router->post('/reconciliation/{id}/recheck', 'ReconciliationController@recheck');

/*
|--------------------------------------------------------------------------
| Import
|--------------------------------------------------------------------------
*/
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->get('/logout', 'AuthController@logout');

$router->get('/import', 'ImportController@index');
$router->post('/import', 'ImportController@store');
$router->post('/import/master', 'ImportController@master');
$router->get("/import/progress-master", "ImportController@progressMaster");
$router->post("/import/bulk-delete-master", "ImportController@bulkDeleteMaster");
$router->get('/import/template', 'ImportController@template');
$router->get('/import/template.xlsx', 'ImportController@templateXlsx');
$router->get('/import/template/master.xlsx', 'ImportController@templateMaster');
$router->post('/import/employees', 'ImportController@employees');
$router->get('/import/template/employees.xlsx', 'ImportController@templateEmployees');
$router->get('/import/logs/{id}/download', 'ImportController@download');

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/
$router->get('/notifications', 'NotificationController@index');
$router->post('/notifications/read-all', 'NotificationController@readAll');
$router->post('/notifications/{id}/read', 'NotificationController@read');
$router->get('/notifications/feed', 'NotificationFeedController@feed');
$router->post('/notifications/read-all-json', 'NotificationFeedController@readAllJson');

/*
|--------------------------------------------------------------------------
| Activity & profile
|--------------------------------------------------------------------------
*/
$router->get('/activity', 'ActivityController@index');
$router->get('/activity/distinct', 'ActivityController@distinct');
$router->get('/profile', 'ProfileController@index');
$router->post('/profile/switch', 'ProfileController@switch');
