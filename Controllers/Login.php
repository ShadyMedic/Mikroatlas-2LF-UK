<?php

namespace Mikroatlas\Controllers;

use Mikroatlas\Models\UserException;

/**
 * @see Controller
 */
class Login extends Controller
{

    /**
     * @inheritDoc
     */
    public function process(array $args = []): int
    {
        self::$data['layout']['title'] = 'Přihlásit se';

        self::$views[] = 'login';

        return 200;
    }
}

