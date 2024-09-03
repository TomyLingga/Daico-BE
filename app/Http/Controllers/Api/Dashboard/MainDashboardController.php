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

            $allMonths = collect([
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ]);

            $cpoKpbnByMonth = $cpoKpbn->groupBy(function ($item) {
                return date('F', strtotime($item->tanggal)); // Group by month name
            })
            ->map(function ($group) {
                return [
                    'month' => date('F', strtotime($group->first()->tanggal)), // Convert tanggal to DateTime and get the month name
                    'avg' => $group->avg('avg'), // Calculate the average for 'avg'
                    'records' => $group, // All records for the month
                ];
            });

            // Step 4: Merge with all months and set default values for missing months
            $cpoKpbnByMonth = $allMonths->map(function ($month) use ($cpoKpbnByMonth) {
                return $cpoKpbnByMonth->get($month, [
                    'month' => $month,
                    'avg' => 0, // Default average to 0 if no records
                    'records' => collect([]), // Empty collection if no records
                ]);
            });

            // Optional: Convert to array if needed
            $cpoKpbnByMonth = $cpoKpbnByMonth->values()->all();

            $avgCpoKpbnMtd = collect($cpoKpbn)->avg('avg');

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
