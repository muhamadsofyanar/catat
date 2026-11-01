<?php

namespace Modules\Projects\Models;

use Core\Model;
use PDO;

class Project extends Model {
    
    public function getAll($filters = []) {
        $conditions = [];
        $params = [];

        // 1. Search (Name, Client, Service)
        if (!empty($filters['q'])) {
            $q = trim($filters['q']);
            $conditions[] = "(p.client_name LIKE ? OR p.service_name LIKE ? OR p.description LIKE ?)";
            $params[] = "%$q%";
            $params[] = "%$q%";
            $params[] = "%$q%";
        }

        // 2. Client Filter
        if (!empty($filters['client_id'])) {
            $conditions[] = "p.client_id = ?";
            $params[] = $filters['client_id'];
        }

        // 3. Partner Filter
        if (!empty($filters['partner_id'])) {
            $conditions[] = "p.partner_id = ?";
            $params[] = $filters['partner_id'];
        }

        // 4. Service Filter
        if (!empty($filters['service_id'])) {
            $conditions[] = "p.service_id = ?";
            $params[] = $filters['service_id'];
        }

        // 5. Status Filter
        $status_filter = $filters['status'] ?? 'active';
        if ($status_filter === 'active') {
            $conditions[] = "p.status != 'done' AND p.client_name != 'INTERNAL'";
        } elseif ($status_filter === 'done') {
            $conditions[] = "p.status = 'done'";
        } elseif ($status_filter === 'internal') {
            $conditions[] = "p.client_name = 'INTERNAL'";
        } elseif ($status_filter === 'all') {
            // No condition
        }

        // 6. Date Range (Created At)
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $conditions[] = "date(p.created_at) BETWEEN ? AND ?";
            $params[] = $filters['start_date'];
            $params[] = $filters['end_date'];
        }

        $whereSql = count($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

        // Complex Subqueries for Financials
        $sql = "
            SELECT p.*, 
            (SELECT SUM(amount) FROM transactions WHERE project_id = p.id AND type = 'income') as paid,
            (SELECT SUM(amount) FROM transactions WHERE project_id = p.id AND type = 'expense' AND category NOT IN ('Kasbon', 'Prive', 'Company Withdrawal', 'Pengembalian Kasbon', 'Pengembalian Dana Perusahaan', 'Operasional Kantor')) as cost,
            (SELECT SUM(amount) FROM transactions WHERE project_id = p.id AND type = 'expense' AND status = 'unpaid' AND category NOT IN ('Kasbon', 'Prive', 'Company Withdrawal', 'Pengembalian Kasbon', 'Pengembalian Dana Perusahaan', 'Operasional Kantor')) as unpaid_vendor,
            part.name as partner_name
            FROM projects p
            LEFT JOIN partners part ON p.partner_id = part.id
            WHERE p.company_id = ?
            " . ($whereSql ? str_replace('WHERE', 'AND', $whereSql) : '') . "
            ORDER BY p.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        array_unshift($params, \Core\Helpers::currentCompanyId());
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find($id) {
        $stmt = $this->db->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function getPartner($partnerId) {
        if (!$partnerId) {
             // Check if there is a default partner set
             $stmt = $this->db->query("SELECT id, name FROM partners WHERE is_default = 1 LIMIT 1");
             return $stmt->fetch();
        }
        $stmt = $this->db->prepare("SELECT name, id FROM partners WHERE id = ?");
        $stmt->execute([$partnerId]);
        return $stmt->fetch();
    }

    public function getFees($id) {
        $stmt = $this->db->prepare("SELECT * FROM project_fees WHERE project_id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    public function getInternalProject() {
        $stmt = $this->db->prepare("SELECT id FROM projects WHERE client_name = 'INTERNAL' AND service_name = 'KEUANGAN PERUSAHAAN'");
        $stmt->execute();
        return $stmt->fetch();
    }

    public function getDocuments($id) {
        $stmt = $this->db->prepare("SELECT * FROM project_documents WHERE project_id = ? ORDER BY category, uploaded_at DESC");
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    public function getTransactions($id) {
        $stmt = $this->db->prepare("SELECT * FROM transactions WHERE project_id = ? ORDER BY transaction_date DESC, id DESC");
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    public function getServiceStandardCosts($serviceId) {
        if (!$serviceId) return [];
        $stmt = $this->db->prepare("SELECT * FROM service_costs WHERE service_id = ?");
        $stmt->execute([$serviceId]);
        return $stmt->fetchAll();
    }

    public function updateStatus($id, $status) {
        $stmt = $this->db->prepare("UPDATE projects SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    public function delete($id) {
        $project = $this->find($id);
        if (!$project) return false;

        // 1. Delete Documents (Files + DB)
        $stmtDocs = $this->db->prepare("SELECT file_path FROM project_documents WHERE project_id = ?");
        $stmtDocs->execute([$id]);
        $docs = $stmtDocs->fetchAll();
        
        foreach ($docs as $doc) {
            $fullPath = __DIR__ . '/../../../' . $doc['file_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
        
        $this->db->prepare("DELETE FROM project_documents WHERE project_id = ?")->execute([$id]);

        // 2. Delete Transactions
        $this->db->prepare("DELETE FROM transactions WHERE project_id = ?")->execute([$id]);

        // 3. Delete Fees
        $this->db->prepare("DELETE FROM project_fees WHERE project_id = ?")->execute([$id]);

        // 4. Delete Project
        $this->db->prepare("DELETE FROM projects WHERE id = ?")->execute([$id]);

        return $project;
    }
}
