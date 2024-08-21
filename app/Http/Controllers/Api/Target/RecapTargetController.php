<?php

namespace App\Http\Controllers\Api\Target;

use App\Http\Controllers\Controller;
use App\Models\DailyDMO;
use App\Models\LaporanProduksi;
use App\Models\MonthlyDMO;
use App\Models\TargetReal;
use App\Models\TargetRKAP;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class RecapTargetController extends Controller
{

    private $messageFail = 'Something went wrong';
    private $messageMissing = 'Data not found in record';
    private $messageAll = 'Success to Fetch All Datas';
    private $messageSuccess = 'Success to Fetch Data';
    private $messageCreate = 'Success to Create Data';
    private $messageUpdate = 'Success to Update Data';

    public function recapTarget(Request $request)
    {
        try {
            $date = Carbon::parse($request->tanggal);
            $year = $date->year;
            $month = $date->month;

            $tanggal = $request->tanggal;

            $dataReal = TargetReal::with('productable')
                ->whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
                ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
                ->orderBy('productable_type', 'desc')
                ->orderBy('productable_id', 'asc')
                ->orderBy('tanggal', 'asc')
                ->get();

            if ($dataReal->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            $targetRealResult = $this->processTarget($dataReal);

            $dataRkap = TargetRKAP::with('productable')
                ->whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
                ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
                ->orderBy('productable_type', 'desc')
                ->orderBy('productable_id', 'asc')
                ->orderBy('tanggal', 'asc')
                ->get();

            if ($dataRkap->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            $targetRkapResult = $this->processTarget($dataRkap);

            $differenceResult = $this->calculateDifference($targetRealResult['target'], $targetRkapResult['target']);

            $dataDailyDmo = DailyDMO::whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
                ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
                ->orderBy('tanggal')
                ->get();

            if ($dataDailyDmo->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }
            $totalDailyDmo = $dataDailyDmo->sum('value');

            $dataMonthlyDmo = MonthlyDMO::whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
                ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
                ->orderBy('tanggal')
                ->first();

            if (is_null($dataMonthlyDmo)) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            $totalMonthlyDmo = $dataMonthlyDmo->dmo;

            $cpoOlahRkap = $dataMonthlyDmo->cpo_olah_rkap;
            $kapasitasUtility = $dataMonthlyDmo->kapasitas_utility;
            $pengaliUtility = $dataMonthlyDmo->pengali_kapasitas_utility;
            $utility = $kapasitasUtility * $pengaliUtility;

            //cpo Olah
            $laporanProduksi = $this->indexLaporanProduksi($request);

            $cpoOlahReal = 0;

            foreach ($laporanProduksi['laporanProduksi'] as $produksi) {
                if ($produksi['nama'] === 'Refinery') {
                    foreach ($produksi['uraian'] as $uraian) {
                        if ($uraian['nama'] === 'CPO (Olah)') {
                            $cpoOlahReal = $uraian['total_qty'];
                            break 2;
                        }
                    }
                }
            }

            $qtyProduksiVsRkap = [
                [
                    'name' => 'Total Real',
                    'value' => $cpoOlahReal
                ],
                [
                    'name' => 'Total RKAP',
                    'value' => $cpoOlahRkap
                ],
                [
                    'name' => 'Difference',
                    'value' => abs($cpoOlahReal - $cpoOlahRkap)
                ],
                [
                    'name' => 'Percentage To Target',
                    'value' => ($cpoOlahReal/$cpoOlahRkap)*100,
                    'remaining' => max(0, 100 - (($cpoOlahReal/$cpoOlahRkap)*100))
                ]
            ];

            $qtyProduksiVsUtility = [
                [
                    'name' => 'Total Real',
                    'value' => $cpoOlahReal
                ],
                [
                    'name' => 'Total Utility',
                    'value' => $utility
                ],
                [
                    'name' => 'Difference',
                    'value' => abs($cpoOlahReal - $utility)
                ],
                [
                    'name' => 'Percentage To Target',
                    'value' => ($cpoOlahReal/$utility)*100,
                    'remaining' => max(0, 100 - (($cpoOlahReal/$utility)*100))
                ]
            ];

            $combinedResult = $this->combinedResultProcess($targetRealResult, $targetRkapResult, $differenceResult, $totalDailyDmo, $totalMonthlyDmo, $qtyProduksiVsRkap, $qtyProduksiVsUtility);

            return response()->json([
                'data' => $combinedResult,
                'message' => $this->messageAll,
                'success' => true,
            ], 200);

        } catch (QueryException $e) {
            return response()->json([
                'message' => $this->messageFail,
                'err' => $e->getTrace()[0],
                'errMsg' => $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    public function combinedResultProcess($targetRealResult, $targetRkapResult, $differenceResult, $totalDailyDmo, $totalMonthlyDmo, $qtyProduksiVsRkap, $qtyProduksiVsUtility)
    {
        $totalRealBulky = $targetRealResult['target'][0]['total'] ?? 0;
        $totalRealRetail = $targetRealResult['target'][1]['total'] ?? 0;
        $totalRealDmo = $totalDailyDmo;

        $totalRkapBulky = $targetRkapResult['target'][0]['total'] ?? 0;
        $totalRkapRetail = $targetRkapResult['target'][1]['total'] ?? 0;
        $totalRkapDmo = $totalMonthlyDmo;

        $totalReal = $totalRealBulky + $totalRealRetail + $totalRealDmo;
        $totalRkap = $totalRkapBulky + $totalRkapRetail + $totalRkapDmo;
        $totalDifference = abs($totalReal - $totalRkap);
        $percentageToTarget = $totalRkap > 0 ? ($totalReal / $totalRkap) * 100 : 0;

        $combinedResult = [
            'targetReal' => array_merge(
                $targetRealResult['target'],
                [
                    [
                        'name' => 'Total DMO',
                        'total' => $totalDailyDmo
                    ]
                ]
            ),
            'targetRkap' => array_merge(
                $targetRkapResult['target'],
                [
                    [
                        'name' => 'Total DMO',
                        'total' => $totalMonthlyDmo
                    ]
                ]
            ),
            'difference' => array_merge(
                $differenceResult,
                [
                    [
                        'name' => 'Total DMO',
                        'total' => abs($totalMonthlyDmo - $totalDailyDmo)
                    ]
                ]
            ),
            'percentageToTarget' => [
                [
                    'name' => 'Total Bulky',
                    'real' => $this->calculatePercentage($targetRealResult['target'], $targetRkapResult['target'], 'Total Bulky'),
                    'remaining' => max(0, 100 - $this->calculatePercentage($targetRealResult['target'], $targetRkapResult['target'], 'Total Bulky'))
                ],
                [
                    'name' => 'Total Retail',
                    'real' => $this->calculatePercentage($targetRealResult['target'], $targetRkapResult['target'], 'Total Retail'),
                    'remaining' => max(0, 100 - $this->calculatePercentage($targetRealResult['target'], $targetRkapResult['target'], 'Total Retail'))
                ],
                [
                    'name' => 'Total DMO',
                    'real' => $this->calculatePercentageDmo($totalDailyDmo, $totalMonthlyDmo),
                    'remaining' => max(0, 100 - $this->calculatePercentageDmo($totalDailyDmo, $totalMonthlyDmo))
                ]
            ],
            'totalOverall' => [
                [
                    'name' => 'Total Real',
                    'value' => $totalReal
                ],
                [
                    'name' => 'Total RKAP',
                    'value' => $totalRkap
                ],
                [
                    'name' => 'Difference',
                    'value' => $totalDifference
                ],
                [
                    'name' => 'Percentage To Target',
                    'value' => $percentageToTarget,
                    'remaining' => max(0, 100 - $percentageToTarget)
                ]
            ],
            'qtyProduksiVsRkap' => $qtyProduksiVsRkap,
            'qtyProduksiVsUtility' => $qtyProduksiVsUtility
        ];

        return $combinedResult;
    }


    public function processTarget($data)
    {
        $groupedData = $data->groupBy(function ($item) {
            return $item->productable_id . '-' . $item->productable_type;
        });

        $totals = $groupedData->map(function ($group) {
            $totalValue = $group->sum('value');
            $latestDate = $group->max('tanggal');
            $firstItem = $group->first();

            return [
                'productable_id' => $firstItem->productable_id,
                'productable_type' => $firstItem->productable_type,
                'productable_name' => $firstItem->productable->name,
                'total' => $totalValue,
                'latest_date' => $latestDate,
            ];
        })->values();

        $targetRealBulky = $totals->where('productable_type', "App\\Models\\MasterBulkProduksi")->values();
        $targetRealRetail = $totals->where('productable_type', "App\\Models\\MasterRetailProduksi")->values();

        $totalBulkProduksi = $targetRealBulky->sum('total');
        $totalRetailProduksi = $targetRealRetail->sum('total');

        $result = [
            'target' => [
                [
                    'name' => 'Total Bulky',
                    'total' => $totalBulkProduksi,
                    'items' => $targetRealBulky
                ],
                [
                    'name' => 'Total Retail',
                    'total' => $totalRetailProduksi,
                    'items' => $targetRealRetail
                ]
            ]
        ];

        return $result;
    }

    public function calculateDifference($targetReal, $targetRkap)
    {
        $difference = [];

        foreach ($targetReal as $index => $realItem) {
            $rkapItem = $targetRkap[$index] ?? null;

            $totalDifference = $realItem['total'] - ($rkapItem['total'] ?? 0);

            $itemDifferences = collect($realItem['items'])->map(function ($realItemData) use ($rkapItem) {
                $matchingRkapItem = collect($rkapItem['items'] ?? [])->firstWhere('productable_id', $realItemData['productable_id']);

                return [
                    'productable_id' => $realItemData['productable_id'],
                    'productable_type' => $realItemData['productable_type'],
                    'productable_name' => $realItemData['productable_name'],
                    'total' => $realItemData['total'] - ($matchingRkapItem['total'] ?? 0),
                    'latest_date' => $realItemData['latest_date'],
                ];
            })->values();

            $difference[] = [
                'name' => $realItem['name'],
                'total' => $totalDifference,
                'items' => $itemDifferences,
            ];
        }

        return $difference;
    }

    public function calculatePercentage($targetReal, $targetRkap, $name)
    {
        $real = collect($targetReal)->firstWhere('name', $name);
        $rkap = collect($targetRkap)->firstWhere('name', $name);

        if ($rkap && $rkap['total'] > 0) {
            return ($real['total'] / $rkap['total']) * 100;
        }

        return 0;
    }

    public function calculatePercentageDmo($dailyDmo, $monthlyDmo)
    {
        if ($monthlyDmo > 0) {
            return ($dailyDmo / $monthlyDmo) * 100;
        }

        return 0;
    }

}
