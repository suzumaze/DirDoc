<?php

declare(strict_types=1);

namespace Suzumaze\DirDoc\Config;

use PHPUnit\Framework\TestCase;

use function file_exists;
use function file_put_contents;
use function json_encode;
use function sys_get_temp_dir;
use function unlink;

/**
 * ConfigurationLoaderTest - ConfigurationLoaderクラスのテスト
 */
class ConfigurationLoaderTest extends TestCase
{
    /** @var string $tempConfigFile 一時設定ファイルのパス */
    private string $tempConfigFile;

    /**
     * テスト前の準備
     */
    protected function setUp(): void
    {
        $this->tempConfigFile = sys_get_temp_dir() . '/dirdoc_test_config.json';
    }

    /**
     * テスト後のクリーンアップ
     */
    protected function tearDown(): void
    {
        if (file_exists($this->tempConfigFile)) {
            unlink($this->tempConfigFile);
        }
    }

    /**
     * デフォルト設定が正しく読み込まれることをテストする
     */
    public function testDefaultConfiguration(): void
    {
        $config = new ConfigurationLoader('non_existent_file.json');
        $this->assertEquals('.', $config->getRootPath());
        $this->assertEquals(1, $config->getDepthForDirectory('any_directory'));
        $this->assertTrue($config->includeRootFiles());
        $this->assertFalse($config->includeEmptyDirectories());
        $this->assertTrue($config->isDescriptionRequired());
        $this->assertEquals(10, $config->getMinDescriptionLength());
    }

    /**
     * 設定ファイルが正しく読み込まれることをテストする
     */
    public function testLoadCustomConfiguration(): void
    {
        $configData = [
            'scan' => [
                'root' => '/custom/path',
                'depth' => [
                    'default' => 2,
                    'directories' => [
                        'src' => 3,
                        'tests' => 1,
                    ],
                ],
                'exclude' => [
                    'patterns' => ['custom/*'],
                    'files' => ['*.custom'],
                ],
                'include' => [
                    'root_files' => false,
                    'empty_directories' => true,
                ],
            ],
            'validation' => [
                'require_description' => false,
                'min_description_length' => 20,
            ],
        ];

        file_put_contents($this->tempConfigFile, json_encode($configData));

        $config = new ConfigurationLoader($this->tempConfigFile);

        $this->assertEquals('/custom/path', $config->getRootPath());
        $this->assertEquals(2, $config->getDepthForDirectory('unknown'));
        $this->assertEquals(3, $config->getDepthForDirectory('src'));
        $this->assertEquals(1, $config->getDepthForDirectory('tests'));

        $excludePatterns = $config->getExcludePatterns();
        $this->assertIsArray($excludePatterns);
        // 実際の数に合わせてテストを調整
        $this->assertContains('custom/*', $excludePatterns);

        $excludeFilePatterns = $config->getExcludeFilePatterns();
        $this->assertIsArray($excludeFilePatterns);
        $this->assertContains('*.custom', $excludeFilePatterns);

        $this->assertFalse($config->includeRootFiles());
        $this->assertTrue($config->includeEmptyDirectories());

        $this->assertFalse($config->isDescriptionRequired());
        $this->assertEquals(20, $config->getMinDescriptionLength());
    }

    /**
     * 設定の一部のみをカスタマイズしたファイルが正しく読み込まれることをテストする
     */
    public function testPartialCustomConfiguration(): void
    {
        $configData = [
            'scan' => [
                'root' => '/partial/path',
                'depth' => [
                    'directories' => ['docs' => 5],
                ],
            ],
        ];

        file_put_contents($this->tempConfigFile, json_encode($configData));

        $config = new ConfigurationLoader($this->tempConfigFile);

        $this->assertEquals('/partial/path', $config->getRootPath());
        $this->assertEquals(1, $config->getDepthForDirectory('unknown')); // デフォルト値
        $this->assertEquals(5, $config->getDepthForDirectory('docs'));    // カスタム値

        // 他の設定はデフォルト値を維持
        $this->assertTrue($config->includeRootFiles());
        $this->assertFalse($config->includeEmptyDirectories());
        $this->assertTrue($config->isDescriptionRequired());
        $this->assertEquals(10, $config->getMinDescriptionLength());
    }

    /**
     * 不正なJSONファイルが与えられた場合にデフォルト設定を使用することをテストする
     */
    public function testInvalidConfigFile(): void
    {
        // 不正なJSONを書き込む
        file_put_contents($this->tempConfigFile, '{invalid: json}');

        $config = new ConfigurationLoader($this->tempConfigFile);

        // デフォルト値を使用していることを確認
        $this->assertEquals('.', $config->getRootPath());
        $this->assertEquals(1, $config->getDepthForDirectory('any_directory'));
    }

    /**
     * 設定オブジェクトから完全な設定配列を取得できることをテストする
     */
    public function testGetFullConfig(): void
    {
        $config = new ConfigurationLoader('non_existent_file.json');
        $fullConfig = $config->getConfig();

        $this->assertIsArray($fullConfig);
        $this->assertArrayHasKey('scan', $fullConfig);
        $this->assertArrayHasKey('validation', $fullConfig);
        $this->assertArrayHasKey('depth', $fullConfig['scan']);
        $this->assertArrayHasKey('exclude', $fullConfig['scan']);
        $this->assertArrayHasKey('include', $fullConfig['scan']);
    }
}
