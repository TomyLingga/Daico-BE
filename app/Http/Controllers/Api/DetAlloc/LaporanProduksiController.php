<?php

namespace App\Http\Controllers\Api\DetAlloc;

use App\Http\Controllers\Controller;
use App\Models\LaporanProduksi;
use App\Models\Plant;
use App\Models\Setting;
use App\Models\UraianProduksi;
use App\Services\LoggerService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LaporanProduksiController extends Controller
{
    private $messageFail = 'Something went wrong';
    private $messageMissing = 'Data not found in record';
    private $messageAll = 'Success to Fetch All Datas';
    private $messageSuccess = 'Success to Fetch Data';
    private $messageCreate = 'Success to Create Data';
    private $messageUpdate = 'Success to Update Data';

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id_uraian' => 'required|integer',
                'tanggal' => 'required|date',
                'value' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    // 'code' => 400,
                    'success' => false,
                ], 400);
            }

            if ($request->has('id_plant')) {
                Plant::findOrFail($request->id_plant);
            }

            UraianProduksi::findOrFail($request->id_uraian);

            $data = LaporanProduksi::create($request->all());

            LoggerService::logAction($this->userData, $data, 'create', null, $data->toArray());

            DB::commit();

            return response()->json([
                'data' => $data,
                'message' => $this->messageCreate,
                // 'code' => 200,
                'success' => true,
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $this->messageFail,
                'err' => $e->getTrace()[0],
                'errMsg' => $e->getMessage(),
                // 'code' => 500,
                'success' => false,
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {

            $rules = [
                'id_uraian' => 'required|integer',
                'tanggal' => 'required|date',
                'value' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'success' => false
                ], 400);
            }

            if ($request->has('id_plant')) {
                Plant::findOrFail($request->id_plant);
            }
            UraianProduksi::findOrFail($request->id_uraian);

            $data = LaporanProduksi::findOrFail($id);
            $oldData = $data->toArray();

            $data->update($request->all());

            LoggerService::logAction($this->userData, $data, 'update', $oldData, $data->toArray());

            DB::commit();

            return response()->json([
                'data' => $data,
                'message' => $this->messageUpdate,
                'success' => true,
            ], 200);

        } catch (\Throwable $e) {
            DB::rollback();
            return response()->json([
                'message' => $this->messageFail,
                'err' => $e->getTrace()[0],
                'errMsg' => $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    // public function indexDate(Request $request)
    // {
    //     try {
    //         $tanggal = $request->tanggal;

    //         $data = LaporanProduksi::whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
    //             ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
    //             ->with(['uraian.kategori', 'plant'])
    //             ->orderBy('tanggal')
    //             ->get();

    //         if ($data->isEmpty()) {
    //             return response()->json(['message' => $this->messageMissing], 401);
    //         }
    //         $settingNames = ['konversi_liter_to_kg', 'pouch_to_box_1_liter', 'pouch_to_box_2_liter', 'konversi_m_liter_to_kg'];

    //         $settings = Setting::whereIn('setting_name', $settingNames)->get();

    //         $groupedData = $data->groupBy(function ($item) {
    //             return $item->uraian->kategori->nama;
    //         });

    //         // $groupedData = $data->groupBy('id_uraian')->map(function ($group) {
    //         //     $totalValue = $group->sum('value');
    //         //     $uraianName = $group->first()->uraian->nama;
    //         //     $group->each(function ($item) use ($totalValue, $uraianName) {
    //         //         $item['total_' . str_replace(' ', '_', $uraianName)] = $totalValue;
    //         //     });
    //         //     return $group;
    //         // });

    //         // // Flatten the grouped data to a single level
    //         // $flattenedData = $groupedData->flatten(1);

    //         return response()->json(['data' => $groupedData, 'setting' => $settings, 'message' => $this->messageAll], 200);

    //     } catch (QueryException $e) {
    //         return response()->json([
    //             'message' => $this->messageFail,
    //             'err' => $e->getTrace()[0],
    //             'errMsg' => $e->getMessage(),
    //             // 'code' => 500,
    //             'success' => false,
    //         ], 500);
    //     }
    // }
    public function indexDate(Request $request)
    {
        try {
            $tanggal = $request->tanggal;

            // Fetch data with relationships
            $data = LaporanProduksi::whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
                ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
                ->with(['uraian.kategori', 'plant'])
                ->orderBy('tanggal')
                ->get();

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            // Fetch settings
            $settingNames = ['konversi_liter_to_kg', 'pouch_to_box_1_liter', 'pouch_to_box_2_liter', 'konversi_m_liter_to_kg'];
            $settings = Setting::whereIn('setting_name', $settingNames)->get();

            // Group data by kategori and then by id_uraian
            $groupedData = [];
            foreach ($data as $item) {
                $kategoriName = $item->uraian->kategori->nama;
                $uraianName = $item->uraian->nama;
                if (!isset($groupedData[$kategoriName])) {
                    $groupedData[$kategoriName] = [];
                }
                if (!isset($groupedData[$kategoriName][$uraianName])) {
                    $groupedData[$kategoriName][$uraianName] = [
                        'items' => [],
                        'total_value' => 0
                    ];
                }
                $groupedData[$kategoriName][$uraianName]['items'][] = $item;
                $groupedData[$kategoriName][$uraianName]['total_value'] += $item->value;
            }

            // Prepare final structure for response
            $finalData = [];
            foreach ($groupedData as $kategori => $uraianGroups) {
                $finalData[$kategori] = [];
                foreach ($uraianGroups as $uraian => $group) {
                    $finalData[$kategori] = array_merge($finalData[$kategori], $group['items']);
                    $totalKey = 'total ' . $uraian;
                    $finalData[$kategori][] = [$totalKey => $group['total_value']];
                }
            }

            return response()->json([
                'data' => $finalData,
                'setting' => $settings,
                'message' => $this->messageAll
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

    public function show($id)
    {
        try {
            $data = LaporanProduksi::with(['uraian.kategori', 'plant'])->find($id);

            // $data['history'] = $this->formatLogs($data->logs);
            // unset($data->logs);


            return response()->json([
                'data' => $data,
                'message' => $this->messageSuccess,
                'code' => 200
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'message' => $this->messageFail,
                'err' => $e->getTrace()[0],
                'errMsg' => $e->getMessage(),
                // 'code' => 500,
                'success' => false,
            ], 500);
        }
    }
}
