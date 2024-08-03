<?php

namespace App\Http\Controllers\Api\GL;

use App\Http\Controllers\Controller;
use App\Models\Debe;
use Carbon\Carbon;
use Illuminate\Http\Request;

class GeneralLedgerController extends Controller
{
    public function index_period(Request $request)
    {
        $tanggal = $request->get('tanggal');
        $date = Carbon::parse($tanggal);

        // Fetch only necessary fields
        $coa = Debe::pluck('coa')->toArray();
        $debe = Debe::with(['cat3', 'mReport', 'cCentre', 'plant', 'allocation'])->whereIn('coa', $coa)->get()->keyBy('coa');

        $glData = $this->getGeneralLedgerData($date);
        $filteredGlData = collect($glData)->filter(function ($item) use ($coa) {
            return in_array($item['account_account']['code'], $coa);
        });

        $totals = $filteredGlData->reduce(function ($carry, $item) {
            $carry['totalDebit'] += $item['debit'];
            $carry['totalCredit'] += $item['credit'];
            return $carry;
        }, ['totalDebit' => 0, 'totalCredit' => 0]);

        $totalDifference = $totals['totalDebit'] - $totals['totalCredit'];

        $filteredGlData->transform(function ($item) use ($debe) {
            $coaCode = $item['account_account']['code'];
            $item['debe'] = $debe->get($coaCode);
            return $item;
        });

        if ($filteredGlData->isEmpty()) {
            return response()->json([
                'message' => "No Data Found",
                'success' => false,
                'code' => 404
            ], 404);
        }

        return response()->json([
            'totalDebit' => $totals['totalDebit'],
            'totalCredit' => $totals['totalCredit'],
            'totalDifference' => $totalDifference,
            'data' => $filteredGlData->values(),
            'message' => 'Data Retrieved Successfully',
            'code' => 200,
            'success' => true,
        ], 200);
    }

}
