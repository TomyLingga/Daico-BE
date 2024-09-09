<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\cpoKpbn;
use App\Models\KursMandiri;
use App\Models\MasterTipeRekening;
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

            $allMonths = collect([
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ]);

            // Group the data by month name and index by date
            $cpoKpbnByMonth = $cpoKpbn->groupBy(function ($item) {
                return date('F', strtotime($item->tanggal)); // Group by month name
            })->map(function ($group) {
                return $group->keyBy(function ($item) {
                    return date('Y-m-d', strtotime($item->tanggal)); // Index by date
                });
            });

            // Generate full date ranges and merge with existing data
            $cpoKpbnByMonth = $allMonths->map(function ($month, $index) use ($cpoKpbnByMonth, $tanggal) {
                $year = date('Y', strtotime($tanggal));
                $monthNumber = str_pad($index + 1, 2, '0', STR_PAD_LEFT); // Convert month index to number
                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $index + 1, $year);

                // Generate all dates for the current month
                $fullDates = collect();
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = $year . '-' . $monthNumber . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                    $fullDates->push($date);
                }

                // Merge existing records with the full date range
                $records = $fullDates->map(function ($date) use ($cpoKpbnByMonth, $month) {
                    $record = $cpoKpbnByMonth->get($month)?->get($date, null);

                    if ($record) {
                        return [
                            'id' => $record->id,
                            'tanggal' => $record->tanggal,
                            'avg' => $record->avg,
                            'created_at' => $record->created_at,
                            'updated_at' => $record->updated_at,
                        ];
                    }

                    // Default for missing records
                    return [
                        'id' => null,
                        'tanggal' => $date,
                        'avg' => 0,
                        'created_at' => null,
                        'updated_at' => null,
                    ];
                });

                // Calculate the average for the month, excluding missing dates
                $avg = $records->whereNotNull('id')->avg('avg');

                return [
                    'month' => $month,
                    'avg' => $avg,
                    'records' => $records,
                ];
            });

            // Convert the result to a list
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

            $dataTipeRekening = MasterTipeRekening::all();
            if ($dataTipeRekening->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }
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
                'dataTipeRekening' => $dataTipeRekening,
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
