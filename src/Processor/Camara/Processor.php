<?php

    namespace SR\Processor\Camara;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
	use SR\Logger\Sucesso;
	use SR\Logger\Erro;
	use SR\Downloader\Camara\Config;
	use SR\Downloader\HttpReader;
	use SR\Util\Constantes;
	use SR\Util\MensagemSistema;	
	use SR\Logger\Logger;
	use SR\Downloader\Camara\Downloader;
	use SR\Util\Util;

	class Processor{
		private $objLogger;
		private $objDownloader;
		
		public function getLogger(){
			return $this->objLogger;
		}
		
		public function getDB(){
			return $this->container->db;
		}
		
		public function __construct($container){
			$this->objLogger = new Logger();
			$this->objDownloader = new Downloader($container);
			$this->container = $container;
		}
		
		private function arquivoJaProcessado($arquivo){
			$path_parts = pathinfo($arquivo);
            $novoArquivo = $path_parts['dirname'] . "/OK-" . $path_parts['filename'] . "." . $path_parts['extension'];
            return file_exists($novoArquivo);
		}
		
		private function finalizarProcessamento($arquivo){
			return $arquivo;
			/*
			$path_parts = pathinfo($arquivo);
            $novoArquivo = $path_parts['dirname'] . "/OK-" . $path_parts['filename'] . "." . $path_parts['extension'];
			if(is_file($arquivo)) rename($arquivo, $novoArquivo);
			return $novoArquivo;
			*/
		}

		private function processaArquivo($arquivo, $sucesso = "1"){
			$path_parts = pathinfo($arquivo);
            $objDB = $this->getDB();
            $sql = "INSERT INTO camara_processamentoarquivo (arquivo, flgSucesso) VALUES ('" . $path_parts['filename'] . "', '" . $sucesso . "')";
            $objDB->query($sql);
		}

		private function arquivoProcessado($arquivo){
			$path_parts = pathinfo($arquivo);
			$objDB = $this->getDB();
			$arrCodProcessamento = $objDB->query("SELECT * FROM camara_processamentoarquivo WHERE arquivo = '" . $path_parts['filename'] . "'")->fetchAll(\PDO::FETCH_ASSOC);
			if(count($arrCodProcessamento) > 0) return true;
			else return false;


		}

		public function defineNomeRelativo($funcao, $codProcessamento = 0, $adicionais = ""){
            $this->codProcessamento = $codProcessamento;
			if($this->codProcessamento == 0){ 
				$this->codProcessamento = $this->getDB()->max("camara_processamento", "codProcessamento");
			}
			$base = Config::getDownloaderFileUrl();
			$nome = ($base . "OK-" . str_pad(intval($this->codProcessamento), 4, 0, STR_PAD_LEFT) . "-" . $funcao . $adicionais . ".xml");
			return $nome;
		}         
		
		public function getUltimoCodigoProcessamento(){
				
			$objDB = $this->getDB();
			$arrCodProcessamento = $objDB->query("SELECT MAX(codProcessamento) AS codProcessamento FROM camara_processamento")->fetchAll(\PDO::FETCH_ASSOC);
			return $arrCodProcessamento[0]["codProcessamento"];

		}		
		
		public function obterPartidosCD(ServerRequestInterface $request, ResponseInterface $response, array $args){
			$objDB = null;
            
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
                
				$objDB = $this->getDB();
				$arquivo = $this->objDownloader->defineNomeArquivo(__FUNCTION__, $codProcessamento);
				//if($this->arquivoProcessado($arquivo)) throw new \Exception("Arquivo já processado.");
                
				$xml = file_get_contents($arquivo);
				$sql = "CALL camaraObterPartidosCD(?, ?)";
				$stmt = $objDB->pdo->prepare($sql);
				$stmt->bindParam(1, $xml, \PDO::PARAM_STR);
				$stmt->bindParam(2, $codProcessamento, \PDO::PARAM_INT);
				$stmt->execute();				
				
				$error = $objDB->error();				
				if(intval($error[0]) > 0) throw new \Exception("Erro ao processar arquivo.");
                
				$novoArquivo = $this->finalizarProcessamento($arquivo);
                
                $this->convertePartidosDBRelacional($codProcessamento); //Inserte na tabela POLITICO
                
                //Salva log com resultados do processamento
                $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Partidos: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio));
				$sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento)));	
				$this->processaArquivo($arquivo);
				
				return $response->withJson(["arquivo" => $novoArquivo, "success" => true, "message" => "Processado com sucesso. Arquivo renomeado.", "log" => get_object_vars($sucesso)]);
			}catch(\Exception $e){
                //Salva log com resultados do processamento
                $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DADOS, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Partidos: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento)));	
                
                return $response->withStatus(500)->withJson(["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_PROCESSAMENTO"), "log" => get_object_vars($erro)]);		
			}
		}
		
		public function obterDeputados(ServerRequestInterface $request, ResponseInterface $response, array $args){
			$objDB = null;
            
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
                
				$objDB = $this->getDB();
				$arquivo = $this->objDownloader->defineNomeArquivo(__FUNCTION__, $codProcessamento);
				//if($this->arquivoProcessado($arquivo)) throw new \Exception("Arquivo já processado.");
				
				$xml = file_get_contents($arquivo);
				$sql = "CALL camaraObterDeputados(?, ?)";
				$stmt = $objDB->pdo->prepare($sql);
				$stmt->bindParam(1, $xml, \PDO::PARAM_STR);
				$stmt->bindParam(2, $codProcessamento, \PDO::PARAM_INT);
				$stmt->execute();					
				
                $error = $objDB->error();
				if(intval($error[0]) > 0) throw new \Exception("Erro ao processar arquivo.");                

				$novoArquivo = $this->finalizarProcessamento($arquivo);
                
                $this->converteDeputadosDBRelacional($codProcessamento); //Inserte na tabela POLITICO
                
                //Salva log com resultados do processamento
                $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Deputados: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio));
                $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento)));	
				
				$this->processaArquivo($arquivo);

                //Coloca CPF dos políticos na tabele político com base nos dados do TSE
                $sql = "CALL atualizaCpfPolitico()";
                $stmt = $objDB->pdo->prepare($sql);
                $stmt->execute();
                
				return $response->withJson(["arquivo" => $novoArquivo, "success" => true, "message" => "Processado com sucesso. Arquivo renomeado.", "log" => get_object_vars($sucesso)]);
			}catch(\Exception $e){                
                //Salva log com resultados do processamento
                $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DADOS, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Deputados: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento)));	
                
                return $response->withStatus(500)->withJson(["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_PROCESSAMENTO"), "log" => get_object_vars($erro)]);		
			}
		}
        
        private function converteDeputadosDBRelacional($codProcessamento){
			
            $objDB = $this->getDB();
            $sql = "INSERT INTO politico (oidPolitico, oidInstituicao, oidPartido, nome, uf, arquivoFoto, arquivoFotoLocal, email)
                SELECT camara_deputado.ideCadastro, 1, 
                    (SELECT partido.oidPartido FROM partido 
                        WHERE partido.sigla = camara_deputado.partido 
                        LIMIT 1) AS oidPartido, 
                    camara_deputado.nomeParlamentar, camara_deputado.uf, camara_deputado.urlFoto,
					SUBSTRING_INDEX(camara_deputado.urlFoto, '/', -1) as arquivoFotoLocal,
					email
                FROM camara_deputado WHERE camara_deputado.codProcessamento = " . $codProcessamento . "
                ON DUPLICATE KEY UPDATE oidPolitico = camara_deputado.ideCadastro, oidInstituicao = 1, oidPartido = (SELECT partido.oidPartido FROM partido 
                        WHERE partido.sigla = camara_deputado.partido 
                        LIMIT 1), nome = camara_deputado.nomeParlamentar, uf = camara_deputado.uf, arquivoFoto = camara_deputado.urlFoto, arquivoFotoLocal = SUBSTRING_INDEX(camara_deputado.urlFoto, '/', -1), email = camara_deputado.email";
            $objDB->query($sql);
            $error = $objDB->error();	

            if(intval($error[0]) > 0) throw new \Exception("Erro ao inserir deputados no DB relacional.");                 
        }
        
        private function convertePartidosDBRelacional($codProcessamento){
            $objDB = $this->getDB();
            $sql = "INSERT INTO partido (nome, sigla, flgAtivo)
                SELECT camara_partido.nomePartido, camara_partido.siglaPartido, CASE WHEN camara_partido.dataExtincao IS NOT NULL THEN '0' ELSE '1' END AS flgAtivo 
                FROM camara_partido
                WHERE camara_partido.codProcessamento = " . $codProcessamento . "
                ON DUPLICATE KEY UPDATE nome = camara_partido.nomePartido, sigla = camara_partido.siglaPartido";
            $objDB->query($sql);
            $error = $objDB->error();				
            if(intval($error[0]) > 0) throw new \Exception("Erro ao inserir partidos no DB relacional.");                 
        } 
        
        private function converteProposicoesDBRelacional($codProcessamento){
            $objDB = $this->getDB();
            $sql = "INSERT IGNORE INTO proposicao (oidInstituicao, tipo, numero, ano, objeto, resumo, tipoProposicao, tema, ementa, url, flgAtivo)
				SELECT "  . Constantes::INSTITUICAO_CAMARA . ", c.tipo, c.numero, c.ano, r.ObjVotacao, r.Resumo, c.tipoProposicao, c.tema, c.Ementa, c.LinkInteiroTeor, 0
				FROM camara_proposicao c
				INNER JOIN camara_proposicaoresumo r ON r.Sigla = c.tipo AND c.numero = r.Numero AND r.Ano = c.ano
                WHERE c.codProcessamento = " . $codProcessamento . "
				ORDER BY r.Sigla, c.numero, r.Data, r.Ano, r.Hora";

            $objDB->query($sql);
            $error = $objDB->error();				
            if(intval($error[0]) > 0) throw new \Exception("Erro ao inserir proposições no DB relacional.");                 
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
            $parametros = $request->getQueryParams();
			if(!isset($parametros["codProcessamento"])){
				$codProcessamento = $this->getUltimoCodigoProcessamento();
			}else{
				$codProcessamento = $parametros["codProcessamento"];
			}
                       
			$respostas = array();
			$objDB = null;

            if($dataInicial == "" && $dataFinal == "") $dataInicial = $dataFinal = date("d/m/Y");
            if($dataInicial != "" && $dataFinal == "") $dataFinal = date("d/m/Y");
            $objDataInicial = \DateTime::createFromFormat("d/m/Y", $dataInicial);
            $objDataFinal = \DateTime::createFromFormat("d/m/Y", $dataFinal);
            $dias = floor((strtotime($objDataFinal->format("Y-m-d")) - strtotime($objDataInicial->format("Y-m-d")))/(60*60*24));
            
            $objDB = $this->getDB();
			
            for($i = 0; $i < $dias + 1; $i++){
                
                try{
                    $data = date("d/m/Y", strtotime($objDataInicial->format("Y-m-d") . " +" . ($i * (60 * 60 * 24)) . " seconds"));	
                    $dataArquivo = ("-" . str_replace("/", "-", $data));
                    $arquivo = $this->objDownloader->defineNomeArquivo(__FUNCTION__, $codProcessamento, $dataArquivo);
					//if($this->arquivoProcessado($arquivo)) throw new \Exception("Arquivo já processado.");
					
					$xml = file_get_contents($arquivo);
					
					$sucesso = [];
					
					if(false && $this->arquivoJaProcessado($arquivo)){
                        $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Lista de Presença: " . "JÁ PROCESSADO", Constantes::TRACE_SUCESSO, $dataHoraInicio));
						$sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $dataArquivo)));	
						$this->processaArquivo($arquivo);
					}else{
						
						$sql = "CALL camaraListarPresencasDia(?, ?)";
						$stmt = $objDB->pdo->prepare($sql);
						$stmt->bindParam(1, $xml, \PDO::PARAM_STR);
						$stmt->bindParam(2, $codProcessamento, \PDO::PARAM_INT);
						$stmt->execute();	
						
						$error = $objDB->error();				
						if(intval($error[0]) > 0) throw new \Exception("Erro ao processar arquivo."); 

						$novoArquivo = $this->finalizarProcessamento($arquivo);
						
						//Salva log com resultados do processamento
						$sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Lista de Presença: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio));
						$sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $dataArquivo)));	
						$this->processaArquivo($arquivo);
					}
                    
					$respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_PROCESSAMENTO"), "log" => get_object_vars($sucesso)];
                }catch(\Exception $e){
                    //Salva log com resultados do processamento
                    $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DADOS, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Lista de Presença: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                    $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $dataArquivo)));	
                    
                    $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
                }   
				
            }
			
			$this->container->db->update("camara_processamento", [
				"flgTipo" => "processamento"
			], ["codProcessamento" => $codProcessamento]);	
			
			return $response->withJson($respostas);	
		}	
		
		public function listarProposicoesVotadasEmPlenario(ServerRequestInterface $request, ResponseInterface $response, array $args){
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $codProcessamento = 0;  
            $arquivo = "";     

			$parametros = $request->getQueryParams();
			$anoInicial = isset($parametros["anoInicial"]) ? $parametros["anoInicial"] : date("Y");
			$anoFinal = isset($parametros["anoFinal"]) ? $parametros["anoFinal"] : date("Y");
            //Recupera código de processamento
			if(!isset($parametros["codProcessamento"])){
				$codProcessamento = $this->getUltimoCodigoProcessamento();
			}else{
				$codProcessamento = $parametros["codProcessamento"];
			}

			$respostas = array();
			
			$objDB = null;

            if($anoInicial == "" && $anoFinal == "") $anoInicial = $anoFinal = date("Y");
            if($anoInicial != "" && $anoFinal == "") $anoFinal = date("Y");
            $anos = $anoFinal - $anoInicial;
            $objDB = $this->getDB();
			
            for($ano = $anoInicial; $ano < $anoFinal + 1; $ano++){
				
			    try{                
                    $dataArquivo = ("-" . $ano);
                    $arquivo = $this->objDownloader->defineNomeArquivo(__FUNCTION__, $codProcessamento, $dataArquivo);
					//if($this->arquivoProcessado($arquivo)) throw new \Exception("Arquivo já processado.");
					
					$xml = file_get_contents($arquivo);
					$sql = "CALL camaraListarProposicoesVotadasEmPlenario(?, ?)";
					$stmt = $objDB->pdo->prepare($sql);
					$stmt->bindParam(1, $xml, \PDO::PARAM_STR);
					$stmt->bindParam(2, $codProcessamento, \PDO::PARAM_INT);
					$stmt->execute();						

                    $error = $objDB->error();				
                    if(intval($error[0]) > 0) throw new \Exception("Erro ao processar arquivo.");                        
                        
                    $novoArquivo = $this->finalizarProcessamento($arquivo);
                    //Salva log com resultados do processamento
                    $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Lista de proposições votadas em plenário: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio));
                    $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $dataArquivo)));	
                    $this->processaArquivo($arquivo);
					$respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_PROCESSAMENTO"), "log" => get_object_vars($sucesso)];
                }catch(\Exception $e){
                    //Salva log com resultados do processamento
                    $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DADOS, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Lista de proposições votadas em plenário: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                    $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $dataArquivo)));	
                    
                    $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
                }                    
            }
			$this->converteProposicoesDBRelacional($codProcessamento);
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
            //Recupera código de processamento
			if(!isset($parametros["codProcessamento"])){
				$codProcessamento = $this->getUltimoCodigoProcessamento();
			}else{
				$codProcessamento = $parametros["codProcessamento"];
			}
                   
            
            
			$respostas = array();
			$objDB = null;

				$arrCodProposicao = $this->objDownloader->listarProposicoesVotadasEmPlenarioPorData($dataInicial, $dataFinal);

				$objDB = $this->getDB();
				if(is_array($arrCodProposicao)){					
                    foreach($arrCodProposicao as $item){
                        
                        try{
                        
                            $codProposicao = ("-" . $item);
							$arquivo = $this->objDownloader->defineNomeArquivo(__FUNCTION__, $codProcessamento, $codProposicao);
							//if($this->arquivoProcessado($arquivo)) throw new \Exception("Arquivo já processado.");
                            
							$xml = file_get_contents($arquivo);
							$sql = "CALL camaraObterProposicaoPorID(?, ?)";
							$stmt = $objDB->pdo->prepare($sql);
							$stmt->bindParam(1, $xml, \PDO::PARAM_STR);
							$stmt->bindParam(2, $codProcessamento, \PDO::PARAM_INT);
							$stmt->execute();								
                            
                            $error = $objDB->error();				
                            if(intval($error[0]) > 0) throw new \Exception("Erro ao processar arquivo.");                               
                            
                            
                            $novoArquivo = $this->finalizarProcessamento($arquivo);
                            //Salva log com resultados do processamento
                            $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Detalhes de proposições votadas em plenário: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio));
                            $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $codProposicao)));	
                            $this->processaArquivo($arquivo);
                            $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_PROCESSAMENTO"), "log" => get_object_vars($sucesso)];
                        }catch(\Exception $e){
                            //Salva log com resultados do processamento
                            $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DADOS, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Detalhes de proposições votadas em plenário: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                            $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $codProposicao)));	
                            
                            $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
                        }                            
                    }
					
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
            
            //Recupera código de processamento
			if(!isset($parametros["codProcessamento"])){
				$codProcessamento = $this->getUltimoCodigoProcessamento();
			}else{
				$codProcessamento = $parametros["codProcessamento"];
			}
                    
            
			$respostas = array();
			$objDB = null;
            $arrCodProposicao = $this->objDownloader->listarProposicoesPorData($dataInicial, $dataFinal);

            $objDB = $this->getDB();
            if(is_array($arrCodProposicao)){ 

                foreach($arrCodProposicao as $proposicao){
			        try{                    
                        $codProposicao = ("-" . $proposicao["idProposicao"]);
						$arquivo = $this->objDownloader->defineNomeArquivo(__FUNCTION__, $codProcessamento, $codProposicao);
						//if($this->arquivoProcessado($arquivo)) throw new \Exception("Arquivo já processado.");

						$xml = file_get_contents($arquivo);
						
						
						$sql = "CALL camaraObterVotacaoProposicao(?, ?)";
						$stmt = $objDB->pdo->prepare($sql);
						$stmt->bindParam(1, $xml, \PDO::PARAM_STR);
						$stmt->bindParam(2, $codProcessamento, \PDO::PARAM_INT);
						$stmt->execute();	
											
                        
                        $error = $objDB->error();				
                        if(intval($error[0]) > 0) throw new \Exception($sql);                               

                        $novoArquivo = $this->finalizarProcessamento($arquivo);					
                        //Salva log com resultados do processamento
                        $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Votos de proposições: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio));
                        $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $codProposicao)));	
                        $this->processaArquivo($arquivo);
                        $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_PROCESSAMENTO"), "log" => get_object_vars($sucesso)];
                    }catch(\Exception $e){                     
                        //Salva log com resultados do processamento
                        $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DADOS, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Votos de proposições: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                        $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $codProposicao)));	
                        
                        $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
                    }
                }
				
            }
			return $response->withJson($respostas);	
		}
	}
