<?php

declare(strict_types=1);

namespace Suzumaze\DirDoc\Command;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * DirDocCommandTest - DirDocCommandクラスのテスト
 */
class DirDocCommandTest extends TestCase
{
    /**
     * simplifyPath メソッドをテストする
     * （privateメソッドなのでリフレクションを使用）
     */
    public function testSimplifyPath(): void
    {
        $command = new DirDocCommand();
        $reflection = new ReflectionClass($command);
        $method = $reflection->getMethod('simplifyPath');

        // ルートパスが指定されている場合
        $this->assertEquals('bin', $method->invoke($command, '/project/bin', '/project/'));
        $this->assertEquals('src/Controller', $method->invoke($command, '/project/src/Controller', '/project/'));

        // パスの中にルートパスがない場合
        $this->assertEquals('/other/path', $method->invoke($command, '/other/path', '/project/'));

        // ルートパスが指定されていない場合
        $this->assertEquals('bin', $method->invoke($command, '/project/bin'));

        // 絶対パスでない場合
        $this->assertEquals('relative/path', $method->invoke($command, 'relative/path', '/project/'));
    }
}
