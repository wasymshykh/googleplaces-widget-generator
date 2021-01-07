<?php

class Widget
{
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function get_widget_template($uuid, $template)
    {
        $q = "SELECT * FROM `caches` WHERE `cache_uuid` = :u AND `cache_template_id` = :t";
        $s = $this->db->prepare($q);
        $s->bindParam(':u', $uuid);
        $s->bindParam(':t', $template);
        
        if ($s->execute()) {
            if ($s->rowCount() > 0) {
                return $s->fetch();
            }
            return false;
        }
        return false;
    }

    public function get_rating_by ($col, $val, $multiple = false)
    {
        $q = "SELECT * FROM `ratings` WHERE `$col` = :v";
        $s = $this->db->prepare($q);
        $s->bindParam(':v', $val);
        
        if ($s->execute()) {
            if ($s->rowCount() > 0) {
                return $multiple ? $s->fetchAll() : $s->fetch();
            }
            return $multiple ? [] : false;
        }
        return $multiple ? [] : false;
    }

    public function update_place_data ($uuid, $place_id, $api_key, $existing)
    {
        $content = file_get_contents("https://maps.googleapis.com/maps/api/place/details/json?place_id=$place_id&fields=name,rating,user_ratings_total,formatted_phone_number&key=$api_key");
        if (!$content) {
            return false;
        }

        $place_data = json_decode($content, true);
        
        if (!array_key_exists('result', $place_data) || !array_key_exists('rating', $place_data['result']) || !array_key_exists('user_ratings_total', $place_data['result'])) {
            return false;
        }

        if ($existing) {
            $q = "UPDATE `ratings` SET `rating_aggregate` = :a, `rating_reviews` = :r, `rating_last_update` = :l WHERE `rating_uuid` = :u";
        } else {
            $q = "INSERT INTO `ratings`(`rating_uuid`, `rating_aggregate`, `rating_reviews`, `rating_last_update`) VALUE (:u, :a, :r, :l)";
        }
        
        $s = $this->db->prepare($q);
        $s->bindParam(':u', $uuid);
        $s->bindParam(':a', $place_data['result']['rating']);
        $s->bindParam(':r', $place_data['result']['user_ratings_total']);
        $datetime = current_date();
        $s->bindParam(':l', $datetime);

        if ($s->execute()) {
            return $this->get_rating_by('rating_uuid', $uuid);
        }        
        return false;
    }

    public function get_customer_by ($col, $val, $status = 'A')
    {
        $q = "SELECT * FROM `customers` WHERE `$col` = :v AND `customer_status` = :s";
        $s = $this->db->prepare($q);
        $s->bindParam(':v', $val);
        $s->bindParam(':s', $status);
        
        if ($s->execute()) {
            if ($s->rowCount() > 0) {
                return $s->fetch();
            }
            return false;
        }
        return false;
    }

    public function get_template_by ($col, $val)
    {
        $q = "SELECT * FROM `templates` WHERE `$col` = :v";
        $s = $this->db->prepare($q);
        $s->bindParam(':v', $val);
        
        if ($s->execute()) {
            if ($s->rowCount() > 0) {
                return $s->fetch();
            }
            return false;
        }
        return false;
    }

}


