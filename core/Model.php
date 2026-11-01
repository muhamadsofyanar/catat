<?php

namespace Core;

abstract class Model {
    protected $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function getDb() {
        return $this->db;
    }
}
