<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\ApiResponse;
use App\Services\MasterDataApiService;
use System\Http\Request;
use System\Http\Response;
use Throwable;

abstract class MasterDataController
{
    abstract protected function definition(): array;

    public function index(Request $request, MasterDataApiService $service): Response
    {
        $definition = $this->definition();
        try {
            $query = $request->all();
            $page = max(1, (int) ($query['page'] ?? 1));
            $perPage = min(100, max(1, (int) ($query['per_page'] ?? 20)));
            $filters = is_array($query['filter'] ?? null) ? $query['filter'] : [];
            foreach (($definition['filters'] ?? []) as $filter) {
                if (array_key_exists($filter, $query)) $filters[$filter] = $query[$filter];
            }
            $result = $service->paginate($definition['table'], $definition['fields'], $page, $perPage, trim((string) ($query['search'] ?? '')), trim((string) ($query['sort'] ?? 'id')), trim((string) ($query['direction'] ?? 'desc')), $filters);
            $total = (int) $result['total'];
            return ApiResponse::success($result['rows'], 200, ['pagination' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => max(1, (int) ceil($total / $perPage))]]);
        } catch (Throwable) {
            return ApiResponse::error('SERVER_ERROR', 'Gagal memuat ' . $definition['label'] . '.', 500);
        }
    }

    public function show(Request $request, MasterDataApiService $service, string $id): Response
    {
        $definition = $this->definition();
        $recordId = (int) $id;
        if ($recordId <= 0) return ApiResponse::error('INVALID_ID', 'ID ' . $definition['label'] . ' tidak valid.', 400);
        try {
            $row = $service->find($definition['table'], $definition['fields'], $recordId);
            return $row === null ? ApiResponse::error('NOT_FOUND', ucfirst($definition['label']) . ' tidak ditemukan.', 404) : ApiResponse::success($row);
        } catch (Throwable) {
            return ApiResponse::error('SERVER_ERROR', 'Gagal memuat ' . $definition['label'] . '.', 500);
        }
    }

    public function store(Request $request, MasterDataApiService $service): Response
    {
        return $this->persist($request, $service, null);
    }

    public function update(Request $request, MasterDataApiService $service, string $id): Response
    {
        $recordId = (int) $id;
        if ($recordId <= 0) return ApiResponse::error('INVALID_ID', 'ID tidak valid.', 400);
        return $this->persist($request, $service, $recordId);
    }

    public function destroy(Request $request, MasterDataApiService $service, string $id): Response
    {
        $definition = $this->definition();
        $recordId = (int) $id;
        if ($recordId <= 0) return ApiResponse::error('INVALID_ID', 'ID tidak valid.', 400);
        try {
            if (!$service->delete($definition['table'], $recordId)) return ApiResponse::error('NOT_FOUND', ucfirst($definition['label']) . ' tidak ditemukan.', 404);
            return ApiResponse::success(null);
        } catch (Throwable) {
            return ApiResponse::error('SERVER_ERROR', 'Gagal menghapus ' . $definition['label'] . '.', 500);
        }
    }

    private function persist(Request $request, MasterDataApiService $service, ?int $id): Response
    {
        $definition = $this->definition();
        $payload = $request->all();
        if ($payload === []) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            $payload = is_array($decoded) ? $decoded : [];
        }
        $data = [];
        $errors = [];
        foreach ($definition['writable'] as $field) {
            $config = $definition['schema'][$field] ?? [];
            if (!array_key_exists($field, $payload) && !$config['required']) continue;
            $value = $payload[$field] ?? null;
            if (is_string($value)) $value = trim($value);
            if (($value === null || $value === '') && $config['required']) {
                $errors[$field] = ['Field wajib diisi.'];
                continue;
            }
            if ($value !== null && $value !== '' && isset($config['max']) && mb_strlen((string) $value) > $config['max']) $errors[$field] = ['Maksimal ' . $config['max'] . ' karakter.'];
            if ($value !== null && $value !== '' && $config['type'] === 'int' && filter_var($value, FILTER_VALIDATE_INT) === false) $errors[$field] = ['Harus berupa angka bulat.'];
            if ($value !== null && $value !== '' && $config['type'] === 'date') {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
                if ($date === false || $date->format('Y-m-d') !== $value) $errors[$field] = ['Format tanggal harus YYYY-MM-DD.'];
            }
            if ($value !== null && $value !== '' && $config['type'] === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) $errors[$field] = ['Format email tidak valid.'];
            $data[$field] = $value === '' ? null : $value;
        }
        if ($errors !== []) return ApiResponse::error('VALIDATION_ERROR', 'Validasi gagal.', 422, $errors);
        try {
            $row = $id === null ? $service->create($definition['table'], $definition['fields'], $data) : $service->update($definition['table'], $definition['fields'], $id, $data);
            if ($row === null || $row === []) return ApiResponse::error('NOT_FOUND', ucfirst($definition['label']) . ' tidak ditemukan.', 404);
            return ApiResponse::success($row, $id === null ? 201 : 200);
        } catch (Throwable $exception) {
            $isConflict = $exception instanceof \PDOException && (($exception->errorInfo[0] ?? '') === '23000');
            return $isConflict ? ApiResponse::error('CONFLICT', 'Data duplikat atau masih dipakai.', 409) : ApiResponse::error('SERVER_ERROR', 'Gagal menyimpan ' . $definition['label'] . '.', 500);
        }
    }
}
