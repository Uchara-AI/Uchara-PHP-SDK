<?php

namespace Uchara\SDK\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Uchara\SDK\ServerSDK server()
 * @method static \Uchara\SDK\VisitorSDK visitor()
 * @method static \Uchara\SDK\ServerSDK|\Uchara\SDK\VisitorSDK sdk()
 * @method static mixed config(?string $key = null, $default = null)
 *
 * @see \Uchara\SDK\Laravel\UcharaManager
 */
class Uchara extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'uchara';
    }
}
