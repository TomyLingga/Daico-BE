<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\cpoKpbn;
use App\Models\KursMandiri;
use App\Models\outstandingCpo;
use App\Models\RekeningUnitKerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MainDashboardController extends Controller
{
    public function indexPeriod(Request $request)
    {
        try {
            $tanggal = $request->tanggal;

            $currencyRates = $this->getRateCurrencyData($tanggal, 'USD');

            $avgRate = collect($currencyRates)->avg('rate');

            $latestRate = collect($currencyRates)->last()['rate'];

            $mandiriRate = KursMandiri::orderBy('tanggal', 'desc')->first();

            $cpoKpbn = cpoKpbn::whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
                    ->orderBy('tanggal')
                    ->get();

            $avgCpoKpbnMtd = collect($cpoKpbn)->avg('avg');

            $cpoKpbnByMonth = $cpoKpbn->groupBy(function ($item) {
                return date('F', strtotime($item->tanggal));
            })
            ->map(function ($group) {
                return [
                    'month' => date('F', strtotime($group->first()->tanggal)),
                    'avg' => $group->avg('avg'),
                    'records' => $group
                ];
            })->values();

            $settingNames = ['coa_bahan_baku_mr'];

            $dataCostProd = $this->processCostProdPeriod($request, $settingNames);
            $result = $dataCostProd['data']->pluck('rp_per_kg_cpo_olah');

            $dataProCost = $this->processProCost($request);
            $averageCpoKpbn = $dataProCost['data']['averageCpoKpbn'];

            $dataAvgPrice = $this->avgPrice($request);

            $dataTarget = $this->targetResult($request);

            $dataOutstanding = outstandingCpo::orderBy('kontrak')->get();

            $latestEntries = RekeningUnitKerja::select('rekening_id', DB::raw('MAX(tanggal) as latest_date'))
                    ->groupBy('rekening_id');

                $dataCash = RekeningUnitKerja::joinSub($latestEntries, 'latest_entries', function ($join) {
                        $join->on('master_rekening_unit_kerja.rekening_id', '=', 'latest_entries.rekening_id')
                            ->on('master_rekening_unit_kerja.tanggal', '=', 'latest_entries.latest_date');
                    })
                    ->with(['rekening.jenis', 'rekening.tipe'])
                    ->orderBy('master_rekening_unit_kerja.rekening_id')
                    ->get();

                if ($dataCash->isEmpty()) {
                    return response()->json(['message' => $this->messageMissing], 401);
                }

                foreach ($dataCash as $entry) {
                    $rekening = $entry->rekening;
                    if ($rekening) {
                        $matauang = $this->getCurrency($rekening->matauang_id);
                        $rekening->matauang = $matauang;
                    }
                }

                $totalCash = $dataCash->sum('value');

            $dataStokBulky = $this->latestStokBulky();

            $dataStokRetail = $this->latestStokRetail();

            $dataStockAwalCpo = $this->processStockAwalCpo($request);


            return response()->json([
                'avgJisdor' => $avgRate,
                'lastDayJisdor' => $latestRate,
                'mandiriJisdor' => $mandiriRate,
                'cpoOlahINL' => $result[0],
                'avgCpoKpbn' => $averageCpoKpbn,
                'avgCpoKpbnMtd' => $avgCpoKpbnMtd,
                'dataAvgPrice' => $dataAvgPrice,
                'dataTarget' => $dataTarget,
                'dataProCost' => $dataProCost['data'],
                'dataOutstanding' => $dataOutstanding,
                'dataCash' => $dataCash,
                'totalCash' => $totalCash,
                'dataStokBulky' => $dataStokBulky,
                'dataStokRetail' => $dataStokRetail,
                'dataStockAwalCpo' => $dataStockAwalCpo,
                'cpoKpbnByMonth' => $cpoKpbnByMonth,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
