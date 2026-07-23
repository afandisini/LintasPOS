<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\ApiResponse;
use App\Services\SatuanApiService;
use System\Http\Request;
use System\Http\Response;
use Throwable;

final class SatuanController
{
    public function index(Request $request, SatuanApiService $service): Response
    {
        try {
            $query = $request->all();
            $page = max(1, (int) ($query['page'] ?? 1));
            $perPage = min(100, max(1, (int) ($query['per_page'] ?? 20)));
            $result = $service->paginate($page, $perPage, trim((string) ($query['search'] ?? '')), trim((string) ($query['sort'] ?? 'id')), trim((string) ($query['direction'] ?? 'desc')));
            $total = (int) $result['total'];
            return ApiResponse::success($result['rows'], 200, ['pagination' => [
                'page' => $page, 'per_page' => $perPage, 'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ]]);
        } catch (Throwable) {
            return ApiResponse::error('SERVER_ERROR', 'Gagal memuat satuan.', 500);
        }
    }

    public function show(Request $request, SatuanApiService $service, string $id): Response
    {
        $recordId = (int) $id;
        if ($recordId <= 0) return ApiResponse::error('INVALID_ID', 'ID satuan tidak valid.', 400);
        try {
            $row = $service->find($recordId);
            return $row === null ? ApiResponse::error('NOT_FOUND', 'Satuan tidak ditemukan.', 404) : ApiResponse::success($row);
        } catch (Throwable) {
            return ApiResponse::error('SERVER_ERROR', 'Gagal memuat satuan.', 500);
        }
    }

    public function store(Request $request, SatuanApiService $service): Response
    {
        return $this->persist($request, $service, null);
    }

    public function update(Request $request, SatuanApiService $service, string $id): Response
    {
        $recordId = (int) $id;
        if ($recordId <= 0) return ApiResponse::error('INVALID_ID', 'ID satuan tidak valid.', 400);
        return $this->persist($request, $service, $recordId);
    }

    public function destroy(Request $request, SatuanApiService $service, string $id): Response
    {
        $recordId = (int) $id;
        if ($recordId <= 0) return ApiResponse::error('INVALID_ID', 'ID satuan tidak valid.', 400);
        try {
            if (!$service->delete($recordId)) return ApiResponse::error('NOT_FOUND', 'Satuan tidak ditemukan.', 404);
            return ApiResponse::success(null);
        } catch (Throwable) {
            return ApiResponse::error('SERVER_ERROR', 'Gagal menghapus satuan.', 500);
        }
    }

    private function persist(Request $request, SatuanApiService $service, ?int $id): Response
    {
        $data = $request->all();
        if ($data === []) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            $data = is_array($decoded) ? $decoded : [];
        }
        $nama = trim((string) ($data['nama'] ?? ''));
        $ket = array_key_exists('ket', $data) ? trim((string) $data['ket']) : null;
        $errors = [];
        if ($nama === '') $errors['nama'] = ['Nama satuan wajib diisi.'];
        elseif (mb_strlen($nama) > 255) $errors['nama'] = ['Nama satuan maksimal 255 karakter.'];
        if ($ket !== null && mb_strlen($ket) > 65535) $errors['ket'] = ['Keterangan terlalu panjang.'];
        if ($errors !== []) return ApiResponse::error('VALIDATION_ERROR', 'Validasi gagal.', 422, $errors);
        try {
            $row = $id === null
                ? $service->create(['nama' => $nama, 'ket' => $ket !== '' ? $ket : null])
                : $service->update($id, ['nama' => $nama, 'ket' => $ket !== '' ? $ket : null]);
            if ($row === null || $row === []) return ApiResponse::error('NOT_FOUND', 'Satuan tidak ditemukan.', 404);
            return ApiResponse::success($row, $id === null ? 201 : 200);
        } catch (Throwable) {
            return ApiResponse::error('SERVER_ERROR', 'Gagal menyimpan satuan.', 500);
        }
    }
}
