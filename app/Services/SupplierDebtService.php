<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use PDO;
use RuntimeException;

class SupplierDebtService
{
    public function __construct(private readonly ?PDO $pdo = null)
    {
    }

    public function resolveKasAkunId(): int
    {
        $pdo = $this->conn();

        $stmt = $pdo->query(
            'SELECT id
             FROM akun_keuangan
             WHERE deleted_at IS NULL
               AND status = 1
               AND is_kas = 1
             ORDER BY is_modal DESC, id ASC
             LIMIT 1'
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $akunId = is_array($row) ? (int) ($row['id'] ?? 0) : 0;
        if ($akunId > 0) {
            return $akunId;
        }

        $stmt = $pdo->prepare(
            'SELECT id
             FROM akun_keuangan
             WHERE deleted_at IS NULL
               AND status = 1
               AND kode_akun = :kode
             LIMIT 1'
        );
        $stmt->execute(['kode' => '1101']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $akunId = is_array($row) ? (int) ($row['id'] ?? 0) : 0;
        if ($akunId > 0) {
            return $akunId;
        }

        $stmt = $pdo->query(
            'SELECT id
             FROM akun_keuangan
             WHERE deleted_at IS NULL
               AND status = 1
               AND is_kas = 1
             ORDER BY id ASC
             LIMIT 1'
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $akunId = is_array($row) ? (int) ($row['id'] ?? 0) : 0;
        if ($akunId > 0) {
            return $akunId;
        }

        throw new RuntimeException('Akun kas/bank belum tersedia.');
    }

    /**
     * @return array<string, int|string|null>
     */
    public function debtStatusByBalance(int $paidAmount, int $totalAmount): array
    {
        if ($totalAmount <= 0) {
            return [
                'payment_status' => 'paid',
                'status_bayar' => 'Lunas',
                'remaining_amount' => 0,
            ];
        }

        if ($paidAmount <= 0) {
            return [
                'payment_status' => 'unpaid',
                'status_bayar' => 'Hutang',
                'remaining_amount' => $totalAmount,
            ];
        }

        if ($paidAmount >= $totalAmount) {
            return [
                'payment_status' => 'paid',
                'status_bayar' => 'Lunas',
                'remaining_amount' => 0,
            ];
        }

        return [
            'payment_status' => 'partial',
            'status_bayar' => 'Hutang',
            'remaining_amount' => $totalAmount - $paidAmount,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function postCashOutflow(array $payload): int
    {
        $amount = max(0, (int) ($payload['amount'] ?? 0));
        if ($amount <= 0) {
            throw new RuntimeException('Nominal kas keluar harus lebih dari 0.');
        }

        $keuanganService = new KeuanganService($this->conn());
        $tanggal = trim((string) ($payload['tanggal'] ?? ''));
        if ($tanggal === '') {
            $tanggal = (new DateTimeImmutable())->format('Y-m-d');
        }

        $kasAkunId = (int) ($payload['kas_akun_id'] ?? 0);
        if ($kasAkunId <= 0) {
            $kasAkunId = $this->resolveKasAkunId();
        }

        return $keuanganService->catatMutasi([
            'tanggal' => $tanggal,
            'no_ref' => trim((string) ($payload['no_ref'] ?? '')),
            'akun_keuangan_id' => $kasAkunId,
            'jenis' => 'kredit',
            'tipe_arus' => 'pengeluaran',
            'nominal' => $amount,
            'sumber_tipe' => (string) ($payload['sumber_tipe'] ?? 'pembelian_supplier'),
            'sumber_id' => $payload['sumber_id'] ?? null,
            'metode_pembayaran' => (string) ($payload['metode_pembayaran'] ?? 'Cash'),
            'deskripsi' => (string) ($payload['deskripsi'] ?? 'Kas keluar pembelian supplier'),
            'status' => 'posted',
            'created_by' => (int) ($payload['created_by'] ?? 0),
        ]);
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function createDebt(array $payload): int
    {
        $purchaseId = max(0, (int) ($payload['purchase_id'] ?? 0));
        $supplierId = max(0, (int) ($payload['supplier_id'] ?? 0));
        $totalAmount = max(0, (int) ($payload['total_amount'] ?? 0));
        if ($purchaseId <= 0 || $supplierId <= 0 || $totalAmount <= 0) {
            throw new RuntimeException('Data hutang supplier tidak valid.');
        }

        $paidAmount = max(0, min($totalAmount, (int) ($payload['paid_amount'] ?? 0)));
        $remainingAmount = max(0, $totalAmount - $paidAmount);
        $status = (string) ($payload['status'] ?? ($remainingAmount > 0 ? ($paidAmount > 0 ? 'partial' : 'unpaid') : 'paid'));
        if (!in_array($status, ['unpaid', 'partial', 'paid'], true)) {
            $status = $remainingAmount > 0 ? ($paidAmount > 0 ? 'partial' : 'unpaid') : 'paid';
        }

        $debtNo = trim((string) ($payload['debt_no'] ?? ''));
        if ($debtNo === '') {
            $debtNo = 'UTANG-' . date('YmdHis') . '-' . substr((string) $purchaseId, -4);
        }

        $debtDate = trim((string) ($payload['debt_date'] ?? ''));
        if ($debtDate === '') {
            $debtDate = (new DateTimeImmutable())->format('Y-m-d');
        }

        $dueDate = trim((string) ($payload['due_date'] ?? ''));
        if ($dueDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            $dueDate = '';
        }
        if ($dueDate === '' && $remainingAmount > 0) {
            $dueDate = $debtDate;
        }

        $stmt = $this->conn()->prepare(
            'INSERT INTO supplier_debts
             (purchase_id, supplier_id, debt_no, debt_date, total_amount, paid_amount, remaining_amount, due_date, status, notes, created_at, updated_at)
             VALUES
             (:purchase_id, :supplier_id, :debt_no, :debt_date, :total_amount, :paid_amount, :remaining_amount, :due_date, :status, :notes, NOW(), NOW())'
        );
        $stmt->execute([
            'purchase_id' => $purchaseId,
            'supplier_id' => $supplierId,
            'debt_no' => $debtNo,
            'debt_date' => $debtDate,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'due_date' => $dueDate !== '' ? $dueDate : null,
            'status' => $status,
            'notes' => $this->nullableString($payload['notes'] ?? null),
        ]);

        return (int) $this->conn()->lastInsertId();
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function recordDebtPayment(array $payload): int
    {
        $debtId = max(0, (int) ($payload['supplier_debt_id'] ?? 0));
        $purchaseId = max(0, (int) ($payload['purchase_id'] ?? 0));
        $supplierId = max(0, (int) ($payload['supplier_id'] ?? 0));
        $amount = max(0, (int) ($payload['amount'] ?? 0));
        if ($debtId <= 0 || $purchaseId <= 0 || $supplierId <= 0 || $amount <= 0) {
            throw new RuntimeException('Data pembayaran hutang supplier tidak valid.');
        }

        $paymentNo = trim((string) ($payload['payment_no'] ?? ''));
        if ($paymentNo === '') {
            $paymentNo = 'BYR-' . date('YmdHis') . '-' . substr((string) $debtId, -4);
        }

        $paymentDate = trim((string) ($payload['payment_date'] ?? ''));
        if ($paymentDate === '') {
            $paymentDate = (new DateTimeImmutable())->format('Y-m-d');
        }

        $stmt = $this->conn()->prepare(
            'INSERT INTO supplier_debt_payments
             (supplier_debt_id, purchase_id, supplier_id, payment_no, payment_date, payment_method, kas_akun_id, amount, reference_no, notes, created_by, created_at)
             VALUES
             (:supplier_debt_id, :purchase_id, :supplier_id, :payment_no, :payment_date, :payment_method, :kas_akun_id, :amount, :reference_no, :notes, :created_by, NOW())'
        );
        $stmt->execute([
            'supplier_debt_id' => $debtId,
            'purchase_id' => $purchaseId,
            'supplier_id' => $supplierId,
            'payment_no' => $paymentNo,
            'payment_date' => $paymentDate,
            'payment_method' => (string) ($payload['payment_method'] ?? 'Cash'),
            'kas_akun_id' => max(0, (int) ($payload['kas_akun_id'] ?? 0)),
            'amount' => $amount,
            'reference_no' => $this->nullableString($payload['reference_no'] ?? null),
            'notes' => $this->nullableString($payload['notes'] ?? null),
            'created_by' => max(0, (int) ($payload['created_by'] ?? 0)) ?: null,
        ]);

        return (int) $this->conn()->lastInsertId();
    }

    private function conn(): PDO
    {
        return $this->pdo instanceof PDO ? $this->pdo : Database::connection();
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text !== '' ? $text : null;
    }
}
