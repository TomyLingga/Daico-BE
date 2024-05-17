<?php

namespace App\Http\Controllers\Api\CostProd;

use App\Http\Controllers\Controller;
use App\Models\Debe;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CostProdController extends Controller
{
    public function index_period(Request $request)
    {
        $tanggal = $request->get('tanggal');
        $settingName = $request->get('setting_name');
        $date = Carbon::parse($tanggal);
        $debe = Debe::with('cat3', 'mReport', 'cCentre', 'plant', 'allocation')->get();

        $coa = Setting::where('setting_name', $settingName)->first();
        if (!$coa) {
            return response()->json([
                'message' => 'Setting not found',
                'success' => false,
                'code' => 404
            ], 404);
        }

        $coaValues = explode(',', $coa->setting_value);

        $allGlData = collect();

        foreach ($coaValues as $coaValue) {
            $costProd = $this->getGeneralLedgerDataWithCoa($date, $coaValue);
            $allGlData = $allGlData->merge($costProd);
        }

        if ($allGlData->isEmpty()) {
            return response()->json([
                'message' => "No Data Found",
                'success' => false,
                'code' => 404
            ], 404);
        }

        $totalDebit = $allGlData->sum('debit');
        $totalCredit = $allGlData->sum('credit');
        $difference = $totalDebit - $totalCredit;

        $allGlData->transform(function ($item) use ($debe) {
            $coaCode = $item['account_account']['code'];
            $debeModel = $debe->firstWhere('coa', $coaCode);
            $item['debe'] = $debeModel;
            return $item;
        });

        return response()->json([
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
            'totalDifference' => $difference,
            'data' => $allGlData->values(),
            'message' => 'Data Retrieved Successfully',
            'code' => 200,
            'success' => true,
        ], 200);

    }
}
