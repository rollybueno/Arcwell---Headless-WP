<?php

declare(strict_types=1);

namespace Arcwell\Core;

final class Signer
{
    public static function preview(array $claims, string $secret): array
    {
        if (strlen($secret) < 32) {
            throw new \InvalidArgumentException('Preview secret must contain at least 32 bytes.');
        }
        $json = json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $payload = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        return ['payload' => $payload, 'signature' => hash_hmac('sha256', 'arcwell-preview-v1.' . $payload, $secret)];
    }
    public static function verifyPreview(string $payload, string $signature, string $secret, int $now, string $source, string $audience): array
    {
        if (
            strlen($secret) < 32 || strlen($payload) > 4096 || !preg_match('/^[A-Za-z0-9_-]+$/D', $payload)
            || !preg_match('/^[a-f0-9]{64}$/D', $signature)
            || !hash_equals(hash_hmac('sha256', 'arcwell-preview-v1.' . $payload, $secret), $signature)
        ) {
            throw new \InvalidArgumentException('Invalid preview signature.');
        }
        $claims = json_decode(base64_decode(strtr($payload, '-_', '+/'), true), true, 32, JSON_THROW_ON_ERROR);
        $required = ['v', 'source', 'audience', 'entityType', 'databaseId', 'mode', 'revisionDatabaseId', 'featuredImageDatabaseId', 'issuedAt', 'expiresAt', 'frontendUrl'];
        if (
            !is_array($claims) || array_diff(array_keys($claims), $required) || array_diff($required, array_keys($claims))
            || $claims['v'] !== 1 || $claims['source'] !== $source || $claims['audience'] !== $audience
            || !is_int($claims['databaseId']) || $claims['databaseId'] < 1
            || !in_array($claims['entityType'], ['post', 'page', 'arcwell_series'], true)
            || !in_array($claims['mode'], ['saved', 'autosave', 'revision'], true)
            || !is_int($claims['issuedAt']) || !is_int($claims['expiresAt'])
            || $claims['issuedAt'] > $now + 30 || $claims['expiresAt'] <= $now
            || $claims['expiresAt'] - $claims['issuedAt'] > 300 || $claims['expiresAt'] <= $claims['issuedAt']
            || !is_string($claims['frontendUrl']) || !str_starts_with($claims['frontendUrl'], $audience . '/')
        ) {
            throw new \InvalidArgumentException('Invalid or expired preview claims.');
        }
        foreach (['revisionDatabaseId' => 1, 'featuredImageDatabaseId' => 0] as $field => $minimum) {
            if ($claims[$field] !== null && (!is_int($claims[$field]) || $claims[$field] < $minimum)) {
                throw new \InvalidArgumentException('Invalid preview context.');
            }
        }
        if ($claims['mode'] === 'revision' && !$claims['revisionDatabaseId']) {
            throw new \InvalidArgumentException('A revision is required.');
        }
        return $claims;
    }
    public static function webhook(string $body, int $timestamp, string $secret): string
    {
        if (strlen($secret) < 32) {
            throw new \InvalidArgumentException('Webhook secret must contain at least 32 bytes.');
        }
        return 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }
}
