<?php

namespace Modules\Finance\Models;

use Core\Model;
use PDO;

class Receivable extends Model {
    
    public function getAll($filters = []) {
        $conditions = [];
        $params = [];
        
        $conditions[] = "p.client_name != 'INTERNAL'";
        $conditions[] = "p.company_id = ?";
        $params[] = \Core\Helpers::currentCompanyId();

        if (!empty($filters['q'])) {
            $q = trim($filters['q']);
            $conditions[] = "(p.client_name LIKE ? OR p.service_name LIKE ?)";
            $params[] = "%$q%";
            $params[] = "%$q%";
        }
        
        if (!empty($filters['partner_id'])) {
            $conditions[] = "p.partner_id = ?";
            $params[] = $filters['partner_id'];
        }

        $whereSql = count($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

        $sql = "
            SELECT p.*,
            (SELECT SUM(amount) FROM transactions WHERE project_id = p.id AND type = 'income' AND company_id = p.company_id) AS paid,
            c.phone, c.email
            FROM projects p
            LEFT JOIN clients c ON c.id = p.client_id AND c.company_id = p.company_id
            $whereSql
            ORDER BY COALESCE(p.due_date, '9999-12-31') ASC, p.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        
        $projects = [];
        $only_overdue = !empty($filters['only_overdue']);

        foreach ($rows as $p) {
            $paid = $p['paid'] ?? 0;
            $remaining = ($p['total_price'] - $paid);
            if ($remaining > 0) {
                if ($only_overdue) {
                    if (!empty($p['due_date']) && strtotime($p['due_date']) < strtotime(date('Y-m-d'))) {
                        $projects[] = $p + ['remaining' => $remaining];
                    }
                } else {
                    $projects[] = $p + ['remaining' => $remaining];
                }
            }
        }
        
        return $projects;
    }
    
    public function getTotalReceivable($projects) {
        $sum = 0;
        foreach ($projects as $p) {
            $sum += $p['remaining'];
        }
        return $sum;
    }
}
