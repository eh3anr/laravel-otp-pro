<?php

namespace Eh3anr\Otp;

use Illuminate\Support\Facades\Facade;

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
