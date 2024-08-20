<?php

namespace App\Http\Controllers\Api\CostProd;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\DetAlloc\LaporanProduksiController;
use App\Models\Debe;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CostProdController extends Controller
{
    public function indexPeriodCoaName(Request $request)
    {
        $tanggal = $request->get('tanggal');
        $settingName = $request->get('setting_name');

        $result = $this->costProdProcess($tanggal, $settingName);

        return response()->json($result, $result['code']);
    }

    public function costProdProcess($tanggal, $settingName)
    {
        $date = Carbon::parse($tanggal);
        $debe = Debe::with('cat3', 'mReport', 'cCentre', 'plant', 'allocation')->get();

        $coa = Setting::where('setting_name', $settingName)->first();
        if (!$coa) {
            return [
                'message' => 'Setting not found',
                'success' => false,
                'code' => 404
            ];
        }

        $coaValues = explode(',', $coa->setting_value);

        $allGlData = collect();

        foreach ($coaValues as $coaValue) {
            $costProd = $this->getGeneralLedgerDataWithCoaPosted($date, $coaValue);
            $allGlData = $allGlData->merge($costProd);
        }

        if ($allGlData->isEmpty()) {
            return [
                'message' => "No Data Found",
                'success' => false,
                'code' => 404
            ];
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

        return [
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
            'totalDifference' => $difference,
            'data' => $allGlData->values(),
            'message' => 'Data Retrieved Successfully',
            'code' => 200,
            'success' => true,
        ];
    }

    public function indexPeriod(Request $request)
    {
        $data = $this->processCostProdPeriod($request);

        return response()->json([
            'data' => $data,
            'message' => 'Data Retrieved Successfully',
            'code' => 200,
            'success' => true,
        ], 200);
    }

}
