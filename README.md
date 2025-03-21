# DirDoc

DirDocは、プロジェクトのディレクトリ構造を可視化してドキュメント化するためのPHPパッケージです。

## 概要

プロジェクトに新しく参加したり、しばらく離れていた後に戻ったりすると、暗黙的な情報の多さに戸惑うことはありませんか？特に問題となるのがディレクトリ構造とファイル構成です。

DirDocは、こうした問題を解決するシンプルなツールで、以下のことを実現します：

1. プロジェクトのディレクトリ構造とルートファイルを自動的にJSON形式で記録
2. 各ディレクトリやファイルに説明文を追加する仕組みの提供
3. 実際のプロジェクト構造とJSON記録の差分を検出
4. 説明が不足している項目の特定と通知

## インストール

Composerを使用してインストールします：

```bash
composer require suzumaze/dirdoc
```

## 使い方

### 基本的な使い方

```bash
composer dirdoc
```

このコマンド一つで以下の処理を実行します：

1. `dirdoc.json`が存在しない場合は新規作成
2. 既存の`dirdoc.json`と実際のディレクトリ構造を比較
3. 説明が不足しているディレクトリやファイルを検出して出力

### オプション

```bash
composer dirdoc -- --config=custom-config.json  # カスタム設定ファイルを指定
composer dirdoc -- --output=custom-output.json  # 出力ファイルを指定
composer dirdoc -- --scan-only                  # スキャンのみを実行
composer dirdoc -- --validate-only              # 既存のJSONファイルの検証のみを実行
composer dirdoc -- -v                           # 詳細な出力を表示
```

### 出力形式

DirDocの出力は使いやすいように最適化されています：

- **基本モード**: 重要な情報のみがシンプルに表示されます
- **詳細モード** (`-v`): 処理の各ステップが詳細に表示されます

### 変更検出

実行時、以下のような変更が検出されます：

- **新しく追加されたアイテム**: ファイルシステムにあるが、まだJSONに記録されていないアイテム
- **削除されたアイテム**: JSONに記録されているが、現在のファイルシステムには存在しないアイテム
- **説明不足のアイテム**: 説明が不足しているか、説明が短すぎるアイテム

## 設定ファイル

デフォルトでは`dirdoc.config.json`という名前の設定ファイルを使用します。以下は設定ファイルの例です：

```json
{
  "scan": {
    "root": ".",
    "depth": {
      "default": 1,
      "directories": {
        "src": 3,
        "tests": 2
      }
    },
    "exclude": {
      "patterns": [
        "vendor/*",
        "node_modules/*",
        ".git/*"
      ],
      "files": [
        "*.log",
        "*.cache"
      ]
    },
    "include": {
      "root_files": true,
      "empty_directories": false
    }
  },
  "validation": {
    "require_description": true,
    "min_description_length": 10
  }
}
```

### 設定項目の説明

#### スキャン設定 (`scan`)

- `root`: スキャンを開始するルートディレクトリ
- `depth`:
  - `default`: デフォルトのスキャン深さ
  - `directories`: 特定のディレクトリに対するスキャン深さの設定
- `exclude`:
  - `patterns`: 除外するパターン (globパターン)
  - `files`: 除外するファイルパターン
- `include`:
  - `root_files`: ルートディレクトリのファイルを含めるかどうか
  - `empty_directories`: 空のディレクトリを含めるかどうか

#### バリデーション設定 (`validation`)

- `require_description`: 説明が必須かどうか
- `min_description_length`: 最小説明文字数

## 出力されるJSONの形式

DirDocは以下のような形式のJSONを生成します。ディレクトリとファイルは自動的に整理され、ディレクトリが先に、次にファイルが表示されます：

```json
{
  "name": "project-root",
  "type": "directory",
  "description": "プロジェクトのルートディレクトリ",
  "children": [
    {
      "name": "src",
      "type": "directory",
      "description": "ソースコードが格納されるディレクトリ",
      "children": [
        {
          "name": "Controller",
          "type": "directory",
          "description": "コントローラークラスを格納するディレクトリ",
          "children": []
        }
      ]
    },
    {
      "name": "README.md",
      "type": "file",
      "description": "プロジェクトの説明ファイル"
    }
  ]
}
```

## 一般的なディレクトリと説明

以下は標準的なプロジェクト構造における一般的なディレクトリとその説明例です：

| ディレクトリ/ファイル | 説明例 |
| --- | --- |
| src/ | ソースコードが格納されるディレクトリ |
| tests/ | テストコードが格納されるディレクトリ |
| bin/ | 実行可能ファイルが格納されるディレクトリ |
| config/ | 設定ファイルが格納されるディレクトリ |
| public/ | 公開用ファイルが格納されるディレクトリ |
| vendor/ | Composerによって管理される外部依存パッケージが格納されるディレクトリ |
| composer.json | Composerパッケージの設定および依存関係を定義するファイル |
| README.md | プロジェクトの概要と使用方法を説明するドキュメント |

## 機能と特徴

- **シンプルなインターフェース**: 必要最小限の情報表示でストレスなく使用可能
- **自動ソート**: ディレクトリが先、ファイルが後という整理された表示
- **パス簡略化**: 長いパスを簡潔に表示し、視認性を向上
- **変更通知の改善**: 追加・削除されたアイテムを明確に表示
- **詳細表示モード**: `-v` オプションで詳細な処理情報を確認可能

## ユースケース

- 新メンバーが短時間でプロジェクト構造を理解できるようにする
- 暗黙知となっているディレクトリ構造の役割を明文化する
- プロジェクト構造の変更履歴を追跡する（Gitで`dirdoc.json`を管理）
- ドキュメントのメンテナンスを簡単にし、最新の状態を保つ

## 対象ユーザー

主にチーム開発を行うエンジニアを対象としていますが、個人開発者にも有用です。特に以下のようなケースで役立ちます：

- 新メンバーのオンボーディングを効率化したいチーム
- プロジェクト構造の一貫性と透明性を維持したい開発リーダー
- 長期間にわたって保守する必要があるプロジェクト
- ドキュメント管理を自動化・効率化したい開発者

## ライセンス

MIT License