<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * Names of cookies that should not be encrypted.
     *
     * @var array<int, string>
     */
    protected $except = [];
}
