<?php

declare(strict_types=1);

// o autoloader so cobre App e Core, entao o teste carrega este arquivo com
// require_once. a pasta de log de cada teste nasce dentro da pasta temporaria
// com um prefixo proprio, e a limpeza recusa qualquer outro caminho: com um
// caminho vazio, o glob da limpeza viraria a raiz do disco.
final class TestLogDir
{
    private const PREFIX = 'crm-test-log-';

    public static function path(string $name): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::PREFIX . $name . '-' . bin2hex(random_bytes(6));
    }

    public static function remove(string $dir): void
    {
        if (!self::isOwn($dir) || !is_dir($dir)) {
            return;
        }
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($dir);
    }

    private static function isOwn(string $dir): bool
    {
        return $dir !== ''
            && dirname($dir) === sys_get_temp_dir()
            && str_starts_with(basename($dir), self::PREFIX);
    }
}
