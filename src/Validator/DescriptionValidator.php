<?php

declare(strict_types=1);

namespace Suzumaze\DirDoc\Validator;

use Suzumaze\DirDoc\Config\ConfigurationLoader;
use Suzumaze\DirDoc\Model\DirectoryItem;

use function count;
use function strlen;

/**
 * DescriptionValidator - 説明文の検証を行うクラス
 */
class DescriptionValidator
{
    /** @var ConfigurationLoader $config 設定ローダー */
    private ConfigurationLoader $config;

    /** @var array<int, array<string, mixed>> $itemsWithoutDescription 説明が不足しているアイテムのリスト */
    private array $itemsWithoutDescription = [];

    /** @var array<int, array<string, mixed>> $itemsWithShortDescription 説明が短すぎるアイテムのリスト */
    private array $itemsWithShortDescription = [];

    /**
     * コンストラクタ
     *
     * @param ConfigurationLoader $config 設定ローダー
     */
    public function __construct(ConfigurationLoader $config)
    {
        $this->config = $config;
    }

    /**
     * ディレクトリ構造の説明文をバリデーションする
     *
     * @param DirectoryItem $structure バリデーションするディレクトリ構造
     *
     * @return bool すべての説明文が有効な場合はtrue
     */
    public function validate(DirectoryItem $structure): bool
    {
        // リセット
        $this->itemsWithoutDescription = [];
        $this->itemsWithShortDescription = [];

        // 再帰的にバリデーション
        $this->validateItem($structure, '');

        // すべてのアイテムが有効な説明文を持っている場合はtrue
        return $this->countIssues() === 0;
    }

    /**
     * アイテムとその子を再帰的にバリデーションする
     *
     * @param DirectoryItem $item バリデーションするアイテム
     * @param string        $path 現在のパス
     */
    private function validateItem(DirectoryItem $item, string $path): void
    {
        $currentPath = $path . '/' . $item->getName();
        $description = $item->getDescription();
        $isDescriptionRequired = $this->config->isDescriptionRequired();
        $minLength = $this->config->getMinDescriptionLength();

        // 説明が必須で説明がない場合
        if ($isDescriptionRequired && $description === '') {
            $this->itemsWithoutDescription[] = [
                'path' => $currentPath,
                'type' => $item->getType(),
                'name' => $item->getName(),
            ];
        } elseif ($description !== '' && strlen($description) < $minLength) {
            // 説明があるが短すぎる場合
            $this->itemsWithShortDescription[] = [
                'path' => $currentPath,
                'type' => $item->getType(),
                'name' => $item->getName(),
                'description' => $description,
                'min_length' => $minLength,
            ];
        }

        // 子アイテムを再帰的にバリデーション
        foreach ($item->getChildren() as $child) {
            $this->validateItem($child, $currentPath);
        }
    }

    /**
     * 説明が欠けているアイテムのリストを取得する
     *
     * @return array<int, array<string, mixed>> アイテムのリスト
     */
    public function getItemsWithoutDescription(): array
    {
        return $this->itemsWithoutDescription;
    }

    /**
     * 説明が短すぎるアイテムのリストを取得する
     *
     * @return array<int, array<string, mixed>> アイテムのリスト
     */
    public function getItemsWithShortDescription(): array
    {
        return $this->itemsWithShortDescription;
    }

    /**
     * 説明文に問題があるアイテムの数を取得する
     *
     * @return int 問題のあるアイテムの数
     */
    public function countIssues(): int
    {
        return count($this->itemsWithoutDescription) + count($this->itemsWithShortDescription);
    }
}
