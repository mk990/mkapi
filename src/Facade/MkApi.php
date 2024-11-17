<?php

namespace Mk990\MkApi\Facade;

use Illuminate\Support\Facades\Facade;

/**
 * @method static object doSomeThing()
 * @method static object test()
 *
 * @see \Mk990\MkApi\
 */
class MkApi extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'MkApi';
    }
}
