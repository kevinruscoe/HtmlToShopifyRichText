<?php

namespace KevinRuscoe\HtmlToShopifyRichText\Tests;

use PHPUnit\Framework\TestCase;

class BinTest extends TestCase
{
    private string $binPath;
    private string $testHtmlPath;
    private string $outputJsonPath;

    protected function setUp(): void
    {
        $this->binPath = __DIR__ . '/../bin/html-to-shopify-rich-text';
        $this->testHtmlPath = __DIR__ . '/fixtures/test.html';
        $this->outputJsonPath = __DIR__ . '/fixtures/test.json';
    }

    protected function tearDown(): void
    {
        // Clean up the output file if it exists
        if (file_exists($this->outputJsonPath)) {
            unlink($this->outputJsonPath);
        }
    }

    public function testBinScriptConvertsHtmlToJson(): void
    {
        // Ensure the test file exists and is readable
        $this->assertFileExists($this->testHtmlPath);
        $this->assertFileIsReadable($this->testHtmlPath);

        // Run the bin script
        $command = sprintf('%s %s 2>&1', $this->binPath, $this->testHtmlPath);
        exec($command, $output, $returnCode);

        // Verify the script executed successfully
        $this->assertEquals(0, $returnCode, 'Script should exit with code 0');
        $this->assertStringContainsString('Successfully converted', implode("\n", $output));

        // Verify the output file was created
        $this->assertFileExists($this->outputJsonPath, 'Output JSON file should be created');

        // Verify the JSON content is valid
        $jsonContent = file_get_contents($this->outputJsonPath);
        $this->assertNotFalse($jsonContent, 'Should be able to read the JSON file');
        
        $jsonData = json_decode($jsonContent, true);
        $this->assertNotNull($jsonData, 'JSON should be valid');
        $this->assertIsArray($jsonData, 'JSON should decode to an array');

        // Verify basic structure of the converted content
        $this->assertEquals('root', $jsonData['type'] ?? '', 'Root type should be "root"');
        $this->assertArrayHasKey('children', $jsonData, 'Root should have children');
        $this->assertNotEmpty($jsonData['children'], 'Root should have non-empty children');
    }

    public function testBinScriptHandlesNonExistentFile(): void
    {
        $nonExistentFile = __DIR__ . '/fixtures/nonexistent.html';
        $command = sprintf('%s %s', $this->binPath, $nonExistentFile);
        exec($command, $output, $returnCode);

        $this->assertEquals(1, $returnCode, 'Script should exit with code 1 for non-existent file');
        $this->assertStringContainsString('does not exist', implode("\n", $output));
    }

    public function testBinScriptHandlesInvalidHtml(): void
    {
        // Create a temporary file with invalid HTML (unclosed tag)
        $invalidHtmlPath = __DIR__ . '/fixtures/invalid.html';
        file_put_contents($invalidHtmlPath, '<div>This is unclosed');
        clearstatcache(true, $invalidHtmlPath);

        // Ensure the file exists and is readable
        $this->assertFileExists($invalidHtmlPath);
        $this->assertFileIsReadable($invalidHtmlPath);

        $command = sprintf('%s %s 2>&1', $this->binPath, $invalidHtmlPath);
        exec($command, $output, $returnCode);

        // Clean up
        if (file_exists($invalidHtmlPath)) {
            unlink($invalidHtmlPath);
        }

        $this->assertEquals(1, $returnCode, 'Script should exit with code 1 for invalid HTML');
        $this->assertStringContainsString('Error', implode("\n", $output));
    }
} 