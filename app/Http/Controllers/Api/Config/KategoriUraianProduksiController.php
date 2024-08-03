<?php

namespace App\Http\Controllers\Api\Config;

use App\Http\Controllers\Controller;
use App\Models\KategoriUraianProduksi;
use App\Models\UraianProduksi;
use App\Services\LoggerService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class KategoriUraianProduksiController extends Controller
{
    private $messageFail = 'Something went wrong';
    private $messageMissing = 'Data not found in record';
    private $messageAll = 'Success to Fetch All Datas';
    private $messageSuccess = 'Success to Fetch Data';
    private $messageCreate = 'Success to Create Data';
    private $messageUpdate = 'Success to Update Data';

    public function index()
    {
        try {
            $kategoriUraians = KategoriUraianProduksi::with('uraian')->orderBy('id', 'asc')->get();

            if ($kategoriUraians->isEmpty()) {
                return response()->json([
                    'message' => $this->messageMissing,
                    'code' => 401
                ], 401);
            }

            return response()->json([
                'data' => $kategoriUraians,
                'message' => $this->messageAll,
                'code' => 200
            ], 200);
        } catch (QueryException $ex) {
            return response()->json([
                'message' => $this->messageFail,
                'errMsg' => $ex->getMessage(),
                'code' => 500
            ], 500);
        }
    }

    public function show($id)
    {
        try {

            $kategoriUraian = KategoriUraianProduksi::with(['uraian' => function ($query) {
                $query->orderBy('id', 'asc');
            }])->find($id);

            if (!$kategoriUraian) {

                return response()->json([
                    'message' => $this->messageMissing,
                    'success' => true,
                    'code' => 401
                ], 401);
            }

            $kategoriUraian->history = $this->formatLogsForMultiple($kategoriUraian->logs);
            unset($kategoriUraian->logs);

            return response()->json([
                'data' => $kategoriUraian,
                'message' => $this->messageSuccess,
                'code' => 200
            ], 200);
        } catch (\Illuminate\Database\QueryException $ex) {
            return response()->json([
                'message' => $this->messageFail,
                'err' => $ex->getTrace()[0],
                'errMsg' => $ex->getMessage(),
                'success' => false,
                'code' => 500,
            ], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'nama' => 'required',
                'uraian.*.nama' => 'required',
                'uraian.*.satuan' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'code' => 400,
                    'success' => false
                ], 400);
            }

            $data = KategoriUraianProduksi::create([
                'nama' => $request->nama,
            ]);

            $uraians = $request->input('uraian', []);

            $uraianData = collect($uraians)->map(function ($uraian) use ($data) {
                return [
                    'id_category' => $data->id,
                    'nama' => $uraian['nama'],
                    'satuan' => $uraian['satuan'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->all();

            UraianProduksi::insert($uraianData);

            $data->load('uraian');

            LoggerService::logAction($this->userData, $data, 'create', null, $data->toArray());

            DB::commit();

            return response()->json([
                'data' => $data,
                'message' => $this->messageCreate,
                'code' => 200,
                'success' => true,
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $this->messageFail,
                'err' => $e->getTrace()[0],
                'errMsg' => $e->getMessage(),
                'code' => 500,
                'success' => false,
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $kategoriUraian = KategoriUraianProduksi::with(['uraian' => function ($query) {
                $query->orderBy('id', 'asc');
            }])->findOrFail($id);

            if (!$kategoriUraian) {

                return response()->json([
                    'message' => $this->messageMissing,
                    'success' => true,
                    'code' => 401
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'nama' => 'required',
                'uraian.*.id' => 'nullable',
                'uraian.*.nama' => 'required',
                'uraian.*.satuan' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'code' => 400,
                    'success' => false
                ], 400);
            }
            $oldData = $kategoriUraian->toArray();

            $kategoriUraian->update([
                'nama' => $request->nama,
            ]);

            $updatedSubGroupIds = [];

            foreach ($request->uraian as $uraianData) {
                if (isset($uraianData['id'])) {
                    $uraian = UraianProduksi::where('id_category', $kategoriUraian->id)->find($uraianData['id']);

                    if ($uraian) {
                        $uraian->update([
                            'nama' => $uraianData['nama'],
                            'satuan' => $uraianData['satuan']
                        ]);

                        $updatedSubGroupIds[] = $uraian->id;
                    }
                } else {
                    $uraian = UraianProduksi::create([
                        'id_category' => $kategoriUraian->id,
                        'nama' => $uraianData['nama'],
                        'satuan' => $uraianData['satuan'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $updatedSubGroupIds[] = $uraian->id;
                }
            }

            $deletedSubGroupIds = $kategoriUraian->uraian()->whereNotIn('id', $updatedSubGroupIds)->pluck('id');
            UraianProduksi::whereIn('id', $deletedSubGroupIds)->delete();

            $kategoriUraian->load('uraian');

            LoggerService::logAction($this->userData, $kategoriUraian, 'update', $oldData, $kategoriUraian->toArray());

            DB::commit();

            return response()->json([
                'data' => $kategoriUraian,
                'message' => $this->messageUpdate,
                'code' => 200,
                'success' => true,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $ex) {
            DB::rollback();
            return response()->json([
                'message' => 'Group not found.',
                'err' => $ex->getTrace()[0],
                'errMsg' => $ex->getMessage(),
                'code' => 401,
                'success' => false,
            ], 401);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $this->messageFail,
                'err' => $e->getTrace()[0],
                'errMsg' => $e->getMessage(),
                'code' => 500,
                'success' => false,
            ], 500);
        }
    }
}
