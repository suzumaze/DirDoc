<?php

declare(strict_types=1);

namespace Suzumaze\DirDoc\Model;

use PHPUnit\Framework\TestCase;

use function json_decode;
use function json_encode;

/**
 * DirectoryItemTest - DirectoryItemクラスのテスト
 */
class DirectoryItemTest extends TestCase
{
    /**
     * DirectoryItemの基本的な機能をテストする
     */
    public function testBasicFunctionality(): void
    {
        $item = new DirectoryItem('test-dir', 'directory', 'テストディレクトリ', '/path/to/test-dir');

        $this->assertEquals('test-dir', $item->getName());
        $this->assertEquals('directory', $item->getType());
        $this->assertEquals('テストディレクトリ', $item->getDescription());
        $this->assertEquals('/path/to/test-dir', $item->getPath());
        $this->assertFalse($item->hasChildren());
        $this->assertEmpty($item->getChildren());
    }

    /**
     * 子アイテムの追加と取得をテストする
     */
    public function testChildOperations(): void
    {
        $parent = new DirectoryItem('parent', 'directory', 'Parent directory');
        $child1 = new DirectoryItem('child1', 'directory', 'Child 1');
        $child2 = new DirectoryItem('child2', 'file', 'Child 2');

        $parent->addChild($child1);
        $parent->addChild($child2);

        $this->assertTrue($parent->hasChildren());
        $this->assertCount(2, $parent->getChildren());

        $foundChild = $parent->getChildByName('child1');
        $this->assertNotNull($foundChild);
        $this->assertEquals('child1', $foundChild->getName());

        $nonExistentChild = $parent->getChildByName('non-existent');
        $this->assertNull($nonExistentChild);
    }

    /**
     * JSONシリアライズをテストする
     */
    public function testJsonSerialization(): void
    {
        $root = new DirectoryItem('root', 'directory', 'Root directory');
        $child = new DirectoryItem('child', 'file', 'Child file');
        $root->addChild($child);

        $json = json_encode($root);
        $data = json_decode($json, true);

        $this->assertEquals('root', $data['name']);
        $this->assertEquals('directory', $data['type']);
        $this->assertEquals('Root directory', $data['description']);
        $this->assertArrayHasKey('children', $data);
        $this->assertCount(1, $data['children']);
        $this->assertEquals('child', $data['children'][0]['name']);
    }

    /**
     * 説明の設定をテストする
     */
    public function testSetDescription(): void
    {
        $item = new DirectoryItem('test', 'directory', '初期説明');
        $this->assertEquals('初期説明', $item->getDescription());

        $item->setDescription('更新された説明');
        $this->assertEquals('更新された説明', $item->getDescription());
    }

    /**
     * 子の設定をテストする
     */
    public function testSetChildren(): void
    {
        $parent = new DirectoryItem('parent', 'directory');
        $children = [
            new DirectoryItem('child1', 'file'),
            new DirectoryItem('child2', 'file'),
        ];

        $parent->setChildren($children);
        $this->assertTrue($parent->hasChildren());
        $this->assertCount(2, $parent->getChildren());

        $newChildren = [new DirectoryItem('child3', 'file')];

        $parent->setChildren($newChildren);
        $this->assertCount(1, $parent->getChildren());
        $this->assertEquals('child3', $parent->getChildren()[0]->getName());
    }

    /**
     * fromArrayメソッドをテストする
     */
    public function testFromArray(): void
    {
        $data = [
            'name' => 'root',
            'type' => 'directory',
            'description' => 'Root directory',
            'path' => '/path/to/root',
            'children' => [
                [
                    'name' => 'child1',
                    'type' => 'file',
                    'description' => 'Child file',
                ],
                [
                    'name' => 'child2',
                    'type' => 'directory',
                    'description' => 'Child directory',
                    'children' => [
                        [
                            'name' => 'grandchild',
                            'type' => 'file',
                            'description' => 'Grandchild file',
                        ],
                    ],
                ],
            ],
        ];

        $item = DirectoryItem::fromArray($data);

        $this->assertEquals('root', $item->getName());
        $this->assertEquals('directory', $item->getType());
        $this->assertEquals('Root directory', $item->getDescription());
        $this->assertEquals('/path/to/root', $item->getPath());
        $this->assertTrue($item->hasChildren());
        $this->assertCount(2, $item->getChildren());

        $child2 = $item->getChildByName('child2');
        $this->assertNotNull($child2);
        $this->assertTrue($child2->hasChildren());
        $this->assertCount(1, $child2->getChildren());
        $this->assertEquals('grandchild', $child2->getChildren()[0]->getName());
    }
}
