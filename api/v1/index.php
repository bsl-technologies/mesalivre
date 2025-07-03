<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Usuarios.php';
require_once __DIR__ . '/../models/Restaurante.php';
require_once __DIR__ . '/../models/Mesa.php';
require_once __DIR__ . '/../models/Reserva.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/AuthMiddleware.php';

$method = $_SERVER['REQUEST_METHOD'];
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base_path = '/api/v1';
$uri = str_replace($base_path, '', $request_uri);
$uri_segments = explode('/', trim($uri, '/'));

$resource = $uri_segments[0] ?? null;
$actionOrId = $uri_segments[1] ?? null;
$subAction = $uri_segments[2] ?? null;
$subActionId = $uri_segments[3] ?? null;

if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

switch ($resource) {
    case 'auth':
        $action = $actionOrId;
        $resourceId = null;
        require __DIR__ . '/auth.php';
        break;

    case 'usuarios':
        if ($actionOrId === 'perfil') {
            $action = 'perfil';
            $resourceId = null;
        } elseif (is_numeric($actionOrId)) {
            $action = null;
            $resourceId = $actionOrId;
        } else {
            $action = null;
            $resourceId = null;
        }
        require __DIR__ . '/usuarios.php';
        break;

    case 'restaurantes':
        if ($actionOrId && $actionOrId !== '' && $subAction === 'mesas') {
            $resourceId = $actionOrId;
            $secondaryResourceId = $subActionId;
            require __DIR__ . '/mesas.php';
        } else {
            $resourceId = $actionOrId;
            require __DIR__ . '/restaurantes.php';
        }
        break;

    case 'reservas':
        $resourceId = $actionOrId;
        require __DIR__ . '/reservas.php';
        break;

    default:
        Response::json(404, ['message' => 'Recurso não encontrado.']);
        break;
}
