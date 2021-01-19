<?php

class Translation {

    private $db;
    private $key_values;
    public $lang;
    
    public function __construct (PDO $db, $lang = '') {
        $this->db = $db;
        $this->lang = $lang;
        $this->key_values = $this->get_pairs($lang);
    }

    public function get_pairs ($lang)
    {
        
        if (!empty($lang)) {
            $q = "SELECT * FROM `translations` WHERE `translation_lang` = '$lang'";
        } else {
            $q = "SELECT * FROM `translations`";
        }
        
        $s = $this->db->prepare($q);
        
        if ($s->execute()) {
            if ($s->rowCount() > 0) {
                if (empty($lang)) {
                    return $s->fetchAll();
                }

                $r = $s->fetchAll();
                // sorting to key value pair if language is given
                $sorted = [];
                foreach ($r as $_r) {
                    $sorted[$_r['translation_key']] = $_r;
                }
                return $sorted;
            }

            return [];
        }
        return [];
    }

    public function get_by_key ($key)
    {
        if (array_key_exists($key, $this->key_values)) {
            return $this->key_values[$key];
        }
        return false;
    }

}
