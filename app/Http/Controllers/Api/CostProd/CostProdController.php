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
        $settingNames = [
            'coa_bahan_baku_mr', 'coa_gaji_tunjangan_sosial_pimpinan_mr', 'coa_gaji_tunjangan_sosial_pelaksana_mr',
            'coa_bahan_bakar_mr', 'coa_bahan_kimia_pendukung_produksi_mr', 'coa_analisa_lab_mr', 'coa_listrik_mr',
            'coa_air_mr', 'coa_assuransi_pabrik_mr', 'coa_limbah_pihak3_mr', 'coa_bengkel_pemeliharaan_mr',
            'coa_gaji_tunjangan_mr', 'coa_salvaco_mr', 'coa_nusakita_mr', 'coa_inl_mr', 'coa_minyakita_mr',
            'coa_bahan_kimia_mr', 'coa_pengangkutan_langsir_mr', 'coa_pengepakan_lain_mr',
            'coa_asuransi_gudang_filling_mr', 'coa_depresiasi_mr'
        ];

        $settings = Setting::whereIn('setting_name', $settingNames)->get();

        $settingIds = $settings->pluck('id')->toArray();

        $data = $this->processIndexPeriod($request, $settingIds);

        return response()->json([
            'data' => $data,
            'message' => 'Data Retrieved Successfully',
            'code' => 200,
            'success' => true,
        ], 200);
    }


    public function processIndexPeriod(Request $request, $settingIds)
    {
        $tanggal = Carbon::parse($request->tanggal);
        $debe = Debe::with('cat3', 'mReport', 'cCentre', 'plant', 'allocation')->get();
        $coa = Setting::whereIn('id', $settingIds)->orderBy('id')->get();
        $gl = collect($this->getGeneralLedgerData($tanggal));

        $laporanProduksiController = new LaporanProduksiController();
        $laporanData = $laporanProduksiController->index($request);

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
