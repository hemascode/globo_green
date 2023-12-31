<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
       '/login', '/user/checkout/sslcommerz-success', '/user/checkout/sslcommerz-failed', '/user/checkout/sslcommerz-cancel', '/user/checkout/sslcommerz-pay'
    ];
}
