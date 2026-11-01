<?php

namespace Modules\Finance\Models;

use Core\Model;
use PDO;

class Transaction extends Model {
    
    public function getAll($filters = []) {
        $conditions = [];
        $params = [];

        // Base Query
        $sql = "SELECT t.*, 
                   p.client_name, 
                   p.service_name,
                   p.partner_id,
                   p.client_id,
                   p.service_id,
                   part.name as partner_name
            FROM transactions t 
            LEFT JOIN projects p ON t.project_id = p.id 
            LEFT JOIN partners part ON p.partner_id = part.id";

        $conditions[] = "t.company_id = ?";
        $params[] = \Core\Helpers::currentCompanyId();

        // Date Range
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $conditions[] = "t.transaction_date BETWEEN ? AND ?";
            $params[] = $filters['start_date'];
            $params[] = $filters['end_date'];
        }

        // Type Filter (Income/Expense)
        if (!empty($filters['type'])) {
            $conditions[] = "t.type = ?";
            $params[] = $filters['type'];
        }

        // Category Filter
        if (!empty($filters['category'])) {
            $conditions[] = "t.category = ?";
            $params[] = $filters['category'];
        }
        if (!empty($filters['category_in']) && is_array($filters['category_in'])) {
            $placeholders = implode(',', array_fill(0, count($filters['category_in']), '?'));
            $conditions[] = "t.category IN ($placeholders)";
            foreach ($filters['category_in'] as $cat) $params[] = $cat;
        }

        // Status Filter (paid/unpaid)
        if (!empty($filters['status'])) {
            $conditions[] = "t.status = ?";
            $params[] = $filters['status'];
        }
        
        // Exclude Unpaid Expenses (for Cash Flow view)
        if (!empty($filters['exclude_unpaid_expense']) && $filters['exclude_unpaid_expense'] === true) {
             $conditions[] = "NOT (t.type = 'expense' AND t.status = 'unpaid')";
        }

        // Partner Filter
        if (!empty($filters['partner_id'])) {
            $conditions[] = "p.partner_id = ?";
            $params[] = $filters['partner_id'];
        }

        // Client Filter
        if (!empty($filters['client_id'])) {
            $conditions[] = "p.client_id = ?";
            $params[] = $filters['client_id'];
        }

        // Service Filter
        if (!empty($filters['service_id'])) {
            $conditions[] = "p.service_id = ?";
            $params[] = $filters['service_id'];
        }

        // Search
        if (!empty($filters['q'])) {
            $q = trim($filters['q']);
            $conditions[] = "(t.note LIKE ? OR t.category LIKE ? OR p.client_name LIKE ? OR p.service_name LIKE ?)";
            $params[] = "%$q%";
            $params[] = "%$q%";
            $params[] = "%$q%";
            $params[] = "%$q%";
        }
        
        // Custom Condition for Payables (unpaid expenses)
        // Handled by combining type='expense' and status='unpaid' in filters

        if (count($conditions) > 0) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $order = $filters['order'] ?? 'ASC';
        $sql .= " ORDER BY t.transaction_date $order, t.id $order";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getTopExpenses($startDate, $endDate, $limit = 5) {
        $sql = "SELECT COALESCE(category, 'Lainnya') as category, SUM(amount) as total
                FROM transactions
                WHERE type = 'expense' AND company_id = ?
                  AND category NOT IN ('Prive','Company Withdrawal','Company Expense','Kasbon','Pengembalian Kasbon','Pengembalian Dana Perusahaan')
                  AND transaction_date BETWEEN ? AND ?
                GROUP BY COALESCE(category, 'Lainnya')
                ORDER BY total DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([\Core\Helpers::currentCompanyId(), $startDate, $endDate, $limit]);
        return $stmt->fetchAll();
    }

