<?php

declare(strict_types=1);

use App\Traits\HasAttachments;

afterEach(function (): void {
    Mockery::close();
});

test('checks if trait has required methods', function (): void {
    $reflection = new ReflectionClass(HasAttachments::class);
    $methods = $reflection->getMethods();
    $methodNames = array_map(function ($method): string {
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
        'detachAll',
    ];

    foreach ($expectedMethods as $method) {
        expect($methodNames)->toContain($method);
    }
});

test('checks if get total attachments size formatted various sizes', function (): void {
    $testObject = new class {
        use HasAttachments;

        private int $testSize = 0;

        public function setTestSize(int $size): void
        {
            $this->testSize = $size;
        }

        public function getTotalAttachmentsSize(): int
        {
            return $this->testSize;
        }
    };

    $testObject->setTestSize(512);
    expect($testObject->getTotalAttachmentsSizeFormatted())->toEqual('512 B');

    $testObject->setTestSize(1536);
    expect($testObject->getTotalAttachmentsSizeFormatted())->toEqual('1.5 KB');

    $testObject->setTestSize(1572864);
    expect($testObject->getTotalAttachmentsSizeFormatted())->toEqual('1.5 MB');

    $testObject->setTestSize(1610612736);
    expect($testObject->getTotalAttachmentsSizeFormatted())->toEqual('1.5 GB');

    $testObject->setTestSize(1649267441664);
    expect($testObject->getTotalAttachmentsSizeFormatted())->toEqual('1.5 TB');

    $testObject->setTestSize(0);
    expect($testObject->getTotalAttachmentsSizeFormatted())->toEqual('0 B');
});

test('checks if get total attachments size formatted edge cases', function (): void {
    $testObject = new class {
        use HasAttachments;

        private int $testSize = 0;

        public function setTestSize(int $size): void
        {
            $this->testSize = $size;
        }

        public function getTotalAttachmentsSize(): int
        {
            return $this->testSize;
        }
    };

    $testObject->setTestSize(1024);
    expect($testObject->getTotalAttachmentsSizeFormatted())->toEqual('1 KB');

    $testObject->setTestSize(1048576);
    expect($testObject->getTotalAttachmentsSizeFormatted())->toEqual('1 MB');

    $testObject->setTestSize(1126);
    expect($testObject->getTotalAttachmentsSizeFormatted())->toEqual('1.1 KB');
});
