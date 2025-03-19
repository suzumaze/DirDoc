<?php

declare(strict_types=1);

namespace Suzumaze\DirDoc\Scanner;

use RuntimeException;
use Suzumaze\DirDoc\Config\ConfigurationLoader;
use Suzumaze\DirDoc\Model\DirectoryItem;
use Symfony\Component\Filesystem\Filesystem;

use function basename;
use function count;
use function fnmatch;
use function is_dir;
use function is_file;
use function realpath;
use function scandir;

use const DIRECTORY_SEPARATOR;

/**
 * DirectoryScanner - ファイルシステムを走査してディレクトリ構造を収集するクラス
 */
class DirectoryScanner
{
    /** @var ConfigurationLoader $config 設定ローダー */
    private ConfigurationLoader $config;

    /** @var Filesystem $filesystem ファイルシステムユーティリティ */
    private Filesystem $filesystem;

    /**
     * コンストラクタ
     *
     * @param ConfigurationLoader $config 設定ローダー
     */
    public function __construct(ConfigurationLoader $config)
    {
        $this->config = $config;
        $this->filesystem = new Filesystem();
    }

    /**
     * 指定されたディレクトリからスキャンを開始し、ディレクトリ構造を構築する
     *
     * @param string|null $rootPath スキャンを開始するルートディレクトリパス（null の場合は設定値を使用）
     *
     * @return DirectoryItem ルートディレクトリを表すDirectoryItemオブジェクト
     */
    public function scan(?string $rootPath = null): DirectoryItem
    {
        $rootPath ??= $this->config->getRootPath();

        // パスが存在しない場合はエラー
        if (! $this->filesystem->exists($rootPath)) {
            throw new RuntimeException("指定されたパスが存在しません: {$rootPath}");
        }

        $realPath = realpath($rootPath);
        $dirName = basename($realPath);

        // ルートディレクトリのDirectoryItemを作成
        $rootItem = new DirectoryItem(
            $dirName,
            'directory',
            'プロジェクトのルートディレクトリ', // デフォルトの説明
            $realPath
        );

        // 再帰的にスキャン
        $this->scanRecursive($rootItem, $realPath, 0);

        return $rootItem;
    }

    /**
     * 再帰的にディレクトリをスキャンする
     *
     * @param DirectoryItem $parentItem   親ディレクトリを表すDirectoryItemオブジェクト
     * @param string        $path         スキャンするパス
     * @param int           $currentDepth 現在の深さ
     * @param string        $relativePath ルートからの相対パス
     */
    private function scanRecursive(DirectoryItem $parentItem, string $path, int $currentDepth, string $relativePath = ''): void
    {
        // 相対パスからディレクトリ名を取得（空の場合はカレントディレクトリ）
        $dirName = $relativePath !== '' ? basename($relativePath) : basename($path);

        // このディレクトリの最大深さを取得
        $maxDepth = $this->config->getDepthForDirectory($dirName);

        // 最大深さに達したらスキャンを中止
        if ($currentDepth >= $maxDepth) {
            return;
        }

        // ディレクトリ内のファイルとディレクトリを取得
        $items = scandir($path);

        // ディレクトリ内のアイテムを処理
        foreach ($items as $item) {
            // . と .. を除外
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            $itemRelativePath = $relativePath !== '' ? $relativePath . DIRECTORY_SEPARATOR . $item : $item;

            // 除外パターンに一致する場合はスキップ
            if ($this->shouldExclude($itemRelativePath)) {
                continue;
            }

            // ディレクトリの場合
            if (is_dir($itemPath)) {
                // 空のディレクトリを含めない設定で、ディレクトリが空の場合はスキップ
                if (! $this->config->includeEmptyDirectories() && $this->isEmptyDirectory($itemPath)) {
                    continue;
                }

                // ディレクトリアイテムを作成
                $directoryItem = new DirectoryItem(
                    $item,
                    'directory',
                    '', // 説明は初期状態では空
                    $itemPath
                );

                // 親ディレクトリに追加
                $parentItem->addChild($directoryItem);

                // 再帰的にスキャン
                $this->scanRecursive($directoryItem, $itemPath, $currentDepth + 1, $itemRelativePath);
            } elseif (
                // ファイルの場合（ルートファイルを含める設定、またはルートディレクトリではない場合）
                is_file($itemPath) &&
                   ($this->config->includeRootFiles() || $currentDepth > 0)
            ) {
                // ファイルパターン除外に一致する場合はスキップ
                if ($this->shouldExcludeFile($item)) {
                    continue;
                }

                // ファイルアイテムを作成
                $fileItem = new DirectoryItem(
                    $item,
                    'file',
                    '', // 説明は初期状態では空
                    $itemPath
                );

                // 親ディレクトリに追加
                $parentItem->addChild($fileItem);
            }
        }
    }

    /**
     * パスが除外パターンに一致するかどうかを確認する
     *
     * @param string $path チェックするパス
     *
     * @return bool 除外すべき場合は true
     */
    private function shouldExclude(string $path): bool
    {
        $excludePatterns = $this->config->getExcludePatterns();

        foreach ($excludePatterns as $pattern) {
            if (fnmatch($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * ファイルが除外ファイルパターンに一致するかどうかを確認する
     *
     * @param string $fileName ファイル名
     *
     * @return bool 除外すべき場合は true
     */
    private function shouldExcludeFile(string $fileName): bool
    {
        $excludeFilePatterns = $this->config->getExcludeFilePatterns();

        foreach ($excludeFilePatterns as $pattern) {
            if (fnmatch($pattern, $fileName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * ディレクトリが空かどうかを確認する
     *
     * @param string $path ディレクトリパス
     *
     * @return bool ディレクトリが空の場合は true
     */
    private function isEmptyDirectory(string $path): bool
    {
        $items = scandir($path);

        return count($items) <= 2; // . と .. のみの場合は空
    }
}
