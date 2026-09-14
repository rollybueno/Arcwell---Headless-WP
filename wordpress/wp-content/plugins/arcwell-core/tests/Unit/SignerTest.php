<?php
declare(strict_types=1);
use Arcwell\Core\Signer;
use PHPUnit\Framework\TestCase;

final class SignerTest extends TestCase
{
    private string $secret = 'a-long-fixture-secret-with-at-least-32-bytes';
    private function claims(): array
    {
        return ['v' => 1, 'source' => 'test-source', 'audience' => 'https://arcwell.test', 'entityType' => 'post',
            'databaseId' => 12, 'mode' => 'autosave', 'revisionDatabaseId' => 13, 'featuredImageDatabaseId' => 0,
            'issuedAt' => 1000, 'expiresAt' => 1300, 'frontendUrl' => 'https://arcwell.test/articles/hello/'];
    }
    public function testRoundTripPreservesImageRemoval(): void
    {
        $signed = Signer::preview($this->claims(), $this->secret);
        $actual = Signer::verifyPreview($signed['payload'], $signed['signature'], $this->secret, 1100, 'test-source', 'https://arcwell.test');
        self::assertSame($this->claims(), $actual);
    }
    public function testTamperingIsRejected(): void
    {
        $signed = Signer::preview($this->claims(), $this->secret);
        $this->expectException(InvalidArgumentException::class);
        Signer::verifyPreview($signed['payload'] . 'a', $signed['signature'], $this->secret, 1100, 'test-source', 'https://arcwell.test');
    }
    public function testExpiredLaunchIsRejected(): void
    {
        $signed = Signer::preview($this->claims(), $this->secret);
        $this->expectException(InvalidArgumentException::class);
        Signer::verifyPreview($signed['payload'], $signed['signature'], $this->secret, 1300, 'test-source', 'https://arcwell.test');
    }
    public function testCrossEnvironmentIsRejected(): void
    {
        $signed = Signer::preview($this->claims(), $this->secret);
        $this->expectException(InvalidArgumentException::class);
        Signer::verifyPreview($signed['payload'], $signed['signature'], $this->secret, 1100, 'production', 'https://arcwell.test');
    }
    public function testUnknownClaimsAreRejected(): void
    {
        $signed = Signer::preview($this->claims() + ['redirect' => 'https://evil.test'], $this->secret);
        $this->expectException(InvalidArgumentException::class);
        Signer::verifyPreview($signed['payload'], $signed['signature'], $this->secret, 1100, 'test-source', 'https://arcwell.test');
    }
    public function testBodyBytesMatterForWebhookSignatures(): void
    {
        self::assertNotSame(Signer::webhook('{"a":1}', 1000, $this->secret), Signer::webhook('{ "a":1}', 1000, $this->secret));
        self::assertSame('sha256=' . hash_hmac('sha256', '1000.{"a":1}', $this->secret), Signer::webhook('{"a":1}', 1000, $this->secret));
    }
}
