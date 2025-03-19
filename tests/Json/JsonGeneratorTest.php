<?php

declare(strict_types=1);

namespace Suzumaze\DirDoc\Json;

use PHPUnit\Framework\TestCase;
use Suzumaze\DirDoc\Model\DirectoryItem;

use function file_exists;
use function file_get_contents;
use function json_decode;
use function sys_get_temp_dir;
use function unlink;

/**
 * JsonGeneratorTest - JsonGeneratorクラスのテスト
 */
class JsonGeneratorTest extends TestCase
{
    /** @var string $tempFile 一時ファイルのパス */
    private string $tempFile;

    /**
     * テスト前の準備
     */
    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/dirdoc_test_json.json';
    }

    /**
     * テスト後のクリーンアップ
     */
    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    /**
     * JSON文字列の生成をテストする
     */
    public function testGenerateJson(): void
    {
        $generator = new JsonGenerator();

        // テスト用のディレクトリ構造を作成
        $root = new DirectoryItem('root', 'directory', 'Root directory');
        $child1 = new DirectoryItem('child1', 'directory', 'Child directory');
        $child2 = new DirectoryItem('child2', 'file', 'Child file');

        $root->addChild($child1);
        $root->addChild($child2);

        // 整形なしのJSON
        $jsonUnformatted = $generator->generateJson($root, false);
        $this->assertIsString($jsonUnformatted);
        $this->assertStringContainsString('"name":"root"', $jsonUnformatted);
        $this->assertStringNotContainsString("\n", $jsonUnformatted);

        // 整形ありのJSON
        $jsonFormatted = $generator->generateJson($root, true);
        $this->assertIsString($jsonFormatted);
        $this->assertStringContainsString('"name": "root"', $jsonFormatted);
        $this->assertStringContainsString("\n", $jsonFormatted);

        // 日本語が正しくエンコードされること
        $japaneseItem = new DirectoryItem('日本語', 'directory', '日本語の説明');
        $japaneseJson = $generator->generateJson($japaneseItem);
        $this->assertStringContainsString('"name": "日本語"', $japaneseJson);
        $this->assertStringContainsString('"description": "日本語の説明"', $japaneseJson);
    }

    /**
     * JSONファイルへの保存をテストする
     */
    public function testSaveToFile(): void
    {
        $generator = new JsonGenerator();

        $item = new DirectoryItem('test', 'directory', 'Test directory');

        // ファイルに保存
        $result = $generator->saveToFile($item, $this->tempFile);
        $this->assertTrue($result);
        $this->assertFileExists($this->tempFile);

        // ファイルの内容を確認
        $content = file_get_contents($this->tempFile);
        $data = json_decode($content, true);

        $this->assertEquals('test', $data['name']);
        $this->assertEquals('directory', $data['type']);
        $this->assertEquals('Test directory', $data['description']);
    }

    /**
     * 複雑な構造のJSONを生成できることをテストする
     */
    public function testGenerateComplexJson(): void
    {
        $generator = new JsonGenerator();

        // 複雑なディレクトリ構造を作成
        $root = new DirectoryItem('project', 'directory', 'Project root');

        $src = new DirectoryItem('src', 'directory', 'Source code');
        $tests = new DirectoryItem('tests', 'directory', 'Test files');
        $docs = new DirectoryItem('docs', 'directory', 'Documentation');

        $root->addChild($src);
        $root->addChild($tests);
        $root->addChild($docs);

        $controllers = new DirectoryItem('controllers', 'directory', 'Controller classes');
        $models = new DirectoryItem('models', 'directory', 'Model classes');

        $src->addChild($controllers);
        $src->addChild($models);

        $json = $generator->generateJson($root);

        // JSONをデコードして構造を確認
        $data = json_decode($json, true);

        $this->assertEquals('project', $data['name']);
        $this->assertCount(3, $data['children']);

        $srcData = null;
        foreach ($data['children'] as $child) {
            if ($child['name'] === 'src') {
                $srcData = $child;
                break;
            }
        }

        $this->assertNotNull($srcData);
        $this->assertCount(2, $srcData['children']);

        $controllerFound = false;
        $modelFound = false;

        foreach ($srcData['children'] as $srcChild) {
            if ($srcChild['name'] === 'controllers') {
                $controllerFound = true;
                $this->assertEquals('Controller classes', $srcChild['description']);
            } elseif ($srcChild['name'] === 'models') {
                $modelFound = true;
                $this->assertEquals('Model classes', $srcChild['description']);
            }
        }

        $this->assertTrue($controllerFound);
        $this->assertTrue($modelFound);
    }
}
