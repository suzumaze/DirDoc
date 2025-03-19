<?php

declare(strict_types=1);

namespace Suzumaze\DirDoc\Command;

use Suzumaze\DirDoc\Comparator\StructureComparator;
use Suzumaze\DirDoc\Config\ConfigurationLoader;
use Suzumaze\DirDoc\Json\JsonGenerator;
use Suzumaze\DirDoc\Json\JsonParser;
use Suzumaze\DirDoc\Model\DirectoryItem;
use Suzumaze\DirDoc\Scanner\DirectoryScanner;
use Suzumaze\DirDoc\Util\DirectorySorter;
use Suzumaze\DirDoc\Validator\DescriptionValidator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

use function array_merge;
use function count;
use function explode;
use function file_exists;
use function getcwd;
use function realpath;
use function strcasecmp;
use function strlen;
use function strpos;
use function substr;
use function trim;
use function usort;

/**
 * DirDocCommand - ディレクトリ構造の解析と検証を行うコマンド
 */
class DirDocCommand extends Command
{
    /** @var string|null $defaultName コマンド名 */
    protected static $defaultName = 'dirdoc';

    /**
     * コマンドの設定
     */
    protected function configure(): void
    {
        $this
            ->setDescription('ディレクトリ構造を解析し、ドキュメント化および検証を行います')
            ->addOption(
                'config',
                'c',
                InputOption::VALUE_OPTIONAL,
                '設定ファイルのパス',
                'dirdoc.config.json'
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_OPTIONAL,
                '出力JSONファイルのパス',
                'dirdoc.json'
            )
            ->addOption(
                'scan-only',
                null,
                InputOption::VALUE_NONE,
                'スキャンのみを行い、比較や検証を行いません'
            )
            ->addOption(
                'validate-only',
                null,
                InputOption::VALUE_NONE,
                '既存のJSONファイルの検証のみを行います'
            );
    }

