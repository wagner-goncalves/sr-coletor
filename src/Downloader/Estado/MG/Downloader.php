<?php

    namespace SR\Downloader\Estado\MG;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
	use SR\Logger\Sucesso;
	use SR\Logger\Erro;
	use SR\Downloader\Estado\MG\Config;
	use SR\Downloader\HttpReader;
    use SR\Downloader\IDownloader;
	use SR\Util\Constantes;
	use SR\Util\MensagemSistema;

	class Downloader implements IDownloader{

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
        
		public function getUltimoCodigoProcessamento(){
				
			$objDB = $this->getDB();
			$arrCodProcessamento = $objDB->query("SELECT MAX(codProcessamento) AS codProcessamento FROM estado_processamento WHERE uf = 'MG'")->fetchAll(\PDO::FETCH_ASSOC);
			return $arrCodProcessamento[0]["codProcessamento"];

		}        

		public function defineNomeArquivo($funcao, $codProcessamento = 0, $adicionais = ""){
            $this->codProcessamento = $codProcessamento;

			if($this->codProcessamento == 0){
				$this->codProcessamento = $this->getDB()->max("estado_processamento", "codProcessamento");
            }
            
			$base = Config::getDownloderBaseFilePath();
			$nome = ($base . str_pad(intval($this->codProcessamento), 4, 0, STR_PAD_LEFT) . "-" . $funcao . $adicionais . ".xml");
			return $nome;
		}

		public function defineNomeRelativo($funcao, $codProcessamento = 0, $adicionais = ""){
            $this->codProcessamento = $codProcessamento;
			if($this->codProcessamento == 0){
				$this->codProcessamento = $this->getDB()->max("estado_processamento", "codProcessamento");
			}
			$base = Config::getDownloaderFileUrl();
			$nome = ($base . str_pad(intval($this->codProcessamento), 4, 0, STR_PAD_LEFT) . "-" . $funcao . $adicionais . ".xml");
			return $nome;
		}

        public function getLogs(ServerRequestInterface $request, ResponseInterface $response, array $args){
            $arrLogs = $this->container->db->query("SELECT * FROM senado_logger ORDER BY oidLogger DESC LIMIT 0, 10")->fetchAll(\PDO::FETCH_ASSOC);
            for($i = 0; $i < count($arrLogs); $i++){
                $arrLogs[$i]["arquivos"] = $this->container->db->query("SELECT * FROM senado_logger_arquivo WHERE oidLogger = " . $arrLogs[$i]["oidLogger"])->fetchAll(\PDO::FETCH_ASSOC);
            }

            return $response->withJson($arrLogs);
        }

		public function criarCodigoProcessamento(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{

                $codProcessamento = $this->container->db->insert("estado_processamento", array("uf" => "MG", "dataHora" => date("Y-m-d H:i:s")));
                $processamento = $this->container->db->get("estado_processamento", "*", ["codProcessamento" => $codProcessamento]);
                $this->container->logger->info("GET criarCodigoProcessamentoSenado");

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
				$objHttpReader = new HttpReader($objConfig->getUrl(__FUNCTION__, "Url"));
                $arquivo = $this->defineNomeArquivo(__FUNCTION__, $codProcessamento);
				$objHttpReader->urlSave($arquivo);

                //Salva log com resultados do processamento
                $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DOWNLOAD, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Senadores: " . MensagemSistema::get("SUS_DOWNLOAD"), Constantes::TRACE_SUCESSO, $dataHoraInicio), "senado_logger");
                $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento)), "senado_logger_arquivo");

                return $response->withJson(["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_DOWNLOAD"), "log" => get_object_vars($sucesso)]);
			}catch(\Exception $e){
                //Salva log com resultados do processamento
                $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DOWNLOAD, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Senadores: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio), "senado_logger");
                $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento)), "senado_logger_arquivo");

                return $response->withStatus(500)->withJson(["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)]);
			}
		}

		public function obterProposicoes(ServerRequestInterface $request, ResponseInterface $response, array $args){
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s");
            $codProcessamento = 0;
            $arquivo = "";
            

			$respostas = array();
            $parametros = $request->getQueryParams();

            $ano = isset($parametros["ano"]) ? $parametros["ano"] : date("Y");
            $p = isset($parametros["p"]) ? $parametros["p"] : 1;
            $ini = isset($parametros["ini"]) ? $parametros["ini"] : date("Ymd", strtotime("-1 day"));
            $fim = isset($parametros["fim"]) ? $parametros["fim"] : date("Ymd");

            //Recupera código de processamento
				
            if(!isset($parametros["codProcessamento"])){
                $codProcessamento = $this->getUltimoCodigoProcessamento();
            }else{
                $codProcessamento = $parametros["codProcessamento"];
            }

            $dataArquivo = $ano;
            try{
                $objConfig = new Config();

                if($ano != "" && !isset($parametros["ini"])){
                    $objConfig->setParametroUrl(__FUNCTION__, "ano", $ano);
                }else{
                    $objConfig->setParametroUrl(__FUNCTION__, "ini", $ini);
                    $objConfig->setParametroUrl(__FUNCTION__, "fim", $fim);
                }

                
                $pages = 1;
                for($i = 0; $i < $pages; $i++){

                    $objConfig->setParametroUrl(__FUNCTION__, "p", ($i + 1));

                    $objHttpReader = new HttpReader($objConfig->getUrl(__FUNCTION__, "Url"));
                    $arquivo = $this->defineNomeArquivo(__FUNCTION__, $codProcessamento, "-ANO-" . $ano . "-P" . ($i + 1));
                    $objXml = null;

                    if(!file_exists($arquivo)){
                        $objXml = $objHttpReader->urlSave($arquivo);
                    }else{
                        $objXml = $objHttpReader->parseXml($objHttpReader->lerArquivo($arquivo));
                    }

                    if($i == 0) $pages = $this->findPageNumber($objXml);

                    //Salva log com resultados do processamento
                    $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DOWNLOAD, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Lista de materias tramitadas/em tramitação: " . MensagemSistema::get("SUS_DOWNLOAD"), Constantes::TRACE_SUCESSO, $dataHoraInicio), "senado_logger");
                    $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $dataArquivo)), "senado_logger_arquivo");

                    $this->processaProposicao($objHttpReader, $codProcessamento);

                }

                $this->ajustaNomes($codProcessamento);
                $this->notificaVotacao($codProcessamento);

                $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_DOWNLOAD"), "log" => get_object_vars($sucesso)];
            }catch(\Exception $e){
                //Salva log com resultados do processamento
                $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DOWNLOAD, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Lista de materias tramitadas/em tramitação: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio), "senado_logger");
                $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $dataArquivo)), "senado_logger_arquivo");

                $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];
            }
			return $response->withJson($respostas);
        }

        protected function processaProposicao($objHttpReader, $codProcessamento){
            $sql = "CALL almgProposicoes(?, ?)";
            $stmt = $this->container->db->pdo->prepare($sql);
            $text = $objHttpReader->getHttpResult();
            $stmt->bindParam(1, $text, \PDO::PARAM_STR);
            $stmt->bindParam(2, $codProcessamento, \PDO::PARAM_INT);
            $stmt->execute();
        }

        protected function ajustaNomes($idProcessamento){
            
            $arrNomes = $this->container->db->query("SELECT v.* FROM estado_votacao v
                INNER JOIN estado_proposicao p ON v.tipo = p.tipo AND v.numero = p.numero AND v.ano = p.ano AND v.uf = p.uf
                WHERE p.codProcessamento = " . $idProcessamento)->fetchAll(\PDO::FETCH_ASSOC);
            for($i = 0; $i < count($arrNomes); $i++){
                $arrNomes[$i]["politico"] = preg_replace('/([a-z])([A-Z])/', '$1 $2', $arrNomes[$i]["politico"]);

                $this->container->db->update("estado_votacao", [
                    "politico" => trim($arrNomes[$i]["politico"])
                ], ["oidEstadoVotacao" => $arrNomes[$i]["oidEstadoVotacao"]]);	
            }

            $this->container->db->query("UPDATE IGNORE estado_votacao SET politico = TRIM(REPLACE(politico, '\n', ' '))");	

        }
        
        protected function findPageNumber($objXml){
            $noDocumentos = $objXml->xpath('/resultado/noDocumentos');
            $tamanhoPagina = $objXml->xpath('/resultado/tamanhoPagina');
            $docs = (int) $noDocumentos[0];
            $itensPage = (int) $tamanhoPagina[0];

            return round($docs / $itensPage, 0, PHP_ROUND_HALF_UP);
        } 

        public function notificaVotacao($codProcessamento){
            //Início do Processamento

            $dataHoraInicio = date("Y-m-d H:i:s"); 

			try{ 
				
				$objDB = $this->getDB();

				$sql = "
                INSERT IGNORE INTO notificacao (codProcessamento, 
                                        oidInteresse,
                                        oidInstituicao,
                                        oidPartido, 
                                        oidPolitico, 
                                        uf,
                                        titulo, 
                                        texto,
                                        url,
                                        tipo,
                                        tema,
                                        dataHoraEvento,
                                        siglaTipo,
                                        numero,
                                        ano,
                            oidTipoNotificacao,
                            objeto)
                                    
                                    SELECT p.codProcessamento,
                                        1,
                                        3,
                                        (
                                SELECT p1.oidPartido 
                                FROM partido p1 
                                INNER JOIN politico po1 ON po1.oidPartido = p1.oidPartido
                                WHERE (po1.nome = v.politico OR po1.nome LIKE CONCAT(v.politico, '%')) 
                                AND (po1.oidInstituicao = 3 OR po1.uf = 'MG')        
                                ORDER BY v.politico DESC      
                                LIMIT 1 				                    
                                        ) AS oidPartido,
                                        -- v.politico,
                                        (
                                SELECT po1.oidPolitico FROM politico po1
                                WHERE (po1.nome = v.politico OR po1.nome LIKE CONCAT(v.politico, '%')) 
                                AND (po1.oidInstituicao = 3 OR po1.uf = 'MG')       
                                ORDER BY v.politico DESC      
                                LIMIT 1              
                                        ) AS oidPolitico,
                                        'MG',
                            CASE TRIM(v.Voto)
                                WHEN '-' THEN CONCAT('Não votou. ', v.tipo, ' ', v.numero, '/', v.ano)
                                ELSE CONCAT('Votou ', v.voto, '. ', v.tipo, ' ', v.numero, '/', v.ano) 
                            END AS titulo,
                                        (	SELECT 
                                CASE WHEN TRIM(p.ementa) != '' THEN p.ementa ELSE p.assunto END AS ementa 
                                FROM estado_proposicao p WHERE p.numero = v.numero AND p.ano = v.ano AND p.tipo = v.tipo
                            ) AS texto,
                                        p.url,
                                        p.descricaoTipo,
                                        p.tema,
                                        v.dataHoraSessao,
                                        v.tipo,
                                        v.numero,
                                        v.ano,
                            CASE TRIM(v.voto)
                                WHEN 'Sim' THEN 3
                                WHEN 'Não' THEN 4
                                WHEN 'Abstenção' THEN 6
                                WHEN 'Obstrução' THEN 7
                                WHEN '-' THEN 8
                                ELSE NULL
                                        END AS oidTipoNotificacao,
                            CONCAT(p.tipo, ' ', p.numero, '/', p.ano) 
                                    FROM estado_votacao v
                                    INNER JOIN estado_proposicao p ON p.numero = v.numero AND p.ano = v.ano AND p.tipo = v.tipo 
                                    WHERE TRIM(v.politico) != '' AND v.tipo NOT IN ('VET', 'IND', 'RQS', 'RQN', 'RQC', 'RQO', 'REL', 'REC', 'PLE', 'OTJ', 'OTC', 'OPJ', 'OGE', 'OFC', 'MSG') 
                            AND (				SELECT po1.oidPolitico FROM politico po1
                                WHERE (po1.nome = v.politico OR po1.nome LIKE CONCAT(v.politico, '%')) AND po1.oidInstituicao = 3           
                            LIMIT 1  
                            ) IS NOT NULL                   
                                    AND p.codProcessamento = " . $codProcessamento;
    
                
                $objDB->query($sql);
                $error = $objDB->error();				
                if(intval($error[0]) > 0) throw new \Exception("Erro ao criar notificações de votação." . $error[2]);    

			}catch(\Exception $e){
                //Salva log com resultados do processamento
                $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_NOTIFICACAO, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Lista de proposições votadas em plenário: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
            }                
        }   

	}
