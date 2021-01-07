<?php

class Settings
{
    
    private $db;
    private $settings;

    public function __construct (PDO $db) {
        $this->db = $db;
        $this->settings = $this->get_settings();
    }

    private function get_settings ()
    {
        $q = 'SELECT * FROM `settings`';
        $s = $this->db->prepare($q);
        $data = [];
        if (!$s->execute()) {
            return $data;
        }
        $st = $s->fetchAll();
        foreach ($st as $a) {
            $data[$a['setting_name']] = $a['setting_value'];
        }
        return $data;
    }
    
    public function get_all ()
    {
        return $this->settings;
    }

    public function get (string $key)
    {
        if (!array_key_exists($key, $this->settings)) {
            return "";
        }
        return $this->settings[$key];
    }

}
