<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomTrigger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class CustomTriggerController extends Controller
{
    private const CATEGORIES = ['triggers', 'pain_types', 'localizations', 'symptoms', 'auras'];

    public function index(): JsonResponse
    {
        $userId = $this->authUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }

        if (! Schema::hasTable('custom_triggers')) {
            return response()->json(['data' => []]);
        }

        $triggers = CustomTrigger::query()
            ->where('user_id', $userId)
            ->orderBy('category')
            ->orderByDesc('id')
            ->get(['id', 'category', 'name', 'status', 'created_at', 'approved_at']);

        return response()->json(['data' => $triggers]);
    }

    public function store(Request $request): JsonResponse
    {
        $userId = $this->authUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }

        $data = $request->validate([
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'name' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[\p{L}\p{N}\s\-().,]+$/u'],
        ]);

        if (! Schema::hasTable('custom_triggers')) {
            return response()->json(['error' => 'Сначала обнови базу данных (migrate)', 'details' => []], 409);
        }

        $category = $data['category'];
        $name = trim($data['name']);
        $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', $name) ?? '');

        if ($normalized === '') {
            return response()->json(['error' => 'Название не может быть пустым', 'details' => []], 422);
        }

        $reserved = array_map(
            static fn (string $value): string => mb_strtolower((string) $value),
            config('migraine.options.labels.'.$category, [])
        );

        if (in_array($normalized, $reserved, true)) {
            return response()->json(['error' => 'Такое значение уже есть в общем списке', 'details' => []], 422);
        }

        $exists = CustomTrigger::query()
            ->where('user_id', $userId)
            ->where('category', $category)
            ->where('name_normalized', $normalized)
            ->exists();

        if ($exists) {
            return response()->json(['error' => 'Такое пользовательское значение уже добавлено', 'details' => []], 422);
        }

        $trigger = CustomTrigger::create([
            'user_id' => $userId,
            'category' => $category,
            'name' => $name,
            'name_normalized' => $normalized,
            'status' => 'pending',
        ]);

        return response()->json(['data' => $trigger], 201);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $admin = $this->authAdmin();
        if ($admin === null) {
            return $this->forbidden();
        }

        if (! Schema::hasTable('custom_triggers')) {
            return response()->json(['data' => []]);
        }

        $status = (string) $request->query('status', 'pending');
        $category = (string) $request->query('category', 'all');
        $validated = validator(['status' => $status], [
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected', 'all'])],
        ])->validate();
        $validatedCategory = validator(['category' => $category], [
            'category' => ['required', Rule::in([...self::CATEGORIES, 'all'])],
        ])->validate();

        $query = CustomTrigger::query()
            ->with('user:id,email,name')
            ->orderByDesc('id');

        if ($validated['status'] !== 'all') {
            $query->where('status', $validated['status']);
        }
        if ($validatedCategory['category'] !== 'all') {
            $query->where('category', $validatedCategory['category']);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomTrigger> $items */
        $items = $query->get();
        $usageByTrigger = $this->usageStatsByTriggerId();

        return response()->json([
            'data' => $items->map(function (CustomTrigger $trigger) use ($usageByTrigger): array {
                $usage = $usageByTrigger[$trigger->id] ?? ['usage_count' => 0, 'unique_users_count' => 0];
                $owner = $trigger->user;
                $ownerId = null;
                $ownerEmail = null;
                $ownerName = null;
                if ($owner instanceof \App\Models\User) {
                    $ownerId = $owner->id;
                    $ownerEmail = $owner->email;
                    $ownerName = $owner->name;
                }

                return [
                    'id' => $trigger->id,
                    'category' => $trigger->category,
                    'name' => $trigger->name,
                    'status' => $trigger->status,
                    'created_at' => $trigger->created_at,
                    'approved_at' => $trigger->approved_at,
                    'usage_count' => (int) $usage['usage_count'],
                    'unique_users_count' => (int) $usage['unique_users_count'],
                    'user' => [
                        'id' => $ownerId,
                        'email' => $ownerEmail,
                        'name' => $ownerName,
                    ],
                ];
            })->values(),
        ]);
    }

    public function approve(CustomTrigger $customTrigger): JsonResponse
    {
        $admin = $this->authAdmin();
        if ($admin === null) {
            return $this->forbidden();
        }

        $customTrigger->status = 'approved';
        $customTrigger->approved_at = now();
        $customTrigger->save();

        return response()->json(['data' => $customTrigger->fresh()]);
    }

    public function reject(CustomTrigger $customTrigger): JsonResponse
    {
        $admin = $this->authAdmin();
        if ($admin === null) {
            return $this->forbidden();
        }

        $customTrigger->status = 'rejected';
        $customTrigger->approved_at = null;
        $customTrigger->save();

        return response()->json(['data' => $customTrigger->fresh()]);
    }

    private function authUserId(): ?int
    {
        $id = Auth::id();

        return is_int($id) ? $id : null;
    }

    private function authAdmin(): ?\App\Models\User
    {
        $user = Auth::user();
        if (! $user instanceof \App\Models\User || ! $user->is_admin) {
            return null;
        }

        return $user;
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json(['error' => 'Unauthorized', 'details' => []], 401);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['error' => 'Forbidden', 'details' => []], 403);
    }

    private function usageStatsByTriggerId(): array
    {
        if (! Schema::hasTable('attacks') || ! Schema::hasTable('custom_triggers')) {
            return [];
        }

        $rows = DB::select(<<<'SQL'
            SELECT
                CAST(REPLACE(a.value, 'custom:', '') AS BIGINT) AS option_id,
                COUNT(*) AS usage_count,
                COUNT(DISTINCT a.user_id) AS unique_users_count
            FROM (
                SELECT user_id, jsonb_array_elements_text(triggers) AS value FROM attacks
                UNION ALL
                SELECT user_id, jsonb_array_elements_text(pain_types) AS value FROM attacks
                UNION ALL
                SELECT user_id, jsonb_array_elements_text(localizations) AS value FROM attacks
                UNION ALL
                SELECT user_id, jsonb_array_elements_text(symptoms) AS value FROM attacks
                UNION ALL
                SELECT user_id, jsonb_array_elements_text(auras) AS value FROM attacks
            ) AS a
            WHERE a.value LIKE 'custom:%'
            GROUP BY option_id
        SQL);

        $result = [];
        foreach ($rows as $row) {
            $id = (int) $row->option_id;
            $result[$id] = [
                'usage_count' => (int) $row->usage_count,
                'unique_users_count' => (int) $row->unique_users_count,
            ];
        }

        return $result;
    }
}
