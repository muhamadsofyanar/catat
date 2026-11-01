<?php

namespace Modules\Dashboard\Controllers;

use Modules\Finance\Models\Transaction;
use Modules\Finance\Models\Receivable;
use Modules\Projects\Models\Project;
use PDO;

class DashboardController {
    protected $transactionModel;
    protected $receivableModel;
    protected $projectModel;
    protected $pdo;

    public function __construct() {
        $this->transactionModel = new Transaction();
        $this->receivableModel = new Receivable();
        $this->projectModel = new Project();
        $this->pdo = $this->transactionModel->getDb();
    }

    public function index() {
        $pdo = $this->pdo;

        // Filters
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        $show_internal = isset($_GET['show_internal']) && $_GET['show_internal'] === '1';
        $only_unpaid = isset($_GET['only_unpaid']) && $_GET['only_unpaid'] === '1';

        // Build WHERE conditions for projects listing and count
        $conditions = [];
        $params = [];
        if (!$show_internal) {
            $conditions[] = "client_name != 'INTERNAL'";
        }
        if ($q !== '') {
            $conditions[] = "(client_name LIKE ? OR service_name LIKE ?)";
            $params[] = "%$q%";
            $params[] = "%$q%";
        }
        $whereSql = count($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

        // Count projects
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM projects $whereSql");
        $stmtCount->execute($params);
        $total_projects = $stmtCount->fetchColumn();

        // Financials
        // Total Income
        $total_income = $this->transactionModel->getSum([
            'type' => 'income',
            'category_not_in' => ['Pengembalian Kasbon', 'Pengembalian Dana Perusahaan'],
            'include_null_category' => true
        ]);

        // Total Prive
        $total_prive = $this->transactionModel->getSum([
            'type' => 'expense',
            'category' => 'Prive'
        ]);

        // Total Company Withdrawal
        $total_company_withdrawal = $this->transactionModel->getSum([
            'type' => 'expense',
            'category' => 'Company Withdrawal'
        ]);

        // Internal Project ID
        $internal_project = $this->projectModel->getInternalProject();
        $internal_pid = $internal_project ? $internal_project['id'] : 0;

        // Total Operational Expenses
        $sqlOps = "SELECT SUM(amount) FROM transactions WHERE type = 'expense' AND project_id != ? AND (category NOT IN ('Prive', 'Company Withdrawal', 'Company Expense', 'Kasbon') OR category IS NULL)";
        $stmtOps = $pdo->prepare($sqlOps);
        $stmtOps->execute([$internal_pid]);
        $total_expense_operational = $stmtOps->fetchColumn() ?: 0;

        // Total Office Ops
        $sqlOffice = "SELECT SUM(amount) FROM transactions WHERE type = 'expense' AND project_id = ? AND category NOT IN ('Prive', 'Company Withdrawal', 'Company Expense', 'Kasbon')";
        $stmtOffice = $pdo->prepare($sqlOffice);
        $stmtOffice->execute([$internal_pid]);
        $total_ops_kantor = $stmtOffice->fetchColumn() ?: 0;

        // Total Paid Expenses
        $total_expense_paid = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'expense' AND (status IS NULL OR status = 'paid')")->fetchColumn() ?: 0;

        // Kasbon
        $total_kasbon_out = $pdo->query("SELECT SUM(amount) FROM transactions WHERE category = 'Kasbon' AND type='expense'")->fetchColumn() ?: 0;
        $total_kasbon_in = $pdo->query("SELECT SUM(amount) FROM transactions WHERE category = 'Pengembalian Kasbon' AND type='income'")->fetchColumn() ?: 0;
        $total_kasbon = $total_kasbon_out - $total_kasbon_in;

        // Company Withdrawal Logic
        $total_company_debt_out = $pdo->query("SELECT SUM(amount) FROM transactions WHERE category = 'Company Withdrawal' AND type='expense'")->fetchColumn() ?: 0;
        $total_company_in = $pdo->query("SELECT SUM(amount) FROM transactions WHERE category = 'Pengembalian Dana Perusahaan' AND type='income'")->fetchColumn() ?: 0;
        $total_company_debt_net = $total_company_debt_out - $total_company_in;

        $total_company_expense = $pdo->query("SELECT SUM(amount) FROM transactions WHERE category = 'Company Expense' AND type='expense'")->fetchColumn() ?: 0;
        $total_company_usage_net = $total_company_debt_net + $total_company_expense;

        // Repayment
        $total_repayment = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'income' AND category IN ('Pengembalian Kasbon', 'Pengembalian Dana Perusahaan')")->fetchColumn() ?: 0;

        // Cash Balance
        $cash_balance = ($total_income + $total_repayment) - $total_expense_paid;

        // Net Profit
        $net_profit = $total_income - $total_expense_operational - $total_ops_kantor;

        // Profit Distribution
        $share_company = ($net_profit > 0) ? $net_profit * 0.40 : 0;
        $company_available = $share_company - $total_company_usage_net;

        $target_company_balance = 100000000;
        $company_progress_percent = ($company_available > 0) ? ($company_available / $target_company_balance) * 100 : 0;
        $company_progress_percent = min(100, max(0, $company_progress_percent));

        $share_owner = ($net_profit > 0) ? $net_profit * 0.60 : 0;
        $owner_available = $share_owner - $total_prive;

        // Monthly Stats
        $current_month = date('m');
        $current_year = date('Y');
        $start_date_m = "$current_year-$current_month-01";
        $end_date_m = date("Y-m-t", strtotime($start_date_m));

        $monthlyStats = $this->transactionModel->getMonthlyStats($start_date_m, $end_date_m);
        $income_this_month = $monthlyStats['income'];
        $expense_this_month = $monthlyStats['expense'];
        $net_this_month = $monthlyStats['net'];

        // Top Expenses
        $top_expenses = $this->transactionModel->getTopExpenses($start_date_m, $end_date_m);

        // Profit Trend (Logic from index.php - could be moved to model but keeping here for now)
        $trend = [];
        $max_abs = 0;
        for ($i = 0; $i < 12; $i++) {
            $month_start = date('Y-m-01', strtotime("-$i months"));
            $month_end = date('Y-m-t', strtotime($month_start));
            
            $stats = $this->transactionModel->getMonthlyStats($month_start, $month_end);
            $inc = $stats['income'];
            $exp = $stats['expense'];
            $net = $inc - $exp;
            
            $max_abs = max($max_abs, abs($net));
            $trend[] = [
                'label' => date('M y', strtotime($month_start)),
                'net' => $net,
                'income' => $inc,
                'expense' => $exp
            ];
        }
        $trend = array_reverse($trend);

        // Hutang Owner Display
        $share_owner_calc = ($net_profit > 0) ? $net_profit * 0.60 : 0;
        $overdraft_prive = max(0, $total_prive - $share_owner_calc);
        $total_hutang_owner_display = $total_kasbon + $total_company_debt_net + $overdraft_prive;

        // Receivables
        $receivables = $this->receivableModel->getAll(); // Fetches all projects with calculation
        $total_receivable = $this->receivableModel->getTotalReceivable($receivables);

        // Top Clients
        $stmtTopClients = $pdo->query("
            SELECT p.client_id, p.client_name, SUM(t.amount) as total_revenue
            FROM transactions t
            JOIN projects p ON t.project_id = p.id
            WHERE t.type = 'income'
            GROUP BY p.client_id, p.client_name
            ORDER BY total_revenue DESC
            LIMIT 5
        ");
        $top_clients = $stmtTopClients->fetchAll();

        // Total Payable
        $total_payable = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'expense' AND status = 'unpaid'")->fetchColumn() ?: 0;
        $total_payable_used = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'expense' AND status = 'unpaid' AND note LIKE '%[UANG TERPAKAI]%'")->fetchColumn() ?: 0;
        $total_payable_pure = $total_payable - $total_payable_used;

        // Projects List (Recent)
        $stmt = $pdo->prepare("
            SELECT p.*, 
            (SELECT SUM(amount) FROM transactions WHERE project_id = p.id AND type = 'income') as paid,
            (SELECT SUM(amount) FROM transactions WHERE project_id = p.id AND type = 'expense' AND category NOT IN ('Kasbon', 'Prive', 'Company Withdrawal', 'Pengembalian Kasbon', 'Pengembalian Dana Perusahaan', 'Operasional Kantor')) as cost,
            (SELECT SUM(amount) FROM transactions WHERE project_id = p.id AND type = 'expense' AND status = 'unpaid' AND category NOT IN ('Kasbon', 'Prive', 'Company Withdrawal', 'Pengembalian Kasbon', 'Pengembalian Dana Perusahaan', 'Operasional Kantor')) as unpaid_vendor
            FROM projects p 
            $whereSql
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute($params);
        $projects = $stmt->fetchAll();

        if ($only_unpaid) {
            $projects = array_filter($projects, function($p) {
                $paid = $p['paid'] ?? 0;
                $commission = $p['partner_commission'] ?? 0;
                return ($p['total_price'] - $paid - $commission) > 0;
            });
        }
        // Recount if filtered in PHP (though index.php logic kept total_projects from SQL count)
        if ($only_unpaid) {
             $total_projects = count($projects); 
        }

        // View Variables
        $data = compact(
            'q', 'show_internal', 'only_unpaid', 'total_projects',
            'cash_balance', 'net_this_month', 'income_this_month', 'expense_this_month', 'net_profit',
            'total_payable', 'total_payable_pure', 'total_payable_used',
            'total_receivable',
            'company_available', 'share_company', 'total_company_usage_net', 'company_progress_percent',
            'owner_available', 'share_owner', 'total_prive', 'total_hutang_owner_display',
            'trend', 'top_expenses', 'top_clients', 'projects', 'base_url'
        );

        // Render View
        extract($data);
        $pageTitle = "Dashboard - LegaVoice";
        
        // Base URL for view
        $base = '../'; // Since we are in public/index.php (via routing) or simply included. 
                       // If using public/index.php, base should be ../
        
        require __DIR__ . '/../Views/index.php';
    }
}
