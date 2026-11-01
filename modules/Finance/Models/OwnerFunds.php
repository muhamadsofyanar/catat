<?php
namespace Modules\Finance\Models;

use Core\Model;
use Core\Helpers;
use InvalidArgumentException;

class OwnerFunds extends Model {
    private function sum($condition) {
        $stmt = $this->db->prepare('SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE company_id = ? AND ' . $condition);
        $stmt->execute([Helpers::currentCompanyId()]);
        return (float)$stmt->fetchColumn();
    }

    // Preserve the allocation formula used by prive_form.php.
    public function available() {
        $income = $this->sum("type = 'income' AND (category IS NULL OR category NOT IN ('Pengembalian Kasbon', 'Pengembalian Dana Perusahaan'))");
        $expense = $this->sum("type = 'expense' AND (category NOT IN ('Prive', 'Company Withdrawal', 'Kasbon', 'Operasional Kantor') OR category IS NULL)");
        return max(0, $income - $expense) * 0.60 - $this->sum("type = 'expense' AND category = 'Prive'");
    }

    public function save($kind, $amount, $date, $note) {
        if (!is_numeric($amount) || !is_finite((float)$amount) || $amount <= 0) throw new InvalidArgumentException('Jumlah harus berupa angka positif.');
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('Tanggal tidak valid.');
        if (!in_array($kind, ['prive', 'kasbon', 'company'], true)) throw new InvalidArgumentException('Jenis transaksi tidak valid.');
        // Acquire a SQLite write lock before checking limits to prevent concurrent overspending.
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            if ($kind === 'prive') {
                $limit = $this->available();
                $category = 'Prive';
                $type = 'expense';
            } else {
                $out = $kind === 'kasbon' ? 'Kasbon' : 'Company Withdrawal';
                $category = $kind === 'kasbon' ? 'Pengembalian Kasbon' : 'Pengembalian Dana Perusahaan';
                $type = 'income';
                $limit = $this->sum("type = 'expense' AND category = '$out'") - $this->sum("type = 'income' AND category = '$category'");
            }
            if ((float)$amount > $limit) throw new InvalidArgumentException('Jumlah melebihi sisa hak owner atau saldo hutang.');
            $cid = Helpers::currentCompanyId();
            $stmt = $this->db->prepare("SELECT id FROM projects WHERE company_id = ? AND client_name = 'INTERNAL' AND service_name = 'KEUANGAN PERUSAHAAN' LIMIT 1");
            $stmt->execute([$cid]);
            $id = $stmt->fetchColumn();
            if (!$id) {
                $stmt = $this->db->prepare("INSERT INTO projects (company_id, client_name, service_name, status) VALUES (?, 'INTERNAL', 'KEUANGAN PERUSAHAAN', 'internal')");
                $stmt->execute([$cid]);
                $id = $this->db->lastInsertId();
            }
            $stmt = $this->db->prepare("INSERT INTO transactions (company_id, project_id, type, category, amount, note, transaction_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'paid')");
            $stmt->execute([$cid, $id, $type, $category, $amount, $note, $date]);
            \Core\Logger::log($this->db, 'OWNER_FUNDS', $category . ': ' . $amount);
            $this->db->exec('COMMIT');
        } catch (\Throwable $e) {
            $this->db->exec('ROLLBACK');
            throw $e;
        }
    }
}
