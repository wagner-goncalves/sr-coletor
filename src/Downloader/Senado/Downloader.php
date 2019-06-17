<?php

    namespace SR\Downloader\Senado;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
	use SR\Logger\Sucesso;
	use SR\Logger\Erro;
	use SR\Downloader\Senado\Config;
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
			$arrCodProcessamento = $objDB->query("SELECT MAX(codProcessamento) AS codProcessamento FROM senado_processamento")->fetchAll(\PDO::FETCH_ASSOC);
			return $arrCodProcessamento[0]["codProcessamento"];

		}        

		public function defineNomeArquivo($funcao, $codProcessamento = 0, $adicionais = ""){
            $this->codProcessamento = $codProcessamento;

			if($this->codProcessamento == 0){
				$this->codProcessamento = $this->getDB()->max("senado_processamento", "codProcessamento");
            }
            
			$base = Config::getDownloderBaseFilePath();
			$nome = ($base . str_pad(intval($this->codProcessamento), 4, 0, STR_PAD_LEFT) . "-" . $funcao . $adicionais . ".xml");
			return $nome;
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

        public function getLogs(ServerRequestInterface $request, ResponseInterface $response, array $args){
            $arrLogs = $this->container->db->query("SELECT * FROM senado_logger ORDER BY oidLogger DESC LIMIT 0, 10")->fetchAll(\PDO::FETCH_ASSOC);
            for($i = 0; $i < count($arrLogs); $i++){
                $arrLogs[$i]["arquivos"] = $this->container->db->query("SELECT * FROM senado_logger_arquivo WHERE oidLogger = " . $arrLogs[$i]["oidLogger"])->fetchAll(\PDO::FETCH_ASSOC);
            }

            return $response->withJson($arrLogs);
        }

		public function criarCodigoProcessamento(ServerRequestInterface $request, ResponseInterface $response, array $args){
            try{

                $codProcessamento = $this->container->db->insert("senado_processamento", array("dataHora" => date("Y-m-d H:i:s")));
                $processamento = $this->container->db->get("senado_processamento", "*", ["codProcessamento" => $codProcessamento]);
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

		public function obterSenadores(ServerRequestInterface $request, ResponseInterface $response, array $args){
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

            $dataArquivo = $data;
            try{
                $objConfig = new Config();
                if($data != "") $objConfig->setParametroUrl(__FUNCTION__, "data", $data);
                $objConfig->setParametroUrl(__FUNCTION__, "tramitando", $tramitando);

                $objHttpReader = new HttpReader($objConfig->getUrl(__FUNCTION__, "Url"));

                $arquivo = $this->defineNomeArquivo(__FUNCTION__, $codProcessamento);
                $objHttpReader->urlSave($arquivo);

                //Salva log com resultados do processamento
                $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DOWNLOAD, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Lista de materias tramitadas/em tramitação: " . MensagemSistema::get("SUS_DOWNLOAD"), Constantes::TRACE_SUCESSO, $dataHoraInicio), "senado_logger");
                $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $dataArquivo)), "senado_logger_arquivo");

                $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_DOWNLOAD"), "log" => get_object_vars($sucesso)];
            }catch(\Exception $e){

                //Salva log com resultados do processamento
                $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DOWNLOAD, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Lista de materias tramitadas/em tramitação: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio), "senado_logger");
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

            $arrCodigoMateria = $this->listarMaterias($codProcessamento);
            
            foreach($arrCodigoMateria as $item){
                try{
                    $objConfig = new Config();
                    $objConfig->setParametroUrlInline(__FUNCTION__, "codMateria", $item);

                    $codMateria = ("-" . $item);
                    $arquivo = $this->defineNomeArquivo(__FUNCTION__, $codProcessamento, $codMateria);

                    if(!is_file($arquivo)){
                        $objHttpReader = new HttpReader($objConfig->getUrl(__FUNCTION__, "Url"));

                        $objHttpReader->urlSave($arquivo);

                        //Salva log com resultados do processamento
                        $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DOWNLOAD, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Detalhes da matéria em tramitação: " . MensagemSistema::get("SUS_DOWNLOAD"), Constantes::TRACE_SUCESSO, $dataHoraInicio), "senado_logger");
                        $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $codMateria)), "senado_logger_arquivo");

                        $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_DOWNLOAD"), "log" => get_object_vars($sucesso)];
                    }
                }catch(\Exception $e){

                    //Salva log com resultados do processamento
                    $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DOWNLOAD, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Detalhes da matéria em tramitação: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio), "senado_logger");
                    $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $codMateria)), "senado_logger_arquivo");

                    $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];
                }
            }

            if(count($respostas) == 0){
                $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DOWNLOAD, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, MensagemSistema::get("ERR_SEM_REGISTRO_DOWNLOAD"), Constantes::TRACE_SUCESSO, $dataHoraInicio));
                $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_DOWNLOAD"), "log" => get_object_vars($sucesso)];
            }

			return $response->withJson($respostas);
		}

		public function listarMaterias($codProcessamento){
			$objDB = $this->getDB();
			$arrCodProposicao = $objDB->select("senado_materia", "CodigoMateria", array("codProcessamento" => $codProcessamento));
			return $arrCodProposicao;
		}

		public function obterVotacaoMateria(ServerRequestInterface $request, ResponseInterface $response, array $args){
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s");
            $codProcessamento = 0;
            $arquivo = "";

			$parametros = $request->getQueryParams();
			$respostas = array();

            //Recupera código de processamento
				
            if(!isset($parametros["codProcessamento"])){
                $codProcessamento = $this->getUltimoCodigoProcessamento();
            }else{
                $codProcessamento = $parametros["codProcessamento"];
            }

            $arrCodigoMateria = $this->listarMaterias($codProcessamento);

            foreach($arrCodigoMateria as $codMateria){
                try{
                    $objConfig = new Config();
                    $objConfig->setParametroUrlInline(__FUNCTION__, "codMateria", $codMateria);

                    $arquivo = $this->defineNomeArquivo(__FUNCTION__, $codProcessamento, ("-" . $codMateria));

                    if(!is_file($arquivo)){
                        $objHttpReader = new HttpReader($objConfig->getUrl(__FUNCTION__, "Url"));
                        $objHttpReader->urlSave($arquivo);
                        //Salva log com resultados do processamento
                        $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DOWNLOAD, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Votos de materias: " . MensagemSistema::get("SUS_DOWNLOAD"), Constantes::TRACE_SUCESSO, $dataHoraInicio), "senado_logger");
                        $sucesso->salvaArquivo(array("oidLogger" => $sucesso->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $codMateria)), "senado_logger_arquivo");

                        $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_DOWNLOAD"), "log" => get_object_vars($sucesso)];
                    }
                }catch(\Exception $e){
                    //Salva log com resultados do processamento
                    $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_DOWNLOAD, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Votos de materias: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio), "senado_logger");
                    $erro->salvaArquivo(array("oidLogger" => $erro->oidLogger, "caminhoAbsoluto" => $arquivo, "caminhoRelativo" => $this->defineNomeRelativo(__FUNCTION__, $codProcessamento, $codMateria)), "senado_logger_arquivo");

                    $respostas[] = ["arquivo" => $arquivo, "success" => false, "message" => MensagemSistema::get("ERR_DOWNLOAD"), "log" => get_object_vars($erro)];
                }
            }

            if(count($respostas) == 0){
                $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_DOWNLOAD, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, MensagemSistema::get("ERR_SEM_REGISTRO_DOWNLOAD"), Constantes::TRACE_SUCESSO, $dataHoraInicio), "senado_logger");
                $respostas[] = ["arquivo" => $arquivo, "success" => true, "message" => MensagemSistema::get("SUS_DOWNLOAD"), "log" => get_object_vars($sucesso)];
            }

			return $response->withJson($respostas);
		}
	}
