<?php

declare(strict_types=1);

namespace Suzumaze\DirDoc\Json;

use Suzumaze\DirDoc\Model\DirectoryItem;

use function file_put_contents;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_UNICODE;

/**
 * JsonGenerator - DirectoryItemオブジェクトをJSON形式に変換するクラス
 */
class JsonGenerator
{
    /**
     * DirectoryItemオブジェクトをJSON文字列に変換する
     *
     * @param DirectoryItem $item      変換するDirectoryItemオブジェクト
     * @param bool          $formatted 整形されたJSONを出力するかどうか
     *
     * @return string JSON文字列
     */
    public function generateJson(DirectoryItem $item, bool $formatted = true): string
    {
        $options = JSON_UNESCAPED_UNICODE;
        if ($formatted) {
            $options |= JSON_PRETTY_PRINT;
        }

        return json_encode($item, $options);
    }

    /**
     * DirectoryItemオブジェクトをJSONファイルに保存する
     *
     * @param DirectoryItem $item      保存するDirectoryItemオブジェクト
     * @param string        $filePath  保存先のファイルパス
     * @param bool          $formatted 整形されたJSONを出力するかどうか
     *
     * @return bool 保存に成功した場合はtrue
     */
    public function saveToFile(DirectoryItem $item, string $filePath, bool $formatted = true): bool
    {
        $json = $this->generateJson($item, $formatted);

        return file_put_contents($filePath, $json) !== false;
    }
}
