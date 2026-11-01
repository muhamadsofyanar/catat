<?php
class JWT {
    private static function getSecret() {
        require_once __DIR__ . '/../core/Env.php';
        \Core\Env::load(__DIR__ . '/../.env');
        $secret = \Core\Env::get('JWT_SECRET', '');
        if (strlen($secret) < 32) throw new RuntimeException('JWT_SECRET must contain at least 32 characters.');
        return $secret;
    }

    public static function encode($payload) {
        $header = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $body = self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        return $header . '.' . $body . '.' . self::base64UrlEncode(hash_hmac('sha256', "$header.$body", self::getSecret(), true));
    }

    public static function decode($jwt) {
        if (!is_string($jwt)) return null;
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) return null;
        foreach ($parts as $part) {
            if ($part === '' || !preg_match('/^[A-Za-z0-9_-]+$/D', $part)) return null;
        }
        $header = json_decode(self::base64UrlDecode($parts[0]), true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256') return null;
        $expected = self::base64UrlEncode(hash_hmac('sha256', $parts[0] . '.' . $parts[1], self::getSecret(), true));
        if (!hash_equals($expected, $parts[2])) return null;
        $payload = json_decode(self::base64UrlDecode($parts[1]), true);
        if (!is_array($payload) || !is_numeric($payload['exp'] ?? null) || $payload['exp'] <= time() || !is_array($payload['data'] ?? null)) return null;
        return $payload;
    }

    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    private static function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/'), true) ?: '';
    }
}
