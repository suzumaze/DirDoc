<?php

declare(strict_types=1);

namespace Suzumaze\DirDoc\Comparator;

use Suzumaze\DirDoc\Model\DirectoryItem;

use function strlen;

/**
 * StructureComparator - 既存のJSONと実際のディレクトリ構造を比較するクラス
 */
class StructureComparator
{
    /** @var array $missingInActual 実際の構造には存在しないがJSONには存在するアイテムのリスト */
    private array $missingInActual = [];

    /** @var array $missingInJson JSONには存在しないが実際の構造に存在するアイテムのリスト */
    private array $missingInJson = [];

    /** @var array $differentDescriptions 説明が異なるアイテムのリスト */
    private array $differentDescriptions = [];

    /** @var array $itemsWithoutDescription 説明が欠けているアイテムのリスト */
    private array $itemsWithoutDescription = [];

    /**
     * 二つのディレクトリ構造を比較する
     *
     * @param DirectoryItem $jsonStructure        JSONから読み込んだ構造
     * @param DirectoryItem $actualStructure      実際のファイルシステムから読み込んだ構造
     * @param int           $minDescriptionLength 最小説明文字数
     */
    public function compare(
        DirectoryItem $jsonStructure,
        DirectoryItem $actualStructure,
        int $minDescriptionLength = 10
    ): void {
        // 結果をリセット
        $this->missingInActual = [];
        $this->missingInJson = [];
        $this->differentDescriptions = [];
        $this->itemsWithoutDescription = [];

        // ルート同士の比較から開始
        $this->compareItems($jsonStructure, $actualStructure, '', $minDescriptionLength);
    }

    /**
     * 二つのDirectoryItemを再帰的に比較する
     *
     * @param DirectoryItem $jsonItem             JSONから読み込んだアイテム
     * @param DirectoryItem $actualItem           実際のファイルシステムから読み込んだアイテム
     * @param string        $path                 現在のパス
     * @param int           $minDescriptionLength 最小説明文字数
     */
    private function compareItems(
        DirectoryItem $jsonItem,
        DirectoryItem $actualItem,
        string $path,
        int $minDescriptionLength
    ): void {
        $currentPath = $path . '/' . $jsonItem->getName();

        // 説明が不足しているかチェック
        if (strlen($jsonItem->getDescription()) < $minDescriptionLength) {
            $this->itemsWithoutDescription[] = [
                'path' => $currentPath,
                'type' => $jsonItem->getType(),
                'name' => $jsonItem->getName(),
                'description' => $jsonItem->getDescription(),
            ];
        }

        // 子アイテムを比較
        $jsonChildren = $jsonItem->getChildren();
        $actualChildren = $actualItem->getChildren();

        // 実際の構造にないJSONの子アイテムを検出
        foreach ($jsonChildren as $jsonChild) {
            $found = false;
            foreach ($actualChildren as $actualChild) {
                if (
                    $jsonChild->getName() === $actualChild->getName() &&
                    $jsonChild->getType() === $actualChild->getType()
                ) {
                    $found = true;

                    // 説明が異なる場合は記録
                    if (
                        $jsonChild->getDescription() !== $actualChild->getDescription() &&
                        $actualChild->getDescription() !== ''
                    ) {
                        $this->differentDescriptions[] = [
                            'path' => $currentPath . '/' . $jsonChild->getName(),
                            'type' => $jsonChild->getType(),
                            'name' => $jsonChild->getName(),
                            'json_description' => $jsonChild->getDescription(),
                            'actual_description' => $actualChild->getDescription(),
                        ];
                    }

                    // 子を持つ場合はさらに再帰的に比較
                    if ($jsonChild->hasChildren() && $actualChild->hasChildren()) {
                        $this->compareItems($jsonChild, $actualChild, $currentPath, $minDescriptionLength);
                    }

                    break;
                }
            }

            if (! $found) {
                $this->missingInActual[] = [
                    'path' => $currentPath . '/' . $jsonChild->getName(),
                    'type' => $jsonChild->getType(),
                    'name' => $jsonChild->getName(),
                ];
            }
        }

        // JSONにない実際の子アイテムを検出
        foreach ($actualChildren as $actualChild) {
            $found = false;
            foreach ($jsonChildren as $jsonChild) {
                if (
                    $actualChild->getName() === $jsonChild->getName() &&
                    $actualChild->getType() === $jsonChild->getType()
                ) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $this->missingInJson[] = [
                    'path' => $currentPath . '/' . $actualChild->getName(),
                    'type' => $actualChild->getType(),
                    'name' => $actualChild->getName(),
                ];
            }
        }
    }

    /**
     * 実際の構造には存在しないがJSONには存在するアイテムのリストを取得する
     *
     * @return array アイテムのリスト
     */
    public function getMissingInActual(): array
    {
        return $this->missingInActual;
    }

    /**
     * JSONには存在しないが実際の構造に存在するアイテムのリストを取得する
     *
     * @return array アイテムのリスト
     */
    public function getMissingInJson(): array
    {
        return $this->missingInJson;
    }

    /**
     * 説明が異なるアイテムのリストを取得する
     *
     * @return array アイテムのリスト
     */
    public function getDifferentDescriptions(): array
    {
        return $this->differentDescriptions;
    }

    /**
     * 説明が欠けているアイテムのリストを取得する
     *
     * @return array アイテムのリスト
     */
    public function getItemsWithoutDescription(): array
    {
        return $this->itemsWithoutDescription;
    }

    /**
     * アイテムに不整合があるかどうかをチェックする
     *
     * @return bool 不整合がある場合はtrue
     */
    public function hasDiscrepancies(): bool
    {
        return ! empty($this->missingInActual) || ! empty($this->missingInJson);
    }

    /**
     * 説明に問題のあるアイテムがあるかどうかをチェックする
     *
     * @return bool 説明に問題のあるアイテムがある場合はtrue
     */
    public function hasDescriptionIssues(): bool
    {
        return ! empty($this->differentDescriptions) || ! empty($this->itemsWithoutDescription);
    }
}
