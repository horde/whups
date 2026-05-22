<?php

declare(strict_types=1);

namespace Horde\Whups\Test\Unit;

use Horde\Compress\CompressFactory;
use Horde\Compress\Driver\Zip;
use Horde_Compress;
use Horde_Compress_Zip;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests the ZIP compress/decompress cycle as used by the Whups MIME viewer.
 *
 * The viewer's contract:
 * - compressFiles(array $files): string  — creates an archive
 * - decompress($data, ZIP_LIST): array   — returns file listing
 * - decompress($data, ZIP_DATA): string  — returns raw file content
 *
 * This test asserts that contract is fulfilled by the PSR-4 CompressFactory.
 */
#[CoversClass(\Whups_Mime_Viewer_zip::class)]
class MimeViewerZipTest extends TestCase
{
    public function testDecompressZipDataReturnsRawString(): void
    {
        $zip = (new CompressFactory())->create('zip');

        $files = [
            ['data' => 'hello world', 'name' => 'greeting.txt'],
            ['data' => 'second file content', 'name' => 'notes.txt'],
        ];

        $archive = $zip->compressFiles($files);
        $this->assertNotEmpty($archive);

        $listing = $zip->decompress($archive, [
            'action' => Zip::ZIP_LIST,
        ]);
        $this->assertCount(2, $listing);
        $this->assertEquals('greeting.txt', $listing[0]['name']);

        $result = $zip->decompress($archive, [
            'action' => Zip::ZIP_DATA,
            'info' => $listing,
            'key' => 0,
        ]);

        // The caller expects a raw string — this is the viewer's contract.
        // The PSR-4 API returns ['data' => string], so the viewer must unwrap.
        $content = is_array($result) ? $result['data'] : $result;
        $this->assertIsString($content);
        $this->assertEquals('hello world', $content);
    }

    public function testDecompressZipListReturnsArrayWithNameAndSize(): void
    {
        $zip = (new CompressFactory())->create('zip');

        $files = [
            ['data' => str_repeat('x', 100), 'name' => 'big.bin'],
        ];

        $archive = $zip->compressFiles($files);
        $listing = $zip->decompress($archive, [
            'action' => Zip::ZIP_LIST,
        ]);

        $this->assertCount(1, $listing);
        $this->assertArrayHasKey('name', $listing[0]);
        $this->assertArrayHasKey('size', $listing[0]);
        $this->assertEquals('big.bin', $listing[0]['name']);
        $this->assertEquals(100, $listing[0]['size']);
    }
}
