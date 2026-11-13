<?php

declare(strict_types=1);

namespace App\Services\Google;

/** o google nao respondeu a tempo ou falhou do lado dele, e a conexao continua valendo. */
final class GoogleUnavailable extends \RuntimeException
{
}
