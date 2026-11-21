<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
use ScholarDesk\Application\SchoolService;
use ScholarDesk\Infrastructure\Database;

ini_set('display_errors','0');
$sessionDirectory=dirname(__DIR__).'/data/sessions';
if (!is_dir($sessionDirectory)) mkdir($sessionDirectory,0700,true);
session_save_path($sessionDirectory);
session_set_cookie_params(['httponly'=>true,'samesite'=>'Strict']);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function respond(mixed $data, int $code=200): never {
    http_response_code($code);
    echo json_encode(['data'=>$data],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); exit;
}
try {
    $_SESSION['csrf'] ??= bin2hex(random_bytes(24));
    $method=$_SERVER['REQUEST_METHOD'];
    $route=$_GET['route'] ?? 'dashboard';
    if (!is_string($route)) throw new InvalidArgumentException('Invalid route.');
    if ($method==='GET' && $route==='session') respond(['csrf_token'=>$_SESSION['csrf']]);
    $input=[];
    if (in_array($method,['POST','PUT','DELETE'],true)) {
        if (!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            http_response_code(403); echo json_encode(['error'=>'Refresh the page or fetch a session token first.']); exit;
        }
        if ($method!=='DELETE') {
            if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''),'application/json')) {
                http_response_code(415); echo json_encode(['error'=>'Use Content-Type: application/json.']); exit;
            }
            if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>32768) { http_response_code(413); echo json_encode(['error'=>'Request is too large.']); exit; }
            try { $input=json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR); }
            catch (JsonException) { throw new InvalidArgumentException('Invalid JSON.'); }
            if (!is_array($input) || array_is_list($input) && $input!==[]) throw new InvalidArgumentException('JSON body must be an object.');
        }
    }
    $service=new SchoolService(Database::connect());
    if (in_array($route,['students','tasks'],true)) {
        $id=null;
        if (isset($_GET['id'])) {
            $id=filter_var($_GET['id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if ($id===false) throw new InvalidArgumentException('ID must be a positive integer.');
        }
        if ($method==='GET') respond($id ? $service->get($route,$id) : $service->all($route,queryText('q')));
        if ($method==='POST' && $id===null) respond($service->save($route,$input),201);
        if ($method==='PUT' && $id!==null) respond($service->save($route,$input,$id));
        if ($method==='DELETE' && $id!==null) { $service->delete($route,$id); respond(['deleted'=>true]); }
    } elseif ($route==='submissions') {
        if ($method==='GET') respond($service->submissions(queryText('q'),queryText('status','all')));
        if ($method==='PUT') {
            $sid=filter_var($input['student_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            $tid=filter_var($input['task_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if (!$sid || !$tid || !is_string($input['status'] ?? null)) throw new InvalidArgumentException('Provide valid student_id, task_id, and status.');
            respond($service->submit($sid,$tid,$input['status']));
        }
    } elseif ($route==='dashboard' && $method==='GET') respond($service->dashboard());
    elseif ($route==='demo' && $method==='POST') { $service->seed(); respond(['loaded'=>true],201); }
    elseif (!in_array($route,['session','dashboard','demo'],true)) { http_response_code(404); echo json_encode(['error'=>'Endpoint was not found.']); exit; }
    http_response_code(405); header('Allow: GET, POST, PUT, DELETE'); echo json_encode(['error'=>'Method or ID is not supported for this endpoint.']);
} catch (InvalidArgumentException $e) {
    http_response_code($e->getCode()===409 ? 409 : 422); echo json_encode(['error'=>$e->getMessage()]);
} catch (OutOfBoundsException $e) {
    http_response_code(404); echo json_encode(['error'=>$e->getMessage()]);
} catch (Throwable $e) {
    error_log((string)$e); http_response_code(500); echo json_encode(['error'=>'The server could not complete the request. Check its log.']);
}
function queryText(string $key, string $default=''): string {
    $value=$_GET[$key] ?? $default;
    if (!is_string($value) || strlen($value)>200) throw new InvalidArgumentException('Invalid query parameter.');
    return trim($value);
}
