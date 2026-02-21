<?php

declare(strict_types=1);

require __DIR__ . '/../src/Http.php';
require __DIR__ . '/../src/Db.php';
require __DIR__ . '/../src/Options.php';

$config = require __DIR__ . '/../src/Config.php';
date_default_timezone_set($config['timezone']);

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$pdo = db($config);
ensureDefaultUser($pdo, $config['default_user']);

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

if (!str_starts_with($path, '/api/v1/')) {
    errorResponse(404, 'Route not found');
    exit;
}

$route = substr($path, strlen('/api/v1'));
$route = $route === '' ? '/' : $route;

function authUserId(): ?int
{
    $userId = $_SESSION['user_id'] ?? null;
    return is_int($userId) ? $userId : null;
}

function requireAuth(): int
{
    $userId = authUserId();
    if ($userId === null) {
        errorResponse(401, 'Unauthorized');
        exit;
    }

    return $userId;
}

function validateEnumArray(array $values, array $allowed, string $field): void
{
    foreach ($values as $value) {
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            errorResponse(422, 'Validation failed', [$field => 'Contains unknown value']);
            exit;
        }
    }
}

function parseAttackPayload(array $payload): array
{
    if (!isset($payload['start_at'], $payload['end_at'], $payload['intensity'])) {
        errorResponse(422, 'Validation failed', ['required' => 'start_at, end_at, intensity']);
        exit;
    }

    try {
        $startAt = new DateTimeImmutable((string) $payload['start_at']);
        $endAt = new DateTimeImmutable((string) $payload['end_at']);
    } catch (Throwable $e) {
        errorResponse(422, 'Validation failed', ['datetime' => 'Invalid start_at or end_at']);
        exit;
    }

    if ($endAt <= $startAt) {
        errorResponse(422, 'Validation failed', ['end_at' => 'end_at must be after start_at']);
        exit;
    }

    $intensity = filter_var($payload['intensity'], FILTER_VALIDATE_INT);
    if ($intensity === false || $intensity < 1 || $intensity > 10) {
        errorResponse(422, 'Validation failed', ['intensity' => 'Intensity must be integer from 1 to 10']);
        exit;
    }

    $medications = isset($payload['medications']) ? trim((string) $payload['medications']) : null;
    $notes = isset($payload['notes']) ? trim((string) $payload['notes']) : null;

    if ($notes !== null && mb_strlen($notes) > 2000) {
        errorResponse(422, 'Validation failed', ['notes' => 'Maximum length is 2000']);
        exit;
    }

    $relief = $payload['relief'] ?? null;
    if (!is_null($relief) && !is_bool($relief)) {
        errorResponse(422, 'Validation failed', ['relief' => 'relief must be boolean or null']);
        exit;
    }

    $painTypes = is_array($payload['pain_types'] ?? null) ? $payload['pain_types'] : [];
    $localizations = is_array($payload['localizations'] ?? null) ? $payload['localizations'] : [];
    $triggers = is_array($payload['triggers'] ?? null) ? $payload['triggers'] : [];
    $symptoms = is_array($payload['symptoms'] ?? null) ? $payload['symptoms'] : [];
    $auras = is_array($payload['auras'] ?? null) ? $payload['auras'] : [];

    validateEnumArray($painTypes, OPTIONS['pain_types'], 'pain_types');
    validateEnumArray($localizations, OPTIONS['localizations'], 'localizations');
    validateEnumArray($triggers, OPTIONS['triggers'], 'triggers');
    validateEnumArray($symptoms, OPTIONS['symptoms'], 'symptoms');
    validateEnumArray($auras, OPTIONS['auras'], 'auras');

    return [
        'start_at' => $startAt->format(DATE_ATOM),
        'end_at' => $endAt->format(DATE_ATOM),
        'intensity' => $intensity,
        'medications' => $medications === '' ? null : $medications,
        'relief' => $relief,
        'pain_types' => $painTypes,
        'localizations' => $localizations,
        'triggers' => $triggers,
        'symptoms' => $symptoms,
        'auras' => $auras,
        'notes' => $notes === '' ? null : $notes,
    ];
}

