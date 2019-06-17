<?php

    namespace SR\Downloader\Camara;

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
            return "http://" . $_SERVER["HTTP_HOST"] . "/private/camara/";
        }
		
		public static function getDownloderBaseFilePath(){
            //EX: C:\Sites\sr-coletor\trunk\private\camara\
            return realpath(".." . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "private" . DIRECTORY_SEPARATOR . "camara" . DIRECTORY_SEPARATOR;
		}
		
		public $urls = array(
			"obterPartidosCD" => array(
				"Descricao" => "Lista de partidos na atual legislatura",
				"Url" => "http://www.camara.leg.br/SitCamaraWS/Deputados.asmx/ObterPartidosCD",
				//"Url" => "http://dev.fiscalizador.com.br/plugins/downloader/camara/xml-exemplo/ObterPartidosCD.xml",
				"Parametros" => array()
			),
			"obterDeputados" => array(
				"Descricao" => "Lista de deputados na atual legislatura",
				"Url" => "http://www.camara.leg.br/SitCamaraWS/Deputados.asmx/ObterDeputados",
				//"Url" => "http://dev.fiscalizador.com.br/plugins/downloader/camara/xml-exemplo/ObterDeputados.xml",
				"Parametros" => array()
			),
			"listarPresencasDia" => array(
				"Descricao" => "Quantidade de sessões no dia e presença do parlamentar em cada sessão.",
				"Url" => "http://www.camara.leg.br/SitCamaraWS/sessoesreunioes.asmx/ListarPresencasDia",
				//"Url" => "http://dev.fiscalizador.com.br/plugins/downloader/camara/xml-exemplo/ListarPresencasDia.xml",
				"Parametros" => array(
					"data" => "", //Obrigatório - dd/mm/aaaa
					"numLegislatura" => "", //Obrigatório - inteiro
					"numMatriculaParlamentar" => "", //Opcional - inteiro
					"siglaPartido" => "", //Opcional - char(10)
					"siglaUF" => "" //Opcional - char(2)
				)
			),				
			"listarProposicoesVotadasEmPlenario" => array(
				"Descricao" => "Lista de proposições votadas em plenário",
				"Url" => "http://www.camara.leg.br/SitCamaraWS/Proposicoes.asmx/ListarProposicoesVotadasEmPlenario",
				//"Url" => "http://dev.fiscalizador.com.br/plugins/downloader/camara/xml-exemplo/ListarProposicoesVotadasEmPlenario.xml",
				"Parametros" => array(
					"ano" => "", //Obrigatório - 9999
					"tipo" => "" //Opcional
				)
			),	
			"obterProposicaoPorID" => array(
				"Descricao" => "Obtem uma proposição pelo seu código",
				"Url" => "http://www.camara.leg.br/SitCamaraWS/Proposicoes.asmx/ObterProposicaoPorID",
				//"Url" => "http://dev.fiscalizador.com.br/plugins/downloader/camara/xml-exemplo/ObterProposicaoPorID.xml",
				"Parametros" => array(
					"IdProp" => "", //Código da Proposição - 999999
				)
			),						
					
			"obterVotacaoProposicao" => array(
				"Descricao" => "Lista de votos de todos os deputados sobre uma proposição.",
				"Url" => "http://www.camara.leg.br/SitCamaraWS/Proposicoes.asmx/ObterVotacaoProposicao",
				//"Url" => "http://dev.fiscalizador.com.br/plugins/downloader/camara/xml-exemplo/ObterVotacaoProposicao.xml",
				"Parametros" => array(
					"tipo" => "", //Obrigatório - char(10)
					"numero" => "", //Obrigatório - inteiro
					"ano" => "" //Obrigatório - inteiro
				)
			)
		);
		
		public function setParametroUrl($funcao, $parametro, $valor){
			if(!isset($this->urls[$funcao]) || !isset($this->urls[$funcao]["Parametros"][$parametro])) throw new Exception(MensagemSistema::get("ERR_PARAMETRO_CONFIG"));
			$this->urls[$funcao]["Parametros"][$parametro] = $valor;
		}
		
		public function getParametroUrl($funcao, $parametro){
			if(!isset($this->urls[$funcao]) || !isset($this->urls[$funcao]["Parametros"][$parametro])) throw new Exception(MensagemSistema::get("ERR_PARAMETRO_CONFIG"));
			return $this->urls[$funcao]["Parametros"][$parametro];
		}
		
		public function getUrl($funcao){
			if(!isset($this->urls[$funcao])) throw new Exception(MensagemSistema::get("ERR_PARAMETRO_CONFIG"));
			$parametros = "";
			if(count($this->urls[$funcao]["Parametros"]) > 0){
				$parametros = "?";
				foreach($this->urls[$funcao]["Parametros"] as $chave => $valor){
					$parametros .= ("&" . $chave . "=" . $valor);
				}
			}
			return ($this->urls[$funcao]["Url"] . $parametros);
		}
		
		public static function getdbProcessor($parametro){
			$objConfig = new Config();
			return $objConfig->dbProcessor[$parametro];
		}		
		
	}
	
?>