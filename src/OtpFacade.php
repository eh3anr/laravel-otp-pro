<?php

namespace Eh3anr\Otp;

use Illuminate\Support\Facades\Facade;

/**
 * @method static generate(string $string)
 * @method static validate(string $string, string $string1)
 */
class OtpFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'otp';
    }
}
