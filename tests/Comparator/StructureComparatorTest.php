<?php

declare(strict_types=1);

namespace Suzumaze\DirDoc\Comparator;

use PHPUnit\Framework\TestCase;
use Suzumaze\DirDoc\Model\DirectoryItem;

/**
 * StructureComparatorTest - StructureComparatorクラスのテスト
 */
class StructureComparatorTest extends TestCase
{
    /**
     * 説明が短いアイテムの検出をテストする
     */
    public function testDescriptionDetection(): void
    {
        $comparator = new StructureComparator();

        // ルート自体が短い説明を持つ構造を作成（より単純なケース）
        $jsonStructure = new DirectoryItem('root', 'directory', 'Short');  // 短い説明
        $actualStructure = new DirectoryItem('root', 'directory', 'Short');

        // 比較実行（最小説明長を説明より長く設定）
        $comparator->compare($jsonStructure, $actualStructure, 10);

        // 説明が短いアイテムの検出を確認
        $itemsWithoutDescription = $comparator->getItemsWithoutDescription();
        $this->assertNotEmpty($itemsWithoutDescription, '説明が不足しているアイテムが検出されませんでした');
        $this->assertEquals('root', $itemsWithoutDescription[0]['name'], 'ルートアイテムが検出されませんでした');
    }

    /**
     * 説明の問題があるかどうかを判定できることをテストする
     */
    public function testHasDescriptionIssues(): void
    {
        $comparator = new StructureComparator();

        // ルート自体が短い説明を持つ構造を作成（より単純なケース）
        $jsonStructure = new DirectoryItem('root', 'directory', 'Short');  // 短い説明
        $actualStructure = new DirectoryItem('root', 'directory', 'Short');

        // 比較実行（最小説明長を説明より長く設定）
        $comparator->compare($jsonStructure, $actualStructure, 10);

        // hasDescriptionIssuesを確認
        $this->assertTrue(
            $comparator->hasDescriptionIssues(),
            'StructureComparator::hasDescriptionIssues()が短い説明を検出できませんでした'
        );

        // hasDescriptionIssuesが本当にitemsWithoutDescriptionを見ているか確認
        $this->assertSame(
            ! empty($comparator->getItemsWithoutDescription()),
            $comparator->hasDescriptionIssues(),
            'hasDescriptionIssuesがitemsWithoutDescriptionの状態を正しく反映していません'
        );
    }
}
