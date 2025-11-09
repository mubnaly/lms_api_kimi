<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateSignature extends \Illuminate\Routing\Middleware\ValidateSignature
{
    /**
     * URIs that should be excluded from signature validation.
     *
     * @var array<int, string>
     */
    protected $except = [];
}
