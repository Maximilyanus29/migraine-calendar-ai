<?php

declare(strict_types=1);

function jsonResponse(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        jsonResponse(400, ['error' => 'Invalid JSON body']);
        exit;
    }

    return $decoded;
}

function ok(array $data = []): void
{
    jsonResponse(200, ['data' => $data]);
}

function created(array $data): void
{
    jsonResponse(201, ['data' => $data]);
}

function errorResponse(int $status, string $message, array $details = []): void
{
    jsonResponse($status, ['error' => $message, 'details' => $details]);
}
