<?php

namespace App\Http\Controllers\Api\Target;

use App\Http\Controllers\Controller;
use App\Models\DailyDMO;
use App\Models\LaporanProduksi;
use App\Models\MonthlyDMO;
use App\Models\TargetReal;
use App\Models\TargetRKAP;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class RecapTargetController extends Controller
{

    private $messageFail = 'Something went wrong';
    private $messageMissing = 'Data not found in record';
    private $messageAll = 'Success to Fetch All Datas';
    private $messageSuccess = 'Success to Fetch Data';
    private $messageCreate = 'Success to Create Data';
    private $messageUpdate = 'Success to Update Data';

    public function recapTarget(Request $request)
    {
        try {
            $combinedResult = $this->targetResult($request);

            return response()->json([
                'data' => $combinedResult,
                'message' => $this->messageAll,
                'success' => true,
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
