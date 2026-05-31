<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attack;
use App\Models\CustomTrigger;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class AttackController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $this->authUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }

        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
        ]);

        $from = CarbonImmutable::createFromFormat('Y-m-d', $validated['from'])->startOfDay();
        $to = CarbonImmutable::createFromFormat('Y-m-d', $validated['to'])->endOfDay();

        $attacks = Attack::query()
            ->where('user_id', $userId)
            ->where('start_at', '<', $to)
            ->where(function ($query) use ($from): void {
                $query->whereNull('end_at')
                    ->orWhere('end_at', '>', $from);
            })
            ->orderBy('start_at')
            ->get();

        return response()->json(['data' => $attacks]);
    }

    public function last(): JsonResponse
    {
        $userId = $this->authUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }

        $attack = Attack::query()
            ->where('user_id', $userId)
            ->orderByDesc('start_at')
            ->first();

        return response()->json(['data' => $attack ?? (object) []]);
    }

    public function store(Request $request): JsonResponse
    {
        $userId = $this->authUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }

        $data = $this->validatedPayload($request, $userId);
        $data['user_id'] = $userId;

        $attack = Attack::create($data);

        return response()->json(['data' => $attack], 201);
    }

    public function show(Attack $attack): JsonResponse
    {
        $userId = $this->authUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }

        if ($attack->user_id !== $userId) {
            return response()->json(['error' => 'Attack not found', 'details' => []], 404);
        }

        return response()->json(['data' => $attack]);
    }

    public function update(Request $request, Attack $attack): JsonResponse
    {
        $userId = $this->authUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }

        if ($attack->user_id !== $userId) {
            return response()->json(['error' => 'Attack not found', 'details' => []], 404);
        }

        $attack->update($this->validatedPayload($request, $userId));

        return response()->json(['data' => $attack->fresh()]);
    }

    public function destroy(Attack $attack): JsonResponse
    {
        $userId = $this->authUserId();
        if ($userId === null) {
            return $this->unauthorized();
        }

        if ($attack->user_id !== $userId) {
            return response()->json(['data' => ['deleted' => false]]);
        }

        $attack->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    private function validatedPayload(Request $request, int $userId): array
    {
        $validated = $request->validate([
            'start_at' => ['required', 'date', 'before_or_equal:now'],
            'end_at' => ['nullable', 'date', 'after:start_at', 'before_or_equal:now'],
            'intensity' => ['required', 'integer', 'min:1', 'max:10'],
            'medications' => ['nullable', 'string'],
            'relief' => ['nullable', 'boolean'],
            'pain_types' => ['sometimes', 'array'],
            'pain_types.*' => $this->optionValueRules('pain_types', $userId),
            'localizations' => ['sometimes', 'array'],
            'localizations.*' => $this->optionValueRules('localizations', $userId),
            'triggers' => ['sometimes', 'array'],
            'triggers.*' => $this->optionValueRules('triggers', $userId),
            'symptoms' => ['sometimes', 'array'],
            'symptoms.*' => $this->optionValueRules('symptoms', $userId),
            'auras' => ['sometimes', 'array'],
            'auras.*' => $this->optionValueRules('auras', $userId),
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        foreach (['pain_types', 'localizations', 'triggers', 'symptoms', 'auras'] as $field) {
            $validated[$field] = array_values($validated[$field] ?? []);
        }

        return $validated;
    }

    /**
     * @return array<int, \Closure|string>
     */
    private function optionValueRules(string $category, int $userId): array
    {
        return [
            'string',
            function (string $attribute, mixed $value, \Closure $fail) use ($category, $userId): void {
                if (! is_string($value) || ! $this->isAllowedOptionValue($value, $category, $userId)) {
                    $fail('Недопустимое значение.');
                }
            },
        ];
    }

    private function isAllowedOptionValue(string $value, string $category, int $userId): bool
    {
        $base = config('migraine.options.'.$category, []);
        if (in_array($value, $base, true)) {
            return true;
        }

        if (preg_match('/^local:[a-zA-Z0-9_-]{8,64}$/', $value) === 1) {
            return true;
        }

        if (preg_match('/^custom:\d+$/', $value) !== 1 || ! Schema::hasTable('custom_triggers')) {
            return false;
        }

        $id = (int) substr($value, 7);

        return CustomTrigger::query()
            ->where('user_id', $userId)
            ->where('id', $id)
            ->where('status', '!=', 'rejected')
            ->exists();
    }

    private function authUserId(): ?int
    {
        $id = Auth::id();

        return is_int($id) ? $id : null;
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json(['error' => 'Unauthorized', 'details' => []], 401);
    }
}