    /**
     * コマンドの実行
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // 詳細表示のオプションを追加
        $verbose = $output->isVerbose();

        if ($verbose) {
            $io->title('DirDoc - ディレクトリ構造解析ツール');
        }

        $configPath = $input->getOption('config');
        $outputPath = $input->getOption('output');
        $scanOnly = $input->getOption('scan-only');
        $validateOnly = $input->getOption('validate-only');

        // 設定の読み込み
        $config = new ConfigurationLoader($configPath);
        if ($verbose) {
            $io->comment("設定ファイル: {$configPath}");
        }

        if ($validateOnly) {
            return $this->validateOnly($io, $config, $outputPath, $verbose);
        }

        // ディレクトリスキャン (セクション見出しは詳細モードでのみ表示)
        if ($verbose) {
            $io->section('ディレクトリ構造のスキャン');
            $io->comment('スキャンパス: ' . $config->getRootPath());
        }

        $scanner = new DirectoryScanner($config);

        try {
            $actualStructure = $scanner->scan();
            if ($verbose) {
                $io->success('スキャン完了');
            }
        } catch (Throwable $e) {
            $io->error('スキャンエラー: ' . $e->getMessage());

            return Command::FAILURE;
        }

        // ルートパスを保存（表示用に使用）
        $rootPath = '/' . $actualStructure->getName() . '/';

        // JSON生成
        $jsonGenerator = new JsonGenerator();
        $jsonExists = file_exists($outputPath);
        $comparator = null;
        $hasChanges = false;

        if ($jsonExists) {
            if ($verbose) {
                $io->section('既存の構造と比較');
            }

            $jsonParser = new JsonParser();
            $jsonStructure = $jsonParser->loadFromFile($outputPath);

            if ($jsonStructure === null) {
                $io->warning('既存のJSONファイルの解析に失敗しました。新規作成を行います。');
                $jsonExists = false;
            } else {
                // 既存のJSONファイルがある場合は比較
                if (! $scanOnly) {
                    $comparator = $this->compareStructures($io, $jsonStructure, $actualStructure, $config, $rootPath);
                    // 変更があるかどうかをチェック
                    $hasChanges = ! empty($comparator->getMissingInJson()) || ! empty($comparator->getMissingInActual());
                }

                // 実際の構造に説明を転送
                $this->transferDescriptions($jsonStructure, $actualStructure);
            }
        }

        // スキャンのみの場合はバリデーションをスキップ
        // また、新規作成時もバリデーションをスキップ
        if (! $scanOnly && $jsonExists) {
            // すべてのアイテムの説明をバリデーション
            if ($verbose) {
                $io->section('説明のバリデーション');
            }

            $validator = new DescriptionValidator($config);
            $isValid = $validator->validate($actualStructure);

            if (! $isValid) {
                $this->displayDescriptionIssues($io, $validator, $rootPath);
            }
        }

        // 新規JSONファイル作成または更新時の見出し (詳細モードでのみ表示)
        if ($verbose) {
            $io->section($jsonExists ? 'JSONファイルの更新' : 'JSONファイルの作成');
        }

        // 既存のJSONファイルがある場合、新しく追加されたアイテムを表示
        if ($jsonExists && $comparator !== null && ! empty($comparator->getMissingInJson())) {
            $missingInJson = $comparator->getMissingInJson();
            $io->note('新しく検出された ' . count($missingInJson) . ' 個のアイテム');

            // ディレクトリとファイルに分類してソート
            $directories = [];
            $files = [];

            foreach ($missingInJson as $item) {
                if ($item['type'] === 'directory') {
                    $directories[] = $item;
                } else {
                    $files[] = $item;
                }
            }

            usort($directories, static function ($a, $b) {
                return strcasecmp($a['path'], $b['path']);
            });

            usort($files, static function ($a, $b) {
                return strcasecmp($a['path'], $b['path']);
            });

            $table = [];
            foreach (array_merge($directories, $files) as $item) {
                $simplifiedPath = $this->simplifyPath($item['path'], $rootPath);
                $table[] = [$item['type'], $simplifiedPath];
            }

            $io->table(['タイプ', 'パス'], $table);
        }

        // 新規作成の場合、dirdoc.json自身をリストに追加
        if (! $jsonExists) {
            // 実際の構造にdirdoc.jsonが含まれているか確認
            $dirdocJsonFound = false;
            foreach ($actualStructure->getChildren() as $child) {
                if ($child->getName() === $outputPath && $child->getType() === 'file') {
                    $dirdocJsonFound = true;
                    break;
                }
            }

            // dirdoc.jsonが含まれていない場合は追加
            if (! $dirdocJsonFound) {
                $dirdocJsonItem = new DirectoryItem(
                    $outputPath,
                    'file',
                    'ディレクトリ構造の定義ファイル',
                    realpath(getcwd()) . '/' . $outputPath
                );
                $actualStructure->addChild($dirdocJsonItem);
            }
        }

        // ディレクトリ構造をソートする（ディレクトリが先、ファイルが後）
        $sorter = new DirectorySorter();
        $actualStructure = $sorter->sort($actualStructure);

        // 状況に応じてメッセージを変更
        if ($jsonExists) {
            $saveMessage = $hasChanges ? "{$outputPath} を更新しました" : "{$outputPath} に変更はありません";
        } else {
            $saveMessage = "{$outputPath} に新規作成しました";
        }

        // ファイルを保存（変更がなくても保存して最新の状態を維持）
        if ($jsonGenerator->saveToFile($actualStructure, $outputPath)) {
            $io->success($saveMessage);
        } else {
            $io->error("{$outputPath} への保存に失敗しました");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * 既存のJSONファイルのバリデーションのみを行う
     *
     * @param bool $verbose 詳細表示モード
     */
    private function validateOnly(SymfonyStyle $io, ConfigurationLoader $config, string $jsonPath, bool $verbose = false): int
    {
        if ($verbose) {
            $io->section('既存のJSONファイルの検証');
        }

        if (! file_exists($jsonPath)) {
            $io->error("JSONファイルが見つかりません: {$jsonPath}");

            return Command::FAILURE;
        }

        $jsonParser = new JsonParser();
        $structure = $jsonParser->loadFromFile($jsonPath);

        if ($structure === null) {
            $io->error('JSONファイルの解析に失敗しました');

            return Command::FAILURE;
        }

        // ルートパスを保存（表示用に使用）
        $rootPath = '/' . $structure->getName() . '/';

        $validator = new DescriptionValidator($config);
        $isValid = $validator->validate($structure);

        if ($isValid) {
            $io->success('すべてのアイテムに有効な説明があります');

            return Command::SUCCESS;
        }

        $this->displayDescriptionIssues($io, $validator, $rootPath);

        return Command::FAILURE;
    }

