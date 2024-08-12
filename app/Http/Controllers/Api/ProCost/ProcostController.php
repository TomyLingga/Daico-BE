<?php

namespace App\Http\Controllers\Api\ProCost;

use App\Http\Controllers\Api\CostingHPP\CostingHppController;
use App\Http\Controllers\Api\DetAlloc\LaporanProduksiController;
use App\Http\Controllers\Controller;
use App\Models\cpoKpbn;
use App\Models\LevyDutyBulky;
use App\Models\MarketRoutersBulky;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class ProcostController extends Controller
{
    private $messageFail = 'Something went wrong';
    private $messageMissing = 'Data not found in record';
    private $messageAll = 'Success to Fetch All Datas';
    private $messageSuccess = 'Success to Fetch Data';
    private $messageCreate = 'Success to Create Data';
    private $messageUpdate = 'Success to Update Data';

    public function indexDate(Request $request)
    {
        try {

            $processResult = $this->processIndexDate($request);

            if ($processResult['error']) {
                return $processResult['response'];
            }

            return response()->json($processResult['data'], 200);

        } catch (QueryException $e) {
            return response()->json([
                'message' => $this->messageFail,
                'err' => $e->getTrace()[0],
                'errMsg' => $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    public function processIndexDate(Request $request)
    {
        $tanggal = $request->tanggal;

        $data = $this->fetchDataMarket($tanggal);

        if ($data['dataMRouters']->isEmpty() || $data['dataLDuty']->isEmpty()) {
            return [
                'error' => true,
                'response' => response()->json(['message' => $this->messageMissing], 401),
            ];
        }

        $formattedDataMRouters = $data['dataMRouters']->groupBy('bulky.id')->map(function ($items, $bulkyId) {
            $bulky = $items->first()['bulky'];
            return [
                'id' => $bulky['id'],
                'name' => $bulky['name'],
                'item' => $items->map(function ($item) {
                    return [
                        'id' => $item['id'],
                        'id_bulky' => $item['id_bulky'],
                        'tanggal' => $item['tanggal'],
                        'nilai' => $item['nilai'],
                        'currency_id' => $item['currency_id'],
                        'created_at' => $item['created_at'],
                        'updated_at' => $item['updated_at'],
                    ];
                })
            ];
        })->values();

        $formattedDataLDuty = $data['dataLDuty']->groupBy('bulky.id')->map(function ($items, $bulkyId) {
            $bulky = $items->first()['bulky'];
            return [
                'id' => $bulky['id'],
                'name' => $bulky['name'],
                'item' => $items->map(function ($item) {
                    return [
                        'id' => $item['id'],
                        'id_bulky' => $item['id_bulky'],
                        'tanggal' => $item['tanggal'],
                        'nilai' => $item['nilai'],
                        'currency_id' => $item['currency_id'],
                        'created_at' => $item['created_at'],
                        'updated_at' => $item['updated_at'],
                    ];
                })
            ];
        })->values();

        $formattedMarketExcludedLevyDuty = $data['marketExcludedLevyDuty']->groupBy('bulky.id')->map(function ($items, $bulkyId) {
            $bulky = $items->first()['bulky'];
            return [
                'id' => $bulky['id'],
                'name' => $bulky['name'],
                'item' => $items->map(function ($item) {
                    return [
                        'tanggal' => $item['tanggal'],
                        'nilai' => $item['nilai'],
                        'id_bulky' => $item['id_bulky'],
                        'currency_id' => $item['currency_id'],
                    ];
                })
            ];
        })->values();

        $averages = $this->calculateAverages($data['averageMarketValue']);
        // dd($averages);
        $laporanProduksi = $this->processRecapData($request);
        // dd($laporanProduksi);

        $costingHppController = new CostingHppController;
        $produksiRefineryData = $this->generateProduksiRefinery($costingHppController, $laporanProduksi, $averages);
        $produksiFraksinasiIV56Data = $this->generateProduksiFraksinasiIV56($costingHppController, $laporanProduksi, $averages);
        $produksiFraksinasiIV57Data = $this->generateProduksiFraksinasiIV57($costingHppController, $laporanProduksi, $averages);
        $produksiFraksinasiIV58Data = $this->generateProduksiFraksinasiIV58($costingHppController, $laporanProduksi, $averages);
        $produksiFraksinasiIV60Data = $this->generateProduksiFraksinasiIV60($costingHppController, $laporanProduksi, $averages);

        return [
            'error' => false,
            'data' => [
                'dataMRouters' => $formattedDataMRouters,
                'averageDataMRoutersPerBulky' => $data['averageDataMRoutersPerBulky'],
                'dataLDuty' => $formattedDataLDuty,
                'averageDataLDutyPerBulky' => $data['averageDataLDutyPerBulky'],
                'currencyRates' => $data['currencyRates'],
                'averageCurrencyRate' => $data['averageCurrencyRate'],
                'marketExcludedLevyDuty' => $formattedMarketExcludedLevyDuty,
                'marketUSD_or_AverageMarketExcludedLevyDutyPerBulky' => $data['averageMarketExcludedLevyDutyPerBulky'],
                'dataCpoKpbn' => $data['dataCpoKpbn'],
                'averageCpoKpbn' => $data['averageCpoKpbn'],
                'marketValueIDR' => $data['marketValue'],
                'averageMarketValueIDR' => $data['averageMarketValue'],
                'produksiRefineryData' => $produksiRefineryData,
                'produksiFraksinasiIV56Data' => $produksiFraksinasiIV56Data,
                'produksiFraksinasiIV57Data' => $produksiFraksinasiIV57Data,
                'produksiFraksinasiIV58Data' => $produksiFraksinasiIV58Data,
                'produksiFraksinasiIV60Data' => $produksiFraksinasiIV60Data,
                'message' => $this->messageAll,
            ],
        ];
    }

    public function calculateAverages($marketValues)
    {
        $averages = [
            'pfad' => null,
            'rbdStearin' => null,
            'rbdOlein' => null,
            'rbdpo' => null
        ];

        foreach ($marketValues as $marketValue) {
            if ($marketValue['name'] === 'PFAD') {
                $averages['pfad'] = $marketValue['average'];
            } elseif ($marketValue['name'] === 'RBD Stearin') {
                $averages['rbdStearin'] = $marketValue['average'];
            } elseif ($marketValue['name'] === 'RBD Olein') {
                $averages['rbdOlein'] = $marketValue['average'];
            } elseif ($marketValue['name'] === 'RBDPO') {
                $averages['rbdpo'] = $marketValue['average'];
            }
        }

        return $averages;
    }

    public function generateProduksiFraksinasiIV60($costingHppController, $laporanProduksi, $averages){
        $rbdpoOlahIV60Qty = $costingHppController->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-56)', 'RBDPO (Olah)');

        $rbdOleinIv60Qty = $costingHppController->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-60)', 'RBD Olein IV 60 (Produksi)');
        $rbdOleinIv60Rendement = $rbdOleinIv60Qty != 0 ? $rbdOleinIv60Qty / $rbdpoOlahIV60Qty : 0;
        $rbdOleinIv60RendementPercentage = $rbdOleinIv60Rendement * 100;

        $rbdStearinQty = $costingHppController->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-60)', 'RBD Stearin (Produksi)');
        $rbdStearinRendement = $rbdStearinQty != 0 ? $rbdStearinQty / $rbdpoOlahIV60Qty : 0;
        $rbdStearinRendementPercentage = $rbdStearinRendement * 100;

        $proporsiBiayaRpKgRbdOleinIv60 = $rbdOleinIv60RendementPercentage * $averages['rbdOlein'];
        $proporsiBiayaRpKgRbdStearin = $rbdStearinRendementPercentage * $averages['rbdStearin'];
        $totalProporsiBiayaRpKgFraksinasiIV60 = $proporsiBiayaRpKgRbdOleinIv60 + $proporsiBiayaRpKgRbdStearin;

        $proporsiBiayaPersenRbdOleinIv60 = $totalProporsiBiayaRpKgFraksinasiIV60 != 0 ? $proporsiBiayaRpKgRbdOleinIv60 / $totalProporsiBiayaRpKgFraksinasiIV60 * 100 : 0;
        $proporsiBiayaPersenRbdStearin = $totalProporsiBiayaRpKgFraksinasiIV60 != 0 ? $proporsiBiayaRpKgRbdStearin / $totalProporsiBiayaRpKgFraksinasiIV60 * 100 : 0;
        $totalProporsiBiayaPersenFraksinasiIV60 = $proporsiBiayaPersenRbdOleinIv60 + $proporsiBiayaPersenRbdStearin;
        $produksiFraksinasiIV60 = [
            'data' => [
                [
                    'nama' => 'Produksi Fraksinasi IV-60',
                    'item' => [
                        [
                            'name' => 'RBDPO Olah',
                            'value' => $rbdpoOlahIV60Qty,
                        ],
                        [
                            'name' => 'RBDOlein IV-60',
                            'value' => $rbdOleinIv60Qty,
                        ],
                        [
                            'name' => 'RBDStearin',
                            'value' => $rbdStearinQty,
                        ]
                    ]
                ],
                [
                    'nama' => 'Rendement Fraksinasi IV-60',
                    'item' => [
                        [
                            'name' => 'RBDOlein IV-60',
                            'value' => $rbdOleinIv60RendementPercentage,
                        ],
                        [
                            'name' => 'RBDStearin',
                            'value' => $rbdStearinRendementPercentage,
                        ]
                    ]
                ],
                [
                    'nama' => 'Proporsi biaya (Rp/Kg)',
                    'item' => [
                        [
                            'name' => 'RBDOlein IV-60',
                            'value' => $proporsiBiayaRpKgRbdOleinIv60,
                        ],
                        [
                            'name' => 'RBDStearin',
                            'value' => $proporsiBiayaRpKgRbdStearin,
                        ]
                    ]
                ],
                [
                    'nama' => 'Proporsi biaya (%)',
                    'item' => [
                        [
                            'name' => 'RBDPO',
                            'value' => $proporsiBiayaPersenRbdOleinIv60,
                        ],
                        [
                            'name' => 'PFAD',
                            'value' => $proporsiBiayaPersenRbdStearin,
                        ],
                        [
                            'name' => 'TOTAL',
                            'value' => $totalProporsiBiayaPersenFraksinasiIV60,
                        ]
                    ]
                ]

            ]
        ];

        return $produksiFraksinasiIV60;
    }

    public function generateProduksiFraksinasiIV58($costingHppController, $laporanProduksi, $averages){
        $rbdpoOlahIV58Qty = $costingHppController->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-56)', 'RBDPO (Olah)');

        $rbdOleinIv58Qty = $costingHppController->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-58)', 'RBD Olein IV 58 (Produksi)');
        $rbdOleinIv58Rendement = $rbdOleinIv58Qty != 0 ? $rbdOleinIv58Qty / $rbdpoOlahIV58Qty : 0;
        $rbdOleinIv58RendementPercentage = $rbdOleinIv58Rendement * 100;

        $rbdStearinQty = $costingHppController->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-58)', 'RBD Stearin (Produksi)');
        $rbdStearinRendement = $rbdStearinQty != 0 ? $rbdStearinQty / $rbdpoOlahIV58Qty : 0;
        $rbdStearinRendementPercentage = $rbdStearinRendement * 100;

        $proporsiBiayaRpKgRbdOleinIv58 = $rbdOleinIv58RendementPercentage * $averages['rbdOlein'];
        $proporsiBiayaRpKgRbdStearin = $rbdStearinRendementPercentage * $averages['rbdStearin'];
        $totalProporsiBiayaRpKgFraksinasiIV58 = $proporsiBiayaRpKgRbdOleinIv58 + $proporsiBiayaRpKgRbdStearin;

        $proporsiBiayaPersenRbdOleinIv58 = $totalProporsiBiayaRpKgFraksinasiIV58 != 0 ? $proporsiBiayaRpKgRbdOleinIv58 / $totalProporsiBiayaRpKgFraksinasiIV58 * 100 : 0;
        $proporsiBiayaPersenRbdStearin = $totalProporsiBiayaRpKgFraksinasiIV58 != 0 ? $proporsiBiayaRpKgRbdStearin / $totalProporsiBiayaRpKgFraksinasiIV58 * 100 : 0;
        $totalProporsiBiayaPersenFraksinasiIV58 = $proporsiBiayaPersenRbdOleinIv58 + $proporsiBiayaPersenRbdStearin;
        $produksiFraksinasiIV58 = [
            'data' => [
                [
                    'nama' => 'Produksi Fraksinasi IV-58',
                    'item' => [
                        [
                            'name' => 'RBDPO Olah',
                            'value' => $rbdpoOlahIV58Qty,
                        ],
                        [
                            'name' => 'RBDOlein IV-58',
                            'value' => $rbdOleinIv58Qty,
                        ],
                        [
                            'name' => 'RBDStearin',
                            'value' => $rbdStearinQty,
                        ]
                    ]
                ],
                [
                    'nama' => 'Rendement Fraksinasi IV-58',
                    'item' => [
                        [
                            'name' => 'RBDOlein IV-58',
                            'value' => $rbdOleinIv58RendementPercentage,
                        ],
                        [
                            'name' => 'RBDStearin',
                            'value' => $rbdStearinRendementPercentage,
                        ]
                    ]
                ],
                [
                    'nama' => 'Proporsi biaya (Rp/Kg)',
                    'item' => [
                        [
                            'name' => 'RBDOlein IV-58',
                            'value' => $proporsiBiayaRpKgRbdOleinIv58,
                        ],
                        [
                            'name' => 'RBDStearin',
                            'value' => $proporsiBiayaRpKgRbdStearin,
                        ]
                    ]
                ],
                [
                    'nama' => 'Proporsi biaya (%)',
                    'item' => [
                        [
                            'name' => 'RBDPO',
                            'value' => $proporsiBiayaPersenRbdOleinIv58,
                        ],
                        [
                            'name' => 'PFAD',
                            'value' => $proporsiBiayaPersenRbdStearin,
                        ],
                        [
                            'name' => 'TOTAL',
                            'value' => $totalProporsiBiayaPersenFraksinasiIV58,
                        ]
                    ]
                ]

            ]
        ];

        return $produksiFraksinasiIV58;
    }

    public function generateProduksiFraksinasiIV57($costingHppController, $laporanProduksi, $averages){
        $rbdpoOlahIV57Qty = $costingHppController->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-56)', 'RBDPO (Olah)');

        $rbdOleinIv57Qty = $costingHppController->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-57)', 'RBD Olein IV 57 (Produksi)');
        $rbdOleinIv57Rendement = $rbdOleinIv57Qty != 0 ? $rbdOleinIv57Qty / $rbdpoOlahIV57Qty : 0;
        $rbdOleinIv57RendementPercentage = $rbdOleinIv57Rendement * 100;

        $rbdStearinQty = $costingHppController->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-57)', 'RBD Stearin (Produksi)');
        $rbdStearinRendement = $rbdStearinQty != 0 ? $rbdStearinQty / $rbdpoOlahIV57Qty : 0;
        $rbdStearinRendementPercentage = $rbdStearinRendement * 100;

        $proporsiBiayaRpKgRbdOleinIv57 = $rbdOleinIv57RendementPercentage * $averages['rbdOlein'];
        $proporsiBiayaRpKgRbdStearin = $rbdStearinRendementPercentage * $averages['rbdStearin'];
        $totalProporsiBiayaRpKgFraksinasiIV57 = $proporsiBiayaRpKgRbdOleinIv57 + $proporsiBiayaRpKgRbdStearin;

        $proporsiBiayaPersenRbdOleinIv57 = $totalProporsiBiayaRpKgFraksinasiIV57 != 0 ? $proporsiBiayaRpKgRbdOleinIv57 / $totalProporsiBiayaRpKgFraksinasiIV57 * 100 : 0;
        $proporsiBiayaPersenRbdStearin = $totalProporsiBiayaRpKgFraksinasiIV57 != 0 ? $proporsiBiayaRpKgRbdStearin / $totalProporsiBiayaRpKgFraksinasiIV57 * 100 : 0;
        $totalProporsiBiayaPersenFraksinasiIV57 = $proporsiBiayaPersenRbdOleinIv57 + $proporsiBiayaPersenRbdStearin;
        $produksiFraksinasiIV57 = [
            'data' => [
                [
                    'nama' => 'Produksi Fraksinasi IV-57',
                    'item' => [
                        [
                            'name' => 'RBDPO Olah',
                            'value' => $rbdpoOlahIV57Qty,
                        ],
                        [
                            'name' => 'RBDOlein IV-57',
                            'value' => $rbdOleinIv57Qty,
                        ],
                        [
                            'name' => 'RBDStearin',
                            'value' => $rbdStearinQty,
                        ]
                    ]
                ],
                [
                    'nama' => 'Rendement Fraksinasi IV-57',
                    'item' => [
                        [
                            'name' => 'RBDOlein IV-57',
                            'value' => $rbdOleinIv57RendementPercentage,
                        ],
                        [
                            'name' => 'RBDStearin',
                            'value' => $rbdStearinRendementPercentage,
                        ]
                    ]
                ],
                [
                    'nama' => 'Proporsi biaya (Rp/Kg)',
                    'item' => [
                        [
                            'name' => 'RBDOlein IV-57',
                            'value' => $proporsiBiayaRpKgRbdOleinIv57,
                        ],
                        [
                            'name' => 'RBDStearin',
                            'value' => $proporsiBiayaRpKgRbdStearin,
                        ]
                    ]
                ],
                [
                    'nama' => 'Proporsi biaya (%)',
                    'item' => [
                        [
                            'name' => 'RBDPO',
                            'value' => $proporsiBiayaPersenRbdOleinIv57,
                        ],
                        [
                            'name' => 'PFAD',
                            'value' => $proporsiBiayaPersenRbdStearin,
                        ],
                        [
                            'name' => 'TOTAL',
                            'value' => $totalProporsiBiayaPersenFraksinasiIV57,
                        ]
                    ]
                ]

            ]
        ];

        return $produksiFraksinasiIV57;
    }

    public function generateProduksiFraksinasiIV56($costingHppController, $laporanProduksi, $averages){
        $rbdpoOlahIV56Qty = $costingHppController->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-56)', 'RBDPO (Olah)');
        $rbdOleinIv56Qty = $costingHppController->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-56)', 'RBD Olein IV 56 (Produksi)');
        $rbdOleinIv56Rendement = $rbdOleinIv56Qty != 0 ? $rbdOleinIv56Qty / $rbdpoOlahIV56Qty : 0;
        $rbdOleinIv56RendementPercentage = $rbdOleinIv56Rendement * 100;

        $rbdStearinQty = $costingHppController->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-56)', 'RBD Stearin (Produksi)');
        $rbdStearinRendement = $rbdStearinQty != 0 ? $rbdStearinQty / $rbdpoOlahIV56Qty : 0;
        $rbdStearinRendementPercentage = $rbdStearinRendement * 100;

        $proporsiBiayaRpKgRbdOleinIv56 = $rbdOleinIv56RendementPercentage * $averages['rbdOlein'];
        $proporsiBiayaRpKgRbdStearin = $rbdStearinRendementPercentage * $averages['rbdStearin'];
        $totalProporsiBiayaRpKgFraksinasiIV56 = $proporsiBiayaRpKgRbdOleinIv56 + $proporsiBiayaRpKgRbdStearin;

        $proporsiBiayaPersenRbdOleinIv56 = $totalProporsiBiayaRpKgFraksinasiIV56 != 0 ? $proporsiBiayaRpKgRbdOleinIv56 / $totalProporsiBiayaRpKgFraksinasiIV56 * 100 : 0;
        $proporsiBiayaPersenRbdStearin = $totalProporsiBiayaRpKgFraksinasiIV56 != 0 ? $proporsiBiayaRpKgRbdStearin / $totalProporsiBiayaRpKgFraksinasiIV56 * 100 : 0;
        $totalProporsiBiayaPersenFraksinasiIV56 = $proporsiBiayaPersenRbdOleinIv56 + $proporsiBiayaPersenRbdStearin;
        $produksiFraksinasiIV56 = [
            'data' => [
                [
                    'nama' => 'Produksi Fraksinasi IV-56',
                    'item' => [
                        [
                            'name' => 'RBDPO Olah',
                            'value' => $rbdpoOlahIV56Qty,
                        ],
                        [
                            'name' => 'RBDOlein IV-56',
                            'value' => $rbdOleinIv56Qty,
                        ],
                        [
                            'name' => 'RBDStearin',
                            'value' => $rbdStearinQty,
                        ]
                    ]
                ],
                [
                    'nama' => 'Rendement Fraksinasi IV-56',
                    'item' => [
                        [
                            'name' => 'RBDOlein IV-56',
                            'value' => $rbdOleinIv56RendementPercentage,
                        ],
                        [
                            'name' => 'RBDStearin',
                            'value' => $rbdStearinRendementPercentage,
                        ]
                    ]
                ],
                [
                    'nama' => 'Proporsi biaya (Rp/Kg)',
                    'item' => [
                        [
                            'name' => 'RBDOlein IV-56',
                            'value' => $proporsiBiayaRpKgRbdOleinIv56,
                        ],
                        [
                            'name' => 'RBDStearin',
                            'value' => $proporsiBiayaRpKgRbdStearin,
                        ]
                    ]
                ],
                [
                    'nama' => 'Proporsi biaya (%)',
                    'item' => [
                        [
                            'name' => 'RBDPO',
                            'value' => $proporsiBiayaPersenRbdOleinIv56,
                        ],
                        [
                            'name' => 'PFAD',
                            'value' => $proporsiBiayaPersenRbdStearin,
                        ],
                        [
                            'name' => 'TOTAL',
                            'value' => $totalProporsiBiayaPersenFraksinasiIV56,
                        ]
                    ]
                ]

            ]
        ];

        return $produksiFraksinasiIV56;
    }

    public function generateProduksiRefinery($costingHppController, $laporanProduksi, $averages)
    {
        $cpoConsumeQty = $costingHppController->getTotalQty($laporanProduksi['laporanProduksi'], 'Refinery', 'CPO (Olah)');

        $rbdpoQty = $costingHppController->getTotalQty($laporanProduksi['laporanProduksi'], 'Refinery', 'RBDPO (Produksi)');
        $rbdpoRendement = $cpoConsumeQty != 0 ? $rbdpoQty / $cpoConsumeQty : 0;
        $rbdpoRendementPercentage = $rbdpoRendement * 100;

        $pfadQty = $costingHppController->getTotalQty($laporanProduksi['laporanProduksi'], 'Refinery', 'PFAD (Produksi)');
        $pfadRendement = $cpoConsumeQty != 0 ? $pfadQty / $cpoConsumeQty : 0;
        $pfadRendementPercentage = $pfadRendement * 100;

        $proporsiBiayaRpKgRbdpo = $rbdpoRendementPercentage * $averages['rbdpo'];
        $proporsiBiayaRpKgPfad = $pfadRendementPercentage * $averages['pfad'];
        $totalProporsiBiayaRpKgRefinery = $proporsiBiayaRpKgRbdpo + $proporsiBiayaRpKgPfad;

        $proporsiBiayaPersenRbdpo = $totalProporsiBiayaRpKgRefinery != 0 ? $proporsiBiayaRpKgRbdpo / $totalProporsiBiayaRpKgRefinery * 100 : 0;
        $proporsiBiayaPersenPfad = $totalProporsiBiayaRpKgRefinery != 0 ? $proporsiBiayaRpKgPfad / $totalProporsiBiayaRpKgRefinery * 100 : 0;
        $totalProporsiBiayaPersenRefinery = $proporsiBiayaPersenRbdpo + $proporsiBiayaPersenPfad;

        $produksiRefinery = [
            'data' => [
                [
                    'nama' => 'Produksi Refinery',
                    'item' => [
                        [
                            'name' => 'CPO Olah',
                            'value' => $cpoConsumeQty,
                        ],
                        [
                            'name' => 'RBDPO',
                            'value' => $rbdpoQty,
                        ],
                        [
                            'name' => 'PFAD',
                            'value' => $pfadQty,
                        ]
                    ]
                ],
                [
                    'nama' => 'Rendement Refinery',
                    'item' => [
                        [
                            'name' => 'RBDPO',
                            'value' => $rbdpoRendementPercentage,
                        ],
                        [
                            'name' => 'PFAD',
                            'value' => $pfadRendementPercentage,
                        ]
                    ]
                ],
                [
                    'nama' => 'Proporsi biaya (Rp/Kg)',
                    'item' => [
                        [
                            'name' => 'RBDPO',
                            'value' => $proporsiBiayaRpKgRbdpo,
                        ],
                        [
                            'name' => 'PFAD',
                            'value' => $proporsiBiayaRpKgPfad,
                        ]
                    ]
                ],
                [
                    'nama' => 'Proporsi biaya (%)',
                    'item' => [
                        [
                            'name' => 'RBDPO',
                            'value' => $proporsiBiayaPersenRbdpo,
                        ],
                        [
                            'name' => 'PFAD',
                            'value' => $proporsiBiayaPersenPfad,
                        ],
                        [
                            'name' => 'TOTAL',
                            'value' => $totalProporsiBiayaPersenRefinery,
                        ]
                    ]
                ]

            ]
        ];

        return $produksiRefinery;
    }

    public function fetchDataMarket($tanggal)
    {
        $currencies = collect($this->getCurrencies());
        $currencyRates = collect($this->getRateCurrencyData($tanggal, "USD"));

        $dataCpoKpbn = $this->getCpoKpbn($tanggal);
        $dataMRouters = $this->getMarketRouters($tanggal);
        $dataLDuty = $this->getLevyDuty($tanggal);
        $setting = $this->getSetting('pembagi_market_idr');

        $marketExcludedLevyDuty = $this->calculateMarketExcludedLevyDuty($dataMRouters, $dataLDuty, $currencies);
        $averageCurrencyRate = $currencyRates->avg('rate');
        $averageCpoKpbn = $dataCpoKpbn->avg('avg');

        $averageDataMRoutersPerBulky = $this->calculateAveragePerBulky($dataMRouters);
        $averageDataLDutyPerBulky = $this->calculateAveragePerBulky($dataLDuty);
        $averageMarketExcludedLevyDutyPerBulky = $this->calculateAveragePerBulky($marketExcludedLevyDuty);

        $marketValue = $this->calculateMarketValue($marketExcludedLevyDuty, $currencyRates, $setting);
        $averageMarketValue = $this->calculateAverageMarketValue($marketValue);

        return compact(
            'dataMRouters',
            'dataLDuty',
            'dataCpoKpbn',
            'setting',
            'marketExcludedLevyDuty',
            'currencies',
            'currencyRates',
            'averageCurrencyRate',
            'averageDataMRoutersPerBulky',
            'averageDataLDutyPerBulky',
            'averageMarketExcludedLevyDutyPerBulky',
            'averageCpoKpbn',
            'marketValue',
            'averageMarketValue'
        );
    }

    protected function getCpoKpbn($tanggal)
    {
        return cpoKpbn::whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
            ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
            ->orderBy('tanggal')
            ->get();
    }

    protected function getMarketRouters($tanggal)
    {
        return MarketRoutersBulky::with('bulky')
            ->whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
            ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
            ->orderBy('tanggal')
            ->get();
    }

    protected function getLevyDuty($tanggal)
    {
        return LevyDutyBulky::with('bulky')
            ->whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
            ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
            ->orderBy('tanggal')
            ->get();
    }

    protected function getSetting($name)
    {
        return Setting::where('setting_name', $name)->first();
    }

    protected function calculateMarketExcludedLevyDuty($dataMRouters, $dataLDuty, $currencies)
    {
        return $dataMRouters->map(function ($router) use ($dataLDuty, $currencies) {
            $levyDuty = $dataLDuty->firstWhere('tanggal', $router->tanggal);
            $excludedValue = $router->nilai - ($levyDuty->nilai ?? 0);
            if (empty($router->nilai) || $router->nilai == 0) {
                $excludedValue = 0;
            }

            $currencyDetails = $currencies->firstWhere('id', $router->currency_id);

            return [
                'tanggal' => $router->tanggal,
                'nilai' => $excludedValue,
                'id_bulky' => $router->id_bulky,
                'bulky' => $router->bulky,
                'currency_id' => $router->currency_id,
                'currency' => $currencyDetails,
            ];
        });
    }

    protected function calculateAveragePerBulky($data)
    {
        return $data->groupBy('bulky.id')->map(function ($items) {
            // Handle if bulky is an object or array
            $bulky = $items->first()->bulky ?? $items->first()['bulky'];

            return [
                'id' => $bulky['id'] ?? $bulky->id,
                'name' => $bulky['name'] ?? $bulky->name,
                'average' => $items->avg('nilai'),
            ];
        })->values();
    }


    protected function calculateMarketValue($marketExcludedLevyDuty, $currencyRates, $setting)
    {
        $settingValue = (int) $setting->setting_value;

        return $marketExcludedLevyDuty->groupBy('bulky.id')->map(function ($items) use ($currencyRates, $settingValue) {
            $bulky = $items->first()['bulky'] ?? (object) $items->first()->bulky;

            return [
                'id' => $bulky['id'] ?? $bulky->id,
                'name' => $bulky['name'] ?? $bulky->name,
                'item' => $items->map(function ($item) use ($currencyRates, $settingValue) {
                    $rate = $currencyRates->firstWhere('name', $item['tanggal'])['rate'] ?? 0;
                    $value = ($item['nilai'] * $rate) / $settingValue;
                    return [
                        'tanggal' => $item['tanggal'],
                        'value' => $value,
                    ];
                })
            ];
        })->values();
    }


    protected function calculateAverageMarketValue($marketValue)
    {
        return collect($marketValue)->map(function ($bulky) {
            return [
                'id' => $bulky['id'],
                'name' => $bulky['name'],
                'average' => round(
                    collect($bulky['item'])->avg('value'),
                    2
                ),
            ];
        });
    }

    public function processRecapData(Request $request)
    {
        $mata_uang = 'USD';
        $tanggal = Carbon::parse($request->tanggal);
        $year = $tanggal->year;
        $month = $tanggal->month;
        $laporanProduksiController = new LaporanProduksiController;


        $data = $laporanProduksiController->dataLaporanProduksi($year, $month);

        $laporanProduksi = $laporanProduksiController->prosesLaporanProd($data);

        $hargaGasSetting = $laporanProduksiController->settingGet('harga_gas');
        $minPemakaianGasSetting = $laporanProduksiController->settingGet('minimum_pemakaian_gas');
        $uraianGasIds = $laporanProduksiController->settingGet('id_uraian_gas');
        $uraianWaterIds = $laporanProduksiController->settingGet('id_uraian_water');
        $uraianSteamIds = $laporanProduksiController->settingGet('id_uraian_steam');
        $uraianPowerIds = $laporanProduksiController->settingGet('id_uraian_listrik');

        $settingPersenCostAllocAirRefinery = $laporanProduksiController->settingGet('persen_cost_alloc_air_refinery');
        $PersenCostAllocAirRefinery = $settingPersenCostAllocAirRefinery->setting_value;
        $PersenCostAllocAirFractionation = 100 - $PersenCostAllocAirRefinery;

        $settingPersenCostAllocGasRefinery = $laporanProduksiController->settingGet('persen_cost_alloc_air_refinery');
        $PersenCostAllocGasRefinery = $settingPersenCostAllocGasRefinery->setting_value;
        $PersenCostAllocGasFractionation = 100 - $PersenCostAllocGasRefinery;

        $currencyRates = collect($this->getRateCurrencyData($tanggal, $mata_uang));

        $additionalData = $laporanProduksiController->processUraianData($laporanProduksi, $uraianGasIds, $uraianWaterIds, $uraianSteamIds, $uraianPowerIds);

        $incomingSteam = null;
        $distributionToRef = null;
        $distributionToFrac = null;
        $distributionToOther = null;

        foreach ($additionalData['steamConsumption'] as $item) {
            if($item['id'] == 51){
                $incomingSteam =[
                    'satuan' => $item['satuan'],
                    'value' => $item['value']
                ];
            }
            if($item['id'] == 52){
                $distributionToRef =[
                    'satuan' => $item['satuan'],
                    'value' => $item['value']
                ];
            }
            if ($item['id'] == 53) {
                $distributionToFrac = [
                    'satuan' => $item['satuan'],
                    'value' => $item['value']
                ];
            }
            if ($item['id'] == 54) {
                $distributionToOther = [
                    'satuan' => $item['satuan'],
                    'value' => $item['value']
                ];
            }
        }

        $incomingPertagas = null;
        $incomingINL = null;
        $outgoingHPBoilerRefinery = null;
        $outgoingMPBoiler12 = null;

        foreach ($additionalData['gasConsumption'] as $item) {
            if ($item['id'] == 47) {
                $incomingINL = [
                    'satuan' => $item['satuan'],
                    'value' => $item['value']
                ];
            }
            if ($item['id'] == 48) {
                $incomingPertagas = [
                    'satuan' => $item['satuan'],
                    'value' => $item['value']
                ];
            }
            if($item['id'] == 49){
                $outgoingHPBoilerRefinery =[
                    'satuan' => $item['satuan'],
                    'value' => $item['value']
                ];
            }
            if($item['id'] == 50){
                $outgoingMPBoiler12 =[
                    'satuan' => $item['satuan'],
                    'value' => $item['value']
                ];
            }
        }

        $outgoingSoftenerProductRef = null;
        $outgoingSoftenerProductFrac = null;
        $outgoingROProduct = null;
        $outgoingOthers = null;
        $wasteWaterEffluent = null;

        foreach ($additionalData['waterConsumption'] as $item) {
            if ($item['id'] == 56) {
                $outgoingSoftenerProductRef = [
                    'satuan' => $item['satuan'],
                    'value' => $item['value']
                ];
            }
            if($item['id'] == 57){
                $outgoingSoftenerProductFrac =[
                    'satuan' => $item['satuan'],
                    'value' => $item['value']
                ];
            }
            if($item['id'] == 58){
                $outgoingROProduct =[
                    'satuan' => $item['satuan'],
                    'value' => $item['value']
                ];
            }
            if($item['id'] == 59){
                $outgoingOthers =[
                    'satuan' => $item['satuan'],
                    'value' => $item['value']
                ];
            }
            if($item['id'] == 60){
                $wasteWaterEffluent =[
                    'satuan' => $item['satuan'],
                    'value' => $item['value']
                ];
            }
        }

        $pemakaianListrikPLN = null;
        $powerAllocRefinery = null;
        $powerAllocFractionation = null;
        $powerAllocOther = null;

        foreach ($additionalData['powerConsumption'] as $item) {
            if ($item['id'] == 61) {
                $pemakaianListrikPLN = [
                    'satuan' => $item['satuan'],
                    'value' => $item['value']
                ];
            }
            if($item['id'] == 62){
                $powerAllocRefinery =[
                    'satuan' => $item['satuan'],
                    'value' => $item['value']
                ];
            }
            if($item['id'] == 63){
                $powerAllocFractionation =[
                    'satuan' => $item['satuan'],
                    'value' => $item['value']
                ];
            }
            if($item['id'] == 64){
                $powerAllocOther =[
                    'satuan' => $item['satuan'],
                    'value' => $item['value']
                ];
            }
        }

        $hargaGas = [
            'satuan' => 'USD',
            'value' =>$hargaGasSetting ? $hargaGasSetting->setting_value : 0
        ];

        $biayaTagihanUSD = [
            'satuan' => 'USD',
            'value' => $incomingPertagas['value'] * $hargaGas['value']
        ];

        $averageCurrencyRate = [
            'satuan' => 'IDR',
            'value' => $currencyRates->avg('rate')
        ];

        $biayaTagihanIDR = [
            'satuan' => 'IDR',
            'value' => $biayaTagihanUSD['value'] * $averageCurrencyRate['value']
        ];

        $biayaPemakaianGas = [
            'Incoming *based on Pertagas' => $incomingPertagas,
            'Harga Gas' => $hargaGas,
            'Nilai Biaya Tagihan USD' => $biayaTagihanUSD,
            'Kurs' => $averageCurrencyRate,
            'Nilai Biaya Tagihan IDR' => $biayaTagihanIDR
        ];

        $minPemakaianGas = [
            'satuan' => 'mmbtu',
            'value' => $minPemakaianGasSetting ? $minPemakaianGasSetting->setting_value : 0
        ];

        $plusminPemakaianGas = [
            'satuan' => 'mmbtu',
            'value' => $minPemakaianGas['value'] - $incomingPertagas['value']
        ];

        $penaltyUSD = [
            'satuan' => 'USD',
            'value' => $plusminPemakaianGas['value'] * $hargaGas['value']
        ];

        $penaltyIDR = [
            'satuan' => 'USD',
            'value' => $penaltyUSD['value'] * $averageCurrencyRate['value']
        ];

        $perhitunganPenaltyGas = [
            'Incoming *based on Pertagas' => $incomingPertagas,
            'Minimum Pemakaian' => $minPemakaianGas,
            '+/(-) Pemakaian Gas' => $plusminPemakaianGas,
            'Harga Gas' => $hargaGas,
            'Nilai Biaya Penalty USD' => $penaltyUSD,
            'Kurs' => $averageCurrencyRate,
            'Nilai Biaya Penalty IDR' => $penaltyIDR
        ];

        $totalPemakaianAirM3 =
            ($outgoingSoftenerProductRef['value'] ?? 0) +
            ($outgoingSoftenerProductFrac['value'] ?? 0) +
            ($outgoingROProduct['value'] ?? 0) +
            ($outgoingOthers['value'] ?? 0) +
            ($wasteWaterEffluent['value'] ?? 0);

        $refineryAllocationAir =
            ($outgoingSoftenerProductRef['value'] ?? 0) +
            ((($outgoingROProduct['value'] ?? 0) +
            ($outgoingOthers['value'] ?? 0) +
            ($wasteWaterEffluent['value'] ?? 0)) *
            $PersenCostAllocAirRefinery / 100);

        $fractionationAllocationAir =
            ($outgoingSoftenerProductFrac['value'] ?? 0) +
            ((($outgoingROProduct['value'] ?? 0) +
            ($outgoingOthers['value'] ?? 0) +
            ($wasteWaterEffluent['value'] ?? 0)) *
            $PersenCostAllocAirFractionation / 100);

        $totalPemakaianAirAllocation = $refineryAllocationAir + $fractionationAllocationAir;
        $refinerym3     = $outgoingSoftenerProductRef['value'] ?? 0;
        $fraksinasim3   = $outgoingSoftenerProductFrac['value'] ?? 0;

        $allocationAir  = [
            'Refinery (m3)' => $refinerym3,
            'Fraksinasi (m3)' => $fraksinasim3,
            'RO' => $outgoingROProduct['value'] ?? 0,
            'Other' => $outgoingOthers['value'] ?? 0,
            'Waste' => $wasteWaterEffluent['value'] ?? 0,
            'Total Pemakaian Air (m3)' => $totalPemakaianAirM3,
            'Refinery (allocation)' => $refineryAllocationAir,
            'Fraksinasi (allocation)' => $fractionationAllocationAir,
            'Total Pemakaian Air (allocation)' => $totalPemakaianAirAllocation,
            'Result' => $totalPemakaianAirM3 - $totalPemakaianAirAllocation,
        ];


        $hpBoilerRefineryValue = $outgoingHPBoilerRefinery['value'] ?? 0;
        $mpBoiler12Value = $outgoingMPBoiler12['value'] ?? 0;
        $totalPemakaianGasmmbtu = $hpBoilerRefineryValue + $mpBoiler12Value;

        $incomingSteamValue = $incomingSteam['value'] ?? 0;
        $refinerySteamValue = $distributionToRef['value'] ?? 0;
        $fractionationSteamValue = $distributionToRef['value'] ?? 0;
        $otherSteamValue = $distributionToRef['value'] ?? 0;

        $refinerySteamRatio = ($incomingSteamValue > 0) ? ($refinerySteamValue / $incomingSteamValue) : 0;
        $fractionationSteamRatio = ($incomingSteamValue > 0) ? ($fractionationSteamValue / $incomingSteamValue) : 0;
        $otherSteamRatio = ($incomingSteamValue > 0) ? ($otherSteamValue / $incomingSteamValue) : 0;

        $refineryAllocationGas = (($refinerySteamRatio + ($otherSteamRatio * $PersenCostAllocGasRefinery)) * $mpBoiler12Value) + $hpBoilerRefineryValue;
        $fractionationAllocationGas = (($fractionationSteamRatio + ($otherSteamRatio * $PersenCostAllocGasFractionation)) * $mpBoiler12Value);

        $totalPemakaianGasAllocation = $refineryAllocationGas + $fractionationAllocationGas;

        $allocationGas = [
            'HP Boiler Refinery' => $hpBoilerRefineryValue,
            'MP Boiler 1, 2' => $mpBoiler12Value,
            'Total Pemakaian Gas (mmbtu)' => $totalPemakaianGasmmbtu,
            'Refinery (allocation)' => $refineryAllocationGas,
            'Fraksinasi (allocation)' => $fractionationAllocationGas,
            'Total Pemakaian Gas (allocation)' => $totalPemakaianGasAllocation,
            'Result' => $totalPemakaianGasmmbtu - $totalPemakaianGasAllocation,
        ];

        $pemakaianListrikPLNValue = $pemakaianListrikPLN['value'] ?? 0;
        $totalListrikKwh = $pemakaianListrikPLNValue;
        $powerAllocRefineryValue = $powerAllocRefinery['value'] ?? 0;
        $powerAllocFractionationValue = $powerAllocFractionation['value'] ?? 0;
        $powerAllocOtherValue = $powerAllocOther['value'] ?? 0;

        $totalPemakaianListrik = $powerAllocRefineryValue + $powerAllocFractionationValue + $powerAllocOtherValue;
        $selisihListrikAlloc = $pemakaianListrikPLNValue - $totalPemakaianListrik;

        $totalPowerAlloc = $powerAllocRefineryValue + $powerAllocFractionationValue;
        $refineryAllocationListrik = ($totalPowerAlloc > 0)
            ? $powerAllocRefineryValue + (($selisihListrikAlloc + $powerAllocOtherValue) * ($powerAllocRefineryValue / $totalPowerAlloc))
            : 0;

        $fractionationAllocationListrik = ($totalPowerAlloc > 0)
            ? $powerAllocFractionationValue + (($selisihListrikAlloc + $powerAllocOtherValue) * ($powerAllocFractionationValue / $totalPowerAlloc))
            : 0;

        $totalPemakaianListrikAllocation = $refineryAllocationListrik + $fractionationAllocationListrik;

        $allocationPower = [
            'Listrik' => $pemakaianListrikPLNValue,
            'Total Listrik (kwh)' => $totalListrikKwh,
            'Refinery (allocation)' => $refineryAllocationListrik,
            'Fraksinasi (allocation)' => $fractionationAllocationListrik,
            'Total Pemakaian Listrik (allocation)' => $totalPemakaianListrikAllocation,
            'Result' => $totalListrikKwh - $totalPemakaianListrikAllocation,
        ];

        $percentageRefineryGas = $totalPemakaianGasAllocation ? ($refineryAllocationGas / $totalPemakaianGasAllocation) : 0;
        $percentageRefineryAir = $totalPemakaianAirAllocation ? ($refineryAllocationAir / $totalPemakaianAirAllocation) : 0;
        $percentageRefineryListrik = $totalPemakaianListrikAllocation ? ($refineryAllocationListrik / $totalPemakaianListrikAllocation) : 0;
        $percentageFractionationGas = $totalPemakaianGasAllocation ? ($fractionationAllocationGas / $totalPemakaianGasAllocation) : 0;
        $percentageFractionationAir = $totalPemakaianAirAllocation ? ($fractionationAllocationAir / $totalPemakaianAirAllocation) : 0;
        $percentageFractionationListrik = $totalPemakaianListrikAllocation ? ($fractionationAllocationListrik / $totalPemakaianListrikAllocation) : 0;

        $alokasiBiaya = [
            'allocation' => [
                [
                    'nama' => 'Refinery',
                    'item' => [
                        [
                            'name' => 'Steam / Gas',
                            'qty' => $refineryAllocationGas,
                            'percentage' => $percentageRefineryGas * 100
                        ],
                        [
                            'name' => 'Air',
                            'qty' => $refineryAllocationAir,
                            'percentage' => $percentageRefineryAir * 100
                        ],
                        [
                            'name' => 'Listrik',
                            'qty' => $refineryAllocationListrik,
                            'percentage' => $percentageRefineryListrik * 100
                        ]
                    ]
                ],
                [
                    'nama' => 'Fraksinasi',
                    'item' => [
                        [
                            'name' => 'Steam / Gas',
                            'qty' => $fractionationAllocationGas,
                            'percentage' => $percentageFractionationGas * 100
                        ],
                        [
                            'name' => 'Air',
                            'qty' => $fractionationAllocationAir,
                            'percentage' => $percentageFractionationAir * 100
                        ],
                        [
                            'name' => 'Listrik',
                            'qty' => $fractionationAllocationListrik,
                            'percentage' => $percentageFractionationListrik * 100
                        ]
                    ]
                ]
            ]
        ];

        foreach ($laporanProduksi as &$kategori) {
            foreach ($kategori['uraian'] as &$uraian) {
                $uraian['items'] = collect($uraian['items'])->groupBy('plant.id')->map(function($items, $plantId) {
                    $totalFinalValue = $items->sum('value');

                    return [
                        'qty' => $totalFinalValue,
                        'plant' => $items->first()['plant']
                    ];
                })->values()->toArray();
            }
        }

        $settingNames = ['konversi_liter_to_kg', 'pouch_to_box_1_liter', 'pouch_to_box_2_liter', 'konversi_m_liter_to_kg'];
        $settings = Setting::whereIn('setting_name', $settingNames)->get();
        return [
            'settings' => $settings,
            'laporanProduksi' => $laporanProduksi,
            'biayaPemakaianGas' => $biayaPemakaianGas,
            'perhitunganPenaltyGas' => $perhitunganPenaltyGas,
            'Allocation' => [
                'Air' => $allocationAir,
                'Gas' => $allocationGas,
                'Listrik' => $allocationPower
            ],
            'alokasiBiaya' => $alokasiBiaya,
            'message' => $this->messageAll
        ];
    }

}
