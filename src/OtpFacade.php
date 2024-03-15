<?php

namespace Eh3anr\Otp;

use Illuminate\Support\Facades\Facade;

/**
 * @method static generate(string $identifier, array $options = [])
 * @method static validate(string $identifier, string $password, array $options = [])
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
