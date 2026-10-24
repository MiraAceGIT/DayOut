<?php
declare(strict_types=1);

namespace Tests;

use Exception;

/** Levee par une assertion qui echoue : le runner la compte en echec, pas en erreur. */
final class AssertionFailed extends Exception
{
}
