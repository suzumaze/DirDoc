<?php

declare(strict_types=1);

namespace Suzumaze\DirDoc\Json;

use PHPUnit\Framework\TestCase;
use Suzumaze\DirDoc\Model\DirectoryItem;

use function file_exists;
use function file_put_contents;
use function sys_get_temp_dir;
use function unlink;

/**
 * JsonParserTest - JsonParserクラスのテスト
 */
class JsonParserTest extends TestCase
{
    /** @var string $tempFile 一時ファイルのパス */
    private string $tempFile;

    /**
     * テスト前の準備
     */
    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/dirdoc_test_json_parser.json';
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
     * JSON文字列の解析をテストする
     */
    public function testParseJson(): void
    {
        $parser = new JsonParser();

        $json = <<<'JSON'
{
    "name": "root",
    "type": "directory",
    "description": "Root directory",
    "children": [
        {
            "name": "src",
            "type": "directory",
            "description": "Source directory"
        },
        {
            "name": "readme.md",
            "type": "file",
            "description": "README file"
        }
    ]
}
JSON;

        $item = $parser->parseJson($json);

        $this->assertInstanceOf(DirectoryItem::class, $item);
        $this->assertEquals('root', $item->getName());
        $this->assertEquals('directory', $item->getType());
        $this->assertEquals('Root directory', $item->getDescription());
        $this->assertTrue($item->hasChildren());
        $this->assertCount(2, $item->getChildren());

        $srcItem = $item->getChildByName('src');
        $this->assertNotNull($srcItem);
        $this->assertEquals('directory', $srcItem->getType());
        $this->assertEquals('Source directory', $srcItem->getDescription());

        $readmeItem = $item->getChildByName('readme.md');
        $this->assertNotNull($readmeItem);
        $this->assertEquals('file', $readmeItem->getType());
        $this->assertEquals('README file', $readmeItem->getDescription());
    }

    /**
     * 不正なJSONを解析した場合の動作をテストする
     */
    public function testParseInvalidJson(): void
    {
        $parser = new JsonParser();

        // 不正なJSON構文
        $invalidJson = '{invalid: json}';
        $result = $parser->parseJson($invalidJson);
        $this->assertNull($result);

        // 有効なJSONだが、想定した構造ではない
        $validButWrongStructure = '{"key": "value"}';
        $result = $parser->parseJson($validButWrongStructure);
        $this->assertNotNull($result);  // 基本的な配列は解析できる

        // 空のJSON
        $emptyJson = '{}';
        $result = $parser->parseJson($emptyJson);
        $this->assertNotNull($result);
        $this->assertEquals('', $result->getName());
        $this->assertEquals('', $result->getType());
    }

    /**
     * ファイルからのロードをテストする
     */
    public function testLoadFromFile(): void
    {
        $json = <<<'JSON'
{
    "name": "test",
    "type": "directory",
    "description": "Test directory",
    "children": [
        {
            "name": "file.txt",
            "type": "file",
            "description": "Test file"
        }
    ]
}
JSON;

        file_put_contents($this->tempFile, $json);

        $parser = new JsonParser();
        $item = $parser->loadFromFile($this->tempFile);

        $this->assertInstanceOf(DirectoryItem::class, $item);
        $this->assertEquals('test', $item->getName());
        $this->assertEquals('Test directory', $item->getDescription());
        $this->assertTrue($item->hasChildren());
        $this->assertCount(1, $item->getChildren());

        $fileItem = $item->getChildByName('file.txt');
        $this->assertNotNull($fileItem);
        $this->assertEquals('file', $fileItem->getType());
        $this->assertEquals('Test file', $fileItem->getDescription());
    }

    /**
     * 存在しないファイルからのロードをテストする
     */
    public function testLoadFromNonExistentFile(): void
    {
        $parser = new JsonParser();
        $item = $parser->loadFromFile('non_existent_file.json');

        $this->assertNull($item);
    }

    /**
     * 複雑な構造のJSONを解析できることをテストする
     */
    public function testParseComplexJson(): void
    {
        $json = <<<'JSON'
{
    "name": "project",
    "type": "directory",
    "description": "Project root",
    "children": [
        {
            "name": "src",
            "type": "directory",
            "description": "Source code",
            "children": [
                {
                    "name": "controllers",
                    "type": "directory",
                    "description": "Controller classes",
                    "children": [
                        {
                            "name": "HomeController.php",
                            "type": "file",
                            "description": "Home controller"
                        }
                    ]
                },
                {
                    "name": "models",
                    "type": "directory",
                    "description": "Model classes"
                }
            ]
        },
        {
            "name": "tests",
            "type": "directory",
            "description": "Test files"
        }
    ]
}
JSON;

        $parser = new JsonParser();
        $item = $parser->parseJson($json);

        $this->assertInstanceOf(DirectoryItem::class, $item);
        $this->assertEquals('project', $item->getName());
        $this->assertTrue($item->hasChildren());
        $this->assertCount(2, $item->getChildren());

        $srcItem = $item->getChildByName('src');
        $this->assertNotNull($srcItem);
        $this->assertTrue($srcItem->hasChildren());
        $this->assertCount(2, $srcItem->getChildren());

        $controllersItem = $srcItem->getChildByName('controllers');
        $this->assertNotNull($controllersItem);
        $this->assertTrue($controllersItem->hasChildren());
        $this->assertCount(1, $controllersItem->getChildren());

        $homeControllerItem = $controllersItem->getChildByName('HomeController.php');
        $this->assertNotNull($homeControllerItem);
        $this->assertEquals('file', $homeControllerItem->getType());
        $this->assertEquals('Home controller', $homeControllerItem->getDescription());
    }
}
