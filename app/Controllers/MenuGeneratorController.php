<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MenuGeneratorService;
use System\Http\Request;
use System\Http\Response;
use Throwable;

class MenuGeneratorController
{
    private MenuGeneratorService $service;

    public function __construct()
    {
        $this->service = new MenuGeneratorService();
    }

    public function index(Request $request): Response
    {
        $auth = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
        $activeTab = trim((string) $request->input('tab', 'config'));
        if (!in_array($activeTab, ['config', 'menu-order'], true)) {
            $activeTab = 'config';
        }
        $menuOrderItems = [];
        try {
            $menuOrderItems = $this->service->listSidebarOrderItems();
        } catch (Throwable) {
            // no-op
        }

        $html = app()->view()->render('menu_generator/index', [
            'title' => 'Menu Generator',
            'auth' => $auth,
            'activeMenu' => 'menu-generator',
            'activeTab' => $activeTab,
            'menuOrderItems' => $menuOrderItems,
        ]);

        return Response::html($html);
    }

    public function datatable(Request $request): Response
    {
        try {
            $payload = $this->service->datatable($request->all());
            return Response::json($payload);
        } catch (Throwable) {
            return Response::json([
                'draw' => (int) $request->input('draw', '0'),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Gagal memuat data generator.',
            ], 500);
        }
    }

    public function create(Request $request): Response
    {
        $auth = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
        $tables = [];
        try {
            $tables = $this->service->listTables();
        } catch (Throwable) {
            toast_add('Gagal memuat daftar tabel.', 'error');
        }

        $html = app()->view()->render('menu_generator/create', [
            'title' => 'Buat Generator',
            'auth' => $auth,
            'activeMenu' => 'menu-generator',
            'tables' => $tables,
        ]);

        return Response::html($html);
    }

    public function scanTable(Request $request): Response
    {
        $tableName = trim((string) $request->input('table_name', ''));
        if ($tableName === '') {
            return Response::json([
                'ok' => false,
                'message' => 'Nama tabel wajib diisi.',
                'fields' => [],
            ], 422);
        }

        try {
            $scan = $this->service->scanTable($tableName);
            return Response::json([
                'ok' => true,
                'message' => 'Scan tabel berhasil.',
                'table_name' => $scan['table_name'] ?? $tableName,
                'fields' => $scan['fields'] ?? [],
            ]);
        } catch (Throwable) {
            return Response::json([
                'ok' => false,
                'message' => 'Scan tabel gagal.',
                'fields' => [],
            ], 500);
        }
    }

    public function store(Request $request): Response
    {
        $actorId = (int) ($_SESSION['auth']['id'] ?? 0);
        try {
            $generatorId = $this->service->store($request->all(), $actorId);
            toast_add('Konfigurasi menu generator berhasil disimpan.', 'success');
            return Response::redirect('/menu-generator/edit?id=' . urlencode((string) signed_id_encode($generatorId)));
        } catch (Throwable $e) {
            toast_add('Gagal menyimpan konfigurasi generator: ' . $this->safeError($e), 'error');
            return Response::redirect('/menu-generator/create');
        }
    }

    public function edit(Request $request): Response
    {
        $auth = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
        $token = trim((string) $request->input('id', ''));
        $generator = $this->service->findByToken($token);
        if ($generator === []) {
            toast_add('Token generator tidak valid atau data tidak ditemukan.', 'error');
            return Response::redirect('/menu-generator');
        }

        $generatorId = (int) ($generator['id'] ?? 0);
        $fields = $this->service->getFields($generatorId);
        $tables = [];
        try {
            $tables = $this->service->listTables();
        } catch (Throwable) {
            // no-op
        }

        $html = app()->view()->render('menu_generator/edit', [
            'title' => 'Edit Generator',
            'auth' => $auth,
            'activeMenu' => 'menu-generator',
            'generator' => $generator,
            'fields' => $fields,
            'tables' => $tables,
        ]);

        return Response::html($html);
    }

