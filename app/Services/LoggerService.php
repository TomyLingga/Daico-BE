<?php

namespace App\Services;

use App\Models\Log;

class LoggerService
{
    public static function logAction($user, $model, $action, $oldData = null, $newData = null)
    {
        $oldDataJson = json_encode($oldData);
        $newDataJson = json_encode($newData);

        Log::create([
            'user_id' => $user->sub,
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'action' => $action,
            'old_data' => $oldDataJson,
            'new_data' => $newDataJson,
        ]);
    }
}
