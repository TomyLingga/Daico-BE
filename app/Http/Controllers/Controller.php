<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public $token;
    public $userData;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->token = $request->get('user_token');
            $this->userData = $request->get('decoded');
            // $this->urlAllSuppliers = env('BASE_URL_ODOO')."supplier/index";
            // $this->urlSupplier = env('BASE_URL_ODOO')."supplier/get/";
            // $this->urlAllMIS = env('BASE_URL_ODOO')."mis/index";
            // $this->urlMIS = env('BASE_URL_ODOO')."mis/get/";
            return $next($request);
        });
    }
}
