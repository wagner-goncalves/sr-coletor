<?php

    namespace SR\Downloader\Camara;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
	use SR\Logger\Sucesso;
	use SR\Logger\Erro;
	use SR\Downloader\Camara\Config;
	use SR\Downloader\HttpReader;
	use SR\Util\Constantes;
	use SR\Util\MensagemSistema;

	class Downloader{
		
		protected $container;
		private $objLogger = 0;
        public $codProcessamento = 0;
        public $dataHoraInicio = null;
		
		public function __construct($container){            
			$this->objLogger = new \SR\Logger\Logger();
			$this->container = $container;
            $this->dataHoraInicio = date("Y-m-d H:i:s"); //Início da execução da função
		}		
		
		public function getLogger(){
			return $this->objLogger;
		}

		public function getDB(){
			return $this->container->db;
		}		
		
		public function defineNomeArquivo($funcao, $codProcessamento = 0, $adicionais = ""){
            $this->codProcessamento = $codProcessamento;
			$base = Config::getDownloderBaseFilePath();
			$nome = ($base . str_pad(intval($this->codProcessamento), 4, 0, STR_PAD_LEFT) . "-" . $funcao . $adicionais . ".xml");
			return $nome;
		}
        
		public function defineNomeRelativo($funcao, $codProcessamento = 0, $adicionais = ""){
            $this->codProcessamento = $codProcessamento;
			$base = Config::getDownloaderFileUrl();
			$nome = ($base . str_pad(intval($this->codProcessamento), 4, 0, STR_PAD_LEFT) . "-" . $funcao . $adicionais . ".xml");
			return $nome;
		}        
		
        public function getLogs(ServerRequestInterface $request, ResponseInterface $response, array $args){            
            $arrLogs = $this->container->db->query("SELECT * FROM camara_logger ORDER BY oidLogger DESC LIMIT 0, 10")->fetchAll(\PDO::FETCH_ASSOC);
            for($i = 0; $i < count($arrLogs); $i++){
                $arrLogs[$i]["arquivos"] = $this->container->db->query("SELECT * FROM camara_logger_arquivo WHERE oidLogger = " . $arrLogs[$i]["oidLogger"])->fetchAll(\PDO::FETCH_ASSOC);
            }

            return $response->withJson($arrLogs);
        }
        
		public function criarCodigoProcessamento(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				
                $codProcessamento = $this->container->db->insert("camara_processamento", array("dataHora" => date("Y-m-d H:i:s")));	
                $processamento = $this->container->db->get("camara_processamento", "*", ["codProcessamento" => $codProcessamento]);
                $this->container->logger->info("GET criarCodigoProcessamento");
    
                $error = $this->container->db->error();				
                if(intval($error[0]) > 0) throw new \Exception("Erro ao criar código de processamento.");		
                
                return $response->withJson(["success" => true, "codProcessamento" => $processamento["codProcessamento"]]);

            }catch(\Exception $e){
                return $response->withStatus(500)->withJson([
                    "success" => false, 
                    "message" => $e->getMessage()
                ]);
            }                  
		}
		
		public function getUltimoCodigoProcessamento(){
				
			$objDB = $this->getDB();
			$arrCodProcessamento = $objDB->query("SELECT MAX(codProcessamento) AS codProcessamento FROM camara_processamento")->fetchAll(\PDO::FETCH_ASSOC);
			return $arrCodProcessamento[0]["codProcessamento"];

		}
		
		public function processamentoConcluido(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				$parametros = $request->getQueryParams();
				$codProcessamento = intval($parametros["codProcessamento"]);
				$processamento = $this->container->db->get("camara_processamento", "*", ["codProcessamento" => $codProcessamento]);

                $this->container->logger->info("GET processamentoConcluido");
    
                $error = $this->container->db->error();				
                if(intval($error[0]) > 0) throw new \Exception("Erro ao criar código de processamento.");		
                
                return $response->withJson([
					"success" => true, 
					"codProcessamento" => $processamento["codProcessamento"],
					"flgConcluido" => $processamento["flgConcluido"]
				]);
            }catch(\Exception $e){
                return $response->withStatus(500)->withJson([
                    "success" => false, 
                    "message" => $e->getMessage()
                ]);
            }                  
		}
		
		public function obterPartidosCD(ServerRequestInterface $request, ResponseInterface $response, array $args){
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $codProcessamento = 0;  
            $arquivo = "";

			try{
                //Recupera código de processamento
                $parametros = $request->getQueryParams();
				
				if(!isset($parametros["codProcessamento"])){
					$codProcessamento = $this->getUltimoCodigoProcessamento();
				}else{
					$codProcessamento = $parametros["codProcessamento"];
				}
                
				$objConfig = new Config();
				
				$arquivo = $this->defineNomeArquivo(__FUNCTION__, $codProcessamento);
				if(!file_exists($arquivo)){
					$objHttpReader = new HttpReader($objConfig->getUrl(__FUNCTION__, "Url"));
					$objHttpReader->urlSave($arquivo);
				}

                //Salva log com resultados do processamento
                $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DOWNLOAD, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Partidos: " . MensagemSistema::get("SUS_DOWNLOAD"), Constantes::TRACE_SUCESSO, $dataHoraInicio));
                $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento)));	
                
                return $response->withJson(["success" => true, "arquivo" => $arquivo, "message" => "Download com sucesso", "log" => get_object_vars($sucesso)]);	
			}catch(\Exception $e){
                //Salva log com resultados do processamento
                $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DOWNLOAD, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Partidos: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento)));	

                return $response->withStatus(500)->withJson(["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)]);		
			}
		}
		
		public function obterDeputados(ServerRequestInterface $request, ResponseInterface $response, array $args){
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $codProcessamento = 0;  
            $arquivo = "";
                       
			try{
                //Recupera código de processamento
                $parametros = $request->getQueryParams();
				if(!isset($parametros["codProcessamento"])){
					$codProcessamento = $this->getUltimoCodigoProcessamento();
				}else{
					$codProcessamento = $parametros["codProcessamento"];
				}
                
				$objConfig = new Config();
                

                $url = $objConfig->getUrl(__FUNCTION__, "Url");
				
                
				$arquivo = $this->defineNomeArquivo(__FUNCTION__, $codProcessamento);
				if(!file_exists($arquivo)){
					$objHttpReader = new HttpReader($url);
					$objHttpReader->urlSave($arquivo);
				}

                
                //Salva log com resultados do processamento
                $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DOWNLOAD, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Deputados: " . MensagemSistema::get("SUS_DOWNLOAD"), Constantes::TRACE_SUCESSO, $dataHoraInicio));
                $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento)));	
   
                return $response->withJson(["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_DOWNLOAD"), "log" => get_object_vars($sucesso)]);		
			}catch(\Exception $e){
                //Salva log com resultados do processamento
                $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DOWNLOAD, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Deputados: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento)));	
                
                return $response->withStatus(500)->withJson(["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)]);		
			}
		}
		
		public function listarPresencasDia(ServerRequestInterface $request, ResponseInterface $response, array $args){
			
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $codProcessamento = 0;  
            $arquivo = "";     
            
			$parametros = $request->getQueryParams();
			$dataInicial = isset($parametros["dataInicial"]) ? $parametros["dataInicial"] : date("d/m/Y", strtotime("-1 day"));
			$dataFinal = isset($parametros["dataFinal"]) ? $parametros["dataFinal"] : date("d/m/Y", strtotime("-1 day"));
            
            //Recupera código de processamento
			if(!isset($parametros["codProcessamento"])){
				$codProcessamento = $this->getUltimoCodigoProcessamento();
			}else{
				$codProcessamento = $parametros["codProcessamento"];
			}
			
			$objConfig = new Config();
			$respostas = array();

			$objDataInicial = \DateTime::createFromFormat("d/m/Y", $dataInicial);
			$objDataFinal = \DateTime::createFromFormat("d/m/Y", $dataFinal);
			$dias = floor((strtotime($objDataFinal->format("Y-m-d")) - strtotime($objDataInicial->format("Y-m-d")))/(60*60*24));

            for($i = 0; $i < $dias + 1; $i++){
				$data = date("d/m/Y", strtotime($objDataInicial->format("Y-m-d") . " +" . ($i * (60 * 60 * 24)) . " seconds"));	
				try{
                    $dataArquivo = ("-" . str_replace("/", "-", $data));
					$arquivo = $this->defineNomeArquivo(__FUNCTION__, $codProcessamento, $dataArquivo);	
					$sucesso = [];
					
					if(file_exists($arquivo)){				
						$sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DOWNLOAD, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Lista de presença: " . "ARQUIVO JÁ BAIXADO", Constantes::TRACE_SUCESSO, $dataHoraInicio));
						$sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $dataArquivo)));	
                    }else{
                        
						$objConfig->setParametroUrl(__FUNCTION__, "data", $data);
                        
						$objHttpReader = new HttpReader($objConfig->getUrl(__FUNCTION__, "Url"));
                        
						$objHttpReader->urlSave($arquivo);
						
                        
						//Salva log com resultados do processamento
						$sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DOWNLOAD, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Lista de presença: " . MensagemSistema::get("SUS_DOWNLOAD"), Constantes::TRACE_SUCESSO, $dataHoraInicio));
						$sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $dataArquivo)));	
					}
					
					$respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_DOWNLOAD"), "log" => get_object_vars($sucesso)];

				}catch(\Exception $e){

                    //Salva log com resultados do processamento
                    $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DOWNLOAD, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Lista de presença: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                    $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $dataArquivo)));	
                    
                    $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
				}
			}
			
			$this->container->db->update("camara_processamento", [
				"flgTipo" => "download"
			], ["codProcessamento" => $codProcessamento]);			
			
			return $response->withJson($respostas);	
		}	
		
		public function listarProposicoesVotadasEmPlenario(ServerRequestInterface $request, ResponseInterface $response, array $args){
            
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $codProcessamento = 0;  
            $arquivo = "";              
            
			$respostas = array();
			$parametros = $request->getQueryParams();
			$anoInicial = isset($parametros["c"]) ? $parametros["anoInicial"] : date("Y");
			$anoFinal = isset($parametros["anoFinal"]) ? $parametros["anoFinal"] : date("Y");
            
            //Recupera código de processamento
			if(!isset($parametros["codProcessamento"])){
				$codProcessamento = $this->getUltimoCodigoProcessamento();
			}else{
				$codProcessamento = $parametros["codProcessamento"];
			}        

			$anos = $anoFinal - $anoInicial;
			
            for($ano = $anoInicial; $ano < $anoFinal + 1; $ano++){
				
				$dataArquivo = "";
			    try{                
                    $objConfig = new Config();
					$objConfig->setParametroUrl(__FUNCTION__, "ano", $ano);

                    
                    $dataArquivo = ("-" . $ano);
					
                    $arquivo = $this->defineNomeArquivo(__FUNCTION__, $codProcessamento, $dataArquivo); 
					if(!file_exists($arquivo)){
						$objHttpReader = new HttpReader($objConfig->getUrl(__FUNCTION__, "Url"));
						$objHttpReader->urlSave($arquivo);
					}
                    //Salva log com resultados do processamento
                    $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DOWNLOAD, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Lista de proposições votadas em plenário: " . MensagemSistema::get("SUS_DOWNLOAD"), Constantes::TRACE_SUCESSO, $dataHoraInicio));
                    $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $dataArquivo)));	
                    
					$respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_DOWNLOAD"), "log" => get_object_vars($sucesso)];
                }catch(\Exception $e){
                    
                    //Salva log com resultados do processamento
                    $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DOWNLOAD, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Lista de proposições votadas em plenário: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                    $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $dataArquivo)));	
                    
                    $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
                }                
            }
			return $response->withJson($respostas);	
		}	

		public function obterProposicaoPorID(ServerRequestInterface $request, ResponseInterface $response, array $args){
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $codProcessamento = 0;  
            $arquivo = "";  			
            
			$parametros = $request->getQueryParams();
			$dataInicial = isset($parametros["dataInicial"]) ? $parametros["dataInicial"] : date("d/m/Y", strtotime("-1 day"));
			$dataFinal = isset($parametros["dataFinal"]) ? $parametros["dataFinal"] : date("d/m/Y", strtotime("-1 day"));
            $respostas = array();
            
            //Recupera código de processamento
			if(!isset($parametros["codProcessamento"])){
				$codProcessamento = $this->getUltimoCodigoProcessamento();
			}else{
				$codProcessamento = $parametros["codProcessamento"];
			}
                     

            $arrCodProposicao = $this->listarProposicoesVotadasEmPlenarioPorData($dataInicial, $dataFinal); 
			
            $objConfig = new Config();
				
            foreach($arrCodProposicao as $item){
                try{                
                    $objConfig->setParametroUrl(__FUNCTION__, "IdProp", $item);
                    
                    $codProposicao = ("-" . $item);
                    $arquivo = $this->defineNomeArquivo(__FUNCTION__, $codProcessamento, $codProposicao);
					
					if(!file_exists($arquivo)){
						$objHttpReader = new HttpReader($objConfig->getUrl(__FUNCTION__, "Url"));
						$objHttpReader->urlSave($arquivo);
					}
					
                    
                    //Salva log com resultados do processamento
                    $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DOWNLOAD, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Detalhes de proposições votadas em plenário: " . MensagemSistema::get("SUS_DOWNLOAD"), Constantes::TRACE_SUCESSO, $dataHoraInicio));
                    $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $codProposicao)));	
                    
					$respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_DOWNLOAD"), "log" => get_object_vars($sucesso)];
                }catch(\Exception $e){
                    
                    //Salva log com resultados do processamento
                    $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DOWNLOAD, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Detalhes de proposições votadas em plenário: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                    $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $codProposicao)));	
                    
                    $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
                }                    
            }

            if(count($respostas) == 0){
                $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DOWNLOAD, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, MensagemSistema::get("ERR_SEM_REGISTRO_DOWNLOAD"), Constantes::TRACE_SUCESSO, $dataHoraInicio));
                $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_DOWNLOAD"), "log" => get_object_vars($sucesso)];
            }
            
			return $response->withJson($respostas);	
		}	
		
		public function obterVotacaoProposicao(ServerRequestInterface $request, ResponseInterface $response, array $args){
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $codProcessamento = 0;  
            $arquivo = "";  		            
			
			$parametros = $request->getQueryParams();
			$dataInicial = isset($parametros["dataInicial"]) ? $parametros["dataInicial"] : date("d/m/Y", strtotime("-1 day"));
			$dataFinal = isset($parametros["dataFinal"]) ? $parametros["dataFinal"] : date("d/m/Y", strtotime("-1 day"));
			$respostas = array();
			
            //Recupera código de processamento
			if(!isset($parametros["codProcessamento"])){
				$codProcessamento = $this->getUltimoCodigoProcessamento();
			}else{
				$codProcessamento = $parametros["codProcessamento"];
			}
            

			$arrCodProposicao = $this->listarProposicoesPorData($dataInicial, $dataFinal);

            $objConfig = new Config();
            
            if(is_array($arrCodProposicao)){ 
                foreach($arrCodProposicao as $proposicao){
			        try{                    
                        $objConfig->setParametroUrl(__FUNCTION__, "tipo", $proposicao["tipo"]);
                        $objConfig->setParametroUrl(__FUNCTION__, "numero", $proposicao["numero"]);
						$objConfig->setParametroUrl(__FUNCTION__, "ano", $proposicao["ano"]);
						
                        $codProposicao = $proposicao["idProposicao"]; //("-" . $item["codProposicao"]);
                        $arquivo = $this->defineNomeArquivo(__FUNCTION__, $codProcessamento, ("-" . $proposicao["idProposicao"]));

						if(!file_exists($arquivo)){
							$objHttpReader = new HttpReader($objConfig->getUrl(__FUNCTION__, "Url"));
							$objHttpReader->urlSave($arquivo);
						}

                        //Salva log com resultados do processamento
                        $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DOWNLOAD, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Votos de proposições: " . MensagemSistema::get("SUS_DOWNLOAD"), Constantes::TRACE_SUCESSO, $dataHoraInicio));
                        $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $codProposicao)));	
                        
                        $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_DOWNLOAD"), "log" => get_object_vars($sucesso)];
                    }catch(\Exception $e){
                        //Salva log com resultados do processamento
                        $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DOWNLOAD, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Votos de proposições: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                        $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $codProposicao)));	
                        
                        $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
                    }                        
                }
            }
            
            if(count($respostas) == 0){
                $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DOWNLOAD, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, MensagemSistema::get("ERR_SEM_REGISTRO_DOWNLOAD"), Constantes::TRACE_SUCESSO, $dataHoraInicio));
                $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_DOWNLOAD"), "log" => get_object_vars($sucesso)];
            }            

			return $response->withJson($respostas);	
		}
		
		public function listarProposicoesPorData($dataInicial = "", $dataFinal = ""){
			if($dataInicial == "" && $dataFinal == "") $dataInicial = $dataFinal = date("d/m/Y");
			if($dataInicial != "" && $dataFinal == "") $dataFinal = date("d/m/Y");		
			$objDataInicial = \DateTime::createFromFormat("d/m/Y", $dataInicial);
			$objDataFinal = \DateTime::createFromFormat("d/m/Y", $dataFinal);	
			
			$dataInicial = $objDataInicial->format("Y-m-d");
			$dataFinal = $objDataFinal->format("Y-m-d");
			
						
			$objDB = $this->getDB();
			$arrCodProposicao = $objDB->query("SELECT p.idProposicao, p.tipo, p.numero, p.ano FROM camara_proposicao p
				INNER JOIN camara_proposicaoplenario pp on pp.codProposicao = p.idProposicao
				WHERE pp.dataVotacao BETWEEN '" . $dataInicial . "' AND '" . $dataFinal . "'")->fetchAll(\PDO::FETCH_ASSOC);
			return $arrCodProposicao;	
		}		
		
		public function listarProposicoesVotadasEmPlenarioPorData($dataInicial = "", $dataFinal = ""){
			if($dataInicial == "" && $dataFinal == "") $dataInicial = $dataFinal = date("d/m/Y");
			if($dataInicial != "" && $dataFinal == "") $dataFinal = date("d/m/Y");		
			$objDataInicial = \DateTime::createFromFormat("d/m/Y", $dataInicial);
			$objDataFinal = \DateTime::createFromFormat("d/m/Y", $dataFinal);	
			
			$dataInicial = $objDataInicial->format("Y-m-d");
			$dataFinal = $objDataFinal->format("Y-m-d");
						
			$objDB = $this->getDB();
			$arrCodProposicao = $objDB->select("camara_proposicaoplenario", "codProposicao", array("dataVotacao[<>]" => array($dataInicial, $dataFinal)));
			return $arrCodProposicao;	
		}
	}
