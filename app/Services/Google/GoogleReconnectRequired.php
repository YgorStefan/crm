<?php

declare(strict_types=1);

namespace App\Services\Google;

/** a conta so volta a servir depois que o usuario autorizar o app de novo. */
final class GoogleReconnectRequired extends \RuntimeException
{
}