    public function getMonthlyStats($startDate, $endDate) {
        // Income
        $stmtInc = $this->db->prepare("SELECT SUM(amount) FROM transactions WHERE type = 'income' AND company_id = ? AND transaction_date BETWEEN ? AND ?");
        $stmtInc->execute([\Core\Helpers::currentCompanyId(), $startDate, $endDate]);
        $income = $stmtInc->fetchColumn() ?: 0;

        // Expense
        $stmtExp = $this->db->prepare("
            SELECT SUM(amount) 
            FROM transactions 
            WHERE type = 'expense' AND company_id = ?
            AND category NOT IN ('Prive', 'Company Withdrawal', 'Company Expense', 'Kasbon', 'Pengembalian Kasbon', 'Pengembalian Dana Perusahaan')
            AND transaction_date BETWEEN ? AND ?
        ");
        $stmtExp->execute([\Core\Helpers::currentCompanyId(), $startDate, $endDate]);
        $expense = $stmtExp->fetchColumn() ?: 0;

        return ['income' => $income, 'expense' => $expense, 'net' => $income - $expense];
    }

    public function getSum($filters = []) {
        $conditions = [];
        $params = [];
        
        // Check if we need join
        $needJoin = !empty($filters['client_id']) || !empty($filters['partner_id']) || !empty($filters['service_id']) || !empty($filters['q']);
        
        $sql = "SELECT SUM(amount) FROM transactions t";
        if ($needJoin) {
            $sql .= " LEFT JOIN projects p ON t.project_id = p.id";
        }

        $conditions[] = "t.company_id = ?";
        $params[] = \Core\Helpers::currentCompanyId();

        // Date Range
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $conditions[] = "t.transaction_date BETWEEN ? AND ?";
            $params[] = $filters['start_date'];
            $params[] = $filters['end_date'];
        }

        // Type Filter
        if (!empty($filters['type'])) {
            $conditions[] = "t.type = ?";
            $params[] = $filters['type'];
        }

        // Category Filter
        if (!empty($filters['category'])) {
            $conditions[] = "t.category = ?";
            $params[] = $filters['category'];
        }
        if (!empty($filters['category_in']) && is_array($filters['category_in'])) {
            $placeholders = implode(',', array_fill(0, count($filters['category_in']), '?'));
            $conditions[] = "t.category IN ($placeholders)";
            foreach ($filters['category_in'] as $cat) $params[] = $cat;
        }
        
        // Category Not In
         if (!empty($filters['category_not_in']) && is_array($filters['category_not_in'])) {
            $placeholders = implode(',', array_fill(0, count($filters['category_not_in']), '?'));
            if (!empty($filters['include_null_category'])) {
                 $conditions[] = "(t.category IS NULL OR t.category NOT IN ($placeholders))";
            } else {
                 $conditions[] = "t.category NOT IN ($placeholders)";
            }
            foreach ($filters['category_not_in'] as $cat) $params[] = $cat;
        }

        // Status Filter
        if (!empty($filters['status'])) {
            $conditions[] = "t.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['status_or_null'])) {
             $conditions[] = "(t.status IS NULL OR t.status = ?)";
             $params[] = $filters['status_or_null'];
        }

        if (count($conditions) > 0) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() ?: 0;
    }

    public function find($id) {
        $stmt = $this->db->prepare("SELECT * FROM transactions WHERE id = ? AND company_id = ?");
        $stmt->execute([$id, \Core\Helpers::currentCompanyId()]);
        return $stmt->fetch();
    }
    
    public function getTotalPrive() {
        // Total Prive (withdrawals) paid
        $stmt = $this->db->prepare("SELECT SUM(amount) FROM transactions WHERE type = 'expense' AND company_id = ? AND category = 'Prive' AND (status IS NULL OR status = 'paid')");
        $stmt->execute([\Core\Helpers::currentCompanyId()]);
        return $stmt->fetchColumn() ?: 0;
    }

    public function create($data) {
        $fields = ['project_id', 'amount', 'transaction_date', 'type', 'category', 'note', 'status', 'due_date', 'company_id'];
        $columns = [];
        $placeholders = [];
        $values = [];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $columns[] = $field;
                $placeholders[] = '?';
                $values[] = $data[$field];
            }
        }
        if (!in_array('company_id', $columns, true)) {
            $columns[] = 'company_id';
            $placeholders[] = '?';
            $values[] = \Core\Helpers::currentCompanyId();
        }
        
        // Add created_at if not present (usually DB handles it, but just in case)
        
        $sql = "INSERT INTO transactions (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    public function update($id, $data) {
        $fields = ['project_id', 'amount', 'transaction_date', 'type', 'category', 'note', 'status', 'due_date'];
        $set = [];
        $values = [];

        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $set[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($set)) return false;

        $values[] = $id;
        $values[] = \Core\Helpers::currentCompanyId();
        $sql = "UPDATE transactions SET " . implode(', ', $set) . " WHERE id = ? AND company_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM transactions WHERE id = ? AND company_id = ?");
        return $stmt->execute([$id, \Core\Helpers::currentCompanyId()]);
    }
}
