<?php

    namespace SR\Downloader\Estado\MG;

    use SR\Util\MensagemSistema;

	class Config{
		
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
            return "http://" . $_SERVER["HTTP_HOST"] . "/private/senado/";
        }
		
		public static function getDownloderBaseFilePath(){
            //EX: C:\Sites\sr-coletor\trunk\private\camara\
            return realpath(".." . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "private" . DIRECTORY_SEPARATOR . "estado" . DIRECTORY_SEPARATOR . "mg" . DIRECTORY_SEPARATOR;
		}
		
		public $urls = array(
			"obterDeputados" => array(
				"Descricao" => "Lista de deputados na atual legislatura",
				"Url" => "http://dadosabertos.almg.gov.br/ws/deputados/em_exercicio",
				"Parametros" => array(),
                "ParametrosUrlInline" => array()
			),
			"obterProposicoes" => array(
				"Descricao" => "Lista de proposicoes que tramitaram e tramitam na legislatura atual",
				"Url" => "http://dadosabertos.almg.gov.br/ws/proposicoes/pesquisa/direcionada",
				//"Url" => "http://coletor.srcidadao.dev.br/private/senado/xml-exemplo/materia-legislaturaatual.xml",
				"Parametros" => array(
					"ano" => "",
					"ini" => "",
					"fim" => "",
                    "sitTram" => "1",
					"tp" => "100",
					"p" => "1",									
                ),
                "ParametrosUrlInline" => array(

				)
			),

		);
		
		public function setParametroUrl($funcao, $parametro, $valor){
			if(!isset($this->urls[$funcao]) || !isset($this->urls[$funcao]["Parametros"][$parametro])) throw new \Exception(MensagemSistema::get("ERR_PARAMETRO_CONFIG"));
			$this->urls[$funcao]["Parametros"][$parametro] = $valor;
		}
		
		public function getParametroUrl($funcao, $parametro){
			if(!isset($this->urls[$funcao]) || !isset($this->urls[$funcao]["Parametros"][$parametro])) throw new \Exception(MensagemSistema::get("ERR_PARAMETRO_CONFIG"));
			return $this->urls[$funcao]["Parametros"][$parametro];
		}
        
		public function setParametroUrlInline($funcao, $parametro, $valor){
			if(!isset($this->urls[$funcao]) || !isset($this->urls[$funcao]["ParametrosUrlInline"][$parametro])) throw new \Exception(MensagemSistema::get("ERR_PARAMETRO_CONFIG"));
			$this->urls[$funcao]["ParametrosUrlInline"][$parametro] = $valor;
		}
		
		public function getParametroUrlInline($funcao, $parametro){
			if(!isset($this->urls[$funcao]) || !isset($this->urls[$funcao]["ParametrosUrlInline"][$parametro])) throw new \Exception(MensagemSistema::get("ERR_PARAMETRO_CONFIG"));
			return $this->urls[$funcao]["ParametrosUrlInline"][$parametro];
		}        
		
		public function getUrl($funcao){
			if(!isset($this->urls[$funcao])) throw new \Exception(MensagemSistema::get("ERR_PARAMETRO_CONFIG"));
			$parametros = "";
            $params = [];
            
			if(count($this->urls[$funcao]["Parametros"]) > 0){
				foreach($this->urls[$funcao]["Parametros"] as $chave => $valor){
					if($valor != "") $params[] = ($chave . "=" . $valor);
				}
                
                $parametros = "?" . implode("&", $params);
			}
            
            
            
			if(count($this->urls[$funcao]["ParametrosUrlInline"]) > 0){
				foreach($this->urls[$funcao]["ParametrosUrlInline"] as $chave => $valor){
                    $this->urls[$funcao]["Url"] = str_replace("{" . $chave . "}", $valor , $this->urls[$funcao]["Url"]);
				}
			}            
            
			return $this->urls[$funcao]["Url"] . $parametros;
		}     
		
		public static function getdbProcessor($parametro){
			$objConfig = new Config();
			return $objConfig->dbProcessor[$parametro];
		}
	}
