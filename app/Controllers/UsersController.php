<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Database;
use App\Services\SecurityLogger;
use System\Http\Request;
use System\Http\Response;
use Throwable;

class UsersController
{
    public function profile(Request $request): Response
    {
        $auth = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
        $authId = (int) ($auth['id'] ?? 0);
        if ($authId <= 0) {
            toast_add('Sesi tidak valid. Silakan login ulang.', 'error');
            return Response::redirect('/login');
        }

        $profile = [];
        try {
            $pdo = Database::connection();
            $profile = $this->findUserById($pdo, $authId);
            if ($profile === []) {
                toast_add('Data profile tidak ditemukan.', 'error');
                return Response::redirect('/dashboard');
            }
        } catch (Throwable) {
            toast_add('Gagal memuat data profile.', 'error');
            return Response::redirect('/dashboard');
        }

        $html = app()->view()->render('users/profile', [
            'title' => 'Edit Profile',
            'auth' => $auth,
            'activeMenu' => 'users',
            'profile' => $profile,
        ]);

        return Response::html($html);
    }

    public function updateProfile(Request $request): Response
    {
        $auth = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
        $authId = (int) ($auth['id'] ?? 0);
        if ($authId <= 0) {
            toast_add('Sesi tidak valid. Silakan login ulang.', 'error');
            return Response::redirect('/login');
        }

        $name = trim((string) $request->input('name', ''));
        $username = trim((string) $request->input('username', ''));
        $email = trim((string) $request->input('email', ''));
        $telepon = trim((string) $request->input('telepon', ''));
        $alamat = trim((string) $request->input('alamat', ''));
        $currentPassword = (string) $request->input('current_password', '');
        $newPassword = (string) $request->input('new_password', '');

        if ($name === '' || $username === '') {
            toast_add('Nama dan username wajib diisi.', 'error');
            return Response::redirect('/profile');
        }

        if ($newPassword !== '' && strlen($newPassword) < 8) {
            toast_add('Password baru minimal 8 karakter.', 'error');
            return Response::redirect('/profile');
        }

        $newAvatarAbsolutePath = '';
        $newAvatarFilemanagerId = 0;
        try {
            $pdo = Database::connection();
            $existing = $this->findUserById($pdo, $authId);
            if ($existing === []) {
                toast_add('Data profile tidak ditemukan.', 'error');
                return Response::redirect('/profile');
            }

            if ($this->usernameExists($pdo, $username, $authId)) {
                toast_add('Username sudah digunakan.', 'error');
                return Response::redirect('/profile');
            }

            if ($email !== '' && $this->emailExists($pdo, $email, $authId)) {
                toast_add('Email sudah digunakan.', 'error');
                return Response::redirect('/profile');
            }

            if ($newPassword !== '') {
                $hash = (string) ($existing['pass'] ?? '');
                if ($currentPassword === '' || !password_verify($currentPassword, $hash)) {
                    toast_add('Password saat ini tidak valid.', 'error');
                    return Response::redirect('/profile');
                }
            }

            $avatarFileId = null;
            $uploadedAvatar = $_FILES['avatar_file'] ?? null;
            if (is_array($uploadedAvatar) && (int) ($uploadedAvatar['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ((int) ($uploadedAvatar['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    toast_add('Upload avatar tidak valid.', 'error');
                    return Response::redirect('/profile');
                }

                $tmp = (string) ($uploadedAvatar['tmp_name'] ?? '');
                $originalName = trim((string) ($uploadedAvatar['name'] ?? ''));
                $size = (int) ($uploadedAvatar['size'] ?? 0);
                if ($tmp === '' || !is_uploaded_file($tmp) || $size <= 0) {
                    toast_add('File avatar gagal diproses.', 'error');
                    return Response::redirect('/profile');
                }

                if ($size > 5_242_880) {
                    toast_add('Ukuran avatar melebihi batas 5MB.', 'error');
                    return Response::redirect('/profile');
                }

                $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (!in_array($extension, $allowedExtensions, true)) {
                    toast_add('Format avatar harus JPG, PNG, WEBP, atau GIF.', 'error');
                    return Response::redirect('/profile');
                }

                $mimeType = (string) mime_content_type($tmp);
                if (!str_starts_with($mimeType, 'image/')) {
                    toast_add('File avatar harus berupa gambar.', 'error');
                    return Response::redirect('/profile');
                }

                $roleRaw = (string) ($existing['role_name'] ?? ($existing['hak_akses_id'] ?? 'users'));
                $roleSegment = $this->normalizePathSegment($roleRaw);
                if ($roleSegment === '') {
                    $roleSegment = 'users';
                }
                $userSegment = (string) $authId;

                $baseStorage = app()->basePath('storage/filemanager/users/' . $roleSegment . '/' . $userSegment);
                if (!is_dir($baseStorage) && !mkdir($baseStorage, 0775, true) && !is_dir($baseStorage)) {
                    toast_add('Gagal membuat direktori avatar.', 'error');
                    return Response::redirect('/profile');
                }

                $storedName = 'avatar_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
                $targetPath = $baseStorage . DIRECTORY_SEPARATOR . $storedName;
                if (!move_uploaded_file($tmp, $targetPath)) {
                    toast_add('Gagal menyimpan avatar ke storage.', 'error');
                    return Response::redirect('/profile');
                }
                $newAvatarAbsolutePath = $targetPath;

                $avatarPath = 'filemanager/users/' . $roleSegment . '/' . $userSegment . '/' . $storedName;
                $avatarFileId = $this->insertFilemanagerRecord(
                    $pdo,
                    $authId,
                    $originalName !== '' ? $originalName : $storedName,
                    $avatarPath,
                    $storedName,
                    $mimeType,
                    $extension,
                    $size
                );
                if ($avatarFileId <= 0) {
                    @unlink($targetPath);
                    toast_add('Gagal menyimpan metadata avatar.', 'error');
                    return Response::redirect('/profile');
                }

                $newAvatarFilemanagerId = $avatarFileId;

                $oldAvatarId = (int) ($existing['avatar'] ?? 0);
                if ($oldAvatarId > 0) {
                    $this->softDeleteFilemanagerById($pdo, $oldAvatarId);
                }
            }

            $setParts = [
                'name = :name',
                'email = :email',
                'telepon = :telepon',
                'alamat = :alamat',
                'user = :user',
            ];
            $params = [
                'name' => $name,
                'email' => $email !== '' ? $email : null,
                'telepon' => $telepon !== '' ? $telepon : null,
                'alamat' => $alamat !== '' ? $alamat : null,
                'user' => $username,
                'id' => $authId,
            ];

            if ($newPassword !== '') {
                $setParts[] = 'pass = :pass';
                $params['pass'] = password_hash($newPassword, PASSWORD_DEFAULT);
            }

            if ($avatarFileId !== null) {
                $setParts[] = 'avatar = :avatar';
                $params['avatar'] = $avatarFileId;
            }

            $sql = 'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $_SESSION['auth']['name'] = $name;
            $_SESSION['auth']['username'] = $username;
            $_SESSION['auth']['email'] = $email;
            if ($avatarFileId !== null) {
                $_SESSION['auth']['avatar'] = $avatarFileId;
            }

            toast_add('Profile berhasil diperbarui.', 'success');
        } catch (Throwable) {
            if ($newAvatarFilemanagerId > 0) {
                try {
                    $pdoRollback = Database::connection();
                    $this->softDeleteFilemanagerById($pdoRollback, $newAvatarFilemanagerId);
                } catch (Throwable) {
                    // no-op
                }
            }
            if ($newAvatarAbsolutePath !== '' && is_file($newAvatarAbsolutePath)) {
                @unlink($newAvatarAbsolutePath);
            }
            toast_add('Gagal memperbarui profile.', 'error');
        }

        return Response::redirect('/profile');
    }

    public function index(Request $request): Response
    {
        $auth = is_array($_SESSION['auth'] ?? null) ? $_SESSION['auth'] : [];
        $users = [];
        $roles = [];

        try {
            $pdo = Database::connection();

            $usersStmt = $pdo->query(
                'SELECT u.id, u.name, u.email, u.telepon, u.alamat, u.user, u.hak_akses_id AS akses, u.active, u.created_at, '
                . 'COALESCE(h.hak_akses, CAST(u.hak_akses_id AS CHAR)) AS role_name '
                . 'FROM users u '
                . 'LEFT JOIN hak_akses h ON h.id = u.hak_akses_id '
                . 'WHERE u.id <> 1 '
                . 'ORDER BY u.id DESC'
            );
            $users = $usersStmt->fetchAll();
            if (!is_array($users)) {
                $users = [];
            }

            $rolesStmt = $pdo->query('SELECT id, hak_akses FROM hak_akses ORDER BY id ASC');
            $roles = $rolesStmt->fetchAll();
            if (!is_array($roles)) {
                $roles = [];
            }
        } catch (Throwable) {
            toast_add('Gagal memuat data pengguna.', 'error');
        }

        $html = app()->view()->render('users/index', [
            'title' => 'Pengguna ' . brand_name(),
            'auth' => $auth,
            'activeMenu' => 'users',
            'users' => $users,
            'roles' => $roles,
        ]);

        return Response::html($html);
    }

    public function datatable(Request $request): Response
    {
        try {
            $pdo = Database::connection();
            $params = $request->all();

            $draw = max(0, (int) ($params['draw'] ?? 0));
            $start = max(0, (int) ($params['start'] ?? 0));
            $length = (int) ($params['length'] ?? 10);
            if ($length < 1) {
                $length = 10;
            }
            if ($length > 100) {
                $length = 100;
            }

            $search = trim((string) (($params['search']['value'] ?? '') ?: ''));

            $orderMap = [
                1 => 'u.name',
                2 => 'u.user',
                3 => 'COALESCE(h.hak_akses, CAST(u.hak_akses_id AS CHAR))',
                4 => 'u.active',
            ];
            $orderIndex = (int) ($params['order'][0]['column'] ?? 0);
            $orderColumn = $orderMap[$orderIndex] ?? 'u.id';
            $orderDir = strtolower((string) ($params['order'][0]['dir'] ?? 'desc'));
            $orderDir = $orderDir === 'asc' ? 'asc' : 'desc';

            $whereSql = ' WHERE u.id <> 1';
            $bindings = [];
            if ($search !== '') {
                $whereSql .= ' AND (u.name LIKE :search_name OR u.user LIKE :search_user OR u.email LIKE :search_email OR COALESCE(h.hak_akses, CAST(u.hak_akses_id AS CHAR)) LIKE :search_role)';
                $like = '%' . $search . '%';
                $bindings['search_name'] = $like;
                $bindings['search_user'] = $like;
                $bindings['search_email'] = $like;
                $bindings['search_role'] = $like;
            }

            $countTotal = (int) $pdo->query('SELECT COUNT(*) FROM users u WHERE u.id <> 1')->fetchColumn();
            if ($search === '') {
                $countFiltered = $countTotal;
            } else {
                $countStmt = $pdo->prepare(
                    'SELECT COUNT(*) FROM users u LEFT JOIN hak_akses h ON h.id = u.hak_akses_id' . $whereSql
                );
                foreach ($bindings as $key => $value) {
                    $countStmt->bindValue(':' . $key, $value);
                }
                $countStmt->execute();
                $countFiltered = (int) $countStmt->fetchColumn();
            }

            $sql = 'SELECT u.id, u.name, u.email, u.telepon, u.alamat, u.user, u.hak_akses_id AS akses, u.active, u.created_at, '
                . 'COALESCE(h.hak_akses, CAST(u.hak_akses_id AS CHAR)) AS role_name '
                . 'FROM users u '
                . 'LEFT JOIN hak_akses h ON h.id = u.hak_akses_id'
                . $whereSql
                . ' ORDER BY ' . $orderColumn . ' ' . $orderDir
                . ' LIMIT :limit OFFSET :offset';
            $stmt = $pdo->prepare($sql);
            foreach ($bindings as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->bindValue(':limit', $length, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $start, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!is_array($rows)) {
                $rows = [];
            }

            return Response::json([
                'draw' => $draw,
                'recordsTotal' => $countTotal,
                'recordsFiltered' => $countFiltered,
                'data' => $rows,
            ]);
        } catch (Throwable) {
            return Response::json([
                'draw' => max(0, (int) ($request->all()['draw'] ?? 0)),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Gagal memuat data pengguna.',
            ], 500);
        }
    }

    public function store(Request $request): Response
    {
        $name = trim((string) $request->input('name', ''));
        $username = trim((string) $request->input('username', ''));
        $email = trim((string) $request->input('email', ''));
        $telepon = trim((string) $request->input('telepon', ''));
        $alamat = trim((string) $request->input('alamat', ''));
        $akses = trim((string) $request->input('akses', ''));
        $hakAksesId = ctype_digit($akses) ? (int) $akses : 0;
        $active = trim((string) $request->input('active', '1'));
        $password = (string) $request->input('password', '');

        if ($name === '' || $username === '' || $password === '' || strlen($password) < 8 || $hakAksesId <= 0) {
            toast_add('Data tidak valid. Nama, username, akses, dan password minimal 8 karakter wajib diisi.', 'error');
            return Response::redirect('/users');
        }

        if (!in_array($active, ['0', '1'], true)) {
            $active = '1';
        }

        try {
            $pdo = Database::connection();

            if ($this->usernameExists($pdo, $username)) {
                toast_add('Username sudah digunakan.', 'error');
                return Response::redirect('/users');
            }

            if ($email !== '' && $this->emailExists($pdo, $email)) {
                toast_add('Email sudah digunakan.', 'error');
                return Response::redirect('/users');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, telepon, alamat, avatar, user, pass, hak_akses_id, active, created_at) '
                . 'VALUES (:name, :email, :telepon, :alamat, :avatar, :user, :pass, :hak_akses_id, :active, :created_at)'
            );
            $stmt->execute([
                'name' => $name,
                'email' => $email !== '' ? $email : null,
                'telepon' => $telepon !== '' ? $telepon : null,
                'alamat' => $alamat !== '' ? $alamat : null,
                'avatar' => null,
                'user' => $username,
                'pass' => password_hash($password, PASSWORD_DEFAULT),
                'hak_akses_id' => $hakAksesId,
                'active' => $active,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $newUserId = (int) $pdo->lastInsertId();
            SecurityLogger::logAudit('users', 'CREATE', 'users', (string) $newUserId,
                null, ['name' => $name, 'username' => $username, 'hak_akses_id' => $hakAksesId, 'active' => $active],
                true, SecurityLogger::RISK_LOW);
            toast_add('Pengguna baru berhasil ditambahkan.', 'success');
        } catch (Throwable) {
            toast_add('Gagal menambahkan pengguna.', 'error');
        }

        return Response::redirect('/users');
    }

    public function update(Request $request, string $id): Response
    {
        $userId = (int) $id;
        if ($userId <= 0 || $userId === 1) {
            toast_add('Pengguna ini tidak boleh diubah.', 'error');
            return Response::redirect('/users');
        }

        $name = trim((string) $request->input('name', ''));
        $username = trim((string) $request->input('username', ''));
        $email = trim((string) $request->input('email', ''));
        $telepon = trim((string) $request->input('telepon', ''));
        $alamat = trim((string) $request->input('alamat', ''));
        $akses = trim((string) $request->input('akses', ''));
        $hakAksesId = ctype_digit($akses) ? (int) $akses : 0;
        $active = trim((string) $request->input('active', '1'));
        $password = (string) $request->input('password', '');

        if ($name === '' || $username === '' || $hakAksesId <= 0) {
            toast_add('Data tidak valid. Nama, username, dan akses wajib diisi.', 'error');
            return Response::redirect('/users');
        }

        if (!in_array($active, ['0', '1'], true)) {
            $active = '1';
        }

        try {
            $pdo = Database::connection();

            if ($this->usernameExists($pdo, $username, $userId)) {
                toast_add('Username sudah digunakan.', 'error');
                return Response::redirect('/users');
            }

            if ($email !== '' && $this->emailExists($pdo, $email, $userId)) {
                toast_add('Email sudah digunakan.', 'error');
                return Response::redirect('/users');
            }

            if ($password !== '' && strlen($password) < 8) {
                toast_add('Password baru minimal 8 karakter.', 'error');
                return Response::redirect('/users');
            }

            $beforeStmt = $pdo->prepare('SELECT id, name, user, email, hak_akses_id, active FROM users WHERE id = :id LIMIT 1');
            $beforeStmt->execute(['id' => $userId]);
            $beforeRow = $beforeStmt->fetch(\PDO::FETCH_ASSOC);

            if ($password === '') {
                $stmt = $pdo->prepare(
                    'UPDATE users SET name = :name, email = :email, telepon = :telepon, alamat = :alamat, user = :user, hak_akses_id = :hak_akses_id, active = :active '
                    . 'WHERE id = :id AND id <> 1'
                );
                $stmt->execute([
                    'name' => $name,
                    'email' => $email !== '' ? $email : null,
                    'telepon' => $telepon !== '' ? $telepon : null,
                    'alamat' => $alamat !== '' ? $alamat : null,
                    'user' => $username,
                    'hak_akses_id' => $hakAksesId,
                    'active' => $active,
                    'id' => $userId,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE users SET name = :name, email = :email, telepon = :telepon, alamat = :alamat, user = :user, pass = :pass, hak_akses_id = :hak_akses_id, active = :active '
                    . 'WHERE id = :id AND id <> 1'
                );
                $stmt->execute([
                    'name' => $name,
                    'email' => $email !== '' ? $email : null,
                    'telepon' => $telepon !== '' ? $telepon : null,
                    'alamat' => $alamat !== '' ? $alamat : null,
                    'user' => $username,
                    'pass' => password_hash($password, PASSWORD_DEFAULT),
                    'hak_akses_id' => $hakAksesId,
                    'active' => $active,
                    'id' => $userId,
                ]);
            }

            toast_add('Data pengguna berhasil diperbarui.', 'success');
            $afterSnap = ['name' => $name, 'username' => $username, 'hak_akses_id' => $hakAksesId, 'active' => $active];
            $isRoleChange = is_array($beforeRow) && (string)($beforeRow['hak_akses_id'] ?? '') !== (string)$hakAksesId;
            SecurityLogger::logAudit('users', 'UPDATE', 'users', (string) $userId,
                is_array($beforeRow) ? $beforeRow : null, $afterSnap,
                true, $isRoleChange ? SecurityLogger::RISK_MEDIUM : SecurityLogger::RISK_LOW);
            if ($isRoleChange) {
                SecurityLogger::logSecurityEvent('USER_ROLE_CHANGE', 'sensitive', 'medium',
                    SecurityLogger::RISK_MEDIUM, 'UsersController',
                    ['target_user_id' => $userId, 'old_role' => $beforeRow['hak_akses_id'] ?? '', 'new_role' => $hakAksesId],
                    'logged');
            }
        } catch (Throwable) {
            toast_add('Gagal memperbarui pengguna.', 'error');
        }

        return Response::redirect('/users');
    }

    public function destroy(Request $request, string $id): Response
    {
        $userId = (int) $id;
        $authId = (int) ($_SESSION['auth']['id'] ?? 0);

        if ($userId <= 0 || $userId === 1) {
            toast_add('Pengguna ini tidak boleh dihapus.', 'error');
            return Response::redirect('/users');
        }

        if ($userId === $authId) {
            toast_add('Anda tidak dapat menghapus akun yang sedang dipakai login.', 'warning');
            return Response::redirect('/users');
        }

        try {
            $pdo = Database::connection();
            $beforeStmt = $pdo->prepare('SELECT id, name, user, email, hak_akses_id, active FROM users WHERE id = :id LIMIT 1');
            $beforeStmt->execute(['id' => $userId]);
            $beforeRow = $beforeStmt->fetch(\PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id AND id <> 1');
            $stmt->execute(['id' => $userId]);

            if ($stmt->rowCount() < 1) {
                toast_add('Pengguna tidak ditemukan.', 'warning');
                return Response::redirect('/users');
            }

            SecurityLogger::logAudit('users', 'DELETE', 'users', (string) $userId,
                is_array($beforeRow) ? $beforeRow : null, null, true, SecurityLogger::RISK_MEDIUM);
            toast_add('Pengguna berhasil dihapus.', 'success');
        } catch (Throwable) {
            toast_add('Gagal menghapus pengguna.', 'error');
        }

        return Response::redirect('/users');
    }

    private function usernameExists(\PDO $pdo, string $username, ?int $ignoreId = null): bool
    {
        if ($ignoreId === null) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE user = :username LIMIT 1');
            $stmt->execute(['username' => $username]);
            return $stmt->fetch() !== false;
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE user = :username AND id <> :id LIMIT 1');
        $stmt->execute([
            'username' => $username,
            'id' => $ignoreId,
        ]);

        return $stmt->fetch() !== false;
    }

    private function emailExists(\PDO $pdo, string $email, ?int $ignoreId = null): bool
    {
        if ($ignoreId === null) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $email]);
            return $stmt->fetch() !== false;
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
        $stmt->execute([
            'email' => $email,
            'id' => $ignoreId,
        ]);

        return $stmt->fetch() !== false;
    }

    /**
     * @return array<string, mixed>
     */
    private function findUserById(\PDO $pdo, int $id): array
    {
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name, u.email, u.telepon, u.alamat, u.user, u.pass, u.hak_akses_id AS akses, u.hak_akses_id, u.active, u.avatar, '
            . 'COALESCE(h.hak_akses, CAST(u.hak_akses_id AS CHAR)) AS role_name '
            . 'FROM users u '
            . 'LEFT JOIN hak_akses h ON h.id = u.hak_akses_id '
            . 'WHERE u.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : [];
    }

    private function normalizePathSegment(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_-]/', '-', $value);
        $value = is_string($value) ? trim($value, '-') : '';
        return is_string($value) ? substr($value, 0, 64) : '';
    }

    private function insertFilemanagerRecord(
        \PDO $pdo,
        int $uploadedBy,
        string $displayName,
        string $path,
        string $filename,
        string $mimeType,
        string $extension,
        int $sizeBytes
    ): int {
        $stmt = $pdo->prepare(
            'INSERT INTO filemanager (`name`, module, ref_id, path, filename, mime_type, extension, size_bytes, visibility, uploaded_by, checksum_sha1, create_time, created_at, updated_at) '
            . 'VALUES (:name, :module, :ref_id, :path, :filename, :mime_type, :extension, :size_bytes, :visibility, :uploaded_by, :checksum_sha1, NOW(), NOW(), NOW())'
        );
        $stmt->execute([
            'name' => $this->sanitizeFileName($displayName),
            'module' => 'users',
            'ref_id' => (string) $uploadedBy,
            'path' => $path,
            'filename' => $filename,
            'mime_type' => $mimeType !== '' ? $mimeType : null,
            'extension' => $extension !== '' ? $extension : null,
            'size_bytes' => $sizeBytes > 0 ? $sizeBytes : null,
            'visibility' => 'private',
            'uploaded_by' => $uploadedBy > 0 ? $uploadedBy : null,
            'checksum_sha1' => null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    private function softDeleteFilemanagerById(\PDO $pdo, int $fileId): void
    {
        if ($fileId <= 0) {
            return;
        }

        $stmt = $pdo->prepare('SELECT id, path FROM filemanager WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $fileId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return;
        }

        $delete = $pdo->prepare('UPDATE filemanager SET deleted_at = NOW(), updated_at = NOW() WHERE id = :id');
        $delete->execute(['id' => $fileId]);

        $relative = ltrim((string) ($row['path'] ?? ''), '/');
        if ($relative === '' || !str_starts_with($relative, 'filemanager/')) {
            return;
        }

        $fullPath = app()->basePath('storage/' . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function sanitizeFileName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'file';
        }
        $clean = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        if (!is_string($clean) || $clean === '') {
            return 'file';
        }

        return substr($clean, 0, 255);
    }
}
