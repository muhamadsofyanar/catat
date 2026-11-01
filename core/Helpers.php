<?php
namespace Core;

class Helpers {
    public static function formatRupiah($number) {
        if ($number === null) {
            $number = 0;
        }
        return 'Rp ' . number_format((float)$number, 0, ',', '.');
    }
    public static function currentCompanyId() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $cid = $_SESSION['company_id'] ?? 1;
        if (!$cid || !is_numeric($cid)) {
            $cid = 1;
        }
        return (int)$cid;
    }
}