    /**
     * 二つの構造を比較し、結果を表示する
     *
     * @param string $rootPath 表示用のルートパス
     *
     * @return StructureComparator 使用したComparatorインスタンス
     */
    private function compareStructures(
        SymfonyStyle $io,
        DirectoryItem $jsonStructure,
        DirectoryItem $actualStructure,
        ConfigurationLoader $config,
        string $rootPath = ''
    ): StructureComparator {
        $comparator = new StructureComparator();
        $comparator->compare(
            $jsonStructure,
            $actualStructure,
            $config->getMinDescriptionLength()
        );

        // 実際の構造にないJSONのアイテム
        $missingInActual = $comparator->getMissingInActual();
        if (! empty($missingInActual)) {
            $io->note('dirdoc.jsonから削除された ' . count($missingInActual) . ' 個のアイテム');

            // ディレクトリとファイルに分類してソート
            $directories = [];
            $files = [];

            foreach ($missingInActual as $item) {
                if ($item['type'] === 'directory') {
                    $directories[] = $item;
                } else {
                    $files[] = $item;
                }
            }

            usort($directories, static function ($a, $b) {
                return strcasecmp($a['path'], $b['path']);
            });

            usort($files, static function ($a, $b) {
                return strcasecmp($a['path'], $b['path']);
            });

            $table = [];
            foreach (array_merge($directories, $files) as $item) {
                $simplifiedPath = $this->simplifyPath($item['path'], $rootPath);
                $table[] = [$item['type'], $simplifiedPath];
            }

            $io->table(['タイプ', 'パス'], $table);
        }

        // JSONにない実際の構造のアイテム
        $missingInJson = $comparator->getMissingInJson();
        if (! empty($missingInJson)) {
            $io->warning('JSONには存在しない ' . count($missingInJson) . ' 個のアイテムが実際の構造に含まれています');

            // ディレクトリとファイルに分類してソート
            $directories = [];
            $files = [];

            foreach ($missingInJson as $item) {
                if ($item['type'] === 'directory') {
                    $directories[] = $item;
                } else {
                    $files[] = $item;
                }
            }

            usort($directories, static function ($a, $b) {
                return strcasecmp($a['path'], $b['path']);
            });

            usort($files, static function ($a, $b) {
                return strcasecmp($a['path'], $b['path']);
            });

            $table = [];
            foreach (array_merge($directories, $files) as $item) {
                $simplifiedPath = $this->simplifyPath($item['path'], $rootPath);
                $table[] = [$item['type'], $simplifiedPath];
            }

            $io->table(['タイプ', 'パス'], $table);
        }

        return $comparator;
    }

