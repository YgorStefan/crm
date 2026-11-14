<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Support/TestLogDir.php';

final class TestLogDirTest extends TestCase
{
    /** @var list<string> */
    private array $iscas = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->iscas) as $caminho) {
            is_file($caminho) ? unlink($caminho) : rmdir($caminho);
        }
    }

    /** pasta que a limpeza nao pode tocar, com um arquivo dentro */
    private function isca(string $pasta): string
    {
        mkdir($pasta);
        file_put_contents($pasta . DIRECTORY_SEPARATOR . 'isca.txt', 'isca');
        $this->iscas[] = $pasta;
        $this->iscas[] = $pasta . DIRECTORY_SEPARATOR . 'isca.txt';
        return $pasta;
    }

    public function testRemoveAPropriaPastaComOsArquivos(): void
    {
        $pasta = TestLogDir::path('teste');
        mkdir($pasta);
        file_put_contents($pasta . DIRECTORY_SEPARATOR . 'logger-falso.log', 'linha');

        TestLogDir::remove($pasta);

        $this->assertDirectoryDoesNotExist($pasta);
    }

    public function testCaminhoVazioNaoFazNada(): void
    {
        // o setup que pula o teste antes de criar a pasta deixava o caminho vazio.
        TestLogDir::remove('');

        $this->addToAssertionCount(1);
    }

    public function testRecusaPastaSemOPrefixo(): void
    {
        $pasta = $this->isca(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'crm-isca-' . bin2hex(random_bytes(6)));

        TestLogDir::remove($pasta);

        $this->assertFileExists($pasta . DIRECTORY_SEPARATOR . 'isca.txt');
    }

    public function testRecusaPastaComOPrefixoForaDaTemporaria(): void
    {
        $fora = $this->isca(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'crm-isca-' . bin2hex(random_bytes(6)));
        $pasta = $this->isca($fora . DIRECTORY_SEPARATOR . 'crm-test-log-dentro');

        TestLogDir::remove($pasta);

        $this->assertFileExists($pasta . DIRECTORY_SEPARATOR . 'isca.txt');
    }
}