    public function update(Request $request): Response
    {
        $actorId = (int) ($_SESSION['auth']['id'] ?? 0);
        $token = trim((string) $request->input('id', ''));
        $generatorId = signed_id_decode($token);
        if ($generatorId === null || $generatorId <= 0) {
            toast_add('Token generator tidak valid.', 'error');
            return Response::redirect('/menu-generator');
        }

        try {
            $this->service->update($generatorId, $request->all(), $actorId);
            toast_add('Konfigurasi generator berhasil diperbarui.', 'success');
        } catch (Throwable $e) {
            toast_add('Gagal memperbarui konfigurasi: ' . $this->safeError($e), 'error');
        }

        return Response::redirect('/menu-generator/edit?id=' . urlencode((string) $token));
    }

    public function generate(Request $request): Response
    {
        $actorId = (int) ($_SESSION['auth']['id'] ?? 0);
        $token = trim((string) $request->input('id', ''));
        $generatorId = signed_id_decode($token);
        if ($generatorId === null || $generatorId <= 0) {
            toast_add('Token generator tidak valid.', 'error');
            return Response::redirect('/menu-generator');
        }

        try {
            $this->service->generate($generatorId, $actorId);
            toast_add('Generate CRUD berhasil.', 'success');
        } catch (Throwable $e) {
            toast_add('Generate CRUD gagal: ' . $this->safeError($e), 'error');
        }

        return Response::redirect('/menu-generator/edit?id=' . urlencode((string) $token));
    }

    public function delete(Request $request): Response
    {
        $actorId = (int) ($_SESSION['auth']['id'] ?? 0);
        $token = trim((string) $request->input('id', ''));
        $generatorId = signed_id_decode($token);
        if ($generatorId === null || $generatorId <= 0) {
            toast_add('Token generator tidak valid.', 'error');
            return Response::redirect('/menu-generator');
        }

        try {
            $this->service->deleteConfig($generatorId, $actorId);
            toast_add('Konfigurasi generator berhasil dinonaktifkan.', 'success');
        } catch (Throwable $e) {
            toast_add('Gagal menghapus konfigurasi: ' . $this->safeError($e), 'error');
        }

        return Response::redirect('/menu-generator');
    }

    public function deleteGenerated(Request $request): Response
    {
        $actorId = (int) ($_SESSION['auth']['id'] ?? 0);
        $token = trim((string) $request->input('id', ''));
        $generatorId = signed_id_decode($token);
        if ($generatorId === null || $generatorId <= 0) {
            toast_add('Token generator tidak valid.', 'error');
            return Response::redirect('/menu-generator');
        }

        try {
            $result = $this->service->deleteGenerated($generatorId, $actorId);
            $count = (int) ($result['deleted_count'] ?? 0);
            toast_add('File hasil generate berhasil dihapus (' . $count . ' file).', 'success');
        } catch (Throwable $e) {
            toast_add('Gagal menghapus file generate: ' . $this->safeError($e), 'error');
        }

        return Response::redirect('/menu-generator/edit?id=' . urlencode((string) $token));
    }

    public function updateMenuOrder(Request $request): Response
    {
        $actorId = (int) ($_SESSION['auth']['id'] ?? 0);
        $orderRaw = trim((string) $request->input('order_json', '[]'));
        $decoded = json_decode($orderRaw, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        $orderedIds = [];
        foreach ($decoded as $id) {
            if (is_numeric($id)) {
                $orderedIds[] = (int) $id;
            }
        }

        try {
            $this->service->updateSidebarMenuOrder($orderedIds, $actorId);
            toast_add('Urutan menu sidebar berhasil diperbarui.', 'success');
        } catch (Throwable $e) {
            toast_add('Gagal memperbarui urutan menu: ' . $this->safeError($e), 'error');
        }

        return Response::redirect('/menu-generator?tab=menu-order');
    }

    private function safeError(Throwable $throwable): string
    {
        $message = trim($throwable->getMessage());
        return $message !== '' ? $message : 'Terjadi kesalahan.';
    }
}
