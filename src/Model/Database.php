<?php
    namespace SR\Model;

    class Database extends \medoo
    {
        
        public function getPdo(){
            return $this->pdo;    
        }
                
        /*
        public function query($query)
        {
            
        }
        
        public function select($table, $join, $columns = null, $where = null)
        
        public function insert($table, $datas)
        
        public function update($table, $data, $where = null)
        
        public function delete($table, $where)
        
        public function replace($table, $columns, $search = null, $replace = null, $where = null)
        
        public function get($table, $join = null, $column = null, $where = null)
        */

    }
?>