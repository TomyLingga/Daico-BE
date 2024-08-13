<?php

namespace App\Http\Controllers\Api\ProCost;

use App\Http\Controllers\Api\CostingHPP\CostingHppController;
use App\Http\Controllers\Api\DetAlloc\LaporanProduksiController;
use App\Http\Controllers\Controller;
use App\Models\cpoKpbn;
use App\Models\LevyDutyBulky;
use App\Models\MarketRoutersBulky;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class ProcostController extends Controller
{
    private $messageFail = 'Something went wrong';
    private $messageMissing = 'Data not found in record';
    private $messageAll = 'Success to Fetch All Datas';
    private $messageSuccess = 'Success to Fetch Data';
    private $messageCreate = 'Success to Create Data';
    private $messageUpdate = 'Success to Update Data';

    public function indexDate(Request $request)
    {
        try {

            $processResult = $this->processProCost($request);

            if ($processResult['error']) {
                return $processResult['response'];
            }

            return response()->json($processResult['data'], 200);

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
