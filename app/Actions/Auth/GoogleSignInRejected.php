<?php

namespace App\Actions\Auth;

use RuntimeException;

/**
 * Google confirmed who the person is, but PDP does not let them in.
 * The message is shown to the person on the login page.
 */
class GoogleSignInRejected extends RuntimeException {}
