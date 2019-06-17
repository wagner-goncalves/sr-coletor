<?php

    namespace SR\Downloader\TSE;

    use SR\Util\MensagemSistema;

	class Config{
        
        public $urls = [];
		
        public static function getDatabaseSettings(){
            
			if(Config::ambienteDesenvolvimento()) return [
                'database_type' => 'mysql',
				'server' => getenv("DATABASE_SERVER"),
                'username' => getenv("DATABASE_USER"),
                'password' => getenv("DATABASE_PASSWORD"),
                'database' => getenv("DATABASE_NAME"),
                'charset' => getenv("DATABASE_CHARSET"),
            ];
			else return [
                'database_type' => 'mysql',
				'server' => getenv("DATABASE_SERVER_PRODUCAO"),
                'username' => getenv("DATABASE_USER_PRODUCAO"),
                'password' => getenv("DATABASE_PASSWORD_PRODUCAO"),
                'database' => getenv("DATABASE_NAME_PRODUCAO"),
                'charset' => getenv("DATABASE_CHARSET_PRODUCAO"),
            ];            
        }   		
		
		public static function ambienteDesenvolvimento(){
			$servidor = $_SERVER['SERVER_NAME'];
			if (strpos($servidor, 'desenv') !== false || strpos($servidor, 'dev') !== false || strpos($servidor, 'local') !== false) return true;
			else return false;
		}   		
        
        public static function getDownloaderFileUrl(){
            return "http://" . $_SERVER["HTTP_HOST"] . "/private/tse/";
        }
		
		public static function getDownloderBaseFilePath(){
            //EX: C:\Sites\sr-coletor\trunk\private\camara\
            return realpath(".." . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "private" . DIRECTORY_SEPARATOR . "tse" . DIRECTORY_SEPARATOR;
		}
		
		public static function getdbProcessor($parametro){
			$objConfig = new Config();
			return $objConfig->dbProcessor[$parametro];
		}
	}
