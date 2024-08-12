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
            'coa_bahan_baku', 'coa_gaji_tunjangan_sosial_pimpinan', 'coa_gaji_tunjangan_sosial_pelaksana',
            'coa_bahan_bakar', 'coa_bahan_kimia_pendukung_produksi', 'coa_analisa_lab', 'coa_listrik',
            'coa_air', 'coa_asuransi_pabrik', 'coa_limbah_pihak3', 'coa_bengkel_pemeliharaan',
            'coa_gaji_tunjangan', 'coa_salvaco', 'coa_nusakita', 'coa_inl', 'coa_minyakita',
            'coa_bahan_kimia', 'coa_pengangkutan_langsir', 'coa_pengepakan_lain',
            'coa_asuransi_gudang_filling', 'coa_depresiasi'
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
