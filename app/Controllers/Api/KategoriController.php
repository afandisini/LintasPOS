<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\ApiResponse;
use App\Services\KategoriApiService;
use System\Http\Request;
use System\Http\Response;
use Throwable;

final class KategoriController
{
    public function index(Request $request, KategoriApiService $service): Response
    {
        try {
            $query = $request->all();
            $page = max(1, (int) ($query['page'] ?? 1));
            $perPage = min(100, max(1, (int) ($query['per_page'] ?? 20)));
            $result = $service->paginate(
                $page,
                $perPage,
                trim((string) ($query['search'] ?? '')),
                trim((string) ($query['sort'] ?? 'id')),
                trim((string) ($query['direction'] ?? 'desc'))
            );
            $total = (int) $result['total'];
            return ApiResponse::success($result['rows'], 200, [
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => max(1, (int) ceil($total / $perPage)),
                ],
            ]);
        } catch (Throwable) {
            return ApiResponse::error('SERVER_ERROR', 'Gagal memuat kategori.', 500);
        }
    }

    public function show(Request $request, KategoriApiService $service, string $id): Response
    {
        $recordId = (int) $id;
        if ($recordId <= 0) {
            return ApiResponse::error('INVALID_ID', 'ID kategori tidak valid.', 400);
        }
        try {
            $row = $service->find($recordId);
            return $row === null
                ? ApiResponse::error('NOT_FOUND', 'Kategori tidak ditemukan.', 404)
                : ApiResponse::success($row);
        } catch (Throwable) {
            return ApiResponse::error('SERVER_ERROR', 'Gagal memuat kategori.', 500);
        }
    }

    public function store(Request $request, KategoriApiService $service): Response
    {
        return $this->persist($request, $service, null);
    }

    public function update(Request $request, KategoriApiService $service, string $id): Response
    {
        $recordId = (int) $id;
        if ($recordId <= 0) {
            return ApiResponse::error('INVALID_ID', 'ID kategori tidak valid.', 400);
        }
        return $this->persist($request, $service, $recordId);
    }

    public function destroy(Request $request, KategoriApiService $service, string $id): Response
    {
        $recordId = (int) $id;
        if ($recordId <= 0) {
            return ApiResponse::error('INVALID_ID', 'ID kategori tidak valid.', 400);
        }
        try {
            if (!$service->delete($recordId)) {
                return ApiResponse::error('NOT_FOUND', 'Kategori tidak ditemukan.', 404);
            }
            return ApiResponse::success(null, 200);
        } catch (Throwable) {
            return ApiResponse::error('SERVER_ERROR', 'Gagal menghapus kategori.', 500);
        }
    }

    private function persist(Request $request, KategoriApiService $service, ?int $id): Response
    {
        $data = $this->payload($request);
        $errors = [];
        $nama = trim((string) ($data['nama'] ?? ''));
        $ket = array_key_exists('ket', $data) ? trim((string) $data['ket']) : null;
        $tglInput = trim((string) ($data['tgl_input'] ?? date('Y-m-d')));
        if ($nama === '') {
            $errors['nama'] = ['Nama kategori wajib diisi.'];
        } elseif (mb_strlen($nama) > 255) {
            $errors['nama'] = ['Nama kategori maksimal 255 karakter.'];
        }
        if ($ket !== null && mb_strlen($ket) > 65535) {
            $errors['ket'] = ['Keterangan terlalu panjang.'];
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $tglInput);
        if ($date === false || $date->format('Y-m-d') !== $tglInput) {
            $errors['tgl_input'] = ['Tanggal input harus berformat YYYY-MM-DD.'];
        }
        if ($errors !== []) {
            return ApiResponse::error('VALIDATION_ERROR', 'Validasi gagal.', 422, $errors);
        }

        try {
            $payload = ['nama' => $nama, 'ket' => $ket !== '' ? $ket : null, 'tgl_input' => $tglInput];
            $row = $id === null ? $service->create($payload) : $service->update($id, $payload);
            if ($row === null || $row === []) {
                return ApiResponse::error('NOT_FOUND', 'Kategori tidak ditemukan.', 404);
            }
            return ApiResponse::success($row, $id === null ? 201 : 200);
        } catch (Throwable) {
            return ApiResponse::error('SERVER_ERROR', 'Gagal menyimpan kategori.', 500);
        }
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        $payload = $request->all();
        if ($payload !== []) {
            return $payload;
        }
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : [];
    }
}
