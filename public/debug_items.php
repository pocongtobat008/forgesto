<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug Items Routes</h1>";

echo "<h2>1. Test /items redirect</h2>";
echo "URL: <a href='/items'>/items</a><br>";

echo "<h2>2. Test /items/pending direct</h2>";
echo "URL: <a href='/items/pending'>/items/pending</a><br>";

echo "<h2>3. Routes loaded?</h2>";
echo "<pre>";
$routesFile = '/www/wwwroot/sto.izal.my.id/config/routes.php';
if (file_exists($routesFile)) {
    echo "✅ Routes file exists\n";
    echo "Size: " . filesize($routesFile) . " bytes\n";
} else {
    echo "❌ Routes file NOT found\n";
}
echo "</pre>";

echo "<h2>4. Controllers exist?</h2>";
echo "<pre>";
$controllers = [
    'ItemsPendingController',
    'ItemsCountedController', 
    'ItemsPostedController',
    'ItemsRecountController'
];
foreach ($controllers as $ctrl) {
    $path = "/www/wwwroot/sto.izal.my.id/app/Controllers/$ctrl.php";
    if (file_exists($path)) {
        echo "✅ $ctrl exists (" . filesize($path) . " bytes)\n";
    } else {
        echo "❌ $ctrl NOT found\n";
    }
}
echo "</pre>";

echo "<h2>5. Views exist?</h2>";
echo "<pre>";
$views = ['pending', 'counted', 'posted', 'recount'];
foreach ($views as $view) {
    $path = "/www/wwwroot/sto.izal.my.id/app/Views/items/$view/index.php";
    if (file_exists($path)) {
        echo "✅ items/$view/index.php exists\n";
    } else {
        echo "❌ items/$view/index.php NOT found\n";
    }
}
echo "</pre>";
