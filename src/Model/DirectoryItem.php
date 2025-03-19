<?php

declare(strict_types=1);

namespace Suzumaze\DirDoc\Model;

use JsonSerializable;

use function is_array;

/**
 * DirectoryItem - ディレクトリまたはファイルの情報を表すモデルクラス
 */
class DirectoryItem implements JsonSerializable
{
    /** @var DirectoryItem[] $children 子アイテムのリスト */
    private array $children = [];

    /**
     * コンストラクタ
     *
     * @param string $name        アイテム名（ディレクトリ名またはファイル名）
     * @param string $type        アイテムタイプ（'directory' または 'file'）
     * @param string $description アイテムの説明文
     * @param string $path        アイテムのフルパス
     */
    public function __construct(
        private string $name,
        private string $type,
        private string $description = '',
        private string $path = ''
    ) {
    }

    /**
     * アイテム名を取得する
     *
     * @return string アイテム名
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * アイテムタイプを取得する
     *
     * @return string アイテムタイプ
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * アイテムの説明文を取得する
     *
     * @return string 説明文
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * アイテムの説明文を設定する
     *
     * @param string $description 説明文
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * アイテムのパスを取得する
     *
     * @return string パス
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * 子アイテムを追加する
     *
     * @param DirectoryItem $child 子アイテム
     */
    public function addChild(DirectoryItem $child): self
    {
        $this->children[] = $child;

        return $this;
    }

    /**
     * 子アイテムを名前で取得する
     *
     * @param string $name 子アイテムの名前
     *
     * @return DirectoryItem|null 見つかった子アイテム、または null
     */
    public function getChildByName(string $name): ?DirectoryItem
    {
        foreach ($this->children as $child) {
            if ($child->getName() === $name) {
                return $child;
            }
        }

        return null;
    }

    /**
     * すべての子アイテムを取得する
     *
     * @return DirectoryItem[] 子アイテムの配列
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * 子アイテムの有無を確認する
     *
     * @return bool 子アイテムがある場合は true
     */
    public function hasChildren(): bool
    {
        return ! empty($this->children);
    }

    /**
     * 子アイテムを設定する
     *
     * @param DirectoryItem[] $children 子アイテムの配列
     */
    public function setChildren(array $children): self
    {
        $this->children = $children;

        return $this;
    }

    /**
     * アイテムをJSON形式でシリアライズする
     *
     * @return array JSON形式に変換するためのデータ
     */
    public function jsonSerialize(): array
    {
        $data = [
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description,
        ];

        if (! empty($this->children)) {
            $data['children'] = $this->children;
        }

        return $data;
    }

    /**
     * JSONデータからDirectoryItemオブジェクトを生成する
     *
     * @param array $data JSONから変換されたデータ
     */
    public static function fromArray(array $data): self
    {
        $item = new self(
            $data['name'] ?? '',
            $data['type'] ?? '',
            $data['description'] ?? '',
            $data['path'] ?? ''
        );

        if (isset($data['children']) && is_array($data['children'])) {
            foreach ($data['children'] as $childData) {
                $item->addChild(self::fromArray($childData));
            }
        }

        return $item;
    }
}
