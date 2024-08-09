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
    public function indexPeriod(Request $request)
    {
        $tanggal = $request->get('tanggal');
        $settingIds = [9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29];

        $data = $this->processIndexPeriod($request, $settingIds);

        return response()->json([
            'data' => $data,
            'message' => 'Data Retrieved Successfully',
            'code' => 200,
            'success' => true,
        ], 200);
    }

    private function processIndexPeriod(Request $request, $settingIds)
    {
        $tanggal = Carbon::parse($request->tanggal);
        $debe = Debe::with('cat3', 'mReport', 'cCentre', 'plant', 'allocation')->get();
        $coa = Setting::whereIn('id', $settingIds)->orderBy('id')->get();
        $gl = collect($this->getGeneralLedgerData($tanggal));

        // Fetch laporanData
        $laporanProduksiController = new LaporanProduksiController();
        $laporanData = $laporanProduksiController->index($request);

        // Extract the total_qty for Refinery's CPO Olah
        $totalQtyRefineryCPO = 0;
        if (isset($laporanData['laporanProduksi'])) {
            foreach ($laporanData['laporanProduksi'] as $laporan) {
                if ($laporan['nama'] === 'Refinery') {
                    foreach ($laporan['uraian'] as $uraian) {
                        if ($uraian['nama'] === 'CPO (Olah)') {
                            $totalQtyRefineryCPO = $uraian['total_qty'];
                            break 2;
                        }
                    }
                }
            }
        }

        // Process COA data
        $data = $coa->map(function($coaSetting) use ($debe, $gl, $totalQtyRefineryCPO) {
            $coaNumbers = explode(',', $coaSetting->setting_value);
            $coaData = [];
            $totalDebitSetting = 0;
            $totalCreditSetting = 0;
            $mReportName = '';

            foreach ($coaNumbers as $coaNumber) {
                $glData = $gl->filter(function($item) use ($coaNumber) {
                    return $item['account_account']['code'] == $coaNumber;
                });

                $debeModel = $debe->firstWhere('coa', $coaNumber);
                $mReportName = $debeModel ? $debeModel->mReport->nama : '';

                $totalDebit = $glData->sum('debit');
                $totalCredit = $glData->sum('credit');
                $result = $totalDebit - $totalCredit;

                $totalDebitSetting += $totalDebit;
                $totalCreditSetting += $totalCredit;

                $coaData[] = [
                    'coa_number' => $coaNumber,
                    'debe' => $debeModel,
                    'gl' => $glData->values(),
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'result' => $result
                ];
            }

            return [
                'nama' => $mReportName,
                'setting' => $coaSetting->setting_name,
                'total_debit' => $totalDebitSetting,
                'total_credit' => $totalCreditSetting,
                'result' => $totalDebitSetting - $totalCreditSetting,
                'total_qty_refinery_cpo_olah' => $totalQtyRefineryCPO,
                'rp_per_kg_cpo_olah' => $totalQtyRefineryCPO > 0 ? ($totalDebitSetting - $totalCreditSetting) / $totalQtyRefineryCPO : 0,
                'coa' => $coaData
            ];
        });

        return ['data' => $data->values()];
    }


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
}
