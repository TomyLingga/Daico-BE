<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Carbon\Carbon;
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
    public $urlGeneralLedger;
    public $urlGeneralLedgerCoa;
    public $urlGeneralLedgerCoaPosted;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->token = $request->get('user_token');
            $this->userData = $request->get('decoded');
            $this->urlDept = env('BASE_URL_PORTAL')."department/get/";
            $this->urlAllDept = env('BASE_URL_PORTAL')."department";
            $this->urlUser = env('BASE_URL_PORTAL')."user/get/";
            $this->urlAllUser = env('BASE_URL_PORTAL')."user";
            $this->urlGeneralLedger = env('BASE_URL_ODOO')."account_move_line/index";
            $this->urlGeneralLedgerCoa = env('BASE_URL_ODOO')."account_move_line/coa";
            $this->urlGeneralLedgerCoaPosted = env('BASE_URL_ODOO')."account_move_line/posted";
            return $next($request);
        });
    }

    public function getGeneralLedgerData($tanggal)
    {
        return Http::withHeaders([
            'Authorization' => $this->token,
        ])->post($this->urlGeneralLedger, [
            'tanggal' => $tanggal
        ])->json()['data'] ?? [];
    }

    public function getGeneralLedgerDataWithCoa($tanggal, $coa)
    {
        $response = Http::withHeaders([
            'Authorization' => $this->token,
        ])->post($this->urlGeneralLedgerCoa, [
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
        // dd($logs);
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
            dd($this->getUserById($log->user_id));
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
}
