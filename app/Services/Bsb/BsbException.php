<?php

namespace App\Services\Bsb;

/** An error answer from BSB's API (bad credentials, no credits, unreachable...). */
class BsbException extends \RuntimeException
{
}
