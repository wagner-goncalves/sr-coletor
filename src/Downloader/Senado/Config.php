<?php

    namespace SR\Downloader\Senado;

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
            return realpath(".." . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "private" . DIRECTORY_SEPARATOR . "senado" . DIRECTORY_SEPARATOR;
		}
		
		public $urls = array(
			"obterSenadores" => array(
				"Descricao" => "Lista de senadores na atual legislatura",
				"Url" => "http://legis.senado.leg.br/dadosabertos/senador/lista/atual",
				//"Url" => "http://coletor.srcidadao.dev.br/private/senado/xml-exemplo/senador-lista-atual.xml",
				"Parametros" => array(),
                "ParametrosUrlInline" => array()
			),
			"obterMaterias" => array(
				"Descricao" => "Lista de materias que tramitaram e tramitam na legislatura atual",
				"Url" => "http://legis.senado.leg.br/dadosabertos/materia/legislaturaatual",
				//"Url" => "http://coletor.srcidadao.dev.br/private/senado/xml-exemplo/materia-legislaturaatual.xml",
				"Parametros" => array(
                    "data" => "",
                    "tramitando" => "N"
                ),
                "ParametrosUrlInline" => array()
			),
			"obterMateriaPorID" => array(
				"Descricao" => "Obtem uma matéria pelo seu código",
				"Url" => "http://legis.senado.leg.br/dadosabertos/materia/{codMateria}",
				//"Url" => "http://coletor.srcidadao.dev.br/private/senado/xml-exemplo/materia-121968.xml",				
                "Parametros" => array(),
                "ParametrosUrlInline" => array(
                    "codMateria" => "", //Código da Matéria - 999999
                )
			),		
            
			"obterVotacaoMateria" => array(
				"Descricao" => "Lista de votos de todos os senadores sobre uma materia.",
				"Url" => "http://legis.senado.leg.br/dadosabertos/materia/votacoes/{codMateria}",
				//"Url" => "http://coletor.srcidadao.dev.br/private/senado/xml-exemplo/materia-votacoes-121968.xml",				
				"Parametros" => array(),
                "ParametrosUrlInline" => array(
                    "codMateria" => "", //Código da Matéria - 999999
                )
			)
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
