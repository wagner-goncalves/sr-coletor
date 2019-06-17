<?php

    namespace SR\Processor\Senado;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
	use SR\Logger\Sucesso;
	use SR\Logger\Erro;
	use SR\Downloader\Senado\Config;
	use SR\Downloader\HttpReader;
	use SR\Util\Constantes;
	use SR\Util\MensagemSistema;	
	use SR\Logger\Logger;
	use SR\Downloader\Senado\Downloader;
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
        
		public function getUltimoCodigoProcessamento(){
				
			$objDB = $this->getDB();
			$arrCodProcessamento = $objDB->query("SELECT MAX(codProcessamento) AS codProcessamento FROM senado_processamento")->fetchAll(\PDO::FETCH_ASSOC);
			return $arrCodProcessamento[0]["codProcessamento"];

		}	

		public function defineNomeRelativo($funcao, $codProcessamento = 0, $adicionais = ""){
            $this->codProcessamento = $codProcessamento;
			if($this->codProcessamento == 0){ 
				$this->codProcessamento = $this->getDB()->max("senado_processamento", "codProcessamento");
			}
			$base = Config::getDownloaderFileUrl();
			$nome = ($base . str_pad(intval($this->codProcessamento), 4, 0, STR_PAD_LEFT) . "-" . $funcao . $adicionais . ".xml");
			return $nome;
		}
		
		public function obterSenadores(ServerRequestInterface $request, ResponseInterface $response, array $args){
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
				
				$xml = file_get_contents($arquivo);
				$sql = "CALL obterSenadores(?, ?)";
				$stmt = $objDB->pdo->prepare($sql);
				$stmt->bindParam(1, $xml, \PDO::PARAM_STR);
				$stmt->bindParam(2, $codProcessamento, \PDO::PARAM_INT);
				$stmt->execute();
				$error = $stmt->errorInfo();
				if(intval($error[0]) > 0) throw new \Exception($error[2]);   

				$novoArquivo = $this->finalizarProcessamento($arquivo);
                
                $this->converteSenadoresDBRelacional($codProcessamento); //Inserte na tabela POLITICO
                
                //Salva log com resultados do processamento
                $sucesso = $this->objLogger->addSucesso(
                    new Sucesso(
                        $codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Senadores: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio
                    ), "senado_logger"
                );
                $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento)), "senado_logger_arquivo");	
                
                //Coloca CPF dos políticos na tabela político com base nos dados do TSE
                $sql = "CALL atualizaCpfPolitico()";
                $stmt = $objDB->pdo->prepare($sql);
                $stmt->execute();	
                
				return $response->withJson(["arquivo" => $novoArquivo, "success" => true, "message" => "Processado com sucesso. Arquivo renomeado.", "log" => get_object_vars($sucesso)]);
			}catch(\Exception $e){                
                //Salva log com resultados do processamento
                $erro = $this->objLogger->addErro(
                    new Erro(
                            $codProcessamento, Constantes::PROCESSA_DADOS, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Senadores: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio
                    ), "senado_logger");
                $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento)), "senado_logger_arquivo");	
                
                return $response->withStatus(500)->withJson(["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_PROCESSAMENTO"), "log" => get_object_vars($erro)]);		
			}
		}
        
        private function converteSenadoresDBRelacional($codProcessamento){
            $objDB = $this->getDB();
            $sql = "INSERT INTO politico (oidPolitico, oidInstituicao, oidPartido, nome, uf, arquivoFoto, arquivoFotoLocal, email)
                        SELECT senado_senador.CodigoParlamentar, 2, 

                            (CASE 
                                WHEN senado_senador.SiglaPartidoParlamentar = 'S/Partido' THEN (SELECT p1.oidPartido FROM partido p1 WHERE p1.sigla = 'S.PART.' LIMIT 1) 
                                ELSE 
                                    (SELECT  partido.oidPartido FROM partido WHERE partido.sigla = senado_senador.SiglaPartidoParlamentar LIMIT 1)
                            END) AS oidPartido, 
                            senado_senador.NomeParlamentar, senado_senador.UfParlamentar, senado_senador.UrlFotoParlamentar,
                            SUBSTRING_INDEX(senado_senador.UrlFotoParlamentar, '/', -1) AS arquivoFotoLocal,
                            senado_senador.EmailParlamentar AS email
                        FROM senado_senador WHERE senado_senador.codProcessamento = " . $codProcessamento . "
                        ON DUPLICATE KEY UPDATE oidPolitico = senado_senador.CodigoParlamentar, oidInstituicao = 2, oidPartido = (SELECT partido.oidPartido FROM partido 
                            WHERE partido.sigla = senado_senador.SiglaPartidoParlamentar 
                            LIMIT 1), nome = senado_senador.NomeParlamentar, uf = senado_senador.UfParlamentar, 
                            arquivoFoto = senado_senador.UrlFotoParlamentar, arquivoFotoLocal = SUBSTRING_INDEX(senado_senador.UrlFotoParlamentar, '/', -1), email = senado_senador.EmailParlamentar ";
            $objDB->query($sql);
            $error = $objDB->error();	

            if(intval($error[0]) > 0) throw new \Exception("Erro ao inserir deputados no DB relacional.");                 
        }       
		
		public function obterMaterias(ServerRequestInterface $request, ResponseInterface $response, array $args){
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $codProcessamento = 0;  
            $arquivo = "";     

            $respostas = array();
			$parametros = $request->getQueryParams();
            $data = isset($parametros["data"]) ? $parametros["data"] : date("Ymd", strtotime("-1 day"));
            $tramitando = isset($parametros["tramitando"]) ? $parametros["tramitando"] : "N";
            
            //Recupera código de processamento
            if(!isset($parametros["codProcessamento"])){
                $codProcessamento = $this->getUltimoCodigoProcessamento();
            }else{
                $codProcessamento = $parametros["codProcessamento"];
            }         

            $objDB = $this->getDB();

			
            try{                
                $dataArquivo = $data;
                $arquivo = $this->objDownloader->defineNomeArquivo(__FUNCTION__, $codProcessamento);

				$xml = file_get_contents($arquivo);
				$sql = "CALL obterMaterias(?, ?)";
				$stmt = $objDB->pdo->prepare($sql);
				$stmt->bindParam(1, $xml, \PDO::PARAM_STR);
				$stmt->bindParam(2, $codProcessamento, \PDO::PARAM_INT);
				$stmt->execute();
				$error = $stmt->errorInfo();
				if(intval($error[0]) > 0) throw new \Exception($error[2]);   
				
                $novoArquivo = $this->finalizarProcessamento($arquivo);
                //Salva log com resultados do processamento
                $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Lista de matérias em tramitação: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio), "senado_logger");
                $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $dataArquivo)), "senado_logger_arquivo");	

                $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_PROCESSAMENTO"), "log" => get_object_vars($sucesso)];
            }catch(\Exception $e){

                //Salva log com resultados do processamento
                $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DADOS, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Lista de matérias em tramitação: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio), "senado_logger");
                $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $dataArquivo)), "senado_logger_arquivo");	

                $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
            }                    
			
			return $response->withJson($respostas);	
		}
        
		public function obterMateriaPorID(ServerRequestInterface $request, ResponseInterface $response, array $args){
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $arquivo = "";  			
            
            $respostas = array();
			$parametros = $request->getQueryParams();
            if(!isset($parametros["codProcessamento"])){
                $codProcessamento = $this->getUltimoCodigoProcessamento();
            }else{
                $codProcessamento = $parametros["codProcessamento"];
            }                
            
			$respostas = array();
			$objDB = null;

            $arrCodigoMateria = $this->objDownloader->listarMaterias($codProcessamento);

            $objDB = $this->getDB();

            foreach($arrCodigoMateria as $item){
                try{
                    $codMateria = ("-" . $item);
                    $arquivo = $this->objDownloader->defineNomeArquivo(__FUNCTION__, $codProcessamento, $codMateria);
                    
                    if(is_file($arquivo)){
						
						$xml = file_get_contents($arquivo);
						$sql = "CALL obterMateriaPorID(?, ?)";
						$stmt = $objDB->pdo->prepare($sql);
						$stmt->bindParam(1, $xml, \PDO::PARAM_STR);
						$stmt->bindParam(2, $codProcessamento, \PDO::PARAM_INT);
						$stmt->execute();
						$error = $stmt->errorInfo();
						if(intval($error[0]) > 0) throw new \Exception($error[2]);   

						
                        $novoArquivo = $this->finalizarProcessamento($arquivo);
                        //Salva log com resultados do processamento
                        $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Detalhes de proposições votadas em plenário: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio), "senado_logger");
                        $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $item)), "senado_logger_arquivo");	

                        $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_PROCESSAMENTO"), "log" => get_object_vars($sucesso)];
                    }
                }catch(\Exception $e){
                    //Salva log com resultados do processamento
                    $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DADOS, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Detalhes de proposições votadas em plenário: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio), "senado_logger");
                    $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $codProposicao)), "senado_logger_arquivo");	

                    $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
                }                            
            }


            $this->converteMateriasDBRelacional($codProcessamento);
            
			return $response->withJson($respostas);	
		}
        
        private function converteMateriasDBRelacional($codProcessamento){
            $objDB = $this->getDB();            
            
            $sql = "INSERT IGNORE INTO proposicao (oidInstituicao, tipo, numero, ano, objeto, resumo, tipoProposicao, tema, ementa, url, flgAtivo, dataHOraEvento)
				SELECT "  . Constantes::INSTITUICAO_SENADO . ", m.SiglaSubtipoMateria, m.NumeroMateria, m.AnoMateria, m.DescricaoSubtipoMateria, dm.EmentaMateria, m.DescricaoSubtipoMateria, 
                    ma.Descricao, dm.ExplicacaoEmentaMateria, '' AS LinkInteiroTeor, 0,
                    dm.DataLeitura
                    FROM senado_materia m
                    INNER JOIN senado_detalhemateria dm ON m.CodigoMateria = dm.CodigoMateria
                    LEFT JOIN senado_materiaassunto ma ON m.CodigoMateria = ma.CodigoMateria
                    WHERE m.codProcessamento = " . $codProcessamento . " AND m.DescricaoSubtipoMateria IN (
                        SELECT m.DescricaoSubtipoMateria AS subtipo FROM senado_materia m 
                        INNER JOIN senado_votoparlamentar v ON m.CodigoMateria = v.CodigoMateria
                        GROUP BY m.DescricaoSubtipoMateria
                    )
                    ORDER BY m.SiglaSubtipoMateria, m.NumeroMateria, m.AnoMateria, dm.DataLeitura";
            
            $objDB->query($sql);
            $error = $objDB->error();				
            if(intval($error[0]) > 0) throw new \Exception("Erro ao inserir materias no DB relacional.");                 
        } 
        
		public function obterVotacaoMateria(ServerRequestInterface $request, ResponseInterface $response, array $args){
			
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $codProcessamento = 0;  
            $arquivo = "";  	            
            
			$parametros = $request->getQueryParams();
            //Recupera código de processamento
            if(!isset($parametros["codProcessamento"])){
                $codProcessamento = $this->getUltimoCodigoProcessamento();
            }else{
                $codProcessamento = $parametros["codProcessamento"];
            }                       
            
			$respostas = array();
			$objDB = null;
            $arrCodigoMateria = $this->objDownloader->listarMaterias($codProcessamento);

            $objDB = $this->getDB();

            $i = 0;
            foreach($arrCodigoMateria as $codigoMateria){
                try{
                    $arquivo = $this->objDownloader->defineNomeArquivo(__FUNCTION__, $codProcessamento, "-" . $codigoMateria);

                    if(is_file($arquivo)){
                        $xml = file_get_contents($arquivo);
                        $sql = "CALL senadoVotos(?, ?)";
                        $stmt = $objDB->pdo->prepare($sql);
                        $stmt->bindParam(1, $xml, \PDO::PARAM_STR);
                        $stmt->bindParam(2, $codProcessamento, \PDO::PARAM_INT);
                        $stmt->execute();
                        $error = $stmt->errorInfo();
                        
                        if(intval($error[0]) > 0) throw new \Exception($error[2]);                               
						
                        $novoArquivo = $this->finalizarProcessamento($arquivo);					
                        //Salva log com resultados do processamento
                        $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Votos de proposições: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio), "senado_logger");
                        $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $codigoMateria)), "senado_logger_arquivo");	

                        $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_PROCESSAMENTO"), "log" => get_object_vars($sucesso)];
                    }
                }catch(\Exception $e){                        
                    //Salva log com resultados do processamento
                    $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DADOS, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Votos de proposições: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio), "senado_logger");
                    $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $codigoMateria)), "senado_logger_arquivo");	

                    $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
                }
                $i++;
            }

			return $response->withJson($respostas);	
		}        
        
	}
