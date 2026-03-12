<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtHelper
{

    public static function generate(array $payload, ?int $expirySeconds = null): string
    {
        if ($expirySeconds === null) {
            $expirySeconds = (int)$_ENV['JWT_EXPIRY'];
        }
        
        $issuedAt = time();
        $payload['iat'] = $issuedAt;
        $payload['exp'] = $issuedAt + $expirySeconds;

        return JWT::encode($payload, $_ENV['JWT_SECRET'], $_ENV['JWT_ALGORITHM']);
    }

    public static function validate(string $token): ?array
    {
        try {
            return (array) JWT::decode($token, new Key($_ENV['JWT_SECRET'], $_ENV['JWT_ALGORITHM']));
        } catch (\Exception $e) {
            return null;
        }
    }
}