function attackById(PDO $pdo, int $id, int $userId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM attacks WHERE id = :id AND user_id = :user_id LIMIT 1');
    $stmt->execute(['id' => $id, 'user_id' => $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    foreach (['pain_types', 'localizations', 'triggers', 'symptoms', 'auras'] as $field) {
        $row[$field] = json_decode((string) $row[$field], true) ?: [];
    }

    return $row;
}

if ($route === '/auth/login' && $method === 'POST') {
    $body = readJsonBody();
    $email = trim((string) ($body['email'] ?? ''));
    $password = (string) ($body['password'] ?? '');

    if ($email === '' || $password === '') {
        errorResponse(422, 'Validation failed', ['required' => 'email and password']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, name, email, password_hash, timezone FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        errorResponse(401, 'Invalid credentials');
        exit;
    }

    $_SESSION['user_id'] = (int) $user['id'];
    ok([
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'timezone' => $user['timezone'],
    ]);
    exit;
}

if ($route === '/auth/logout' && $method === 'POST') {
    session_destroy();
    ok(['message' => 'Logged out']);
    exit;
}

if ($route === '/auth/me' && $method === 'GET') {
    $userId = requireAuth();
    $stmt = $pdo->prepare('SELECT id, name, email, timezone FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();
    if (!$user) {
        errorResponse(404, 'User not found');
        exit;
    }

    ok([
        'id' => (int) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'timezone' => $user['timezone'],
    ]);
    exit;
}

if ($route === '/meta/options' && $method === 'GET') {
    requireAuth();
    ok(OPTIONS);
    exit;
}

if ($route === '/attacks/last' && $method === 'GET') {
    $userId = requireAuth();
    $stmt = $pdo->prepare('SELECT * FROM attacks WHERE user_id = :user_id ORDER BY start_at DESC LIMIT 1');
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch();
    if (!$row) {
        ok([]);
        exit;
    }

    foreach (['pain_types', 'localizations', 'triggers', 'symptoms', 'auras'] as $field) {
        $row[$field] = json_decode((string) $row[$field], true) ?: [];
    }

    ok($row);
    exit;
}

if ($route === '/attacks' && $method === 'GET') {
    $userId = requireAuth();
    $from = $_GET['from'] ?? null;
    $to = $_GET['to'] ?? null;

    if (!is_string($from) || !is_string($to)) {
        errorResponse(422, 'Validation failed', ['required' => 'from and to are required']);
        exit;
    }

    $query = $pdo->prepare(
        'SELECT * FROM attacks
         WHERE user_id = :user_id
         AND start_at < :to_end
         AND end_at > :from_start
         ORDER BY start_at ASC'
    );
    $query->execute([
        'user_id' => $userId,
        'from_start' => $from . ' 00:00:00+00',
        'to_end' => $to . ' 23:59:59+00',
    ]);

    $rows = $query->fetchAll();
    foreach ($rows as &$row) {
        foreach (['pain_types', 'localizations', 'triggers', 'symptoms', 'auras'] as $field) {
            $row[$field] = json_decode((string) $row[$field], true) ?: [];
        }
    }

    ok($rows);
    exit;
}

if ($route === '/attacks' && $method === 'POST') {
    $userId = requireAuth();
    $body = readJsonBody();
    $attack = parseAttackPayload($body);

    $stmt = $pdo->prepare(
        'INSERT INTO attacks (
            user_id, start_at, end_at, intensity, medications, relief,
            pain_types, localizations, triggers, symptoms, auras, notes
        ) VALUES (
            :user_id, :start_at, :end_at, :intensity, :medications, :relief,
            :pain_types, :localizations, :triggers, :symptoms, :auras, :notes
        ) RETURNING id'
    );

    $stmt->execute([
        'user_id' => $userId,
        'start_at' => $attack['start_at'],
        'end_at' => $attack['end_at'],
        'intensity' => $attack['intensity'],
        'medications' => $attack['medications'],
        'relief' => $attack['relief'],
        'pain_types' => json_encode($attack['pain_types']),
        'localizations' => json_encode($attack['localizations']),
        'triggers' => json_encode($attack['triggers']),
        'symptoms' => json_encode($attack['symptoms']),
        'auras' => json_encode($attack['auras']),
        'notes' => $attack['notes'],
    ]);

    $id = (int) $stmt->fetchColumn();
    $createdAttack = attackById($pdo, $id, $userId);
    created($createdAttack ?: []);
    exit;
}

if (preg_match('#^/attacks/(\d+)$#', $route, $matches) === 1) {
    $attackId = (int) $matches[1];
    $userId = requireAuth();

    if ($method === 'GET') {
        $attack = attackById($pdo, $attackId, $userId);
        if (!$attack) {
            errorResponse(404, 'Attack not found');
            exit;
        }

        ok($attack);
        exit;
    }

    if ($method === 'PUT') {
        $existing = attackById($pdo, $attackId, $userId);
        if (!$existing) {
            errorResponse(404, 'Attack not found');
            exit;
        }

        $body = readJsonBody();
        $attack = parseAttackPayload($body);

        $stmt = $pdo->prepare(
            'UPDATE attacks SET
                start_at = :start_at,
                end_at = :end_at,
                intensity = :intensity,
                medications = :medications,
                relief = :relief,
                pain_types = :pain_types,
                localizations = :localizations,
                triggers = :triggers,
                symptoms = :symptoms,
                auras = :auras,
                notes = :notes,
                updated_at = NOW()
             WHERE id = :id AND user_id = :user_id'
        );

        $stmt->execute([
            'id' => $attackId,
            'user_id' => $userId,
            'start_at' => $attack['start_at'],
            'end_at' => $attack['end_at'],
            'intensity' => $attack['intensity'],
            'medications' => $attack['medications'],
            'relief' => $attack['relief'],
            'pain_types' => json_encode($attack['pain_types']),
            'localizations' => json_encode($attack['localizations']),
            'triggers' => json_encode($attack['triggers']),
            'symptoms' => json_encode($attack['symptoms']),
            'auras' => json_encode($attack['auras']),
            'notes' => $attack['notes'],
        ]);

        $updatedAttack = attackById($pdo, $attackId, $userId);
        ok($updatedAttack ?: []);
        exit;
    }

    if ($method === 'DELETE') {
        $stmt = $pdo->prepare('DELETE FROM attacks WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $attackId, 'user_id' => $userId]);
        ok(['deleted' => $stmt->rowCount() > 0]);
        exit;
    }
}

errorResponse(404, 'Route not found');
