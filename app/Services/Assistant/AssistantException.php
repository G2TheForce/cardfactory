<?php

namespace App\Services\Assistant;

use RuntimeException;

/** A reply could not be produced. The message is safe to show the user. */
class AssistantException extends RuntimeException {}
