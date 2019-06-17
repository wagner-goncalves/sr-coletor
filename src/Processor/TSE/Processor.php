<?php

    namespace SR\Processor\TSE;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
	use SR\Logger\Sucesso;
	use SR\Logger\Erro;
	use SR\Downloader\TSE\Config;
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
            $novoArquivo = $path_parts['dirname'] . DIRECTORY_SEPARATOR . "OK-" . $path_parts['filename'] . "." . $path_parts['extension'];
            return file_exists($novoArquivo);
		}        
        
		public function criarCodigoProcessamento(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{
				
                $codProcessamento = $this->container->db->insert("tse_processamento", array("dataHora" => date("Y-m-d H:i:s")));	
                $processamento = $this->container->db->get("tse_processamento", "*", ["codProcessamento" => $codProcessamento]);
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
        
		private function finalizarProcessamento($arquivo){
			$path_parts = pathinfo($arquivo);
            $novoArquivo = $path_parts['dirname'] . "/OK-" . $path_parts['filename'] . "." . $path_parts['extension'];
			if(is_file($arquivo)) rename($arquivo, $novoArquivo);
            return $novoArquivo;
		}
        
		public function defineNomeRelativo($arquivo){
			$base = Config::getDownloaderFileUrl();
			$nome = $base . $arquivo;
			return $nome;
		}
        
        private function getFiles($dir, $filter = '', &$results = array()) {
            $files = scandir($dir);

            foreach($files as $key => $value){
                $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
                if(!is_dir($path)) {
                    if(empty($filter) || preg_match($filter, $path)) $results[] = $path;
                } elseif($value != "." && $value != "..") {
                    $this->getFiles($path, $filter, $results);
                }
            }
            return $results;
        } 
        
		public function consultaCand(ServerRequestInterface $request, ResponseInterface $response, array $args){
			$objDB = null;
            
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $codProcessamento = 0;  
            $respostas = array();
            
            $objConfig = new Config();
            $arquivos = $this->getFiles($objConfig->getDownloderBaseFilePath() . DIRECTORY_SEPARATOR . "consulta_cand" . DIRECTORY_SEPARATOR);            

            //Recupera código de processamento
            $parametros = $request->getQueryParams();
            $codProcessamento = $parametros["codProcessamento"];
            if(intval($codProcessamento) == 0) throw new \Exception("Processamento inválido.");  
            $objDB = $this->getDB();
			
			

            foreach($arquivos as $arquivo){
				
				$quebraLinha = strpos($arquivo, "2006") !== false || strpos($arquivo, "2010") !== false ? "\\r\\n" : "\\n";
				
                if(!is_dir($arquivo)){
                    try{
                        $sql = "LOAD DATA LOCAL INFILE '" . Util::escapaBarraInvertida($arquivo) . "' 
                            REPLACE
                            INTO TABLE tse_candidato 
                            CHARACTER SET 'latin1'
                            FIELDS TERMINATED BY ';' 
                            ENCLOSED BY '\"' 
                            LINES TERMINATED BY '" . $quebraLinha . "' 
                            (DATA_GERACAO, HORA_GERACAO, ANO_ELEICAO, NUM_TURNO, DESCRICAO_ELEICAO, SIGLA_UF, 
                            SIGLA_UE, DESCRICAO_UE, CODIGO_CARGO, DESCRICAO_CARGO, NOME_CANDIDATO, 
                            SEQUENCIAL_CANDIDATO, NUMERO_CANDIDATO, CPF_CANDIDATO, NOME_URNA_CANDIDATO, 
                            COD_SITUACAO_CANDIDATURA, DES_SITUACAO_CANDIDATURA, NUMERO_PARTIDO, SIGLA_PARTIDO, 
                            NOME_PARTIDO, CODIGO_LEGENDA, SIGLA_LEGENDA, COMPOSICAO_LEGENDA, NOME_LEGENDA, 
                            CODIGO_OCUPACAO, DESCRICAO_OCUPACAO, DATA_NASCIMENTO, NUM_TITULO_ELEITORAL_CANDIDATO,
							
							IDADE_DATA_ELEICAO, CODIGO_SEXO, DESCRICAO_SEXO, COD_GRAU_INSTRUCAO,
							DESCRICAO_GRAU_INSTRUCAO, CODIGO_ESTADO_CIVIL, DESCRICAO_ESTADO_CIVIL, 
							
							" . (strpos($arquivo, "2014") !== false ? "CODIGO_COR_RACA, DESCRICAO_COR_RACA, " : "") . "
							
							CODIGO_NACIONALIDADE, 
							DESCRICAO_NACIONALIDADE, SIGLA_UF_NASCIMENTO, CODIGO_MUNICIPIO_NASCIMENTO, NOME_MUNICIPIO_NASCIMENTO, 
							DESPESA_MAX_CAMPANHA, COD_SIT_TOT_TURNO, DESC_SIT_TOT_TURNO
							
							" . (strpos($arquivo, "2014") !== false ? ", EMAIL" : "") . "
							
							)
                            SET tse_candidato.codProcessamento = " . $codProcessamento;

							
                        $objDB->query($sql);
                        $error = $objDB->error();                
                        if(intval($error[0]) > 0) throw new \Exception($error[2]);  
                        $novoArquivo = $arquivo; //$this->finalizarProcessamento($arquivo);

                        //Salva log com resultados do processamento
                        $sucesso = $this->objLogger->addSucesso(
                            new Sucesso(
                                $codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "TSE Candidatos: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio
                            ), "tse_logger"
                        );
                        $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo($novoArquivo)), "tse_logger_arquivo");	

                       $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_PROCESSAMENTO"), "log" => get_object_vars($sucesso)];
                    }catch(\Exception $e){
                        //Salva log com resultados do processamento
                        $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DADOS, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Candidatos: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                        $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo($arquivo)));	

                        $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
                    } 
                }
            } 
            
            //Vincula o CPF do TSE aó político
            
			$sql = "CALL tseAtualizaCpfPolitico(?)";
            $stmt = $objDB->pdo->prepare($sql);
            $stmt->bindParam(1, $codProcessamento, \PDO::PARAM_STR);
            $stmt->execute();            

            return $response->withJson($respostas);
		}
         
		
		public function bemCandidato(ServerRequestInterface $request, ResponseInterface $response, array $args){
			$objDB = null;
            
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $codProcessamento = 0;  
            $respostas = array();
            
            $objConfig = new Config();
            $arquivos = $this->getFiles($objConfig->getDownloderBaseFilePath() . DIRECTORY_SEPARATOR . "bem_candidato" . DIRECTORY_SEPARATOR);
            

            //Recupera código de processamento
            $parametros = $request->getQueryParams();
            $codProcessamento = $parametros["codProcessamento"];
            if(intval($codProcessamento) == 0) throw new \Exception("Processamento inválido.");  
            $objDB = $this->getDB();

            foreach($arquivos as $arquivo){
				
				$quebraLinha = strpos($arquivo, "2006") !== false || strpos($arquivo, "2010") !== false ? "\r\n" : "\n";
				
                if(!is_dir($arquivo)){
                    try{
                        $sql = "LOAD DATA LOCAL INFILE '" . Util::escapaBarraInvertida($arquivo) . "' 
                            REPLACE
                            INTO TABLE tse_bem_candidato 
                            CHARACTER SET 'latin1'
                            FIELDS TERMINATED BY ';' 
                            ENCLOSED BY '\"' 
                            LINES TERMINATED BY '" . $quebraLinha . "' 
                            (DATA_GERACAO, HORA_GERACAO, ANO_ELEICAO, 
                            DESCRICAO_ELEICAO, SIGLA_UF, SQ_CANDIDATO, CD_TIPO_BEM_CANDIDATO, 
                            DS_TIPO_BEM_CANDIDATO, DETALHE_BEM, VALOR_BEM, DATA_ULTIMA_ATUALIZACAO, 
                            HORA_ULTIMA_ATUALIZACAO)
                            SET tse_bem_candidato.codProcessamento = " . $codProcessamento;
                        $objDB->query($sql);
                        $error = $objDB->error();                
                        if(intval($error[0]) > 0) throw new \Exception($error[2]);  
                        
                        $novoArquivo = $this->finalizarProcessamento($arquivo);

                        //Salva log com resultados do processamento
                        $sucesso = $this->objLogger->addSucesso(
                            new Sucesso(
                                $codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "TSE Bens Candidato: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio
                            ), "tse_logger"
                        );
                        $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo($novoArquivo)), "tse_logger_arquivo");	

                       $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_PROCESSAMENTO"), "log" => get_object_vars($sucesso)];
                    }catch(\Exception $e){
                        //Salva log com resultados do processamento
                        $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DADOS, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Bens candidatos: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                        $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo($arquivo)));	

                        $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
                    } 
                }
            }  
            
            //Colocar bens do candidato no banco relacional
            $sql = "CALL tseAtualizaBensCandidato(?)";
            $stmt = $objDB->pdo->prepare($sql);
            $stmt->bindParam(1, $codProcessamento, \PDO::PARAM_STR);
            $stmt->execute();     

            return $response->withJson($respostas);
		}
            
        
		public function prestacaoCandidato2014(ServerRequestInterface $request, ResponseInterface $response, array $args){
			$objDB = null;
            
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $codProcessamento = 0;  
            $respostas = array();
            
            $objConfig = new Config();
            $arquivos = $this->getFiles($objConfig->getDownloderBaseFilePath() . "prestacao_final_2014" . DIRECTORY_SEPARATOR);
            

            //Recupera código de processamento
            $parametros = $request->getQueryParams();
            $codProcessamento = $parametros["codProcessamento"];
            if(intval($codProcessamento) == 0) throw new \Exception("Processamento inválido.");  
            $objDB = $this->getDB();

            foreach($arquivos as $arquivo){
                if(!is_dir($arquivo) && strpos($arquivo, "despesas") !== false){
                    try{
                        $sql = "LOAD DATA LOCAL INFILE '" . Util::escapaBarraInvertida($arquivo) . "' 
                            REPLACE
                            INTO TABLE tse_despesas_candidato 
                            CHARACTER SET 'latin1'
                            FIELDS TERMINATED BY ';' 
                            ENCLOSED BY '\"' 
                            LINES TERMINATED BY '\n' 
                            IGNORE 1 LINES
                            (codigoEleicao, descricaoEleicao, dataHora, cnpjConta, sequencialCandidato, 
                            ufCandidato, siglaPartidoCandidato, numeroCandidato, cargoCandidato, 
                            nomeCandidato, cpfCandidato, tipoDocCandidato, numDocCandidato, 
                            cpfCnpjFornecedor, nomeFornecedor, nomeFornecedorRfb, 
                            codSetorEconomicoFornecedor, setorEconomicoFornecedor, dataDespesa, 
                            valorDespesa, tipoDespesa, descricaoDespesa)

                            SET tse_despesas_candidato.anoEleicao = 2014, tse_despesas_candidato.codProcessamento = " . $codProcessamento;
                        $objDB->query($sql);
                        $error = $objDB->error();                
                        if(intval($error[0]) > 0) throw new \Exception($error[2]);  
                        $novoArquivo = $this->finalizarProcessamento($arquivo);

                        //Salva log com resultados do processamento
                        $sucesso = $this->objLogger->addSucesso(
                            new Sucesso(
                                $codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "TSE Despesas 2014: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio
                            ), "tse_logger"
                        );
                        $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo($novoArquivo)), "tse_logger_arquivo");	

                        $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_PROCESSAMENTO"), "log" => get_object_vars($sucesso)];
                    }catch(\Exception $e){
                        //Salva log com resultados do processamento
                        $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DADOS, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Despesas candidatos: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                        $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo($arquivo)));	

                        $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
                    } 

                }else if(!is_dir($arquivo) && strpos($arquivo, "receitas") !== false){
                    try{
                        $sql = "LOAD DATA LOCAL INFILE '" . Util::escapaBarraInvertida($arquivo) . "' 
                            REPLACE
                            INTO TABLE tse_receitas_candidato 
                            CHARACTER SET 'latin1'
                            FIELDS TERMINATED BY ';' 
                            ENCLOSED BY '\"' 
                            LINES TERMINATED BY '\n' 
                            IGNORE 1 LINES
                            (codigoEleicao, descricaoEleicao, dataHora, cnpjConta, sequencialCandidato, 
                            ufCandidato, siglaPartidoCandidato, numeroCandidato, cargoCandidato, nomeCandidato, cpfCandidato, 
                            numeroRecibo, numeroDocumento, cpfCnpjDoador, nomeDoador, nomeDoadorRfb, siglaUEDoador, 
                            numeroPartidoDoador, numeroCandidatoDoador, codSetorEconomico, descricaoSetorEconomico, dataReceita, 
                            valorReceita, tipoReceita, fonteRecurso, especieRecurso, descricaoReceita, cpfCnpjDoadorOriginario, 
                            nomeDoadorOriginario, tipoDoadorOriginario, setorDoadorOriginario, nomeDoadorOriginarioRfb)
                            SET tse_receitas_candidato.anoEleicao = 2014, tse_receitas_candidato.codProcessamento = " . $codProcessamento;
                        $objDB->query($sql);
                        $error = $objDB->error();                
                        if(intval($error[0]) > 0) throw new \Exception($error[2]);  
                        $novoArquivo = $this->finalizarProcessamento($arquivo);

                        //Salva log com resultados do processamento
                        $sucesso = $this->objLogger->addSucesso(
                            new Sucesso(
                                $codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "TSE Receitas 2014: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio
                            ), "tse_logger"
                        );
                        $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo($novoArquivo)), "tse_logger_arquivo");	

                       $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_PROCESSAMENTO"), "log" => get_object_vars($sucesso)];
                    }catch(\Exception $e){
                        //Salva log com resultados do processamento
                        $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DADOS, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Despesas candidatos: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                        $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo($arquivo)));	

                        $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
                    } 

                }                
            }  

            return $response->withJson($respostas);
		}       
        
        
		public function prestacaoCandidato2010(ServerRequestInterface $request, ResponseInterface $response, array $args){
			$objDB = null;
            
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $parametros = $request->getQueryParams();
            $codProcessamento = $parametros["codProcessamento"];
            if(intval($codProcessamento) == 0) throw new \Exception("Processamento inválido.");  
            $respostas = array();
            
            $objConfig = new Config();
            $arquivos = $this->getFiles($objConfig->getDownloderBaseFilePath() . "prestacao_contas_2010" . DIRECTORY_SEPARATOR  ); 

            $objDB = $this->getDB();

            foreach($arquivos as $arquivo){
                if(!is_dir($arquivo) && strpos($arquivo, "Despesas") !== false){
                    
                    try{
                        $sql = "LOAD DATA LOCAL INFILE '" . Util::escapaBarraInvertida($arquivo) . "' 
                            REPLACE
                            INTO TABLE tse_despesas_candidato 
                            CHARACTER SET 'latin1'
                            FIELDS TERMINATED BY ';' 
                            ENCLOSED BY '\"' 
                            LINES TERMINATED BY '\n' 
                            IGNORE 1 LINES
                            (dataHora, sequencialCandidato, 
                            ufCandidato, siglaPartidoCandidato, numeroCandidato, cargoCandidato, 
                            nomeCandidato, cpfCandidato, entregaConjunto, tipoDocCandidato, numDocCandidato, 
                            cpfCnpjFornecedor, nomeFornecedor, dataDespesa, 
                            valorDespesa, tipoDespesa, fonteRecurso, especieRecurso, descricaoDespesa)
                            SET tse_despesas_candidato.anoEleicao = 2010, tse_despesas_candidato.codProcessamento = " . $codProcessamento;
                        $objDB->query($sql);
                        $error = $objDB->error();                
                        if(intval($error[0]) > 0) throw new \Exception($error[2]);  
                        $novoArquivo = $this->finalizarProcessamento($arquivo);

                        //Salva log com resultados do processamento
                        $sucesso = $this->objLogger->addSucesso(
                            new Sucesso(
                                $codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "TSE Despesas 2010: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio
                            ), "tse_logger"
                        );
                        $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo($novoArquivo)), "tse_logger_arquivo");	

                       $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_PROCESSAMENTO"), "log" => get_object_vars($sucesso)];
                    }catch(\Exception $e){
                        //Salva log com resultados do processamento
                        $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DADOS, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Despesas candidatos: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                        $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo($arquivo)));	

                        $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
                    } 
                    
                }else if(!is_dir($arquivo) && strpos($arquivo, "Receitas") !== false){
                    try{
                        $sql = "LOAD DATA LOCAL INFILE '" . Util::escapaBarraInvertida($arquivo) . "' 
                            REPLACE
                            INTO TABLE tse_receitas_candidato 
                            CHARACTER SET 'latin1'
                            FIELDS TERMINATED BY ';' 
                            ENCLOSED BY '\"' 
                            LINES TERMINATED BY '\n' 
                            IGNORE 1 LINES
                            (dataHora, sequencialCandidato, ufCandidato, siglaPartidoCandidato, 
                            numeroCandidato, cargoCandidato, nomeCandidato, cpfCandidato, 
                            entregaConjunto, numeroRecibo, numeroDocumento, 
                            cpfCnpjDoador, nomeDoador, dataReceita, valorReceita, tipoReceita, 
                            fonteRecurso, especieRecurso, descricaoReceita)
                            SET tse_receitas_candidato.anoEleicao = 2010, tse_receitas_candidato.codProcessamento = " . $codProcessamento;
                        $objDB->query($sql);
                        $error = $objDB->error();                
                        if(intval($error[0]) > 0) throw new \Exception($error[2]);  
                        $novoArquivo = $this->finalizarProcessamento($arquivo);

                        //Salva log com resultados do processamento
                        $sucesso = $this->objLogger->addSucesso(
                            new Sucesso(
                                $codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "TSE Receitas 2010: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio
                            ), "tse_logger"
                        );
                        $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo($novoArquivo)), "tse_logger_arquivo");	

                       $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_PROCESSAMENTO"), "log" => get_object_vars($sucesso)];
                    }catch(\Exception $e){
                        //Salva log com resultados do processamento
                        $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DADOS, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Despesas candidatos: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                        $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo($arquivo)));	

                        $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
                    } 
                }                
            }  

            return $response->withJson($respostas);
		}     
        
		public function prestacaoCandidato2006(ServerRequestInterface $request, ResponseInterface $response, array $args){
			$objDB = null;
            
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $codProcessamento = 0;  
            $respostas = array();
            
            $objConfig = new Config();
            $arquivos = $this->getFiles($objConfig->getDownloderBaseFilePath() . "prestacao_contas_2006" . DIRECTORY_SEPARATOR  ); 

            //Recupera código de processamento
            $parametros = $request->getQueryParams();
            $codProcessamento = $parametros["codProcessamento"];
            if(intval($codProcessamento) == 0) throw new \Exception("Processamento inválido.");  
            $objDB = $this->getDB();

            foreach($arquivos as $arquivo){
                if(!is_dir($arquivo) && strpos($arquivo, "Despesa") !== false){
                    
                    try{
                        $sql = "LOAD DATA LOCAL INFILE '" . Util::escapaBarraInvertida($arquivo) . "' 
                            REPLACE
                            INTO TABLE tse_despesas_candidato 
                            CHARACTER SET 'latin1'
                            FIELDS TERMINATED BY ';' 
                            ENCLOSED BY '\"' 
                            LINES TERMINATED BY '\r\n' 
                            IGNORE 1 LINES
                            (sequencialCandidato, nomeCandidato, cargoCandidato, codigoCargoCandidato, numeroCandidato, 
                            ufCandidato, cnpjConta, numeroPartidoCandidato, siglaPartidoCandidato,
                            valorDespesa, dataDespesa, tipoDespesa, codigoTipoDespesa, descricaoFormaPagamento, 
                            codigoFormaPagamento, numeroDocumento, tipoDocumento, codigoTipoDocumento, 
                            nomeFornecedor, cpfCnpjFornecedor, unidadeEleitoralFornecedor, situacaoCadastral)
                            SET tse_despesas_candidato.anoEleicao = 2006, tse_despesas_candidato.codProcessamento = " . $codProcessamento;
                        $objDB->query($sql);
                        $error = $objDB->error();                
                        if(intval($error[0]) > 0) throw new \Exception($error[2]);  
                        $novoArquivo = $this->finalizarProcessamento($arquivo);

                        //Salva log com resultados do processamento
                        $sucesso = $this->objLogger->addSucesso(
                            new Sucesso(
                                $codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "TSE Despesas 2006: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio
                            ), "tse_logger"
                        );
                        $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo($novoArquivo)), "tse_logger_arquivo");	

                       $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_PROCESSAMENTO"), "log" => get_object_vars($sucesso)];
                    }catch(\Exception $e){
                        //Salva log com resultados do processamento
                        $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DADOS, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Despesas candidatos: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                        $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo($arquivo)));	

                        $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
                    } 
                    
                }else if(!is_dir($arquivo) && strpos($arquivo, "Receita") !== false){
                    try{
                        $sql = "LOAD DATA LOCAL INFILE '" . Util::escapaBarraInvertida($arquivo) . "' 
                            REPLACE
                            INTO TABLE tse_receitas_candidato 
                            CHARACTER SET 'latin1'
                            FIELDS TERMINATED BY ';' 
                            ENCLOSED BY '\"' 
                            LINES TERMINATED BY '\r\n' 
                            IGNORE 1 LINES
                            (sequencialCandidato, nomeCandidato, cargoCandidato, codigoCargoCandidato, numeroCandidato, 
                            ufCandidato, cnpjConta, numeroPartidoCandidato, siglaPartidoCandidato,
                            valorReceita, dataReceita, tipoReceita, codigoTipoReceita, fonteRecurso, 
                            codigoTipoRecurso, nomeDoador, cpfCnpjDoador, siglaUEDoador, 
                            situacaoCadastral)
                            SET tse_receitas_candidato.anoEleicao = 2006, tse_receitas_candidato.codProcessamento = " . $codProcessamento;
                        $objDB->query($sql);
                        $error = $objDB->error();                
                        if(intval($error[0]) > 0) throw new \Exception($error[2]);  
                        $novoArquivo = $this->finalizarProcessamento($arquivo);

                        //Salva log com resultados do processamento
                        $sucesso = $this->objLogger->addSucesso(
                            new Sucesso(
                                $codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "TSE Receitas 2006: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio
                            ), "tse_logger"
                        );
                        $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo($novoArquivo)), "tse_logger_arquivo");	

                       $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_PROCESSAMENTO"), "log" => get_object_vars($sucesso)];
                    }catch(\Exception $e){
                        //Salva log com resultados do processamento
                        $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DADOS, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Despesas candidatos: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                        $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo($arquivo)));	

                        $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
                    } 
                    
                    //Associar CPF à despesa
                    $sql = "UPDATE tse_despesas_candidato dc
                    JOIN tse_candidato c ON c.SEQUENCIAL_CANDIDATO = dc.sequencialCandidato
                    SET dc.cpfCandidato = c.CPF_CANDIDATO";
                    $objDB->query($sql);
                    
                    //Associar CPF à receitas
                    $sql = "UPDATE tse_receitas_candidato dc
                    JOIN tse_candidato c ON c.SEQUENCIAL_CANDIDATO = dc.sequencialCandidato
                    SET dc.cpfCandidato = c.CPF_CANDIDATO";
                    $objDB->query($sql);
                    
                }                
            }  

            return $response->withJson($respostas);
		}     
        
		public function convertePrestacaoContasRelacional(ServerRequestInterface $request, ResponseInterface $response, array $args){
			$objDB = null;
            
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $codProcessamento = 0;  
            $respostas = array();
            
            $objConfig = new Config();
            $objDB = $this->getDB();

            try{

                //Colocar receitas do candidato no banco relacional
                $sql = "CALL tseAtualizaReceitasPolitico()";
                $stmt = $objDB->pdo->prepare($sql);
                $stmt->execute(); 

                $error = $objDB->error();                
                if(intval($error[0]) > 0) throw new \Exception($error[2]);  

                //Salva log com resultados do processamento
                $sucesso = $this->objLogger->addSucesso(
                    new Sucesso(
                        $codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "TSE Converte Receitas Relacional: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio
                    ), "tse_logger"
                );

               $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_PROCESSAMENTO"), "log" => get_object_vars($sucesso)];
            }catch(\Exception $e){
                $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
            } 
            

            try{

                //Colocar receitas do candidato no banco relacional
                $sql = "CALL tseAtualizaDespesasPolitico()";
                $stmt = $objDB->pdo->prepare($sql);
                $stmt->execute(); 

                $error = $objDB->error();                
                if(intval($error[0]) > 0) throw new \Exception($error[2]);  
                
                //Salva log com resultados do processamento
                $sucesso = $this->objLogger->addSucesso(
                    new Sucesso(
                        $codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "TSE Converte Despesas Relacional: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio
                    ), "tse_logger"
                );

               $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_PROCESSAMENTO"), "log" => get_object_vars($sucesso)];
            }catch(\Exception $e){
                //Salva log com resultados do processamento
                $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
            } 
            
            //Atualiza números dos candidatos
            $sql = "CALL tseAtualizaResumoDeclaracaoPolitico()";
            $stmt = $objDB->pdo->prepare($sql);
            $stmt->execute(); 
            
            return $response->withJson($respostas);
		} 
        
		public function resultadoEleicao2006(ServerRequestInterface $request, ResponseInterface $response, array $args){
			$objDB = null;
            
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $codProcessamento = 0;  
            $respostas = array();
            
            $objConfig = new Config();
            $arquivos = $this->getFiles($objConfig->getDownloderBaseFilePath() . DIRECTORY_SEPARATOR . "resultado_eleicoes" . DIRECTORY_SEPARATOR. "2006" . DIRECTORY_SEPARATOR);            

            //Recupera código de processamento
            $parametros = $request->getQueryParams();
            $codProcessamento = $parametros["codProcessamento"];
            if(intval($codProcessamento) == 0) throw new \Exception("Processamento inválido.");  
            $objDB = $this->getDB();

            foreach($arquivos as $arquivo){
                if(!is_dir($arquivo)){
                    try{

                        $xml = file_get_contents($arquivo);
                        $sql = "CALL tseProcessaResultadoEleicao2006(?, ?)";
                        $stmt = $objDB->pdo->prepare($sql);
                        $stmt->bindParam(1, $xml, \PDO::PARAM_STR);
                        $stmt->bindParam(2, $codProcessamento, \PDO::PARAM_STR);
                        $stmt->execute();            
                        
                        $error = $objDB->error();                
                        if(intval($error[0]) > 0) throw new \Exception($error[2]);  
                        $novoArquivo = $this->finalizarProcessamento($arquivo);

                        //Salva log com resultados do processamento
                        $sucesso = $this->objLogger->addSucesso(
                            new Sucesso(
                                $codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "TSE Resultado Eleições: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio
                            ), "tse_logger"
                        );
                        $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo($novoArquivo)), "tse_logger_arquivo");	

                       $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_PROCESSAMENTO"), "log" => get_object_vars($sucesso)];
                    }catch(\Exception $e){
                        //Salva log com resultados do processamento
                        $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DADOS, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "TSE Resultado Eleições: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                        $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo($arquivo)));	

                        $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
                    } 
                }
            } 
            
            return $response->withJson($respostas);
		}
        
		public function resultadoEleicao2010(ServerRequestInterface $request, ResponseInterface $response, array $args){
			$objDB = null;
            
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $codProcessamento = 0;  
            $respostas = array();
            
            $objConfig = new Config();
            $arquivos = $this->getFiles($objConfig->getDownloderBaseFilePath() . "resultado_eleicoes" . DIRECTORY_SEPARATOR . "2010" . DIRECTORY_SEPARATOR  ); 

            //Recupera código de processamento
            $parametros = $request->getQueryParams();
            $codProcessamento = $parametros["codProcessamento"];
            if(intval($codProcessamento) == 0) throw new \Exception("Processamento inválido.");  
            $objDB = $this->getDB();

            foreach($arquivos as $arquivo){


                try{
                    $sql = "LOAD DATA LOCAL INFILE '" . Util::escapaBarraInvertida($arquivo) . "' 
                        REPLACE
                        INTO TABLE tse_resultado_eleicao 
                        CHARACTER SET 'latin1'
                        FIELDS TERMINATED BY ';' 
                        ENCLOSED BY '\"' 
                        LINES TERMINATED BY '\n' 
                        IGNORE 1 LINES
                        (uf, cargo, nome, idade, numero, partido, votosNominais, situacao, sexo)
                        SET tse_resultado_eleicao.anoEleicao = 2010, tse_resultado_eleicao.codProcessamento = " . $codProcessamento;
                    

                    $objDB->query($sql);
                    $error = $objDB->error();                
                    if(intval($error[0]) > 0) throw new \Exception($error[2]);  
                    $novoArquivo = $this->finalizarProcessamento($arquivo);

                    //Salva log com resultados do processamento
                    $sucesso = $this->objLogger->addSucesso(
                        new Sucesso(
                            $codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "TSE Resultados 2010: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio
                        ), "tse_logger"
                    );
                    $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo($novoArquivo)), "tse_logger_arquivo");	

                   $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_PROCESSAMENTO"), "log" => get_object_vars($sucesso)];
                }catch(\Exception $e){
                    //Salva log com resultados do processamento
                    $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DADOS, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "TSE Resultados 2010: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                    $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo($arquivo)));	

                    $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
                } 


            }  

            return $response->withJson($respostas);
		}   
        
		public function resultadoEleicao2014(ServerRequestInterface $request, ResponseInterface $response, array $args){
			$objDB = null;
            
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $codProcessamento = 0;  
            $respostas = array();
            
            $objConfig = new Config();
            $arquivos = $this->getFiles($objConfig->getDownloderBaseFilePath() . "resultado_eleicoes" . DIRECTORY_SEPARATOR . "2014" . DIRECTORY_SEPARATOR  ); 

            //Recupera código de processamento
            $parametros = $request->getQueryParams();
            $codProcessamento = $parametros["codProcessamento"];
            if(intval($codProcessamento) == 0) throw new \Exception("Processamento inválido.");  
            $objDB = $this->getDB();

            foreach($arquivos as $arquivo){


                try{
                    $sql = "LOAD DATA LOCAL INFILE '" . Util::escapaBarraInvertida($arquivo) . "' 
                        REPLACE
                        INTO TABLE tse_resultado_eleicao 
                        CHARACTER SET 'latin1'
                        FIELDS TERMINATED BY ';' 
                        ENCLOSED BY '\"' 
                        LINES TERMINATED BY '\n' 
                        IGNORE 1 LINES
                        (anoEleicao, uf, ue, nome, ocupacao, nomeUrna, cargo, partido, legenda, votosNominais, situacao)
                        SET tse_resultado_eleicao.anoEleicao = 2014, tse_resultado_eleicao.codProcessamento = " . $codProcessamento;
                    

                    $objDB->query($sql);
                    $error = $objDB->error();                
                    if(intval($error[0]) > 0) throw new \Exception($error[2]);  
                    $novoArquivo = $this->finalizarProcessamento($arquivo);

                    //Salva log com resultados do processamento
                    $sucesso = $this->objLogger->addSucesso(
                        new Sucesso(
                            $codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "TSE Resultados 2010: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio
                        ), "tse_logger"
                    );
                    $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo($novoArquivo)), "tse_logger_arquivo");	

                   $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_PROCESSAMENTO"), "log" => get_object_vars($sucesso)];
                }catch(\Exception $e){
                    //Salva log com resultados do processamento
                    $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DADOS, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "TSE Resultados 2010: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                    $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo($arquivo)));	

                    $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
                } 


            }  

            return $response->withJson($respostas);
		}           
        
		public function   converteResutadoEleicaoRelacional(ServerRequestInterface $request, ResponseInterface $response, array $args){
			$objDB = null;
            
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $codProcessamento = 0;  
            $respostas = array();
            
            $objConfig = new Config();
            $objDB = $this->getDB();

            try{

                //Colocar receitas do candidato no banco relacional
                $sql = "CALL tseSumarizaResultadoEleicao()";
                $stmt = $objDB->pdo->prepare($sql);
                $stmt->execute(); 

                $error = $objDB->error();                
                if(intval($error[0]) > 0) throw new \Exception($error[2]);  

                //Salva log com resultados do processamento
                $sucesso = $this->objLogger->addSucesso(
                    new Sucesso(
                        $codProcessamento, Constantes::PROCESSA_DADOS, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "TSE Converte Resultado Eleição Relacional: " . MensagemSistema::get("SUS_PROCESSAMENTO"), Constantes::TRACE_SUCESSO, $dataHoraInicio
                    ), "tse_logger"
                );

               $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_PROCESSAMENTO"), "log" => get_object_vars($sucesso)];
            }catch(\Exception $e){
                $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];		
            } 

            return $response->withJson($respostas);
		} 
        
	}
