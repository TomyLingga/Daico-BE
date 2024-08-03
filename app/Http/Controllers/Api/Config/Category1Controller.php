<?php

namespace App\Http\Controllers\Api\Config;

use App\Http\Controllers\Controller;
use App\Models\Category1;
use App\Models\Category2;
use App\Models\Category3;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;
use App\Rules\UniqueValues;
use Illuminate\Validation\Rule;
use App\Services\LoggerService;

class Category1Controller extends Controller
{
    private $messageFail = 'Something went wrong';
    private $messageMissing = 'Data not found in record';
    private $messageAll = 'Success to Fetch All Datas';
    private $messageSuccess = 'Success to Fetch Data';
    private $messageCreate = 'Success to Create Data';
    private $messageUpdate = 'Success to Update Data';

    public function index()
    {
        $category1 = Category1::with([
            'cat2.cat3',
        ])
        ->orderBy('nama', 'asc')
        ->get();

        if ($category1->isEmpty()) {
            return response()->json([
                'message' => $this->messageMissing,
                'success' => true,
                'code' => 401
            ], 401);
        }

        return response()->json([
            'data' => $category1,
            'message' => $this->messageAll,
            'code' => 200,
            'success' => true,
        ], 200);
    }

    public function show($id)
    {
        try {

            $category1 = Category1::with([
                'cat2.cat3',
            ])->find($id);

            if (!$category1) {

                return response()->json([
                    'message' => $this->messageMissing,
                    'success' => true,
                    'code' => 401
                ], 401);
            }

            $category1->history = $this->formatLogsForMultiple($category1->logs);
            unset($category1->logs);

            return response()->json([
                'data' => $category1,
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
                'nama_category1' => 'required',
                'category2.*.nama_category2' => 'required',
                'category2.*.category3.*.nama_category3' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'code' => 400,
                    'success' => false
                ], 400);
            }

            $data = Category1::create([
                'nama' => $request->nama_category1
            ]);

            $category2s = $request->input('category2', []);

            foreach ($category2s as $category2Data) {
                $category2 = $data->cat2()->create([
                    'id_category1' => $data->id,
                    'nama' => $category2Data['nama_category2'],
                ]);

                if (isset($category2Data['category3'])) {
                    foreach ($category2Data['category3'] as $category3Data) {
                        $category2->cat3()->create([
                            'id_category2' => $category2->id,
                            'nama' => $category3Data['nama_category3'],
                        ]);
                    }
                }
            }

            DB::commit();

            $data->load('cat2.cat3');

            LoggerService::logAction($this->userData, $data, 'create', null, $data->toArray());

            // Return success response
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
            $category1 = Category1::with(['cat2.cat3'])->findOrFail($id);

            if (!$category1) {
                return response()->json([
                    'message' => $this->messageMissing,
                    'success' => true,
                    'code' => 401
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'nama_category1' => 'required',
                'category2.*.id' => 'nullable',
                'category2.*.nama_category2' => 'required',
                'category2.*.category3.*.id' => 'nullable',
                'category2.*.category3.*.nama_category3' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'code' => 400,
                    'success' => false
                ], 400);
            }
            $oldData = $category1->toArray();

            $category1->update([
                'nama' => $request->nama_category1,
            ]);

            $updatedCategories2Ids = [];
            $category2s = $request->input('category2', []);

            foreach ($category2s as $category2Data) {
                if (isset($category2Data['id'])) {
                    $category2 = Category2::where('id_category1', $category1->id)->find($category2Data['id']);

                    if ($category2) {
                        $category2->update([
                            'nama' => $category2Data['nama_category2'],
                        ]);

                        $updatedCategories2Ids[] = $category2->id;

                        $updatedCategory3Ids = [];
                        $category3s = $category2Data['category3'] ?? [];

                        foreach ($category3s as $category3Data) {
                            if (isset($category3Data['id'])) {
                                $category3 = Category3::where('id_category2', $category2->id)->find($category3Data['id']);

                                if ($category3) {
                                    $category3->update([
                                        'nama' => $category3Data['nama_category3'],
                                    ]);

                                    $updatedCategory3Ids[] = $category3->id;
                                }
                            } else {
                                $category3 = Category3::create([
                                    'id_category2' => $category2->id,
                                    'nama' => $category3Data['nama_category3'],
                                ]);

                                $updatedCategory3Ids[] = $category3->id;
                            }
                        }

                        // Delete Category3s not included in the updated ones
                        $deletedCategory3Ids = $category2->cat3()->whereNotIn('id', $updatedCategory3Ids)->pluck('id');
                        Category3::whereIn('id', $deletedCategory3Ids)->delete();
                    }
                } else {
                    $category2 = Category2::create([
                        'id_category1' => $category1->id,
                        'nama' => $category2Data['nama_category2'],
                    ]);

                    $updatedCategories2Ids[] = $category2->id;

                    // Process Category3 for newly created Category2
                    $updatedCategory3Ids = [];
                    $category3s = $category2Data['category3'] ?? [];

                    foreach ($category3s as $category3Data) {
                        $category3 = Category3::create([
                            'id_category2' => $category2->id,
                            'nama' => $category3Data['nama_category3'],
                        ]);

                        $updatedCategory3Ids[] = $category3->id;
                    }
                }
            }

            // Delete Category2s not included in the updated ones
            $deletedCategory2Ids = $category1->cat2()->whereNotIn('id', $updatedCategories2Ids)->pluck('id');
            Category2::whereIn('id', $deletedCategory2Ids)->delete();

            $category1->load('cat2.cat3');

            LoggerService::logAction($this->userData, $category1, 'update', $oldData, $category1->toArray());

            DB::commit();

            return response()->json([
                'data' => $category1,
                'message' => $this->messageUpdate,
                'code' => 200,
                'success' => true,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $ex) {
            DB::rollback();
            return response()->json([
                'message' => 'Category1 not found.',
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
