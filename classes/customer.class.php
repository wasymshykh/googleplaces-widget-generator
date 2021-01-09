<?php

class Customer
{
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function get_customer_by ($col, $val)
    {
        $q = "SELECT * FROM `customers` WHERE `$col` = :v";
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

    public function get_customers ()
    {
        $q = "SELECT * FROM `customers`";
        $s = $this->db->prepare($q);
        
        if ($s->execute()) {
            if ($s->rowCount() > 0) {
                return $s->fetchAll();
            }
            return [];
        }
        return [];
    }

    public function create_customer ($uuid, $place, $subscription, $status, $interval)
    {
        $q = "INSERT INTO `customers` (`customer_uuid`, `customer_place_id`, `customer_subscription`, `customer_status`, `customer_interval`, `customer_created`) VALUE (:u, :p, :s, :t, :i, :dt)";
        
        $s = $this->db->prepare($q);
        $s->bindParam(':u', $uuid);
        $s->bindParam(':p', $place);
        $s->bindParam(':s', $subscription);
        $s->bindParam(':t', $status);
        $s->bindParam(':i', $interval);
        $dt = current_date();
        $s->bindParam(':dt', $dt);

        return $s->execute();
    }

    public function delete_customer_data ($uuid)
    {
        $q = "DELETE FROM `customers` WHERE `customer_uuid` = :u";
        $s = $this->db->prepare($q);
        $s->bindParam(':u', $uuid);
        if (!$s->execute()) {
            return false;
        }

        $q = "DELETE FROM `caches` WHERE `cache_uuid` = :u";
        $s = $this->db->prepare($q);
        $s->bindParam(':u', $uuid);
        if (!$s->execute()) {
            return false;
        }

        $q = "DELETE FROM `ratings` WHERE `rating_uuid` = :u";
        $s = $this->db->prepare($q);
        $s->bindParam(':u', $uuid);
        if (!$s->execute()) {
            return false;
        }

        return true;
    }

    public function update_customer ($data, $uuid)
    {
        $q = "UPDATE `customers` SET ";
        $i = 0;
        foreach ($data as $col => $val) {
            if ($i > 0) { $q .= ', '; }
            $q .= "`$col` = '$val'";
            $i++;
        }
        $q .= " WHERE `customer_uuid` = '$uuid'";
        $s = $this->db->prepare($q);
        return $s->execute();
    }

}

