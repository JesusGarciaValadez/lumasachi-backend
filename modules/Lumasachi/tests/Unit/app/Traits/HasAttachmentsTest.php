<?php

namespace Modules\Lumasachi\Tests\Unit\app\Traits;

use PHPUnit\Framework\TestCase;
use Modules\Lumasachi\app\Traits\HasAttachments;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

// Test class to verify formatting logic
class HasAttachmentsFormattingTest
{
    use HasAttachments;

    private $testSize = 0;

    public function setTestSize($size)
    {
        $this->testSize = $size;
    }

    public function getTotalAttachmentsSize(): int
    {
        return $this->testSize;
    }
}

final class HasAttachmentsTest extends TestCase
{
    /**
     * Test that trait has all required methods
     */
    #[Test]
    public function it_checks_if_trait_has_required_methods(): void
    {
        $reflection = new \ReflectionClass(HasAttachments::class);
        $methods = $reflection->getMethods();
        $methodNames = array_map(function($method) {
            return $method->getName();
        }, $methods);

        $expectedMethods = [
            'attachments',
            'attach',
            'detach',
            'hasAttachments',
            'getAttachmentsByType',
            'getImageAttachments',
            'getDocumentAttachments',
            'getTotalAttachmentsSize',
            'getTotalAttachmentsSizeFormatted',
            'detachAll'
        ];

        foreach ($expectedMethods as $method) {
            $this->assertContains($method, $methodNames, "Trait should have method: {$method}");
        }
    }

    /**
     * Test getTotalAttachmentsSizeFormatted with various sizes
     */
    #[Test]
    public function it_checks_if_get_total_attachments_size_formatted_various_sizes(): void
    {
        $testObject = new HasAttachmentsFormattingTest();

        // Test bytes
        $testObject->setTestSize(512);
        $this->assertEquals('512 B', $testObject->getTotalAttachmentsSizeFormatted());

        // Test KB
        $testObject->setTestSize(1536);
        $this->assertEquals('1.5 KB', $testObject->getTotalAttachmentsSizeFormatted());

        // Test MB
        $testObject->setTestSize(1572864); // 1.5 MB
        $this->assertEquals('1.5 MB', $testObject->getTotalAttachmentsSizeFormatted());

        // Test GB
        $testObject->setTestSize(1610612736); // 1.5 GB
        $this->assertEquals('1.5 GB', $testObject->getTotalAttachmentsSizeFormatted());

        // Test TB
        $testObject->setTestSize(1649267441664); // 1.5 TB
        $this->assertEquals('1.5 TB', $testObject->getTotalAttachmentsSizeFormatted());

        // Test 0 bytes
        $testObject->setTestSize(0);
        $this->assertEquals('0 B', $testObject->getTotalAttachmentsSizeFormatted());
    }

    /**
     * Test getTotalAttachmentsSizeFormatted edge cases
     */
    #[Test]
    public function it_checks_if_get_total_attachments_size_formatted_edge_cases(): void
    {
        $testObject = new HasAttachmentsFormattingTest();

        // Test exactly 1 KB
        $testObject->setTestSize(1024);
        $this->assertEquals('1 KB', $testObject->getTotalAttachmentsSizeFormatted());

        // Test exactly 1 MB
        $testObject->setTestSize(1048576);
        $this->assertEquals('1 MB', $testObject->getTotalAttachmentsSizeFormatted());

        // Test rounding
        $testObject->setTestSize(1126); // 1.099609375 KB
        $this->assertEquals('1.1 KB', $testObject->getTotalAttachmentsSizeFormatted());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
