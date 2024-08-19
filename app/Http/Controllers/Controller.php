<?php

namespace App\Http\Controllers;

use App\Models\BiayaPenyusutan;
use App\Models\cpoKpbn;
use App\Models\Debe;
use App\Models\InitialSupply;
use App\Models\LaporanProduksi;
use App\Models\LevyDutyBulky;
use App\Models\MarketRoutersBulky;
use App\Models\Setting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public $token;
    public $userData;
    public $urlDept;
    public $urlAllDept;
    public $urlUser;
    public $urlAllUser;
    // public $urlGeneralLedger;
    public $urlGeneralLedgerPosted;
    public $urlGeneralLedgerCoaPosted;
    public $urlCurrency;
    public $urlCurrencyGet;
    public $urlRateCurrency;

    private $messageFail = 'Something went wrong';
    private $messageMissing = 'Data not found in record';
    private $messageAll = 'Success to Fetch All Datas';
    private $messageSuccess = 'Success to Fetch Data';
    private $messageCreate = 'Success to Create Data';
    private $messageUpdate = 'Success to Update Data';

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->token = $request->get('user_token');
            $this->userData = $request->get('decoded');
            $this->urlDept = env('BASE_URL_PORTAL')."department/get/";
            $this->urlAllDept = env('BASE_URL_PORTAL')."department";
            $this->urlUser = env('BASE_URL_PORTAL')."user/get/";
            $this->urlAllUser = env('BASE_URL_PORTAL')."user";
            // $this->urlGeneralLedger = env('BASE_URL_ODOO')."account_move_line/index";
            $this->urlGeneralLedgerPosted = env('BASE_URL_ODOO')."account_move_line/posted";
            $this->urlGeneralLedgerCoaPosted = env('BASE_URL_ODOO')."account_move_line/coa";
            $this->urlCurrency = env('BASE_URL_ODOO')."currency/index";
            $this->urlCurrencyGet = env('BASE_URL_ODOO')."currency/get/";
            $this->urlRateCurrency = env('BASE_URL_ODOO')."currency_rate/period";
            return $next($request);
        });
    }

    public function getCurrency($id)
    {
        return Http::withHeaders([
            'Authorization' => $this->token,
        ])->get($this->urlCurrencyGet. $id)->json()['data'] ?? [];
    }

    public function getCurrencies()
    {
        return Http::withHeaders([
            'Authorization' => $this->token,
        ])->get($this->urlCurrency)->json()['data'] ?? [];
    }

    public function getRateCurrencyData($tanggal, $mata_uang)
    {
        return Http::post($this->urlRateCurrency, [
            'tanggal' => $tanggal,
            'mata_uang' => $mata_uang,
        ])->json()['data'] ?? [];
    }

    public function getGeneralLedgerData($tanggal)
    {
        return Http::withHeaders([
            'Authorization' => $this->token,
        ])->post($this->urlGeneralLedgerPosted, [
            'tanggal' => $tanggal
        ])->json()['data'] ?? [];
    }

    public function getGeneralLedgerDataWithCoa($tanggal, $coa)
    {
        $response = Http::withHeaders([
            'Authorization' => $this->token,
        ])->post($this->urlGeneralLedgerCoaPosted, [
            'tanggal' => $tanggal,
            'coa' => $coa
        ])->json();

        return $response['data'] ?? [];
    }

    public function getGeneralLedgerDataWithCoaPosted($tanggal, $coa)
    {
        $response = Http::withHeaders([
            'Authorization' => $this->token,
        ])->post($this->urlGeneralLedgerCoaPosted, [
            'tanggal' => $tanggal,
            'coa' => $coa
        ])->json();

        return $response['data'] ?? [];
    }

    public function getDepartmentData()
    {
        return Http::withHeaders([
            'Authorization' => $this->token,
        ])->get($this->urlAllDept)->json()['data'] ?? [];
    }

    public function getDepartmentById($id)
    {
        return Http::withHeaders([
            'Authorization' => $this->token,
        ])->get($this->urlDept. $id)->json()['data'] ?? [];
    }

    public function getUserData()
    {
        return Http::withHeaders([
            'Authorization' => $this->token,
        ])->get($this->urlAllUser)->json()['data'] ?? [];
    }

    public function getUserById($id)
    {
        return Http::withHeaders([
            'Authorization' => $this->token,
        ])->get($this->urlUser . $id)->json()['data'] ?? [];
    }

    public function formatLogs($logs)
    {
        return $logs->map(function ($log) {
            $user = $this->getUserById($log->user_id);
            $oldData = json_decode($log->old_data, true);
            $newData = json_decode($log->new_data, true);

            $changes = [];
            if ($log->action === 'update') {
                $changes = collect($newData)->map(function ($value, $key) use ($oldData) {
                    if ($oldData[$key] !== $value) {
                        return [
                            'old' => $oldData[$key],
                            'new' => $value,
                        ];
                    }
                })->filter();
            }

            return [
                'action' => $log->action,
                'user_name' => $user['name'],
                'changes' => $changes,
                'created_at' => $log->created_at,
            ];
        })->sortByDesc('created_at');
    }

    public function formatLogsForMultiple($logs)
    {
        $formattedLogs = $logs->map(function ($log) {
            $user = $this->getUserById($log->user_id);
            $oldData = json_decode($log->old_data, true);
            $newData = json_decode($log->new_data, true);
            return [
                'action' => $log->action,
                'user_name' => $user['name'],
                'old_data' => $oldData,
                'new_data' => $newData,
                'created_at' => $log->created_at,
            ];
        });

        $formattedLogs = $formattedLogs->sortByDesc('created_at');

        return $formattedLogs;
    }

    public function dataLaporanProduksi($year, $month){
        $data = LaporanProduksi::whereYear('tanggal', $year)
                ->whereMonth('tanggal', $month)
                ->with(['uraian.kategori', 'hargaSatuan', 'plant'])
                ->get();

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

        return $data;
    }

    public function prosesLaporanProd($data)
    {
        $groupedData = $data->groupBy(function($item) {
            return $item->uraian->kategori->id;
        });

        $laporanProduksi = [];

        foreach ($groupedData as $kategoriId => $items) {
            $kategoriName = $items->first()->uraian->kategori->nama;

            $uraianGroups = $items->groupBy(function($item) {
                return $item->uraian->id;
            });

            $uraianData = [];
            foreach ($uraianGroups as $uraianId => $group) {
                $uraianName = $group->first()->uraian->nama;
                $totalQty = $group->sum('value');
                $totalFinalValue = $group->sum(function($item) {
                    return $item->value * $item->hargaSatuan->value;
                });

                $itemsData = $group->sortBy('tanggal')->map(function($item) {
                    return [
                        'id' => $item->id,
                        'id_plant' => $item->id_plant,
                        'id_uraian' => $item->id_uraian,
                        'tanggal' => $item->tanggal,
                        'value' => $item->value,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                        'id_harga_satuan' => $item->id_harga_satuan,
                        'harga_satuan' => $item->hargaSatuan,
                        'plant' => $item->plant,
                    ];
                })->values();

                $uraianData[] = [
                    'id' => $uraianId,
                    'id_category' => $group->first()->uraian->id_category,
                    'nama' => $uraianName,
                    'satuan' => $group->first()->uraian->satuan,
                    'total_qty' => $totalQty,
                    'total_final_value' => $totalFinalValue,
                    'items' => $itemsData
                ];
            }

            $uraianData = collect($uraianData)->sortBy('id')->values()->toArray();

            $laporanProduksi[] = [
                'id' => $kategoriId,
                'nama' => $kategoriName,
                'uraian' => $uraianData
            ];
        }

        return $laporanProduksi;
    }

    public function settingGet($setting_name)
    {
        $setting = Setting::where('setting_name', $setting_name)->first();

        return $setting;
    }

    public function explodeSettingIds($ids)
    {
        $exploded = explode(', ', $ids);

        return $exploded;
    }

    public function processUraianData($laporanProduksi, $uraianGasIds, $uraianWaterIds, $uraianSteamIds, $uraianPowerIds)
    {
        $uraianIdsMapping = [
            'gasConsumption' => $this->explodeSettingIds($uraianGasIds->setting_value),
            'waterConsumption' => $this->explodeSettingIds($uraianWaterIds->setting_value),
            'steamConsumption' => $this->explodeSettingIds($uraianSteamIds->setting_value),
            'powerConsumption' => $this->explodeSettingIds($uraianPowerIds->setting_value),
        ];

        $additionalData = [
            'powerConsumption' => [],
            'gasConsumption' => [],
            'waterConsumption' => [],
            'steamConsumption' => [],
        ];

        foreach ($laporanProduksi as $kategori) {
            foreach ($kategori['uraian'] as $uraian) {
                foreach ($uraianIdsMapping as $category => $idsArray) {
                    if (in_array($uraian['id'], $idsArray)) {
                        $additionalData[$category][] = [
                            'id' => $uraian['id'],
                            'nama' => $uraian['nama'],
                            'satuan' => $uraian['satuan'],
                            'value' => $uraian['total_qty']
                        ];
                        break;
                    }
                }
            }
        }
        return $additionalData;
    }

    public function processPenyusutan(Request $request)
    {
        $recap = $this->processRecapData($request);

        $subQuery = BiayaPenyusutan::select('alokasi_id', DB::raw('MAX(tanggal) as latest_date'))
                ->groupBy('alokasi_id');

        $penyusutan = BiayaPenyusutan::with('allocation')
            ->joinSub($subQuery, 'latest', function($join) {
                $join->on('biaya_penyusutan.alokasi_id', '=', 'latest.alokasi_id')
                    ->on('biaya_penyusutan.tanggal', '=', 'latest.latest_date');
            })
            ->get();

        $unitQty = [];
        $unitPercent = [];
        $totalUnitQty = 0;

        foreach ($penyusutan as $item) {
            $totalUnitQty += $item->value;
        }

        foreach ($penyusutan as $item) {
            $unitQty[] = [
                'name' => $item->allocation->nama,
                'value' => $item->value,
            ];

            $unitPercent[] = [
                'name' => $item->allocation->nama,
                'value' => $totalUnitQty ? ($item->value / $totalUnitQty) * 100 : 0,
            ];
        }

        $biayaPenyusutanUnit = [
            'name' => 'Unit',
            'columns' => [
                [
                    'name' => 'Qty',
                    'total' => $totalUnitQty,
                    'alokasi' => $unitQty,
                ],
                [
                    'name' => '%',
                    'total' => 100,
                    'alokasi' => $unitPercent,
                ]
            ]
        ];

        $avgPrice = $this->processQtyBebanProduksi($request);

        $totalQtyPFAD = $avgPrice['qtyBebanProduksi']['pfad'] ?? 0;
        $totalQtyRBDPO = $avgPrice['qtyBebanProduksi']['rbdpo'] ?? 0;
        $refineryAllProduction = $totalQtyPFAD + $totalQtyRBDPO;



        $fraksinasiTypes = [
            'Fraksinasi (IV-56)' => 0,
            'Fraksinasi (IV-57)' => 0,
            'Fraksinasi (IV-58)' => 0,
            'Fraksinasi (IV-60)' => 0
        ];

        foreach ($recap['laporanProduksi'] as $report) {
            if (array_key_exists($report['nama'], $fraksinasiTypes)) {
                foreach ($report['uraian'] as $uraian) {
                    if ($uraian['nama'] === 'RBDPO (Olah)') {
                        $fraksinasiTypes[$report['nama']] += $uraian['total_qty'];
                    }
                }
            }
        }

        $productionFraksinasi56 = $fraksinasiTypes['Fraksinasi (IV-56)'] ?? 0;
        $productionFraksinasi57 = $fraksinasiTypes['Fraksinasi (IV-57)'] ?? 0;
        $productionFraksinasi58 = $fraksinasiTypes['Fraksinasi (IV-58)'] ?? 0;
        $productionFraksinasi60 = $fraksinasiTypes['Fraksinasi (IV-60)'] ?? 0;

        $productionPackaging56 = $avgPrice['qtyBebanProduksi']['kemasanMinyakita'] ?? 0;
        $productionPackaging57 = $avgPrice['qtyBebanProduksi']['kemasanINL'] ?? 0;
        $productionPackaging58 = $avgPrice['qtyBebanProduksi']['kemasan58'] ?? 0;
        $productionPackaging60Salvaco = $avgPrice['qtyBebanProduksi']['kemasanSalvaco'] ?? 0;
        $productionPackaging60Nusakita = $avgPrice['qtyBebanProduksi']['kemasanNusakita'] ?? 0;
        $productionPackaging60 = $productionPackaging60Salvaco + $productionPackaging60Nusakita;

        $packagingAllProduction = $productionPackaging56 + $productionPackaging57 + $productionPackaging58 + $productionPackaging60;

        $fraksinasiAllProduction = $productionFraksinasi56 + $productionFraksinasi57 + $productionFraksinasi58 + $productionFraksinasi60;
        $fraksinasiMinusPackagingAllProduction = $productionFraksinasi56 + $productionFraksinasi57 + $productionFraksinasi58 + $productionFraksinasi60 - $packagingAllProduction;

        $totalAllProduction = $refineryAllProduction + $packagingAllProduction + $fraksinasiMinusPackagingAllProduction;

        $refineryAllProductionPercentage = $totalAllProduction ? ($refineryAllProduction / $totalAllProduction) * 100 : 0;
        $fraksinasiMinusPackagingAllProductionPercentage = $totalAllProduction ? ($fraksinasiMinusPackagingAllProduction / $totalAllProduction) * 100 : 0;
        $packagingAllProductionPercentage = $totalAllProduction ? ($packagingAllProduction / $totalAllProduction) * 100 : 0;

        $totalAllProductionPercentage = $refineryAllProductionPercentage + $fraksinasiMinusPackagingAllProductionPercentage + $packagingAllProductionPercentage;

        $packagingTotalProduction = $packagingAllProduction;
        $fraksinasiTotalProduction = $fraksinasiAllProduction;

        $totalProductionResult = [
            'totalAllProduction' => $totalAllProduction,
            'totalAllProductionPercentage' => $totalAllProductionPercentage,
            'production' => [
                [
                    'name' => 'Refinery',
                    'total' => $refineryAllProduction,
                    'percentage' => $refineryAllProductionPercentage,
                    'items' => null,
                ],
                [
                    'name' => 'Fraksinasi',
                    'total' => $fraksinasiMinusPackagingAllProduction,
                    'percentage' => $fraksinasiMinusPackagingAllProductionPercentage,
                    'items' => [
                        [
                            'name' => 'RBD Olein IV-56',
                            'value' => $productionFraksinasi56,
                        ],
                        [
                            'name' => 'RBD Olein IV-57',
                            'value' => $productionFraksinasi57,
                        ],
                        [
                            'name' => 'RBD Olein IV-58',
                            'value' => $productionFraksinasi58,
                        ],
                        [
                            'name' => 'RBD Olein IV-60',
                            'value' => $productionFraksinasi60,
                        ],
                    ],
                ],
                [
                    'name' => 'Packaging',
                    'total' => $packagingAllProduction,
                    'percentage' => $packagingAllProductionPercentage,
                    'items' => [
                        [
                            'name' => 'RBD Olein IV-56',
                            'value' => $productionPackaging56,
                        ],
                        [
                            'name' => 'RBD Olein IV-57',
                            'value' => $productionPackaging57,
                        ],
                        [
                            'name' => 'RBD Olein IV-58',
                            'value' => $productionPackaging58,
                        ],
                        [
                            'name' => 'RBD Olein IV-60',
                            'value' => $productionPackaging60,
                        ],
                    ],
                ],
            ],
        ];

        $auxiliaryPercentageRefinery = $refineryAllProductionPercentage;
        $auxiliaryPercentageFraksinasi = $fraksinasiMinusPackagingAllProductionPercentage;
        $auxiliaryPercentagePackaging = $packagingAllProductionPercentage;

        $refineryPenyusutanUnitQty = 0;
        $refineryPenyusutanUnitPercent = 0;
        $fraksinasiPenyusutanUnitQty = 0;
        $fraksinasiPenyusutanUnitPercent = 0;
        $packagingPenyusutanUnitQty = 0;
        $packagingPenyusutanUnitPercent = 0;
        $auxiliaryPenyusutanUnitQty = 0;
        $auxiliaryPenyusutanUnitPercent = 0;

        // Assuming 'biayaPenyusutanUnit' is already populated
        foreach ($biayaPenyusutanUnit['columns'] as $column) {
            if ($column['name'] === 'Qty') {
                foreach ($column['alokasi'] as $alokasi) {
                    if ($alokasi['name'] === 'Auxiliary') {
                        $auxiliaryPenyusutanUnitQty = $alokasi['value'];
                    }else if($alokasi['name'] === 'Refinery'){
                        $refineryPenyusutanUnitQty = $alokasi['value'];
                    }else if($alokasi['name'] === 'Fraksinasi'){
                        $fraksinasiPenyusutanUnitQty = $alokasi['value'];
                    }else if($alokasi['name'] === 'Packaging'){
                        $packagingPenyusutanUnitQty = $alokasi['value'];
                    }
                }
            } elseif ($column['name'] === '%') {
                foreach ($column['alokasi'] as $alokasi) {
                    if ($alokasi['name'] === 'Auxiliary') {
                        $auxiliaryPenyusutanUnitPercent = $alokasi['value'];
                    }else if($alokasi['name'] === 'Refinery'){
                        $refineryPenyusutanUnitPercent = $alokasi['value'];
                    }else if($alokasi['name'] === 'Fraksinasi'){
                        $fraksinasiPenyusutanUnitPercent = $alokasi['value'];
                    }else if($alokasi['name'] === 'Packaging'){
                        $packagingPenyusutanUnitPercent = $alokasi['value'];
                    }
                }
            }
        }

        $auxiliaryQtyRefinery = $auxiliaryPenyusutanUnitQty * ($auxiliaryPercentageRefinery / 100);
        $auxiliaryQtyFraksinasi = $auxiliaryPenyusutanUnitQty * ($auxiliaryPercentageFraksinasi / 100);
        $auxiliaryQtyPackaging = $auxiliaryPenyusutanUnitQty * ($auxiliaryPercentagePackaging / 100);

        $biayaPenyusutanAuxiliary = [
            'name' => 'Auxiliary',
            'columns' => [
                [
                    'name' => 'Qty',
                    'total' => $auxiliaryQtyRefinery + $auxiliaryQtyFraksinasi + $auxiliaryQtyPackaging,
                    'alokasi' => [
                        [
                            'name' => 'Refinery',
                            'value' => $auxiliaryQtyRefinery,
                        ],
                        [
                            'name' => 'Fraksinasi',
                            'value' => $auxiliaryQtyFraksinasi,
                        ],
                        [
                            'name' => 'Auxiliary',
                            'value' => 0,
                        ],
                        [
                            'name' => 'Packaging',
                            'value' => $auxiliaryQtyPackaging,
                        ],
                    ],
                ],
                [
                    'name' => '%',
                    'total' =>  $auxiliaryPercentageRefinery+$auxiliaryPercentageFraksinasi+$auxiliaryPercentagePackaging,
                    'alokasi' => [
                        [
                            'name' => 'Refinery',
                            'value' => $auxiliaryPercentageRefinery,
                        ],
                        [
                            'name' => 'Fraksinasi',
                            'value' => $auxiliaryPercentageFraksinasi,
                        ],
                        [
                            'name' => 'Auxiliary',
                            'value' => 0,
                        ],
                        [
                            'name' => 'Packaging',
                            'value' => $auxiliaryPercentagePackaging,
                        ],
                    ],
                ]
            ]
        ];

        $allocationQtyRefinery = $refineryPenyusutanUnitQty + $auxiliaryQtyRefinery;
        $allocationQtyFraksinasi = $fraksinasiPenyusutanUnitQty + $auxiliaryQtyFraksinasi;
        $allocationQtyPackaging = $packagingPenyusutanUnitQty + $auxiliaryQtyPackaging;

        $totalAllocationQty = $allocationQtyRefinery + $allocationQtyFraksinasi + $allocationQtyPackaging;

        $allocationPercentageRefinery = $totalAllocationQty > 0 ? $allocationQtyRefinery / $totalAllocationQty : 0;
        $allocationPercentageFraksinasi = $totalAllocationQty > 0 ? $allocationQtyFraksinasi / $totalAllocationQty : 0;
        $allocationPercentagePackaging = $totalAllocationQty > 0 ? $allocationQtyPackaging / $totalAllocationQty : 0;


        $biayaPenyusutanAllocation = [
            'name' => 'Allocation',
            'columns' => [
                [
                    'name' => 'Qty',
                    'total' => $totalAllocationQty,
                    'alokasi' => [
                        [
                            'name' => 'Refinery',
                            'value' => $allocationQtyRefinery,
                        ],
                        [
                            'name' => 'Fraksinasi',
                            'value' => $allocationQtyFraksinasi,
                        ],
                        [
                            'name' => 'Auxiliary',
                            'value' => 0,
                        ],
                        [
                            'name' => 'Packaging',
                            'value' => $allocationQtyPackaging,
                        ],
                    ],
                ],
                [
                    'name' => '%',
                    'total' =>  $allocationPercentageRefinery+$allocationPercentageFraksinasi+$allocationPercentagePackaging,
                    'alokasi' => [
                        [
                            'name' => 'Refinery',
                            'value' => $allocationPercentageRefinery,
                        ],
                        [
                            'name' => 'Fraksinasi',
                            'value' => $allocationPercentageFraksinasi,
                        ],
                        [
                            'name' => 'Auxiliary',
                            'value' => 0,
                        ],
                        [
                            'name' => 'Packaging',
                            'value' => $allocationPercentagePackaging,
                        ],
                    ],
                ]
            ]
        ];

        $percentage56Packaging = $packagingTotalProduction ? ($productionPackaging56 / $packagingTotalProduction) * 100 : 0;
        $percentage57Packaging = $packagingTotalProduction ? ($productionPackaging57 / $packagingTotalProduction) * 100 : 0;
        $percentage58Packaging = $packagingTotalProduction ? ($productionPackaging58 / $packagingTotalProduction) * 100 : 0;
        $percentage60Packaging = $packagingTotalProduction ? ($productionPackaging60 / $packagingTotalProduction) * 100 : 0;
        $totalPercentagePackaging = $percentage56Packaging + $percentage57Packaging + $percentage58Packaging + $percentage60Packaging;

        $percentage56Fraksinasi = $fraksinasiTotalProduction ? ($productionFraksinasi56 / $fraksinasiTotalProduction) * 100 : 0;
        $percentage57Fraksinasi = $fraksinasiTotalProduction ? ($productionFraksinasi57 / $fraksinasiTotalProduction) * 100 : 0;
        $percentage58Fraksinasi = $fraksinasiTotalProduction ? ($productionFraksinasi58 / $fraksinasiTotalProduction) * 100 : 0;
        $percentage60Fraksinasi = $fraksinasiTotalProduction ? ($productionFraksinasi60 / $fraksinasiTotalProduction) * 100 : 0;
        $totalPercentageFraksinasi = $percentage56Fraksinasi + $percentage57Fraksinasi + $percentage58Fraksinasi + $percentage60Fraksinasi;

        $packagingNFraksinasi = [
            'production' => [
                [
                    'name' => 'Fraksinasi',
                    'totalpercentage' => $totalPercentageFraksinasi,
                    'items' => [
                        [
                            'name' => 'RBD Olein IV-56',
                            'percentage' => $percentage56Fraksinasi,
                        ],
                        [
                            'name' => 'RBD Olein IV-57',
                            'percentage' => $percentage57Fraksinasi,
                        ],
                        [
                            'name' => 'RBD Olein IV-58',
                            'percentage' => $percentage58Fraksinasi,
                        ],
                        [
                            'name' => 'RBD Olein IV-60',
                            'percentage' => $percentage60Fraksinasi,
                        ],
                    ],
                ],
                [
                    'name' => 'Packaging',
                    'totalpercentage' => $totalPercentagePackaging,
                    'items' => [
                        [
                            'name' => 'RBD Olein IV-56',
                            'percentage' => $percentage56Packaging,
                        ],
                        [
                            'name' => 'RBD Olein IV-57',
                            'percentage' => $percentage57Packaging,
                        ],
                        [
                            'name' => 'RBD Olein IV-58',
                            'percentage' => $percentage58Packaging,
                        ],
                        [
                            'name' => 'RBD Olein IV-60',
                            'percentage' => $percentage60Packaging,
                        ],
                    ],
                ],
            ],
        ];

        $produksiAllValue = $refineryAllProduction + $fraksinasiTotalProduction;
        $produksiAllRefineryPercent = $produksiAllValue ? ($refineryAllProduction / $produksiAllValue) * 100 : 0;
        $produksiAllFraksinasiPercent = $produksiAllValue ? ($fraksinasiTotalProduction / $produksiAllValue) * 100 : 0;
        $produksiAllPercent = $produksiAllRefineryPercent + $produksiAllFraksinasiPercent;

        $produksiAll = [
            'production' => [
                [
                    'name' => 'Produksi All',
                    'totalValue' => $produksiAllValue,
                    'totalPercentage' => $produksiAllPercent,
                    'items' => [
                        [
                            'name' => 'Refinery',
                            'value' => $refineryAllProduction,
                            'percentage' => $produksiAllRefineryPercent,
                        ],
                        [
                            'name' => 'Fraksinasi',
                            'value' => $fraksinasiTotalProduction,
                            'percentage' => $produksiAllFraksinasiPercent,
                        ]
                    ],
                ],
            ],
        ];

        return [
            'recap' => $recap,
            'biayaPenyusutanUnit' => $biayaPenyusutanUnit,
            'biayaPenyusutanAuxiliary' => $biayaPenyusutanAuxiliary,
            'biayaPenyusutanAllocation' => $biayaPenyusutanAllocation,
            'totalProduction' => $totalProductionResult,
            'packagingNFraksinasi' => $packagingNFraksinasi,
            'produksiAll' => $produksiAll,
        ];
    }

    public function processRecapData(Request $request)
    {
        $mata_uang = 'USD';
        $tanggal = Carbon::parse($request->tanggal);
        $year = $tanggal->year;
        $month = $tanggal->month;

        $data = $this->dataLaporanProduksi($year, $month);

        $laporanProduksi = $this->prosesLaporanProd($data);

        $hargaGasSetting = $this->settingGet('harga_gas');
        $minPemakaianGasSetting = $this->settingGet('minimum_pemakaian_gas');
        $uraianGasIds = $this->settingGet('id_uraian_gas');
        $uraianWaterIds = $this->settingGet('id_uraian_water');
        $uraianSteamIds = $this->settingGet('id_uraian_steam');
        $uraianPowerIds = $this->settingGet('id_uraian_listrik');

        $settingPersenCostAllocAirRefinery = $this->settingGet('persen_cost_alloc_air_refinery');
        $PersenCostAllocAirRefinery = $settingPersenCostAllocAirRefinery->setting_value;
        $PersenCostAllocAirFractionation = 100 - $PersenCostAllocAirRefinery;

        $settingPersenCostAllocGasRefinery = $this->settingGet('persen_cost_alloc_air_refinery');
        $PersenCostAllocGasRefinery = $settingPersenCostAllocGasRefinery->setting_value;
        $PersenCostAllocGasFractionation = 100 - $PersenCostAllocGasRefinery;

        $currencyRates = collect($this->getRateCurrencyData($tanggal, $mata_uang));

        $additionalData = $this->processUraianData($laporanProduksi, $uraianGasIds, $uraianWaterIds, $uraianSteamIds, $uraianPowerIds);

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
            'incomingBasedOnPertagas' => $incomingPertagas,
            'hargaGas' => $hargaGas,
            'nilaiBiayaTagihanUSD' => $biayaTagihanUSD,
            'Kurs' => $averageCurrencyRate,
            'nilaiBiayaTagihanIDR' => $biayaTagihanIDR
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
            'incomingBasedOnPertagas' => $incomingPertagas,
            'minimumPemakaian' => $minPemakaianGas,
            'plusMinusPemakaianGas' => $plusminPemakaianGas,
            'hargaGas' => $hargaGas,
            'nilaiBiayaPenaltyUSD' => $penaltyUSD,
            'Kurs' => $averageCurrencyRate,
            'nilaiBiayaPenaltyIDR' => $penaltyIDR
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
            'refinery_m3' => $refinerym3,
            'fraksinasi_m3' => $fraksinasim3,
            'ro' => $outgoingROProduct['value'] ?? 0,
            'other' => $outgoingOthers['value'] ?? 0,
            'waste' => $wasteWaterEffluent['value'] ?? 0,
            'totalPemakaianAir_m3' => $totalPemakaianAirM3,
            'refinery_allocation' => $refineryAllocationAir,
            'fraksinasi_allocation' => $fractionationAllocationAir,
            'totalPemakaianAir_allocation' => $totalPemakaianAirAllocation,
            'result' => $totalPemakaianAirM3 - $totalPemakaianAirAllocation,
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
            'hpBoilerRefinery' => $hpBoilerRefineryValue,
            'mpBoiler12' => $mpBoiler12Value,
            'totalPemakaianGas_mmbtu' => $totalPemakaianGasmmbtu,
            'refinery_allocation' => $refineryAllocationGas,
            'fraksinasi_allocation' => $fractionationAllocationGas,
            'totalPemakaianGas_allocation' => $totalPemakaianGasAllocation,
            'result' => $totalPemakaianGasmmbtu - $totalPemakaianGasAllocation,
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
            'listrik' => $pemakaianListrikPLNValue,
            'totalListrik_kwh' => $totalListrikKwh,
            'refinery_allocation' => $refineryAllocationListrik,
            'fraksinasi_allocation' => $fractionationAllocationListrik,
            'totalPemakaianListrik_allocation' => $totalPemakaianListrikAllocation,
            'result' => $totalListrikKwh - $totalPemakaianListrikAllocation,
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
            // 'biayaPenyusutan' => $biayaPenyusutanUnit,
            'message' => $this->messageAll
        ];
    }

    public function processProCost(Request $request)
    {
        $tanggal = $request->tanggal;

        $data = $this->fetchDataMarket($tanggal);

        if ($data['dataMRouters']->isEmpty() || $data['dataLDuty']->isEmpty()) {
            return [
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
        $laporanProduksi = $this->processRecapData($request);

        $produksiRefineryData = $this->generateProduksiRefinery($laporanProduksi, $averages);
        $produksiFraksinasiIV56Data = $this->generateProduksiFraksinasiIV56($laporanProduksi, $averages);
        $produksiFraksinasiIV57Data = $this->generateProduksiFraksinasiIV57($laporanProduksi, $averages);
        $produksiFraksinasiIV58Data = $this->generateProduksiFraksinasiIV58($laporanProduksi, $averages);
        $produksiFraksinasiIV60Data = $this->generateProduksiFraksinasiIV60($laporanProduksi, $averages);

        return [
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

    public function generateProduksiFraksinasiIV60($laporanProduksi, $averages){
        $rbdpoOlahIV60Qty = $this->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-60)', 'RBDPO (Olah)');

        $rbdOleinIv60Qty = $this->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-60)', 'RBD Olein IV 60 (Produksi)');
        $rbdOleinIv60Rendement = $rbdOleinIv60Qty != 0 ? $rbdOleinIv60Qty / $rbdpoOlahIV60Qty : 0;
        $rbdOleinIv60RendementPercentage = $rbdOleinIv60Rendement * 100;

        $rbdStearinQty = $this->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-60)', 'RBD Stearin (Produksi)');
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
                            'name' => 'RBDOlein IV-60',
                            'value' => $proporsiBiayaPersenRbdOleinIv60,
                        ],
                        [
                            'name' => 'RBDStearin',
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

    public function generateProduksiFraksinasiIV58($laporanProduksi, $averages){
        $rbdpoOlahIV58Qty = $this->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-58)', 'RBDPO (Olah)');

        $rbdOleinIv58Qty = $this->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-58)', 'RBD Olein IV 58 (Produksi)');
        $rbdOleinIv58Rendement = $rbdOleinIv58Qty != 0 ? $rbdOleinIv58Qty / $rbdpoOlahIV58Qty : 0;
        $rbdOleinIv58RendementPercentage = $rbdOleinIv58Rendement * 100;

        $rbdStearinQty = $this->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-58)', 'RBD Stearin (Produksi)');
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
                            'name' => 'RBDOlein IV-58',
                            'value' => $proporsiBiayaPersenRbdOleinIv58,
                        ],
                        [
                            'name' => 'RBDStearin',
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

    public function generateProduksiFraksinasiIV57($laporanProduksi, $averages){
        $rbdpoOlahIV57Qty = $this->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-57)', 'RBDPO (Olah)');

        $rbdOleinIv57Qty = $this->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-57)', 'RBD Olein IV 57 (Produksi)');
        $rbdOleinIv57Rendement = $rbdOleinIv57Qty != 0 ? $rbdOleinIv57Qty / $rbdpoOlahIV57Qty : 0;
        $rbdOleinIv57RendementPercentage = $rbdOleinIv57Rendement * 100;

        $rbdStearinQty = $this->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-57)', 'RBD Stearin (Produksi)');
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
                            'name' => 'RBDOlein IV-57',
                            'value' => $proporsiBiayaPersenRbdOleinIv57,
                        ],
                        [
                            'name' => 'RBDStearin',
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

    public function generateProduksiFraksinasiIV56($laporanProduksi, $averages){
        $rbdpoOlahIV56Qty = $this->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-56)', 'RBDPO (Olah)');
        $rbdOleinIv56Qty = $this->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-56)', 'RBD Olein IV 56 (Produksi)');
        $rbdOleinIv56Rendement = $rbdOleinIv56Qty != 0 ? $rbdOleinIv56Qty / $rbdpoOlahIV56Qty : 0;
        $rbdOleinIv56RendementPercentage = $rbdOleinIv56Rendement * 100;

        $rbdStearinQty = $this->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-56)', 'RBD Stearin (Produksi)');
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
                            'name' => 'RBDOlein IV-56',
                            'value' => $proporsiBiayaPersenRbdOleinIv56,
                        ],
                        [
                            'name' => 'RBDStearin',
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

    public function generateProduksiRefinery($laporanProduksi, $averages)
    {
        $cpoConsumeQty = $this->getTotalQty($laporanProduksi['laporanProduksi'], 'Refinery', 'CPO (Olah)');

        $rbdpoQty = $this->getTotalQty($laporanProduksi['laporanProduksi'], 'Refinery', 'RBDPO (Produksi)');
        $rbdpoRendement = $cpoConsumeQty != 0 ? $rbdpoQty / $cpoConsumeQty : 0;
        $rbdpoRendementPercentage = $rbdpoRendement * 100;

        $pfadQty = $this->getTotalQty($laporanProduksi['laporanProduksi'], 'Refinery', 'PFAD (Produksi)');
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

    public function generateCostOutput($categoryName, $data, $totalQty, $proportionData = [])
    {
        $totalValue = 0;
        $items = array_map(function($dataItem) use ($totalQty, $proportionData, &$totalValue) {
            $proportion = 100;
            $proportion2 = 100; // Default proportion2 value

            // Find the proportion and proportion2 in proportionData
            foreach ($proportionData as $proportionItem) {
                if ($proportionItem['nama'] === $dataItem['nama']) {
                    $proportion = $proportionItem['proportion'];
                    $proportion2 = isset($proportionItem['proportion2']) ? $proportionItem['proportion2'] : 1;
                    break;
                }
            }

            // Calculate the item total value
            $itemTotalValue = $dataItem['result'] * ($proportion / 100) * ($proportion2 / 100);

            $totalValue += $itemTotalValue;

            return [
                'name' => $dataItem['nama'],
                'proportion' => $proportion,
                'proportion2' => $proportion2, // Include proportion2 in the return data
                'value' => $dataItem['result'],
                'totalValue' => $itemTotalValue,
                'rpPerKg' => $totalQty != 0 ? $itemTotalValue / $totalQty : 0,
            ];
        }, $data['data']->toArray());

        $totalRpPerKg = $totalQty != 0 ? $totalValue / $totalQty : 0;

        return [
            'cost' => [
                [
                    'nama' => $categoryName,
                    'totalValue' => $totalValue,
                    'totalRpPerKg' => $totalRpPerKg,
                    'item' => $items
                ]
            ]
        ];
    }

    public function getTotalQty($laporanProduksi, $namaItem, $namaUraian)
    {
        $totalQty = 0;

        foreach ($laporanProduksi as $item) {
            if (isset($item['nama']) && $item['nama'] === $namaItem) {
                if (isset($item['uraian']) && is_array($item['uraian'])) {
                    foreach ($item['uraian'] as $uraian) {
                        if (isset($uraian['nama']) && $uraian['nama'] === $namaUraian) {
                            $totalQty += isset($uraian['total_qty']) ? (float) $uraian['total_qty'] : 0;
                        }
                    }
                }
            }
        }

        return $totalQty;
    }

    public function costingHppFraksinasiIv56($laporanProduksi, $alokasiCost, $proporPercentFrak56, $proporPercentFrak56PlusPackaging, $konversiLiterToKg, $dataDirectFrak56, $dataInDirectFrak56, $dataPackagingCostFrak56)
    {
        $rbdpoConsumeQty = $this->getTotalQty($laporanProduksi['recap']['laporanProduksi'], 'Fraksinasi (IV-56)', 'RBDPO (Olah)');
        $rbdOleinIv56Qty = $this->getTotalQty($laporanProduksi['recap']['laporanProduksi'], 'Fraksinasi (IV-56)', 'RBD Olein IV 56 (Produksi)');
        $rbdStearinQty = $this->getTotalQty($laporanProduksi['recap']['laporanProduksi'], 'Fraksinasi (IV-56)', 'RBD Stearin (Produksi)');
        $oleinConsumeQty = $this->getTotalQty($laporanProduksi['recap']['laporanProduksi'], 'Packaging (Minyakita)', 'Olein IV 56 Consume');

        $rbdOleinIv56RendementPercentage = $rbdpoConsumeQty != 0 ? ($rbdOleinIv56Qty / $rbdpoConsumeQty) * 100 : 0;
        $rbdStearinRendementPercentage = $rbdpoConsumeQty != 0 ? ($rbdStearinQty / $rbdpoConsumeQty) * 100 : 0;
        $cartonMinyakita1LProportion = $konversiLiterToKg;
        $cartonMinyakita2LProportion = $konversiLiterToKg;

        $cartonMinyakita1LQty = $this->getTotalQty($laporanProduksi['recap']['laporanProduksi'], 'Packaging (Minyakita)', 'Carton Minyakita 1 Liter');
        $cartonMinyakita2LQty = $this->getTotalQty($laporanProduksi['recap']['laporanProduksi'], 'Packaging (Minyakita)', 'Carton Minyakita 2 Liter');
        $cartonMinyakitaQty = $cartonMinyakita1LQty + $cartonMinyakita2LQty;
        $cartonMinyakita1LPercent = $cartonMinyakitaQty != 0 ? ($cartonMinyakita1LQty / $cartonMinyakitaQty) * 100 : 0;
        $cartonMinyakita2LPercent = $cartonMinyakitaQty != 0 ? ($cartonMinyakita2LQty / $cartonMinyakitaQty) * 100 : 0;
        $cartonMinyakitaTotalQty = $cartonMinyakitaQty*$konversiLiterToKg;
        $additionalMinyakita = abs($oleinConsumeQty - $cartonMinyakitaTotalQty);    // abs = alwasy positive
        $minyakita1LTotalQty = ($cartonMinyakita1LQty*$cartonMinyakita1LProportion)+($additionalMinyakita*($cartonMinyakita1LPercent/100));
        $minyakita2LTotalQty = ($cartonMinyakita2LQty*$cartonMinyakita2LProportion)+($additionalMinyakita*($cartonMinyakita2LPercent/100));
        $totalMinyakitaQty = $minyakita1LTotalQty + $minyakita2LTotalQty;
        $minyakita1LRendementPercentage = $totalMinyakitaQty != 0 ? ($minyakita1LTotalQty / $totalMinyakitaQty) * 100 : 0;
        $minyakita2LRendementPercentage = $totalMinyakitaQty != 0 ? ($minyakita2LTotalQty / $totalMinyakitaQty) * 100 : 0;

        $produksiAll = $laporanProduksi['produksiAll'];
        $produksiAllFraksinasiPercentage = 0;

        if (isset($produksiAll['production']) && is_array($produksiAll['production'])) {
            foreach ($produksiAll['production'] as $production) {
                if (isset($production['items']) && is_array($production['items'])) {
                    foreach ($production['items'] as $item) {
                        if ($item['name'] === 'Fraksinasi') {
                            $produksiAllFraksinasiPercentage = $item['percentage'];
                        }
                    }
                }
            }
        }

        $penyusutanAllocation = $laporanProduksi['biayaPenyusutanAllocation'];
        $penyusutanAllocationFraksinasiPercentage = 0;

        foreach ($penyusutanAllocation['columns'] as $column) {
            if ($column['name'] === '%') {
                foreach ($column['alokasi'] as $alokasi) {
                    if ($alokasi['name'] === 'Fraksinasi') {
                        $penyusutanAllocationFraksinasiPercentage = $alokasi['value'];
                    }
                }
            }
        }

        $bahanBakarProportionFrak56 = $alokasiCost['Fraksinasi']['gasPercentage'] ?? 0;
        $othersProportionFrak56 = $produksiAllFraksinasiPercentage;
        $analisaLabProportionFrak56 = $othersProportionFrak56;
        $listrikProportionFrak56 = $alokasiCost['Fraksinasi']['listrikPercentage'] ?? 0;
        $airProportionFrak56 = $alokasiCost['Fraksinasi']['airPercentage'] ?? 0;
        $gajiPimpinanProportionFrak56 = $othersProportionFrak56 ?? 0;
        $gajiPelaksanaProportionFrak56 = $othersProportionFrak56 ?? 0;
        $asuransiPabrikProportionFrak56 = $othersProportionFrak56 ?? 0;
        $bengkelProportionFrak56 = $othersProportionFrak56 ?? 0;
        $depresiasiProportionFrak56 = $penyusutanAllocationFraksinasiPercentage ?? 0;

        $proportionDirectFrak56 = [
            [
                'nama' => 'Bahan Bakar',
                'proportion' => $bahanBakarProportionFrak56,
                'proportion2' => $proporPercentFrak56,
            ],
            [
                'nama' => 'Others',
                'proportion' => $othersProportionFrak56,
                'proportion2' => $proporPercentFrak56,
            ],
            [
                'nama' => 'Biaya Analisa & Laboratorium',
                'proportion' => $analisaLabProportionFrak56,
                'proportion2' => $proporPercentFrak56,
            ],
            [
                'nama' => 'Biaya Listrik',
                'proportion' => $listrikProportionFrak56,
                'proportion2' => $proporPercentFrak56,
            ],
            [
                'nama' => 'Biaya Air',
                'proportion' => $airProportionFrak56,
                'proportion2' => $proporPercentFrak56,
            ],
        ];

        $proportionInDirectFrak56 = [
            [
                'nama' => 'Gaji, Tunjangan & Biaya Sosial Karyawan Pimpinan',
                'proportion' => $gajiPimpinanProportionFrak56,
                'proportion2' => $proporPercentFrak56,
            ],
            [
                'nama' => 'Gaji, Tunjangan & Biaya Sosial Karyawan Pelaksana',
                'proportion' => $gajiPelaksanaProportionFrak56,
                'proportion2' => $proporPercentFrak56,
            ],
            [
                'nama' => 'Biaya Assuransi Pabrik',
                'proportion' => $asuransiPabrikProportionFrak56,
                'proportion2' => $proporPercentFrak56,
            ],
            [
                'nama' => 'Biaya Bengkel & Pemeliharaan',
                'proportion' => $bengkelProportionFrak56,
                'proportion2' => $proporPercentFrak56,
            ],
            [
                'nama' => 'Depresiasi',
                'proportion' => $depresiasiProportionFrak56,
                'proportion2' => $proporPercentFrak56,
            ],
        ];

        $proportionPackagingFrak56 = [
            [
                'nama' => 'Gaji & Tunjangan',
                'proportion' => $proporPercentFrak56PlusPackaging,
            ],
            [
                'nama' => 'Bahan Kimia',
                'proportion' => $proporPercentFrak56PlusPackaging,
            ],
            [
                'nama' => 'Pengangkutan / Langsir',
                'proportion' => $proporPercentFrak56PlusPackaging,
            ],
            [
                'nama' => 'Bahan Pengepakan Lainnya',
                'proportion' => $proporPercentFrak56PlusPackaging,
            ],
            [
                'nama' => 'Biaya Asuransi Gudang & Filling',
                'proportion' => $proporPercentFrak56PlusPackaging,
            ],
            [
                'nama' => 'Depresiasi',
                'proportion' => $proporPercentFrak56PlusPackaging,
            ],
        ];

        $directCost = $this->generateCostOutput('Fraksinasi IV-56', $dataDirectFrak56, $rbdpoConsumeQty, $proportionDirectFrak56);
        $inDirectCost = $this->generateCostOutput('Fraksinasi IV-56', $dataInDirectFrak56, $rbdpoConsumeQty, $proportionInDirectFrak56);
        $packagingCost = $this->generateCostOutput('Fraksinasi IV-56', $dataPackagingCostFrak56, $rbdpoConsumeQty, $proportionPackagingFrak56);

        return [
            'rbdpoConsume' => $rbdpoConsumeQty,
            'rbdOleinIv56Qty' => $rbdOleinIv56Qty,
            'rbdStearinQty' => $rbdStearinQty,
            'rbdOleinIv56RendementPercentage' => $rbdOleinIv56RendementPercentage,
            'rbdStearinRendementPercentage' => $rbdStearinRendementPercentage,
            'minyakita1Liter' => [
                'proportion' => $cartonMinyakita1LProportion,
                'proportionPercentage' => $cartonMinyakita1LPercent,
                'totalQty' => $minyakita1LTotalQty,
                'rendementPercentage' => $minyakita1LRendementPercentage,
            ],
            'minyakita2Liter' => [
                'proportion' => $cartonMinyakita2LProportion,
                'proportionPercentage' => $cartonMinyakita2LPercent,
                'totalQty' => $minyakita2LTotalQty,
                'rendementPercentage' => $minyakita2LRendementPercentage,
            ],
            'additional' => $additionalMinyakita,
            'dataDirect' => $directCost,
            'dataInDirect' => $inDirectCost,
            'dataPackaging' => $packagingCost,
        ];

    }

    public function nextCostingHppFraksinasiIv56($costingHppFraksinasiIv56, $rpPerKgRbdpoFraksinasiIv56, $proCost)
    {
        $bahanBakuValueFraksinasiIv56 = $rpPerKgRbdpoFraksinasiIv56 * $costingHppFraksinasiIv56['rbdpoConsume'];

        $bahanBakuFraksinasiIv56 = [
            'name' => 'Bahan Baku',
            'proportion' => 100,
            'value' => $rpPerKgRbdpoFraksinasiIv56,
            'totalValue' => $bahanBakuValueFraksinasiIv56,
            'rpPerKg' => $rpPerKgRbdpoFraksinasiIv56
        ];

        $costingHppFraksinasiIv56['dataDirect']['cost'][0]['item'][] = $bahanBakuFraksinasiIv56;
        $totalCostFraksinasiIv56 = $bahanBakuValueFraksinasiIv56;

        foreach ($costingHppFraksinasiIv56['dataDirect']['cost'][0]['item'] as $item) {
            $totalCostFraksinasiIv56 += $item['totalValue'];
        }

        foreach ($costingHppFraksinasiIv56['dataInDirect']['cost'][0]['item'] as $item) {
            $totalCostFraksinasiIv56 += $item['totalValue'];
        }

        $totalRpPerKgFraksinasiIv56 = $costingHppFraksinasiIv56['rbdpoConsume']> 0 ?$totalCostFraksinasiIv56 / $costingHppFraksinasiIv56['rbdpoConsume'] : 0;
        $costingHppFraksinasiIv56['totalCostFraksinasiIv56'] = $totalCostFraksinasiIv56;
        $costingHppFraksinasiIv56['totalRpPerKgFraksinasiIv56'] = $totalRpPerKgFraksinasiIv56;

        $totalCostFraksinasiIv56PlusPackaging = 0;
        foreach ($costingHppFraksinasiIv56['dataPackaging']['cost'][0]['item'] as $item) {
            $totalCostFraksinasiIv56PlusPackaging += $item['totalValue'];
        }

        $totalRpPerKgFraksinasiIv56PlusPackaging = $costingHppFraksinasiIv56['rbdpoConsume']> 0 ?$totalCostFraksinasiIv56PlusPackaging / $costingHppFraksinasiIv56['rbdpoConsume'] : 0;
        $costingHppFraksinasiIv56['totalCostFraksinasiIv56PlusPackaging'] = $totalCostFraksinasiIv56PlusPackaging;
        $costingHppFraksinasiIv56['totalRpPerKgFraksinasiIv56PlusPackaging'] = $totalRpPerKgFraksinasiIv56PlusPackaging;
        $proporsiBiayaPercentage = [];
        foreach ($proCost['data']['produksiFraksinasiIV56Data']['data'] as $data) {
            if ($data['nama'] === 'Proporsi biaya (%)') {
                foreach ($data['item'] as $item) {
                    $proporsiBiayaPercentage[$item['name']] = $item['value'];
                }
            }
        }

        $dataPackaging = $costingHppFraksinasiIv56['dataPackaging']['cost'][0]['item'];

        $gajiTunjangan = null;
        $bahanKimia = null;
        $angkutLangsir = null;
        $bahanPengepak = null;
        $asuransiGudangFilling = null;
        $depresiasi = null;
        $minyakita1Ltr = null;
        $minyakita2Ltr = null;
        // dd($dataPackaging);
        foreach ($dataPackaging as $item) {
            if ($item['name'] === 'Gaji & Tunjangan') {
                $gajiTunjangan = $item;
            }else if($item['name'] === 'Bahan Kimia'){
                $bahanKimia = $item;
            }else if($item['name'] === 'Pengangkutan / Langsir'){
                $angkutLangsir = $item;
            }else if($item['name'] === 'Bahan Pengepakan Lainnya'){
                $bahanPengepak = $item;
            }else if($item['name'] === 'Biaya Asuransi Gudang & Filling'){
                $asuransiGudangFilling = $item;
            }else if($item['name'] === 'Depresiasi'){
                $depresiasi = $item;
            }else if($item['name'] === 'Minyakita - 1 Ltr'){
                $minyakita1Ltr = $item;
            }else if($item['name'] === 'Minyakita - 2 Ltr'){
                $minyakita2Ltr = $item;
            }
        }

        $rbdOlein56ProportionFrak56 = $proporsiBiayaPercentage['RBDOlein IV-56'] ?? 0;
        $rbdStearinProportionFrak56 = $proporsiBiayaPercentage['RBDStearin'] ?? 0;
        $rbdOlein56TotalValueFrak56 = $totalCostFraksinasiIv56 * ($rbdOlein56ProportionFrak56 / 100);
        $rbdStearinTotalValueFrak56 = $totalCostFraksinasiIv56 * ($rbdStearinProportionFrak56 / 100);
        $rbdOlein56RpPerKgFrak56 = ($costingHppFraksinasiIv56['rbdOleinIv56Qty'] != 0) ? ($rbdOlein56TotalValueFrak56 / $costingHppFraksinasiIv56['rbdOleinIv56Qty']) : 0;
        $rbdStearinRpPerKgFrak56 = ($costingHppFraksinasiIv56['rbdStearinQty'] != 0) ? ($rbdStearinTotalValueFrak56 / $costingHppFraksinasiIv56['rbdStearinQty']) : 0;
        $minyakita1LProportionFrak56 = $costingHppFraksinasiIv56['minyakita1Liter']['rendementPercentage'] ?? 0;
        $minyakita2LProportionFrak56 = $costingHppFraksinasiIv56['minyakita2Liter']['rendementPercentage'] ?? 0;
        $minyakita1LTotalValueFrak56 = ($rbdOlein56RpPerKgFrak56*$costingHppFraksinasiIv56['minyakita1Liter']['totalQty'])+
                                        (($gajiTunjangan['totalValue']+$bahanKimia['totalValue']+$angkutLangsir['totalValue']+
                                        $bahanPengepak['totalValue']+$asuransiGudangFilling['totalValue']+$depresiasi['totalValue'])*$minyakita1LProportionFrak56)+$minyakita1Ltr['totalValue'] ?? 0;
        $minyakita2LTotalValueFrak56 = ($rbdOlein56RpPerKgFrak56*$costingHppFraksinasiIv56['minyakita2Liter']['totalQty'])+
                                        (($gajiTunjangan['totalValue']+$bahanKimia['totalValue']+$angkutLangsir['totalValue']+
                                        $bahanPengepak['totalValue']+$asuransiGudangFilling['totalValue']+$depresiasi['totalValue'])*$minyakita2LProportionFrak56)+$minyakita2Ltr['totalValue'] ?? 0;
        $minyakita1LRpPerKgFrak56 = $costingHppFraksinasiIv56['minyakita1Liter']['totalQty'] != 0 ? ($minyakita1LTotalValueFrak56 / $costingHppFraksinasiIv56['minyakita1Liter']['totalQty']) * 100 : 0;
        $minyakita2LRpPerKgFrak56 = $costingHppFraksinasiIv56['minyakita2Liter']['totalQty'] != 0 ? ($minyakita2LTotalValueFrak56 / $costingHppFraksinasiIv56['minyakita2Liter']['totalQty']) * 100 : 0;
        $selisihFrak56 = $costingHppFraksinasiIv56['totalCostFraksinasiIv56'] - $rbdOlein56TotalValueFrak56 - $rbdStearinTotalValueFrak56;
        $palingBawahFrak56 = ($rbdOlein56RpPerKgFrak56 * ($costingHppFraksinasiIv56['minyakita1Liter']['totalQty'] + $costingHppFraksinasiIv56['minyakita2Liter']['totalQty'])) +
                            ($gajiTunjangan['totalValue']+$minyakita1Ltr['totalValue']+$minyakita2Ltr['totalValue']+$bahanKimia['totalValue']+$angkutLangsir['totalValue']+$bahanPengepak['totalValue']+
                            $asuransiGudangFilling['totalValue']+$depresiasi['totalValue'])-$minyakita1LTotalValueFrak56-$minyakita2LTotalValueFrak56;


        $allocationCostFraksinasiIv56 = [
            [
                'nama' => 'RBD Olein IV-56',
                'proportion' => $rbdOlein56ProportionFrak56,
                'totalValue' => $rbdOlein56TotalValueFrak56,
                'rpPerKg' => $rbdOlein56RpPerKgFrak56,
            ],
            [
                'nama' => 'RBD Stearin',
                'proportion' => $rbdStearinProportionFrak56,
                'totalValue' => $rbdStearinTotalValueFrak56,
                'rpPerKg' => $rbdStearinRpPerKgFrak56,
            ],
            [
                'nama' => 'Minyakita - 1 Ltr',
                'proportion' => $minyakita1LProportionFrak56,
                'totalValue' => $minyakita1LTotalValueFrak56,
                'rpPerKg' => $minyakita1LRpPerKgFrak56,
            ],
            [
                'nama' => 'Minyakita - 2 Ltr',
                'proportion' => $minyakita2LProportionFrak56,
                'totalValue' => $minyakita2LTotalValueFrak56,
                'rpPerKg' => $minyakita2LRpPerKgFrak56,
            ],
            [
                'nama' => 'Selisih',
                'totalValue' => $selisihFrak56,
            ],
            [
                'nama' => 'palingBawah',
                'totalValue' => $palingBawahFrak56,
            ]
        ];

        $costingHppFraksinasiIv56['allocationCostFraksinasiIv56'] = $allocationCostFraksinasiIv56;

        return $costingHppFraksinasiIv56;
    }

    public function costingHppRefinery($laporanProduksi, $proCost, $alokasiCost, $dataDirect, $dataInDirect)
    {
        $cpoConsumeQty = $this->getTotalQty($laporanProduksi['recap']['laporanProduksi'], 'Refinery', 'CPO (Olah)');
        $rbdpoQty = $this->getTotalQty($laporanProduksi['recap']['laporanProduksi'], 'Refinery', 'RBDPO (Produksi)');
        $pfadQty = $this->getTotalQty($laporanProduksi['recap']['laporanProduksi'], 'Refinery', 'PFAD (Produksi)');

        $rbdpoRendementPercentage = $cpoConsumeQty != 0 ? ($rbdpoQty / $cpoConsumeQty) * 100 : 0;
        $pfadRendementPercentage = $cpoConsumeQty != 0 ? ($pfadQty / $cpoConsumeQty) * 100 : 0;

        $produksiAllRefineryPercentage = 0;
        $produksiAll = $laporanProduksi['produksiAll'];
        if (isset($produksiAll['production']) && is_array($produksiAll['production'])) {
            foreach ($produksiAll['production'] as $production) {
                if (isset($production['items']) && is_array($production['items'])) {
                    foreach ($production['items'] as $item) {
                        if ($item['name'] === 'Refinery') {
                            $produksiAllRefineryPercentage = $item['percentage'];
                        }
                    }
                }
            }
        }

        $penyusutanAllocation = $laporanProduksi['biayaPenyusutanAllocation'];
        $penyusutanAllocationRefineryPercentage = 0;

        foreach ($penyusutanAllocation['columns'] as $column) {
            if ($column['name'] === '%') {
                foreach ($column['alokasi'] as $alokasi) {
                    if ($alokasi['name'] === 'Refinery') {
                        $penyusutanAllocationRefineryPercentage = $alokasi['value'];
                    }
                }
            }
        }

        $proporsiBiayaPercentage = [];
        foreach ($proCost['data']['produksiRefineryData']['data'] as $data) {
            if ($data['nama'] === 'Proporsi biaya (%)') {
                foreach ($data['item'] as $item) {
                    $proporsiBiayaPercentage[$item['name']] = $item['value'];
                }
            }
        }


        $bahanBakarProportionRefinery = $alokasiCost['Refinery']['gasPercentage'] ?? 0;
        $othersProportionRefinery = $produksiAllRefineryPercentage ?? 0;
        $analisaLabProportionRefinery = $produksiAllRefineryPercentage ?? 0;
        $listrikProportionRefinery = $alokasiCost['Refinery']['listrikPercentage'] ?? 0;
        $airProportionRefinery = $alokasiCost['Refinery']['airPercentage'] ?? 0;
        $gajiPimpinanProportionRefinery = $produksiAllRefineryPercentage ?? 0;
        $gajiPelaksanaProportionRefinery = $produksiAllRefineryPercentage ?? 0;
        $asuransiPabrikProportionRefinery = $produksiAllRefineryPercentage ?? 0;
        $bengkelProportionRefinery = $produksiAllRefineryPercentage ?? 0;
        $depresiasiProportionRefinery = $penyusutanAllocationRefineryPercentage ?? 0;

        $proportionDirectRefinery = [
            [
                'nama' => 'Bahan Bakar',
                'proportion' => $bahanBakarProportionRefinery,
            ],
            [
                'nama' => 'Others',
                'proportion' => $othersProportionRefinery,
            ],
            [
                'nama' => 'Biaya Analisa & Laboratorium',
                'proportion' => $analisaLabProportionRefinery,
            ],
            [
                'nama' => 'Biaya Listrik',
                'proportion' => $listrikProportionRefinery,
            ],
            [
                'nama' => 'Biaya Air',
                'proportion' => $airProportionRefinery,
            ],
        ];

        $proportionInDirectRefinery = [
            [
                'nama' => 'Gaji, Tunjangan & Biaya Sosial Karyawan Pimpinan',
                'proportion' => $gajiPimpinanProportionRefinery,
            ],
            [
                'nama' => 'Gaji, Tunjangan & Biaya Sosial Karyawan Pelaksana',
                'proportion' => $gajiPelaksanaProportionRefinery,
            ],
            [
                'nama' => 'Biaya Assuransi Pabrik',
                'proportion' => $asuransiPabrikProportionRefinery,
            ],
            [
                'nama' => 'Biaya Bengkel & Pemeliharaan',
                'proportion' => $bengkelProportionRefinery,
            ],
            [
                'nama' => 'Depresiasi',
                'proportion' => $depresiasiProportionRefinery,
            ],
        ];

        $directCost = $this->generateCostOutput('Refinery', $dataDirect, $cpoConsumeQty, $proportionDirectRefinery);
        $inDirectCost = $this->generateCostOutput('Refinery', $dataInDirect, $cpoConsumeQty, $proportionInDirectRefinery);

        $totalCostRefinery = $directCost['cost'][0]['totalValue'] + $inDirectCost['cost'][0]['totalValue'];
        $totalRpPerKgRefinery = ($cpoConsumeQty != 0) ? ($totalCostRefinery / $cpoConsumeQty) : 0;

        $rbdpoProportionRefinery = $proporsiBiayaPercentage['RBDPO'] ?? 0;
        $pfadProportionRefinery = $proporsiBiayaPercentage['PFAD'] ?? 0;
        $rbdpoTotalValueRefinery = $totalCostRefinery * ($rbdpoProportionRefinery / 100);
        $pfadTotalValueRefinery = $totalCostRefinery * ($pfadProportionRefinery / 100);
        $rbdpoRpPerKgRefinery = ($rbdpoQty != 0) ? ($rbdpoTotalValueRefinery / $rbdpoQty) : 0;
        $pfadRpPerKgRefinery = ($pfadQty != 0) ? ($pfadTotalValueRefinery / $pfadQty) : 0;

        $allocationCostRefinery = [
            [
                'nama' => 'RBDPO',
                'proportion' => $rbdpoProportionRefinery,
                'totalValue' => $rbdpoTotalValueRefinery,
                'rpPerKg' => $rbdpoRpPerKgRefinery,
            ],
            [
                'nama' => 'PFAD',
                'proportion' => $pfadProportionRefinery,
                'totalValue' => $pfadTotalValueRefinery,
                'rpPerKg' => $pfadRpPerKgRefinery,
            ]
        ];

        return [
            'cpoConsume' => $cpoConsumeQty,
            'rbdpo' => $rbdpoQty,
            'pfad' => $pfadQty,
            'rbdpoRendementPercentage' => $rbdpoRendementPercentage,
            'pfadRendementPercentage' => $pfadRendementPercentage,
            'dataDirect' => $directCost,
            'dataInDirect' => $inDirectCost,
            'totalCostRefinery' => $totalCostRefinery,
            'totalRpPerKgRefinery' => $totalRpPerKgRefinery,
            'allocationCostRefinery' => $allocationCostRefinery,
        ];
    }

    public function costingHppRecap($request)
    {
        $proCost = $this->processProCost($request);
        $laporanProduksi = $this->processPenyusutan($request);
        $avgPrice = $this->processQtyBebanBlendingDowngradeForCostingHpp($request, $proCost, $laporanProduksi);

        $alokasiBiaya = $laporanProduksi['recap']['alokasiBiaya']['allocation'];

        $alokasiCost = [
            'Refinery' => [
                'gasQty' => 0, 'gasPercentage' => 0,
                'airQty' => 0, 'airPercentage' => 0,
                'listrikQty' => 0, 'listrikPercentage' => 0
            ],
            'Fraksinasi' => [
                'gasQty' => 0, 'gasPercentage' => 0,
                'airQty' => 0, 'airPercentage' => 0,
                'listrikQty' => 0, 'listrikPercentage' => 0
            ]
        ];

        foreach ($alokasiBiaya as $alokasiItem) {
            $type = $alokasiItem['nama'];
            if (isset($alokasiCost[$type])) {
                foreach ($alokasiItem['item'] as $item) {
                    switch ($item['name']) {
                        case "Steam / Gas":
                            $alokasiCost[$type]['gasQty'] = $item['qty'];
                            $alokasiCost[$type]['gasPercentage'] = $item['percentage'];
                            break;
                        case "Air":
                            $alokasiCost[$type]['airQty'] = $item['qty'];
                            $alokasiCost[$type]['airPercentage'] = $item['percentage'];
                            break;
                        case "Listrik":
                            $alokasiCost[$type]['listrikQty'] = $item['qty'];
                            $alokasiCost[$type]['listrikPercentage'] = $item['percentage'];
                            break;
                    }
                }
            }
        }

        $konversiLiterToKg = $this->settingGet('konversi_liter_to_kg')->setting_value;

        $proporPercentFrak56 = 0;
        $proporPercentFrak56PlusPackaging = 0;

        foreach ($laporanProduksi['packagingNFraksinasi']['production'] as $production) {
            if ($production['name'] === 'Fraksinasi') {
                foreach ($production['items'] as $item) {
                    if ($item['name'] === 'RBD Olein IV-56') {
                        $proporPercentFrak56 = $item['percentage'];
                        break;
                    }
                }
            } elseif ($production['name'] === 'Packaging') {
                foreach ($production['items'] as $item) {
                    if ($item['name'] === 'RBD Olein IV-56') {
                        $proporPercentFrak56PlusPackaging = $item['percentage'];
                        break;
                    }
                }
            }
        }

        $settingDirectIdsRefinery = $this->getSettingIds([
            'coa_bahan_baku_cat2', 'coa_bahan_bakar_cat2', 'coa_bleaching_earth_cat2',
            'coa_phosporic_acid_cat2', 'coa_others_cat2','coa_analisa_lab_cat2',
            'coa_listrik_cat2', 'coa_air_cat2'
        ]);

        $settingInDirectIdsRefinery = $this->getSettingIds([
            'coa_gaji_tunjangan_sosial_pimpinan_cat2', 'coa_gaji_tunjangan_sosial_pelaksana_cat2',
            'coa_assuransi_pabrik_cat2', 'coa_limbah_pihak3_cat2', 'coa_bengkel_pemeliharaan_cat2', 'coa_depresiasi_cat2'
        ]);

        $settingDirectIdsFraksinasi56 = $this->getSettingIds([
            'coa_bahan_bakar_cat2', 'coa_others_cat2','coa_analisa_lab_cat2',
            'coa_listrik_cat2', 'coa_air_cat2'
        ]);

        $settingInDirectIdsFraksinasi56 = $this->getSettingIds([
            'coa_gaji_tunjangan_sosial_pimpinan_cat2', 'coa_gaji_tunjangan_sosial_pelaksana_cat2',
            'coa_assuransi_pabrik_cat2', 'coa_bengkel_pemeliharaan_cat2', 'coa_depresiasi_cat2'
        ]);

        $settingPackagingCostFraksinasi56 = $this->getSettingIds([
            'coa_gaji_dan_tunjangan_cat2', 'coa_minyakita_1L_cat2', 'coa_minyakita_2L_cat2',
            'coa_bahan_kimia_cat2', 'coa_pengangkutan_langsir_cat2', 'coa_pengepakan_lain_cat2',
            'coa_asuransi_gudang_filling_cat2', 'coa_depresiasi_cat2'
        ]);

        $tanggal = Carbon::parse($request->tanggal);
        $generalLedgerData = $this->getGeneralLedgerData($tanggal);

        $dataDirectRef = $this->processGeneralLedger($request, $settingDirectIdsRefinery, $generalLedgerData);
        $dataInDirectRef = $this->processGeneralLedger($request, $settingInDirectIdsRefinery, $generalLedgerData);
        $dataDirectFrak56 = $this->processGeneralLedger($request, $settingDirectIdsFraksinasi56, $generalLedgerData);
        $dataInDirectFrak56 = $this->processGeneralLedger($request, $settingInDirectIdsFraksinasi56, $generalLedgerData);
        $dataPackagingCostFrak56 = $this->processGeneralLedger($request, $settingPackagingCostFraksinasi56, $generalLedgerData);

        $costingHppRefinery = $this->costingHppRefinery($laporanProduksi, $proCost, $alokasiCost, $dataDirectRef, $dataInDirectRef);
        $costingHppFraksinasiIv56 = $this->costingHppFraksinasiIv56($laporanProduksi, $alokasiCost, $proporPercentFrak56, $proporPercentFrak56PlusPackaging, $konversiLiterToKg, $dataDirectFrak56, $dataInDirectFrak56, $dataPackagingCostFrak56);
        $rpPerKgRbdpoFraksinasiIv56 = $avgPrice['rbdpo']['rpPerKg'];
        $costingHppFraksinasiIv56Next = $this->nextCostingHppFraksinasiIv56($costingHppFraksinasiIv56, $rpPerKgRbdpoFraksinasiIv56, $proCost);

        return [
            'costingHppRefinery' => $costingHppRefinery,
            'costingHppFraksinasiIv56' => $costingHppFraksinasiIv56Next,
        ];
    }

    public function processGeneralLedger(Request $request, $settingIds, $generalLedgerData)
    {
        $tanggal = Carbon::parse($request->tanggal);
        $debe = Debe::with(['cat3.cat2', 'mReport', 'cCentre', 'plant', 'allocation'])->get();
        $coa = Setting::whereIn('id', $settingIds)->orderBy('id')->get();
        $gl = collect($generalLedgerData);

        $laporanData = $this->indexLaporanProduksi($request);

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
            $cat2Name = '';

            foreach ($coaNumbers as $coaNumber) {
                $glData = $gl->filter(function($item) use ($coaNumber) {
                    return $item['account_account']['code'] == $coaNumber;
                });

                $debeModel = $debe->firstWhere('coa', $coaNumber);

                if ($debeModel && $debeModel->cat3 && $debeModel->cat3->cat2) {
                    $cat2Name = $debeModel->cat3->cat2->nama;
                }

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
                'nama' => $cat2Name,
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

    private function getSettingIds(array $settingNames)
    {
        return Setting::whereIn('setting_name', $settingNames)->pluck('id')->toArray();
    }

    public function indexLaporanProduksi(Request $request)
    {
        $tanggal = Carbon::parse($request->tanggal);
        $year = $tanggal->year;
        $month = $tanggal->month;

        $data = $this->dataLaporanProduksi($year, $month);

        $settingNames = ['konversi_liter_to_kg', 'pouch_to_box_1_liter', 'pouch_to_box_2_liter', 'konversi_m_liter_to_kg'];
        $settings = Setting::whereIn('setting_name', $settingNames)->get();

        $laporanProduksi = $this->prosesLaporanProd($data);

        return [
            'laporanProduksi' => $laporanProduksi,
            'settings' => $settings
        ];
    }

    public function processQtyBebanBlendingDowngradeForCostingHpp(Request $request,$proCost, $laporanProduksi)
    {
        $persediaanAwal = $this->persediaanAwal($request);
        $qtyBebanProduksi = $this->processQtyBebanProduksi($request);
        $allocationCostCostingHpp = $this->allocationCostCostingHpp($request, $proCost, $laporanProduksi);
        $qtyBebanProduksiData = $qtyBebanProduksi['qtyBebanProduksi'];
        $pengolahanBlendingDowngradeRbdpoQty = $qtyBebanProduksi['pengolahanBlendingDowngradeRbdpoQty'];
        $rpPerKgQtyBebanProduksi = $this->processRpPerKgQtyBebanProduksi($qtyBebanProduksiData, $allocationCostCostingHpp);

        $result = [];

        foreach ($pengolahanBlendingDowngradeRbdpoQty as $key => $qty) {
            $totalQty = 0;
            $totalJumlah = 0;

            foreach ($persediaanAwal['items'] as $item) {
                if (strtolower($item['nama']) === strtolower($key)) {
                    $totalQty += $item['qty'];
                    $totalJumlah += $item['jumlah'];
                    break;
                }
            }

            if (isset($rpPerKgQtyBebanProduksi[$key])) {
                $totalQty += $rpPerKgQtyBebanProduksi[$key]['qty'];
                $totalJumlah += $rpPerKgQtyBebanProduksi[$key]['jumlah'];
            }

            $rpPerKg = $totalQty > 0 ? $totalJumlah / $totalQty : 0;

            $jumlah = $qty * $rpPerKg;

            $result[$key] = [
                'qty' => $qty,
                'rpPerKg' => $rpPerKg,
                'jumlah' => $jumlah
            ];
        }

        return $result;
    }

    public function avgPrice(Request $request)
    {
        $proCost = $this->processProCost($request);
        $laporanProduksi = $this->processPenyusutan($request);

        $persediaanAwal = $this->persediaanAwal($request);
        $qtyBebanProduksi = $this->processQtyBebanProduksi($request);
        $allocationCostCostingHpp = $this->allocationCostCostingHpp($request, $proCost, $laporanProduksi);
        $qtyBebanProduksiData = $qtyBebanProduksi['qtyBebanProduksi'];
        $pengolahanBlendingDowngradeRbdpoQty = $qtyBebanProduksi['pengolahanBlendingDowngradeRbdpoQty'];
        $rpPerKgQtyBebanProduksi = $this->processRpPerKgQtyBebanProduksi($qtyBebanProduksiData, $allocationCostCostingHpp);
        $processQtyBebanBlendingDowngrade = $this->processQtyBebanBlendingDowngrade($pengolahanBlendingDowngradeRbdpoQty, $persediaanAwal, $rpPerKgQtyBebanProduksi);
        return [
            'persediaanAwal' => $persediaanAwal,
            'qtyBebanProduksi' => $rpPerKgQtyBebanProduksi,
            'processQtyBebanBlendingDowngrade' => $processQtyBebanBlendingDowngrade,
        ];
    }

    public function processRpPerKgQtyBebanProduksi($qtyBebanProduksi, $allocationCostCostingHpp)
    {
        $result = [];

        foreach ($qtyBebanProduksi as $key => $qty) {
            $rpPerKg = 0;

            foreach ($allocationCostCostingHpp['allocationCostRefinery'] as $allocation) {
                if (strtolower($allocation['nama']) === strtolower($key)) {
                    $rpPerKg = $allocation['rpPerKg'];
                    break;
                }
            }

            $jumlah = $qty * $rpPerKg;

            $result[$key] = [
                'qty' => $qty,
                'rpPerKg' => $rpPerKg,
                'jumlah' => $jumlah
            ];
        }

        return $result;
    }

    public function processQtyBebanBlendingDowngrade($pengolahanBlendingDowngradeRbdpoQty, $persediaanAwal, $rpPerKgQtyBebanProduksi)
    {
        $result = [];

        foreach ($pengolahanBlendingDowngradeRbdpoQty as $key => $qty) {
            $totalQty = 0;
            $totalJumlah = 0;

            foreach ($persediaanAwal['items'] as $item) {
                if (strtolower($item['nama']) === strtolower($key)) {
                    $totalQty += $item['qty'];
                    $totalJumlah += $item['jumlah'];
                    break;
                }
            }

            if (isset($rpPerKgQtyBebanProduksi[$key])) {
                $totalQty += $rpPerKgQtyBebanProduksi[$key]['qty'];
                $totalJumlah += $rpPerKgQtyBebanProduksi[$key]['jumlah'];
            }

            $rpPerKg = $totalQty > 0 ? $totalJumlah / $totalQty : 0;

            $jumlah = $qty * $rpPerKg;

            $result[$key] = [
                'qty' => $qty,
                'rpPerKg' => $rpPerKg,
                'jumlah' => $jumlah
            ];
        }

        return $result;
    }


    public function persediaanAwal(Request $request){
        $tanggal = $request->tanggal;

        $persediaanAwal = InitialSupply::with('productable')
            ->whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
            ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
            ->get();

        $persediaanAwal->each(function ($item) {
            $item->makeHidden('productable');
            $item->extended_productable;
        });

        if ($persediaanAwal->isEmpty()) {
            return response()->json(['message' => $this->messageMissing], 401);
        }

        $totalQty = $persediaanAwal->sum('qty');
        $totalJumlah = $persediaanAwal->sum(function ($item) {
            return $item->qty * $item->harga;
        });
        $totalHarga = $totalJumlah/$totalQty;

        $transformedPersediaanAwal = $persediaanAwal->map(function ($item) {
            return [
                'id' => $item->extended_productable['id'],
                'product_id' => $item->extended_productable['product_id'] ?? null,
                'nama' => $item->extended_productable['nama'] ?? $item->extended_productable['name'],
                'product' => $item->extended_productable['product'] ?? null,
                'tanggal' => $item->tanggal,
                'qty' => $item->qty,
                'harga' => $item->harga,
                'jumlah' => $item->qty * $item->harga,
            ];
        });

        return [
            'totalQty' => $totalQty,
            'totalHarga' => $totalHarga,
            'totalJumlah' => $totalJumlah,
            'items' => $transformedPersediaanAwal,
            ];
    }

    public function processQtyBebanProduksi(Request $request)
    {
        $detAlloc = $this->processRecapData($request);
        $proCost = $this->processProCost($request);
        $extractedData = [
            'refinery' => [],
            'fraksinasi_iv56' => [],
            'fraksinasi_iv57' => [],
            'fraksinasi_iv58' => [],
            'fraksinasi_iv60' => [],
        ];

        $extractValues = function ($data, $groupName) use (&$extractedData) {
            foreach ($data as $group) {
                foreach ($group['item'] as $item) {
                    $extractedData[$groupName][$group['nama']][$item['name']] = $item['value'];
                }
            }
        };

        $extractValues($proCost['data']['produksiRefineryData']['data'], 'refinery');
        $extractValues($proCost['data']['produksiFraksinasiIV56Data']['data'], 'fraksinasi_iv56');
        $extractValues($proCost['data']['produksiFraksinasiIV57Data']['data'], 'fraksinasi_iv57');
        $extractValues($proCost['data']['produksiFraksinasiIV58Data']['data'], 'fraksinasi_iv58');
        $extractValues($proCost['data']['produksiFraksinasiIV60Data']['data'], 'fraksinasi_iv60');

        $totalQtyOleinIV56Consume = 0;
        $totalQtyOleinIV57Consume = 0;
        $totalQtyOleinIV58Consume = 0;
        $totalQtyOleinIV60NusakitaConsume = 0;
        $totalQtyOleinIV60SalvacoConsume = 0;

        foreach ($detAlloc['laporanProduksi'] as $production) {
            foreach ($production['uraian'] as $uraian) {
                if ($uraian['nama'] === 'Olein IV 56 Consume') {
                    $totalQtyOleinIV56Consume += $uraian['total_qty'] ?? 0;
                } elseif ($uraian['nama'] === 'Olein IV 57 Consume') {
                    $totalQtyOleinIV57Consume += $uraian['total_qty'] ?? 0;
                } elseif ($uraian['nama'] === 'Olein IV 58 Consume') {
                    $totalQtyOleinIV58Consume += $uraian['total_qty'] ?? 0;
                } elseif ($uraian['nama'] === 'Olein IV 60 Consume') {
                    if ($production['nama'] === 'Packaging (Nusakita)') {
                        $totalQtyOleinIV60NusakitaConsume += $uraian['total_qty'] ?? 0;
                    } elseif ($production['nama'] === 'Packaging (Salvaco)') {
                        $totalQtyOleinIV60SalvacoConsume += $uraian['total_qty'] ?? 0;
                    }
                }
            }
        }

        $qtyBebanProdRBDPO = $extractedData['refinery']['Produksi Refinery']['RBDPO'] ?? 0;
        $qtyBebanProdPFAD = $extractedData['refinery']['Produksi Refinery']['PFAD'] ?? 0;
        $qtyBebanProdRBDOlein56Minyakita = $totalQtyOleinIV56Consume ?? 0;
        $qtyBebanProdRBDOlein56Bulk = ($extractedData['fraksinasi_iv56']['Produksi Fraksinasi IV-56']['RBDOlein IV-56'] ?? 0) - $qtyBebanProdRBDOlein56Minyakita;
        $qtyBebanProdRBDOlein57INL = $totalQtyOleinIV57Consume ?? 0;
        $qtyBebanProdRBDOlein57Bulk = ($extractedData['fraksinasi_iv57']['Produksi Fraksinasi IV-57']['RBDOlein IV-57'] ?? 0) - $qtyBebanProdRBDOlein56Minyakita;
        $qtyBebanProdRBDOlein58Kemasan = 0;
        $qtyBebanProdRBDOlein58Bulk = ($extractedData['fraksinasi_iv58']['Produksi Fraksinasi IV-58']['RBDOlein IV-58'] ?? 0) - $qtyBebanProdRBDOlein58Kemasan;
        $qtyBebanProdRBDOlein60Salvaco = $totalQtyOleinIV60SalvacoConsume ?? 0;
        $qtyBebanProdRBDOlein60Nusakita = $totalQtyOleinIV60NusakitaConsume ?? 0;
        $qtyBebanProdRBDOlein60Bulk = ($extractedData['fraksinasi_iv60']['Produksi Fraksinasi IV-60']['RBDOlein IV-60'] ?? 0) - ($qtyBebanProdRBDOlein60Salvaco + $qtyBebanProdRBDOlein60Nusakita);
        $qtyBebanProdRBDStearin = ($extractedData['fraksinasi_iv56']['Produksi Fraksinasi IV-56']['RBDStearin'] ?? 0) +
                                    ($extractedData['fraksinasi_iv57']['Produksi Fraksinasi IV-57']['RBDStearin'] ?? 0) +
                                    ($extractedData['fraksinasi_iv58']['Produksi Fraksinasi IV-58']['RBDStearin'] ?? 0) +
                                    ($extractedData['fraksinasi_iv60']['Produksi Fraksinasi IV-60']['RBDStearin'] ?? 0);

        $pengolahanBlendingDowngradeRbdpoQty = abs(-($extractedData['fraksinasi_iv56']['Produksi Fraksinasi IV-56']['RBDPO Olah'] ?? 0) -
                                                ($extractedData['fraksinasi_iv57']['Produksi Fraksinasi IV-57']['RBDPO Olah'] ?? 0) -
                                                ($extractedData['fraksinasi_iv58']['Produksi Fraksinasi IV-58']['RBDPO Olah'] ?? 0) -
                                                ($extractedData['fraksinasi_iv60']['Produksi Fraksinasi IV-60']['RBDPO Olah'] ?? 0));

        // $pengolahanBlendingDowngradeRbdpoRpPerKg =
        // $pengolahanBlendingDowngradeRbdpoJumlah =

        return[
            'qtyBebanProduksi' => [
                'rbdpo' => $qtyBebanProdRBDPO,
                'pfad' => $qtyBebanProdPFAD,
                'bulk56' => $qtyBebanProdRBDOlein56Bulk,
                'kemasanMinyakita' => $qtyBebanProdRBDOlein56Minyakita,
                'bulk57' => $qtyBebanProdRBDOlein57Bulk,
                'kemasanINL' => $qtyBebanProdRBDOlein57INL,
                'bulk58' => $qtyBebanProdRBDOlein58Bulk,
                'kemasan58' => $qtyBebanProdRBDOlein58Kemasan,
                'bulk60' => $qtyBebanProdRBDOlein60Bulk,
                'kemasanSalvaco' => $qtyBebanProdRBDOlein60Salvaco,
                'kemasanNusakita' => $qtyBebanProdRBDOlein60Nusakita,
                'rbdStearin' => $qtyBebanProdRBDStearin,
            ],
            'pengolahanBlendingDowngradeRbdpoQty' =>[
                'rbdpo' => $pengolahanBlendingDowngradeRbdpoQty,
                'pfad' => 0,
                'bulk56' => 0,
                'kemasanMinyakita' => 0,
                'bulk57' => 0,
                'kemasanINL' => 0,
                'bulk58' => 0,
                'kemasan58' => 0,
                'bulk60' => 0,
                'kemasanSalvaco' => 0,
                'kemasanNusakita' => 0,
                'rbdStearin' => 0,
            ]
        ];
    }

    public function allocationCostCostingHpp(Request $request, $proCost, $laporanProduksi)
    {
        $alokasiBiaya = $laporanProduksi['recap']['alokasiBiaya']['allocation'];

        $alokasiCost = [
            'Refinery' => [
                'gasQty' => 0, 'gasPercentage' => 0,
                'airQty' => 0, 'airPercentage' => 0,
                'listrikQty' => 0, 'listrikPercentage' => 0
            ],
            'Fraksinasi' => [
                'gasQty' => 0, 'gasPercentage' => 0,
                'airQty' => 0, 'airPercentage' => 0,
                'listrikQty' => 0, 'listrikPercentage' => 0
            ]
        ];

        foreach ($alokasiBiaya as $alokasiItem) {
            $type = $alokasiItem['nama'];
            if (isset($alokasiCost[$type])) {
                foreach ($alokasiItem['item'] as $item) {
                    switch ($item['name']) {
                        case "Steam / Gas":
                            $alokasiCost[$type]['gasQty'] = $item['qty'];
                            $alokasiCost[$type]['gasPercentage'] = $item['percentage'];
                            break;
                        case "Air":
                            $alokasiCost[$type]['airQty'] = $item['qty'];
                            $alokasiCost[$type]['airPercentage'] = $item['percentage'];
                            break;
                        case "Listrik":
                            $alokasiCost[$type]['listrikQty'] = $item['qty'];
                            $alokasiCost[$type]['listrikPercentage'] = $item['percentage'];
                            break;
                    }
                }
            }
        }

        $settingDirectIdsRefinery = $this->getSettingIds([
            'coa_bahan_baku_cat2', 'coa_bahan_bakar_cat2', 'coa_bleaching_earth_cat2',
            'coa_phosporic_acid_cat2', 'coa_others_cat2','coa_analisa_lab_cat2',
            'coa_listrik_cat2', 'coa_air_cat2'
        ]);

        $settingInDirectIdsRefinery = $this->getSettingIds([
            'coa_gaji_tunjangan_sosial_pimpinan_cat2', 'coa_gaji_tunjangan_sosial_pelaksana_cat2',
            'coa_assuransi_pabrik_cat2', 'coa_limbah_pihak3_cat2', 'coa_bengkel_pemeliharaan_cat2', 'coa_depresiasi_cat2'
        ]);

        $tanggal = Carbon::parse($request->tanggal);
        $generalLedgerData = $this->getGeneralLedgerData($tanggal);

        $dataDirectRef = $this->processGeneralLedger($request, $settingDirectIdsRefinery, $generalLedgerData);
        $dataInDirectRef = $this->processGeneralLedger($request, $settingInDirectIdsRefinery, $generalLedgerData);

        $costingHppRefinery = $this->costingHppRefinery($laporanProduksi, $proCost, $alokasiCost, $dataDirectRef, $dataInDirectRef);

        return[
            'allocationCostRefinery' => $costingHppRefinery['allocationCostRefinery']
        ];
    }

    // public function processQtyBebanPengolahanBlendingDowngrade(Request $request){
    //     processProCost
    // }

}
