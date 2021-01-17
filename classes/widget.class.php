<?php

class Widget
{
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function get_widget_template($uuid, $template, $lang, $mode, $stars)
    {
        $q = "SELECT * FROM `caches` WHERE `cache_uuid` = :u AND `cache_template_id` = :t AND `cache_lang` = :l AND `cache_mode` = :m AND `cache_stars` = :s";
        $s = $this->db->prepare($q);
        $s->bindParam(':u', $uuid);
        $s->bindParam(':t', $template);
        $s->bindParam(':l', $lang);
        $s->bindParam(':m', $mode);
        $s->bindParam(':s', $stars);
        
        if ($s->execute()) {
            if ($s->rowCount() > 0) {
                return $s->fetch();
            }
            return false;
        }
        return false;
    }

    public function get_widget_templates ()
    {
        $q = "SELECT * FROM `caches`";
        $s = $this->db->prepare($q);
        
        if ($s->execute()) {
            if ($s->rowCount() > 0) {
                return $s->fetchAll();
            }
            return [];
        }
        return [];
    }

    public function delete_widgets_caches ($widgets)
    {
        $q = "DELETE FROM `caches` WHERE ";
        $i = 0;
        foreach ($widgets as $widget) {
            if ($i > 0) {
                $q .= ' OR ';
            }
            $q .= "(`cache_uuid` = '".$widget['uuid']."' AND `cache_template_id` = '".$widget['template_id']."')";
            $i++;
        }
        $s = $this->db->prepare($q);
        return $s->execute();
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

    public function get_reviews_by_language ($col, $val, $lang, $filtered = false, $multiple = false)
    {
        $q = "SELECT * FROM `reviews` WHERE `$col` = :v AND `review_lang` = :l ORDER BY `review_time`";
        $s = $this->db->prepare($q);
        $s->bindParam(':v', $val);
        $s->bindParam(':l', $lang);
        
        if ($s->execute()) {
            if ($s->rowCount() > 0) {
                if ($filtered !== false && $multiple !== false) {
                    return $this->_result_by_id_key($filtered, $s->fetchAll());
                }
                return $multiple ? $s->fetchAll() : $s->fetch();
            }
            return $multiple ? [] : false;
        }
        return $multiple ? [] : false;
    }

    private function _result_by_id_key ($key_name, $arr)
    {
        $filtered = [];
        foreach ($arr as $ar) {
            $filtered[$ar[$key_name]] = $ar;
        }
        return $filtered;
    }


    public function update_place_data ($uuid, $place_id, $old_reviews, $lang, $api_key, $existing)
    {
        $URL = "https://maps.googleapis.com/maps/api/place/details/json?place_id=".$place_id."&language=".$lang."&fields=rating,review,user_ratings_total&key=".$api_key;
        $content = file_get_contents($URL, true);
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
        $s->execute();

        // updating reviews
        $to_insert = [];
        $to_update = [];
        foreach ($place_data['result']['reviews'] as $review) {
            // check if review already in the database
            $author_id = $this->_extract_author_id($review['author_url']);
            $review['author_id'] = $author_id;
            $review['review_uuid'] = $uuid;
            $review['time'] = date('Y-m-d H:i:s', $review['time']);
            $review['text'] = normal_text($review['text']);
            if (array_key_exists($author_id, $old_reviews)) {
                if ($old_reviews[$author_id]['review_text'] != $review['text'] || $old_reviews[$author_id]['review_rating'] != $review['rating']) {
                    array_push($to_update, $review);
                }
            } else {
                array_push($to_insert, $review);
            }
        }

        $review_change = false;
        if (!empty($to_insert)) {
            $this->insert_reviews_data($to_insert);
            $review_change = true;
        }
        if (!empty($to_update)) {
            $this->update_reviews_data($to_update);
            $review_change = true;
        }
        $updated_rating = $this->get_rating_by('rating_uuid', $uuid);
        $updated_rating['review_change'] = $review_change;
        return $updated_rating;
    }

    public function insert_reviews_data ($reviews)
    {
        $vals = "";
        foreach ($reviews as $review) {
            if (!empty($vals)) {
                $vals .= ", ";
            }
            $vals .= "('".$review['review_uuid']."', '".$review['author_id']."', '".$review['language']."', '".$review['author_name']."', '".$review['text']."', '".$review['rating']."', '".$review['time']."', '".$review['relative_time_description']."')";
        }
        $q = "INSERT INTO `reviews` (`review_uuid`, `review_author_id`, `review_lang`, `review_author_name`, `review_text`, `review_rating`, `review_time`, `review_time_description`) VALUES $vals";

        $s = $this->db->prepare($q);
        return $s->execute();
    }

    public function update_reviews_data ($reviews)
    {
        foreach ($reviews as $review) {
            $q = "UPDATE `reviews` SET `review_author_name` = :n, `review_text` = :t, `review_rating` = :r, `review_time` = :c, `review_time_description` = :d WHERE `review_uuid` = :u AND `review_lang` = :l AND `review_author_id` = :a";
            $s = $this->db->prepare($q);
            $s->bindParam(":n", $review['author_name']);
            $s->bindParam(":t", $review['text']);
            $s->bindParam(":r", $review['rating']);
            $s->bindParam(":c", $review['time']);
            $s->bindParam(":d", $review['relative_time_description']);
            $s->bindParam(":u", $review['review_uuid']);
            $s->bindParam(":l", $review['language']);
            $s->bindParam(":a", $review['author_id']);
            $s->execute();
        }

        return true;
    }

    private function _extract_author_id ($author_url)
    {
        preg_match_all ("/^[\w:\/.]+contrib\/(\d+)/i", $author_url, $pat_array);
        return $pat_array[1][0] ?? false;
    }

    public function get_company_by ($col, $val, $status = 'A')
    {
        $q = "SELECT * FROM `companies` WHERE `$col` = :v AND `company_status` = :s";
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

    
    public function get_template_by_language ($col, $val, $lang)
    {
        $q = "SELECT * FROM `templates` WHERE `$col` = :v && `template_lang` = :l";
        $s = $this->db->prepare($q);
        $s->bindParam(':v', $val);
        $s->bindParam(':l', $lang);
        
        if ($s->execute()) {
            if ($s->rowCount() > 0) {
                return $s->fetch();
            }
            return false;
        }
        return false;
    }

    public function replace_placeholders ($html, $rating, $place_id, $mode, $branding)
    {
        $replaced = str_replace('{#aggregate}', $rating['rating_aggregate'], $html);
        $replaced = str_replace('{#reviews_total}', $rating['rating_reviews'], $replaced);
        $replaced = str_replace('{#stars_id}', $this->get_svg_star_html_id($rating['rating_aggregate']), $replaced);
        $replaced = str_replace('{#place_id}', $place_id, $replaced);
        $replaced = str_replace('{#mode}', $mode, $replaced);
        $replaced = str_replace('{#branding}', $branding, $replaced);


        return $replaced;
    }

    public function replace_placeholders_reviews ($html, $rating, $reviews, $place_id, $mode, $branding)
    {
        $html = $this->replace_placeholders($html, $rating, $place_id, $mode, $branding);

        $tag_start = '{#comments}';
        $tag_end = '{/#comments}';
                
        $r_start = strpos($html, $tag_start);
        $r_end = strpos($html, $tag_end);
        
        $repeating = substr($html, $r_start, (strlen('{/#comments}') + $r_end) - $r_start);
        
        $html = str_replace($repeating, '', $html);
        $repeating = str_replace($tag_start, '', $repeating);
        $repeating = str_replace($tag_end, '', $repeating);
        
        $comments_html = "";
        
        foreach ($reviews as $review) {
            $review_html = $repeating;
            // replacing placeholders
            $review_html = str_replace('{#comment_rating}', $review['review_rating'], $review_html);
            $review_html = str_replace('{#comment_text}', $review['review_text'], $review_html);
            $review_html = str_replace('{#comment_time_description}', $review['review_time_description'], $review_html);
            $review_html = str_replace('{#comment_author_name}', $review['review_author_name'], $review_html);
            $review_html = str_replace('{#comment_stars_id}', $this->get_svg_star_html_id($review['review_rating']), $review_html);
            $comments_html .= $review_html;
        }
        
        // inject generated comments back to html
        $html = substr_replace($html, $comments_html, $r_start, 0);

        return $html;
    }

    public function minify_html ($html)
    {
        $search = ['/(\n|^)(\x20+|\t)/', '/(\n|^)\/\/(.*?)(\n|$)/', '/\n/',
            '/\<\!--.*?-->/', '/(\x20+|\t)/', '/\>\s+\</', '/(\"|\')\s+\>/', '/=\s+(\"|\')/'];
        $replace = ["\n", "\n", " ", "", " ", "><", "$1>", "=$1"];
        $html = preg_replace($search, $replace, $html);
        return $html;
    }

    public function get_svg_star_html_id ($aggregate)
    {
        // stars-5-0-star
        $stars_id = 'stars-0-0-star';
        if ($aggregate > 0 && $aggregate <= 0.5) {
            $stars_id = 'stars-0-5-star';
        } else if ($aggregate > 0.5 && $aggregate <= 1) {
            $stars_id = 'stars-1-0-star';
        } else if ($aggregate > 1 && $aggregate <= 1.5) {
            $stars_id = 'stars-1-5-star';
        } else if ($aggregate > 1.5 && $aggregate <= 2) {
            $stars_id = 'stars-2-0-star';
        } else if ($aggregate > 2 && $aggregate <= 2.5) {
            $stars_id = 'stars-2-5-star';
        } else if ($aggregate > 2.5 && $aggregate <= 3) {
            $stars_id = 'stars-3-0-star';
        } else if ($aggregate > 3 && $aggregate <= 3.5) {
            $stars_id = 'stars-3-5-star';
        } else if ($aggregate > 3.5 && $aggregate <= 4) {
            $stars_id = 'stars-4-0-star';
        } else if ($aggregate > 4 && $aggregate <= 4.5) {
            $stars_id = 'stars-4-5-star';
        } else if ($aggregate > 4.5 && $aggregate <= 5) {
            $stars_id = 'stars-5-0-star';
        }
        
        return $stars_id;
    }

    public function insert_widget_cache ($uuid, $template_id, $html, $update, $lang, $mode, $stars)
    {
        if ($update) {
            $q = "UPDATE `caches` SET `cache_html` = :h, `cache_created` = :c WHERE `cache_uuid` = :u AND `cache_template_id` = :t";
        } else {
            $q = "INSERT INTO `caches` (`cache_uuid`, `cache_template_id`, `cache_html`, `cache_created`, `cache_lang`, `cache_mode`, `cache_stars`) VALUE (:u, :t, :h, :c, :l, :m, :s)";
        }
        
        $s = $this->db->prepare($q);
        $s->bindParam(':u', $uuid);
        $s->bindParam(':t', $template_id);
        $s->bindParam(':h', $html);
        $datetime = current_date();
        $s->bindParam(':c', $datetime);
        $s->bindParam(':l', $lang);
        $s->bindParam(':m', $mode);
        $s->bindParam(':s', $stars);

        if ($s->execute()) {
            return $this->get_widget_template($uuid, $template_id, $lang, $mode, $stars);
        }
        return false;
    }

}


