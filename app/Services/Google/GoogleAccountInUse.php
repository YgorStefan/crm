<?php

declare(strict_types=1);

namespace App\Services\Google;

/** a conta google ja esta ligada a outro usuario do tenant, e por isso nada foi gravado. */
final class GoogleAccountInUse extends \RuntimeException
{
}
