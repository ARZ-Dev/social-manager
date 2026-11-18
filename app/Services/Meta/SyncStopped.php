<?php

namespace App\Services\Meta;

/**
 * Thrown inside a sync when someone pressed Stop (or a newer fetch took over).
 */
class SyncStopped extends \RuntimeException
{
}
