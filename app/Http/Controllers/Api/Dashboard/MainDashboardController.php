<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\cpoKpbn;
use App\Models\KursMandiri;
use App\Models\outstandingCpo;
use App\Models\RekeningUnitKerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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

            // $cpoKpbn = cpoKpbn::whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
            //         ->orderBy('tanggal')
            //         ->get();

            // $allMonths = collect([
            //     'January', 'February', 'March', 'April', 'May', 'June',
            //     'July', 'August', 'September', 'October', 'November', 'December'
            // ]);

            // $cpoKpbnByMonth = $cpoKpbn->groupBy(function ($item) {
            //     return date('F', strtotime($item->tanggal)); // Group by month name
            // })
            // ->map(function ($group) {
            //     return [
            //         'month' => date('F', strtotime($group->first()->tanggal)),
            //         'avg' => $group->avg('avg'),
            //         'records' => $group,
            //     ];
            // });

            // $cpoKpbnByMonth = $allMonths->map(function ($month) use ($cpoKpbnByMonth) {
            //     return $cpoKpbnByMonth->get($month, [
            //         'month' => $month,
            //         'avg' => 0,
            //         'records' => collect([]),
            //     ]);
            // });

            // $cpoKpbnByMonth = $cpoKpbnByMonth->values()->all();
            $cpoKpbn = cpoKpbn::whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
                ->orderBy('tanggal')
                ->get();

            // Define all months and days in the selected year
            $allMonths = collect([
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ]);

            // Group records by month
            $cpoKpbnByMonth = $cpoKpbn->groupBy(function ($item) {
                return date('F', strtotime($item->tanggal)); // Group by month name
            })->map(function ($group) {
                // Calculate the average for actual records only (skip null entries later)
                $validRecords = $group->filter(function ($record) {
                    return !is_null($record->id); // Only use records with valid ids
                });

                return [
                    'month' => date('F', strtotime($group->first()->tanggal)),
                    'avg' => $validRecords->avg('avg'), // Calculate avg for valid records
                    'records' => $group,
                ];
            });

            // Create all months with default values for missing dates
            $cpoKpbnByMonth = $allMonths->map(function ($month) use ($cpoKpbnByMonth) {
                return $cpoKpbnByMonth->get($month, [
                    'month' => $month,
                    'avg' => 0,
                    'records' => collect([]),
                ])->map(function ($group) {
                    // Add missing dates in each month, with default 'id' => null and 'avg' => 0
                    $filledRecords = collect();
                    $firstDate = Carbon::parse("first day of {$group['month']}")->startOfMonth();
                    $lastDate = $firstDate->copy()->endOfMonth();

                    for ($date = $firstDate; $date <= $lastDate; $date->addDay()) {
                        $record = $group['records']->firstWhere('tanggal', $date->format('Y-m-d'));

                        $filledRecords->push($record ? $record : [
                            'id' => null,
                            'tanggal' => $date->format('Y-m-d'),
                            'avg' => 0,
                            'created_at' => null,
                            'updated_at' => null
                        ]);
                    }

                    // Recalculate the actual average excluding missing records
                    $validRecords = $filledRecords->filter(function ($record) {
                        return !is_null($record['id']); // Only valid records for avg calculation
                    });

                    return [
                        'month' => $group['month'],
                        'avg' => $validRecords->avg('avg'), // Recalculate the average for valid records
                        'records' => $filledRecords, // Use the filled records with missing dates included
                    ];
                });
            });

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
