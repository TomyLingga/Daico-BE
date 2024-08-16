<?php

namespace App\Http\Controllers\Api\CostingHPP;

use App\Http\Controllers\Api\CostProd\CostProdController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\DetAlloc\LaporanProduksiController;
use App\Http\Controllers\Api\ProCost\ProcostController;
use App\Models\cpoKpbn;
use App\Models\Debe;
use App\Models\LevyDutyBulky;
use App\Models\MarketRoutersBulky;
use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Services\LoggerService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;


class CostingHppController extends Controller
{
    private $messageFail = 'Something went wrong';
    private $messageMissing = 'Data not found in record';
    private $messageAll = 'Success to Fetch All Datas';
    private $messageSuccess = 'Success to Fetch Data';
    private $messageCreate = 'Success to Create Data';
    private $messageUpdate = 'Success to Update Data';

    public function indexPeriod(Request $request)
    {
        try {
            $processResult = $this->costingHppRecap($request);

            return response()->json([
                'data' => $processResult,
                'message' => $this->messageAll
            ], 200);

        } catch (QueryException $e) {
            return response()->json([
                'message' => $this->messageFail,
                'err' => $e->getTrace()[0],
                'errMsg' => $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

}

