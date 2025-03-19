<?php

declare(strict_types=1);

namespace Suzumaze\DirDoc\Util;

use Suzumaze\DirDoc\Model\DirectoryItem;

use function array_merge;
use function strcasecmp;
use function usort;

/**
 * DirectorySorter - ディレクトリ構造をソートするユーティリティクラス
 */
class DirectorySorter
{
    /**
     * ディレクトリ構造を再帰的にソートする
     * ディレクトリが先、その後にファイルが来るようにソートされる
     *
     * @param DirectoryItem $root ソートするルートアイテム
     *
     * @return DirectoryItem ソートされたルートアイテム
     */
    public function sort(DirectoryItem $root): DirectoryItem
    {
        $this->sortRecursive($root);

        return $root;
    }

    /**
     * アイテムを再帰的にソートする
     *
     * @param DirectoryItem $item ソートするアイテム
     */
    private function sortRecursive(DirectoryItem $item): void
    {
        if (! $item->hasChildren()) {
            return;
        }

        // 子アイテムをディレクトリとファイルに分ける
        $directories = [];
        $files = [];

        foreach ($item->getChildren() as $child) {
            // 再帰的に子アイテムをソート
            $this->sortRecursive($child);

            if ($child->getType() === 'directory') {
                $directories[] = $child;
            } else {
                $files[] = $child;
            }
        }

        // 各グループ内でさらに名前でソート
        usort($directories, static function ($a, $b) {
            return strcasecmp($a->getName(), $b->getName());
        });

        usort($files, static function ($a, $b) {
            return strcasecmp($a->getName(), $b->getName());
        });

        // 並べ替えた子要素を親アイテムに設定
        $item->setChildren(array_merge($directories, $files));
    }
}
