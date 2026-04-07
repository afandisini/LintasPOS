<?php

declare(strict_types=1);

namespace App\Controllers;

use System\Http\Request;
use System\Http\Response;

class HomeController
{
    public function index(Request $request): Response
    {
        $flash = $_SESSION['_flash'] ?? null;
        if (isset($_SESSION['_flash'])) {
            unset($_SESSION['_flash']);
        }

        $html = app()->view()->renderWithLayout('home', [
            'title' => brand_name(),
            'tagline' => 'AitiCore - Simplicity and security',
            'message' => is_array($flash) ? (string) ($flash['message'] ?? '') : '',
            'messageType' => is_array($flash) ? (string) ($flash['type'] ?? '') : '',
            'messageName' => is_array($flash) ? (string) ($flash['name'] ?? '') : '',
        ], 'layouts/app');

        return Response::html($html);
    }

    public function submit(Request $request): Response
    {
        $name = trim((string) $request->input('name', ''));

        if ($name === '') {
            $_SESSION['_flash'] = [
                'type' => 'error',
                'message' => 'Gagal: nama wajib diisi.',
            ];
            return Response::redirect('/');
        }

        $_SESSION['_flash'] = [
            'type' => 'success',
            'message' => 'Berhasil terkirim untuk',
            'name' => $name,
        ];

        return Response::redirect('/');
    }
}
