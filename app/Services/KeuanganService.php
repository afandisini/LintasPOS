<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

class KeuanganService
{
    public function __construct(private readonly ?PDO $pdo = null)
    {
    }

    public function resolveAkunIdByKode(string $kodeAkun): int
    {
        $kode = trim($kodeAkun);
        if ($kode === '') {
            return 0;
        }

        $stmt = $this->conn()->prepare(
            'SELECT id FROM akun_keuangan WHERE deleted_at IS NULL AND kode_akun = :kode LIMIT 1'
        );
        $stmt->execute(['kode' => $kode]);
        $row = $stmt->fetch();

        return is_array($row) ? (int) ($row['id'] ?? 0) : 0;
    }

    public function resolveDefaultAkunId(string $tipeArus): int
    {
        $normalized = strtolower(trim($tipeArus));
        $kode = match ($normalized) {
            'pemasukan' => '4101',
            'pengeluaran' => '5102',
            default => '9999',
        };

        $akunId = $this->resolveAkunIdByKode($kode);
        if ($akunId > 0) {
            return $akunId;
        }

        $fallbackId = $this->resolveAkunIdByKode('9999');
        if ($fallbackId > 0) {
            return $fallbackId;
        }

        throw new RuntimeException('Akun default keuangan belum tersedia.');
    }

    /**
     * @param array{
     *   tanggal?: string|null,
     *   no_ref?: string|null,
     *   akun_keuangan_id?: int|null,
     *   kode_akun?: string|null,
     *   jenis?: string|null,
     *   tipe_arus?: string|null,
     *   nominal: int|float|string,
     *   saldo_setelah?: int|float|string|null,
     *   sumber_tipe?: string|null,
     *   sumber_id?: int|string|null,
     *   metode_pembayaran?: string|null,
     *   deskripsi?: string|null,
     *   status?: string|null,
     *   created_by?: int|null
     * } $payload
     */
    public function catatMutasi(array $payload): int
    {
        $nominal = (float) ($payload['nominal'] ?? 0);
        if ($nominal <= 0) {
            throw new RuntimeException('Nominal mutasi harus lebih dari 0.');
        }

        $tipeArus = strtolower(trim((string) ($payload['tipe_arus'] ?? 'netral')));
        if (!in_array($tipeArus, ['pemasukan', 'pengeluaran', 'netral'], true)) {
            $tipeArus = 'netral';
        }

        $jenis = strtolower(trim((string) ($payload['jenis'] ?? ($tipeArus === 'pemasukan' ? 'debit' : 'kredit'))));
        if (!in_array($jenis, ['debit', 'kredit'], true)) {
            $jenis = $tipeArus === 'pemasukan' ? 'debit' : 'kredit';
        }

        $akunId = (int) ($payload['akun_keuangan_id'] ?? 0);
        if ($akunId <= 0) {
            $kodeAkun = trim((string) ($payload['kode_akun'] ?? ''));
            $akunId = $kodeAkun !== '' ? $this->resolveAkunIdByKode($kodeAkun) : 0;
        }
        if ($akunId <= 0) {
            $akunId = $this->resolveDefaultAkunId($tipeArus);
        }

        $tanggal = trim((string) ($payload['tanggal'] ?? ''));
        if ($tanggal === '') {
            $tanggal = (new DateTimeImmutable())->format('Y-m-d');
        }

        $status = strtolower(trim((string) ($payload['status'] ?? 'posted')));
        if (!in_array($status, ['draft', 'posted', 'void'], true)) {
            $status = 'posted';
        }

        $createdBy = (int) ($payload['created_by'] ?? 0);
        if ($createdBy <= 0) {
            $createdBy = (int) ($_SESSION['auth']['id'] ?? 0);
        }

        $sql = 'INSERT INTO keuangan (
            tanggal, no_ref, akun_keuangan_id, jenis, tipe_arus, nominal, saldo_setelah,
            sumber_tipe, sumber_id, metode_pembayaran, deskripsi, status,
            created_by, updated_by, created_at, updated_at,
            nama_operasional, akun_keunagan_id, harga_operasional, ket_operasional, tgl_input, id_users
        ) VALUES (
            :tanggal, :no_ref, :akun_keuangan_id, :jenis, :tipe_arus, :nominal, :saldo_setelah,
            :sumber_tipe, :sumber_id, :metode_pembayaran, :deskripsi, :status,
            :created_by, :updated_by, NOW(), NOW(),
            :nama_operasional, :akun_keunagan_id, :harga_operasional, :ket_operasional, :tgl_input, :id_users
        )';

        $stmt = $this->conn()->prepare($sql);
        $stmt->execute([
            'tanggal' => $tanggal,
            'no_ref' => $this->nullableString($payload['no_ref'] ?? null, 64),
            'akun_keuangan_id' => $akunId,
            'jenis' => $jenis,
            'tipe_arus' => $tipeArus,
            'nominal' => $nominal,
            'saldo_setelah' => $this->nullableDecimal($payload['saldo_setelah'] ?? null),
            'sumber_tipe' => $this->nullableString($payload['sumber_tipe'] ?? null, 50),
            'sumber_id' => $this->nullableInt($payload['sumber_id'] ?? null),
            'metode_pembayaran' => $this->nullableString($payload['metode_pembayaran'] ?? null, 50),
            'deskripsi' => $this->nullableString($payload['deskripsi'] ?? null, 65535),
            'status' => $status,
            'created_by' => $createdBy > 0 ? $createdBy : null,
            'updated_by' => $createdBy > 0 ? $createdBy : null,
            'nama_operasional' => $this->nullableString($payload['deskripsi'] ?? null, 255),
            'akun_keunagan_id' => $akunId,
            'harga_operasional' => (int) round($nominal),
            'ket_operasional' => $this->nullableString($payload['deskripsi'] ?? null, 65535),
            'tgl_input' => $tanggal,
            'id_users' => $createdBy > 0 ? $createdBy : 0,
        ]);

        return (int) $this->conn()->lastInsertId();
    }

    public function saldoKasSaatIni(): float
    {
        $sql = "SELECT
                COALESCE(SUM(
                    CASE
                        WHEN k.jenis = 'debit' THEN k.nominal
                        WHEN k.jenis = 'kredit' THEN -k.nominal
                        ELSE 0
                    END
                ), 0) AS saldo
            FROM keuangan k
            INNER JOIN akun_keuangan a ON a.id = k.akun_keuangan_id
            WHERE k.deleted_at IS NULL
              AND a.deleted_at IS NULL
              AND a.is_kas = 1
              AND k.status = 'posted'";

        $stmt = $this->conn()->query($sql);
        $row = $stmt->fetch();

        return (float) ($row['saldo'] ?? 0);
    }

    private function conn(): PDO
    {
        return $this->pdo instanceof PDO ? $this->pdo : Database::connection();
    }

    private function nullableString(mixed $value, int $maxLength): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return substr($text, 0, $maxLength);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $intValue = (int) $value;
        return $intValue > 0 ? $intValue : null;
    }

    private function nullableDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return (float) $value;
        } catch (Throwable) {
            return null;
        }
    }
}
