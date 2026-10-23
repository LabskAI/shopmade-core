<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class PreflightController extends Controller
{
    /**
     * CORS preflight for the OAuth endpoints; CrossOriginHeaders adds the headers.
     */
    public function __invoke(): Response
    {
        return response()->noContent();
    }
}
