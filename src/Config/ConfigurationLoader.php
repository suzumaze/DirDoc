<?php

declare(strict_types=1);

namespace Suzumaze\DirDoc\Config;

use function array_replace_recursive;
use function file_exists;
use function file_get_contents;
use function getcwd;
use function is_array;
use function json_decode;
use function json_last_error;

use const JSON_ERROR_NONE;

/**
 * ConfigurationLoader - コンフィグファイルの読み込みと設定の管理を行うクラス
 */
class ConfigurationLoader
{
    /**
     * デフォルト設定
     */
    private array $defaultConfig = [
        'scan' => [
            'root' => '.',
            'depth' => [
                'default' => 1,
                'directories' => [],
            ],
            'exclude' => [
                'patterns' => [
                    'vendor/*',
                    'node_modules/*',
                    '.git/*',
                ],
                'files' => [
                    '*.log',
                    '*.cache',
                ],
            ],
            'include' => [
                'root_files' => true,
                'empty_directories' => false,
            ],
        ],
        'validation' => [
            'require_description' => true,
            'min_description_length' => 10,
        ],
    ];

    /**
     * 現在のコンフィグ
     */
    private array $config;

    /**
     * コンフィグファイルのパス
     */
    private string $configPath;

    /**
     * コンストラクタ
     *
     * @param string|null $configPath コンフィグファイルのパス（null の場合は dirdoc.config.json を使用）
     */
    public function __construct(?string $configPath = null)
    {
        $this->configPath = $configPath ?? getcwd() . '/dirdoc.config.json';
        $this->config = $this->defaultConfig;
        $this->loadConfig();
    }

    /**
     * コンフィグファイルを読み込む
     */
    private function loadConfig(): void
    {
        if (file_exists($this->configPath)) {
            $configData = json_decode(file_get_contents($this->configPath), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($configData)) {
                $this->config = array_replace_recursive($this->defaultConfig, $configData);
            }
        }
    }

    /**
     * 現在のコンフィグを取得する
     *
     * @return array コンフィグ配列
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * スキャン設定を取得する
     *
     * @return array スキャン設定
     */
    public function getScanConfig(): array
    {
        return $this->config['scan'] ?? $this->defaultConfig['scan'];
    }

    /**
     * バリデーション設定を取得する
     *
     * @return array バリデーション設定
     */
    public function getValidationConfig(): array
    {
        return $this->config['validation'] ?? $this->defaultConfig['validation'];
    }

    /**
     * ルートディレクトリパスを取得する
     *
     * @return string ルートディレクトリパス
     */
    public function getRootPath(): string
    {
        return $this->config['scan']['root'] ?? $this->defaultConfig['scan']['root'];
    }

    /**
     * 指定されたディレクトリのスキャン深さを取得する
     *
     * @param string $directory ディレクトリパス
     *
     * @return int スキャン深さ
     */
    public function getDepthForDirectory(string $directory): int
    {
        $directoryDepths = $this->config['scan']['depth']['directories'] ?? [];

        return $directoryDepths[$directory] ??
               ($this->config['scan']['depth']['default'] ??
                $this->defaultConfig['scan']['depth']['default']);
    }

    /**
     * 除外パターンを取得する
     *
     * @return array 除外パターンの配列
     */
    public function getExcludePatterns(): array
    {
        return $this->config['scan']['exclude']['patterns'] ??
               $this->defaultConfig['scan']['exclude']['patterns'];
    }

    /**
     * 除外ファイルパターンを取得する
     *
     * @return array 除外ファイルパターンの配列
     */
    public function getExcludeFilePatterns(): array
    {
        return $this->config['scan']['exclude']['files'] ??
               $this->defaultConfig['scan']['exclude']['files'];
    }

    /**
     * ルートファイルを含めるかどうかを取得する
     *
     * @return bool ルートファイルを含める場合は true
     */
    public function includeRootFiles(): bool
    {
        return $this->config['scan']['include']['root_files'] ??
               $this->defaultConfig['scan']['include']['root_files'];
    }

    /**
     * 空のディレクトリを含めるかどうかを取得する
     *
     * @return bool 空のディレクトリを含める場合は true
     */
    public function includeEmptyDirectories(): bool
    {
        return $this->config['scan']['include']['empty_directories'] ??
               $this->defaultConfig['scan']['include']['empty_directories'];
    }

    /**
     * 説明が必須かどうかを取得する
     *
     * @return bool 説明が必須の場合は true
     */
    public function isDescriptionRequired(): bool
    {
        return $this->config['validation']['require_description'] ??
               $this->defaultConfig['validation']['require_description'];
    }

    /**
     * 最小説明文字数を取得する
     *
     * @return int 最小説明文字数
     */
    public function getMinDescriptionLength(): int
    {
        return $this->config['validation']['min_description_length'] ??
               $this->defaultConfig['validation']['min_description_length'];
    }
}
