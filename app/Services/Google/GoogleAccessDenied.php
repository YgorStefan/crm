<?php

declare(strict_types=1);

namespace App\Services\Google;

/** a troca do code nao rendeu uma conexao utilizavel, e por isso nada foi gravado. */
final class GoogleAccessDenied extends \RuntimeException
{
}
