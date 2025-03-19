<?php

declare(strict_types=1);

namespace Suzumaze\DirDoc\Util;

use PHPUnit\Framework\TestCase;
use Suzumaze\DirDoc\Model\DirectoryItem;

/**
 * DirectorySorterTest - DirectorySorterクラスのテスト
 */
class DirectorySorterTest extends TestCase
{
    /**
     * ソート機能の基本動作をテストする
     */
    public function testBasicSorting(): void
    {
        // 未ソートの構造を作成
        $root = new DirectoryItem('root', 'directory', 'Root directory');

        // わざと順番をバラバラにして追加
        $file1 = new DirectoryItem('file1', 'file', 'File 1');
        $dir1 = new DirectoryItem('dir1', 'directory', 'Directory 1');
        $file2 = new DirectoryItem('file2', 'file', 'File 2');
        $dir2 = new DirectoryItem('dir2', 'directory', 'Directory 2');

        $root->addChild($file1);
        $root->addChild($dir1);
        $root->addChild($file2);
        $root->addChild($dir2);

        // ソート前はこの順番のはず
        $children = $root->getChildren();
        $this->assertEquals('file1', $children[0]->getName());
        $this->assertEquals('dir1', $children[1]->getName());
        $this->assertEquals('file2', $children[2]->getName());
        $this->assertEquals('dir2', $children[3]->getName());

        // ソート実行
        $sorter = new DirectorySorter();
        $sortedRoot = $sorter->sort($root);

        // ソート後はディレクトリが先、ファイルが後になるはず
        $sortedChildren = $sortedRoot->getChildren();
        $this->assertEquals('dir1', $sortedChildren[0]->getName());
        $this->assertEquals('dir2', $sortedChildren[1]->getName());
        $this->assertEquals('file1', $sortedChildren[2]->getName());
        $this->assertEquals('file2', $sortedChildren[3]->getName());
    }

    /**
     * 名前によるソートもテストする
     */
    public function testNameSorting(): void
    {
        // 未ソートの構造を作成
        $root = new DirectoryItem('root', 'directory', 'Root directory');

        // 名前がバラバラな順序で追加
        $dirC = new DirectoryItem('dirC', 'directory', 'Directory C');
        $dirA = new DirectoryItem('dirA', 'directory', 'Directory A');
        $fileD = new DirectoryItem('fileD', 'file', 'File D');
        $fileB = new DirectoryItem('fileB', 'file', 'File B');

        $root->addChild($dirC);
        $root->addChild($dirA);
        $root->addChild($fileD);
        $root->addChild($fileB);

        // ソート実行
        $sorter = new DirectorySorter();
        $sortedRoot = $sorter->sort($root);

        // ソート後はディレクトリが先（アルファベット順）、ファイルが後（アルファベット順）になるはず
        $sortedChildren = $sortedRoot->getChildren();
        $this->assertEquals('dirA', $sortedChildren[0]->getName());
        $this->assertEquals('dirC', $sortedChildren[1]->getName());
        $this->assertEquals('fileB', $sortedChildren[2]->getName());
        $this->assertEquals('fileD', $sortedChildren[3]->getName());
    }

    /**
     * 再帰的なソートをテストする
     */
    public function testRecursiveSorting(): void
    {
        // 階層のある構造を作成
        $root = new DirectoryItem('root', 'directory', 'Root directory');
        $dirA = new DirectoryItem('dirA', 'directory', 'Directory A');

        // dirAの中にファイルとディレクトリを追加（順番は逆）
        $fileA1 = new DirectoryItem('fileA1', 'file', 'File A1');
        $dirA1 = new DirectoryItem('dirA1', 'directory', 'Directory A1');

        $dirA->addChild($fileA1); // 先にファイル
        $dirA->addChild($dirA1);  // 後にディレクトリ

        $root->addChild($dirA);

        // ソート実行
        $sorter = new DirectorySorter();
        $sortedRoot = $sorter->sort($root);

        // dirAの中身も正しくソートされているか確認（ディレクトリが先になるはず）
        $dirA = $sortedRoot->getChildByName('dirA');
        $this->assertNotNull($dirA);

        $dirAChildren = $dirA->getChildren();
        $this->assertEquals('dirA1', $dirAChildren[0]->getName()); // ディレクトリが先
        $this->assertEquals('fileA1', $dirAChildren[1]->getName()); // ファイルが後
    }
}
