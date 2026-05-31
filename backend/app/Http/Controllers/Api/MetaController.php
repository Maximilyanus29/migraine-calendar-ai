<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomTrigger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class MetaController extends Controller
{
    private const CATEGORIES = ['triggers', 'pain_types', 'localizations', 'symptoms', 'auras'];

    public function options(): JsonResponse
    {
        if (! Auth::check()) {
            return response()->json(['error' => 'Unauthorized', 'details' => []], 401);
        }

        $options = config('migraine.options', []);
        $labels = $options['labels'] ?? [];

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $customTriggers = collect();
        if (Schema::hasTable('custom_triggers')) {
            $customTriggers = CustomTrigger::query()
                ->where('user_id', $user->id)
                ->where('status', '!=', 'rejected')
                ->orderBy('category')
                ->orderBy('name')
                ->get(['id', 'category', 'name']);
        }

        foreach (self::CATEGORIES as $category) {
            $labels[$category] = $labels[$category] ?? [];
            $options[$category] = array_values($options[$category] ?? []);
        }

        foreach ($customTriggers as $item) {
            $category = (string) $item->category;
            if (! in_array($category, self::CATEGORIES, true)) {
                continue;
            }

            $key = 'custom:'.$item->id;
            $options[$category][] = $key;
            $labels[$category][$key] = $item->name;
        }

        $options['labels'] = $labels;

        return response()->json([
            'data' => $options,
        ]);
    }
}
