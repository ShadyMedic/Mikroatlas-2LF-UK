<?php

namespace Mikroatlas\Controllers;

/**
 * @see Controller
 */
class Index extends Controller
{

    /**
     * @inheritDoc
     */
    public function process(array $args = []): int
    {
        self::$data['layout']['page_id'] = 'index';
        self::$data['layout']['title'] = 'Mikrobiologický atlas 2.LF';

        self::$views[] = 'index';

        return 200;
    }
}

