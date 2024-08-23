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
        $settingNames = [
            'coa_bahan_baku_mr', 'coa_gaji_tunjangan_sosial_pimpinan_mr', 'coa_gaji_tunjangan_sosial_pelaksana_mr',
            'coa_bahan_bakar_mr', 'coa_bahan_kimia_pendukung_produksi_mr', 'coa_analisa_lab_mr', 'coa_listrik_mr',
            'coa_air_mr', 'coa_assuransi_pabrik_mr', 'coa_limbah_pihak3_mr', 'coa_bengkel_pemeliharaan_mr',
            'coa_gaji_tunjangan_mr', 'coa_salvaco_mr', 'coa_nusakita_mr', 'coa_inl_mr', 'coa_minyakita_mr',
            'coa_bahan_kimia_mr', 'coa_pengangkutan_langsir_mr', 'coa_pengepakan_lain_mr',
            'coa_asuransi_gudang_filling_mr', 'coa_depresiasi_mr'
        ];

        $data = $this->processCostProdPeriod($request, $settingNames);

        return response()->json([
            'data' => $data,
            'message' => 'Data Retrieved Successfully',
            'code' => 200,
            'success' => true,
        ], 200);
    }

}
