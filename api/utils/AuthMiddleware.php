<?php
// api/utils/AuthMiddleware.php

class AuthMiddleware {
    private const SECRET_KEY = "sua_super_chave_secreta_aqui";

    public static function generateToken(string $userId, string $userRole): string {
        $payload = [
            'iat' => time(),
            'exp' => time() + (3600 * 24),
            'uid' => $userId,
            'role' => $userRole
        ];
        return base64_encode(json_encode($payload));
    }

    public static function validateToken(string $authHeader): ?array {
        if (!str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);

        try {
            $decoded = json_decode(base64_decode($token), true);

            if (
                json_last_error() !== JSON_ERROR_NONE ||
                !isset($decoded['uid'], $decoded['role'], $decoded['exp'])
            ) {
                return null;
            }

            if ($decoded['exp'] < time()) {
                return null; 
            }

            return $decoded;
        } catch (Exception $e) {
            return null;
        }
    }

    public static function hasRole(array $tokenPayload, string $requiredRole): bool {
        return $tokenPayload['role'] === $requiredRole || $tokenPayload['role'] === 'admin';
    }

    public static function generateResetToken(): string {
        return uniqid('reset_', true);
    }
}
