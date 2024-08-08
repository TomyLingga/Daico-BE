<?php

namespace App\Http\Controllers\Api\CostProd;

use App\Http\Controllers\Controller;
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

        $data = $this->processIndexPeriod($tanggal, $settingIds);

        return response()->json([
            'data' => $data,
            'message' => 'Data Retrieved Successfully',
            'code' => 200,
            'success' => true,
        ], 200);
    }

    private function processIndexPeriod($tanggal, $settingIds)
    {
        $date = Carbon::parse($tanggal);
        $debe = Debe::with('cat3', 'mReport', 'cCentre', 'plant', 'allocation')->get();

        $coa = Setting::whereIn('id', $settingIds)->orderBy('id')->get();
        $gl = collect($this->getGeneralLedgerData($tanggal)); // Convert $gl to a collection

        $data = $coa->map(function($coaSetting) use ($debe, $gl) {
            $coaNumbers = explode(',', $coaSetting->setting_value);
            $coaData = [];
            $totalDebitSetting = 0;
            $totalCreditSetting = 0;

            foreach ($coaNumbers as $coaNumber) {
                // Filter GL and Debe data by COA number
                $glData = $gl->filter(function($item) use ($coaNumber) {
                    return $item['account_account']['code'] == $coaNumber;
                });

                $debeModel = $debe->firstWhere('coa', $coaNumber);

                $totalDebit = $glData->sum('debit');
                $totalCredit = $glData->sum('credit');
                $result = $totalDebit - $totalCredit;

                // Sum totals for the setting
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
                'name' => $coaSetting->setting_name,
                'total_debit' => $totalDebitSetting,
                'total_credit' => $totalCreditSetting,
                'result' => $totalDebitSetting - $totalCreditSetting,
                'coa' => $coaData
            ];
        });

        return $data;
    }

    // public function indexPeriod(Request $request)
    // {
    //     $tanggal = $request->get('tanggal');
    //     $settingIds = [9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29];

    //     $date = Carbon::parse($tanggal);
    //     $debe = Debe::with('cat3', 'mReport', 'cCentre', 'plant', 'allocation')->get();

    //     $coa = Setting::whereIn('id', $settingIds)->orderBy('id')->get();
    //     $gl = collect($this->getGeneralLedgerData($tanggal)); // Convert $gl to a collection

    //     $data = $coa->map(function($coaSetting) use ($debe, $gl) {
    //         $coaNumbers = explode(',', $coaSetting->setting_value);
    //         $coaData = [];
    //         $totalDebitSetting = 0;
    //         $totalCreditSetting = 0;

    //         foreach ($coaNumbers as $coaNumber) {
    //             // Filter GL and Debe data by COA number
    //             $glData = $gl->filter(function($item) use ($coaNumber) {
    //                 return $item['account_account']['code'] == $coaNumber;
    //             });

    //             $debeModel = $debe->firstWhere('coa', $coaNumber);

    //             $totalDebit = $glData->sum('debit');
    //             $totalCredit = $glData->sum('credit');
    //             $result = $totalDebit - $totalCredit;

    //             // Sum totals for the setting
    //             $totalDebitSetting += $totalDebit;
    //             $totalCreditSetting += $totalCredit;

    //             $coaData[] = [
    //                 'coa_number' => $coaNumber,
    //                 'debe' => $debeModel,
    //                 'gl' => $glData->values(),
    //                 'total_debit' => $totalDebit,
    //                 'total_credit' => $totalCredit,
    //                 'result' => $result
    //             ];
    //         }

    //         return [
    //             'name' => $coaSetting->setting_name,
    //             'total_debit' => $totalDebitSetting,
    //             'total_credit' => $totalCreditSetting,
    //             'result' => $totalDebitSetting - $totalCreditSetting,
    //             'coa' => $coaData
    //         ];
    //     });

    //     return response()->json([
    //         'data' => $data,
    //         'message' => 'Data Retrieved Successfully',
    //         'code' => 200,
    //         'success' => true,
    //     ], 200);
    // }

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