    /**
     * 説明に関する問題を表示する
     *
     * @param string $rootPath 表示用のルートパス
     */
    private function displayDescriptionIssues(SymfonyStyle $io, DescriptionValidator $validator, string $rootPath = ''): void
    {
        $itemsWithoutDescription = $validator->getItemsWithoutDescription();
        if (! empty($itemsWithoutDescription)) {
            $io->warning(count($itemsWithoutDescription) . ' 個のアイテムに説明文がありません');

            // ディレクトリとファイルに分類
            $directories = [];
            $files = [];

            foreach ($itemsWithoutDescription as $item) {
                if ($item['type'] === 'directory') {
                    $directories[] = $item;
                } else {
                    $files[] = $item;
                }
            }

            // 各グループ内でパスによるソート
            usort($directories, static function ($a, $b) {
                return strcasecmp($a['path'], $b['path']);
            });

            usort($files, static function ($a, $b) {
                return strcasecmp($a['path'], $b['path']);
            });

            // テーブル表示用のデータを作成（ディレクトリが先、次にファイル）
            $table = [];
            foreach (array_merge($directories, $files) as $item) {
                $simplifiedPath = $this->simplifyPath($item['path'], $rootPath);
                $table[] = [$item['type'], $simplifiedPath];
            }

            $io->table(['タイプ', 'パス'], $table);
        }

        $itemsWithShortDescription = $validator->getItemsWithShortDescription();
        if (! empty($itemsWithShortDescription)) {
            $io->warning(count($itemsWithShortDescription) . ' 個のアイテムの説明文が短すぎます');

            // ディレクトリとファイルに分類
            $directories = [];
            $files = [];

            foreach ($itemsWithShortDescription as $item) {
                if ($item['type'] === 'directory') {
                    $directories[] = $item;
                } else {
                    $files[] = $item;
                }
            }

            // 各グループ内でパスによるソート
            usort($directories, static function ($a, $b) {
                return strcasecmp($a['path'], $b['path']);
            });

            usort($files, static function ($a, $b) {
                return strcasecmp($a['path'], $b['path']);
            });

            // テーブル表示用のデータを作成（ディレクトリが先、次にファイル）
            $table = [];
            foreach (array_merge($directories, $files) as $item) {
                $simplifiedPath = $this->simplifyPath($item['path'], $rootPath);
                $table[] = [
                    $item['type'],
                    $simplifiedPath,
                    $item['description'],
                    $item['min_length'] . ' 文字以上必要',
                ];
            }

            $io->table(['タイプ', 'パス', '現在の説明', '要件'], $table);
        }
    }

    /**
     * 表示用にパスを簡略化する
     *
     * @param string $path     元のパス
     * @param string $rootPath ルートパス（省略する部分）
     *
     * @return string 簡略化されたパス
     */
    private function simplifyPath(string $path, string $rootPath = ''): string
    {
        // ルートパスが指定されていない場合は、パスからルートディレクトリ名を抽出
        if (empty($rootPath)) {
            $parts = explode('/', trim($path, '/'));
            if (count($parts) > 0) {
                $rootPath = '/' . $parts[0] . '/';
            }
        }

        // ルートパスが含まれる場合は削除
        if (! empty($rootPath) && strpos($path, $rootPath) === 0) {
            return substr($path, strlen($rootPath));
        }

        return $path;
    }

    /**
     * 既存のJSONファイルから実際の構造に説明を転送する
     */
    private function transferDescriptions(
        DirectoryItem $jsonStructure,
        DirectoryItem $actualStructure
    ): void {
        // ルート項目の説明を転送
        if (! empty($jsonStructure->getDescription())) {
            $actualStructure->setDescription($jsonStructure->getDescription());
        }

        // 子項目の説明を再帰的に転送
        $this->transferChildDescriptions($jsonStructure, $actualStructure);
    }

    /**
     * 子項目の説明を再帰的に転送する
     */
    private function transferChildDescriptions(
        DirectoryItem $jsonItem,
        DirectoryItem $actualItem
    ): void {
        foreach ($jsonItem->getChildren() as $jsonChild) {
            $actualChild = $actualItem->getChildByName($jsonChild->getName());

            if ($actualChild !== null && $jsonChild->getType() === $actualChild->getType()) {
                // 説明を転送
                if (! empty($jsonChild->getDescription())) {
                    $actualChild->setDescription($jsonChild->getDescription());
                }

                // 子を持つ場合は再帰的に処理
                if ($jsonChild->hasChildren() && $actualChild->hasChildren()) {
                    $this->transferChildDescriptions($jsonChild, $actualChild);
                }
            }
        }
    }
}
