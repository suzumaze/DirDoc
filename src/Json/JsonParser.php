<?php

declare(strict_types=1);

namespace Suzumaze\DirDoc\Json;

use Suzumaze\DirDoc\Model\DirectoryItem;

use function file_exists;
use function file_get_contents;
use function is_array;
use function json_decode;
use function json_last_error;

use const JSON_ERROR_NONE;

/**
 * JsonParser - JSON文字列からDirectoryItemオブジェクトを生成するクラス
 */
class JsonParser
{
    /**
     * JSONファイルからDirectoryItemオブジェクトを読み込む
     *
     * @param string $filePath JSONファイルのパス
     *
     * @return DirectoryItem|null 読み込んだDirectoryItemオブジェクト、エラー時はnull
     */
    public function loadFromFile(string $filePath): ?DirectoryItem
    {
        if (! file_exists($filePath)) {
            return null;
        }

        $json = file_get_contents($filePath);
        if ($json === false) {
            return null;
        }

        return $this->parseJson($json);
    }

    /**
     * JSON文字列からDirectoryItemオブジェクトを生成する
     *
     * @param string $json JSON文字列
     *
     * @return DirectoryItem|null 生成したDirectoryItemオブジェクト、エラー時はnull
     */
    public function parseJson(string $json): ?DirectoryItem
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            return null;
        }

        return DirectoryItem::fromArray($data);
    }
}
