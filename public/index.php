<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\App;

require __DIR__ . '/../vendor/autoload.php';

$settings = [
    'settings' => [
        'displayErrorDetails' => true,
        'db' => [
            'dsn' => getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=phonebook_slim;charset=utf8mb4',
            'user' => getenv('DB_USER') ?: 'root',
            'pass' => getenv('DB_PASS') ?: '',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ],
    ],
];

$app = new App($settings);
$container = $app->getContainer();

$container['db'] = function($c) {
    $db = $c->get('settings')['db'];
    return new PDO($db['dsn'], $db['user'], $db['pass'], $db['options']);
};

$container['AuthController'] = function($c) {
    return new App\Controllers\AuthController($c->get('db'));
};
$container['ContactController'] = function($c) {
    return new App\Controllers\ContactController($c->get('db'));
};
$container['TokenMiddleware'] = function($c) {
    return new App\Middleware\TokenMiddleware($c->get('db'));
};

$app->post('/auth/register', 'AuthController:register');
$app->post('/auth/login', 'AuthController:login');

$app->group('', function() use ($app) {
    $app->get('/contacts', 'ContactController:index');
    $app->post('/contacts', 'ContactController:create');
    $app->put('/contacts/{id}', 'ContactController:update');
    $app->delete('/contacts/{id}', 'ContactController:delete');
})->add($container->get('TokenMiddleware'));

$app->run();
