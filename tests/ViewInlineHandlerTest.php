<?php

use PHPUnit\Framework\TestCase;

// a csp da app (nonce com strict-dynamic, sem unsafe-hashes) bloqueia atributo on* em
// silencio: o confirm nao aparece e o formulario segue sem perguntar.
class ViewInlineHandlerTest extends TestCase
{
    public function testNenhumaViewUsaHandlerEmAtributoQueACspBloqueia(): void
    {
        $achados = [];
        $arquivos = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(VIEW_PATH, \FilesystemIterator::SKIP_DOTS));
        foreach ($arquivos as $arquivo) {
            if ($arquivo->getExtension() !== 'php') {
                continue;
            }
            foreach (file($arquivo->getPathname()) as $i => $linha) {
                if (preg_match('/\son[a-z]+\s*=\s*["\']/i', $linha)) {
                    $achados[] = substr($arquivo->getPathname(), strlen(ROOT_PATH) + 1) . ':' . ($i + 1) . ' ' . trim($linha);
                }
            }
        }

        $this->assertSame([], $achados);
    }
}
