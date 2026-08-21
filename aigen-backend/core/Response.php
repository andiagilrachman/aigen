<?php
// File: core/Response.php

class Response {
    public static function success($data = null, string $message = '', int $code = 200): void {
        self::emit(['success' => true, 'message' => $message, 'data' => $data], $code);
    }

    public static function error(string $message, int $code = 400, $errors = null): void {
        self::emit(['success' => false, 'message' => $message, 'errors' => $errors], $code);
    }

    private static function emit(array $payload, int $code): void {
        http_response_code($code);
        if (ob_get_length()) {
            ob_clean(); // buang output tak sengaja (warning PHP dll) sebelum kirim JSON
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
