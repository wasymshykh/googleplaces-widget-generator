<?php

class Template
{
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function get_templates ()
    {
        $q = "SELECT * FROM `templates`";
        $s = $this->db->prepare($q);
        
        if ($s->execute()) {
            if ($s->rowCount() > 0) {
                $templates = $s->fetchAll();
                for ($i = 0; $i < count($templates); $i++) {
                    $templates[$i]['template_html'] = htmlspecialchars_decode($templates[$i]['template_html'], ENT_QUOTES);
                }
                return $templates;
            }
            return [];
        }
        return [];
    }

    public function get_template_by ($col, $val)
    {
        $q = "SELECT * FROM `templates` WHERE `$col` = :v";
        $s = $this->db->prepare($q);
        $s->bindParam(':v', $val);
        
        if ($s->execute()) {
            if ($s->rowCount() > 0) {
                $template = $s->fetch();
                $template['template_html'] = htmlspecialchars_decode($template['template_html'], ENT_QUOTES);
                return $template;
            }
            return false;
        }
        return false;
    }
    
    public function get_template_by_language ($col, $val, $lang)
    {
        $q = "SELECT * FROM `templates` WHERE `$col` = :v WHERE `template_lang` = :l";
        $s = $this->db->prepare($q);
        $s->bindParam(':v', $val);
        $s->bindParam(':l', $lang);
        
        if ($s->execute()) {
            if ($s->rowCount() > 0) {
                $template = $s->fetch();
                $template['template_html'] = htmlspecialchars_decode($template['template_html'], ENT_QUOTES);
                return $template;
            }
            return false;
        }
        return false;
    }

    public function delete_template_data ($id, $lang)
    {
        $q = "DELETE FROM `templates` WHERE `template_id` = :i, `template_lang` = :l";
        $s = $this->db->prepare($q);
        $s->bindParam(':i', $id);
        $s->bindParam(':l', $lang);
        if (!$s->execute()) {
            return false;
        }

        $q = "DELETE FROM `caches` WHERE `cache_template_id` = :i AND `cache_lang` = :l";
        $s = $this->db->prepare($q);
        $s->bindParam(':i', $id);
        $s->bindParam(':l', $lang);
        if (!$s->execute()) {
            return false;
        }

        return true;
    }

    public function create_template ($id, $html, $subscription, $type, $lang)
    {
        $q = "INSERT INTO `templates` (`template_id`, `template_lang`, `template_html`, `template_subscription`, `template_type`, `template_created`) VALUE (:i, :l, :h, :s, :t, :dt)";
        $s = $this->db->prepare($q);
        $s->bindParam(':i', $id);
        $s->bindParam(':l', $lang);
        $s->bindParam(':h', $html);
        $s->bindParam(':s', $subscription);
        $s->bindParam(':t', $type);
        $dt = current_date();
        $s->bindParam(':dt', $dt);

        return $s->execute();
    }

    public function update_template ($data, $id, $lang)
    {
        $q = "UPDATE `templates` SET ";
        $i = 0;
        foreach ($data as $col => $val) {
            if ($i > 0) { $q .= ', '; }
            $q .= "`$col` = '$val'";
            $i++;
        }
        $q .= " WHERE `template_id` = '$id' AND `template_lang` = '$lang'";
        $s = $this->db->prepare($q);
        return $s->execute();
    }

}
