<?php
    namespace SR\Downloader\Camara;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
	use SR\Logger\Sucesso;
	use SR\Logger\Erro;
	use SR\Logger\Logger;
	use SR\Downloader\Camara\Config;
	use SR\Downloader\HttpReader;
	use SR\Util\Constantes;
	use SR\Util\MensagemSistema;	

	class Notifier{
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
		
		public function getUltimoCodigoProcessamento(){
				
			$objDB = $this->getDB();
			$arrCodProcessamento = $objDB->query("SELECT MAX(codProcessamento) AS codProcessamento FROM camara_processamento")->fetchAll(\PDO::FETCH_ASSOC);
			return $arrCodProcessamento[0]["codProcessamento"];

		}

        
        public function presenca(ServerRequestInterface $request, ResponseInterface $response, array $args){
			//Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $codProcessamento = 0;  

			try{            

                $parametros = $request->getQueryParams();
                //Recupera código de processamento
				if(!isset($parametros["codProcessamento"])){
					$codProcessamento = $this->getUltimoCodigoProcessamento();
				}else{
					$codProcessamento = $parametros["codProcessamento"];
				}
                
                $sql = "INSERT IGNORE INTO notificacao (codProcessamento, 
                            oidInteresse,
                            oidInstituicao,
                            oidPartido, 
                            oidPolitico, 
                            uf,
                            titulo, 
                            texto, 
                            dataHoraEvento,
							oidTipoNotificacao,
                            flgAtivo)
                    SELECT 
                        p.codProcessamento,
                        2,
                        " . Constantes::INSTITUICAO_CAMARA . ",
                        (SELECT p1.oidPartido FROM partido p1 WHERE p1.sigla = p.siglaPartido OR p1.nome = p.siglaPartido) AS oidPartido,
                        p.ideCadastro,
                        p.siglaUF,
                        CONCAT(p.descricaoFrequenciaDia, ' de ', p.nomeParlamentar) AS titulo,
                        CASE p.descricaoFrequenciaDia
                            WHEN 'Presença' THEN  CONCAT('Parlamentar registrou presença em ', DATE_FORMAT(p.data, '%d/%m'), '.')
                            WHEN 'Ausência' THEN  CONCAT('Parlamentar não esteve presente em ', DATE_FORMAT(p.data, '%d/%m'), '.')
                            WHEN 'Ausência justificada' THEN  CONCAT('Parlamentar não registrou presença com a justificativa ', p.justificativa, '.')
                        END AS texto,
                        p.data,
						CASE p.descricaoFrequenciaDia
                            WHEN 'Presença' THEN  1
							WHEN 'Presença (~)' THEN  1
                            WHEN 'Ausência' THEN  2
                            WHEN 'Ausência justificada' THEN  5
                        END AS oidTipoNotificacao,
                        1
                    FROM camara_presenca p
                        INNER JOIN camara_deputado d ON d.ideCadastro = p.ideCadastro
                        -- INNER JOIN camara_partido pa ON p.siglaPartido = pa.siglaPartido 
                        WHERE p.codProcessamento = " . $codProcessamento;                               

                $objDB = $this->getDB();
                $objDB->query($sql);
                
                $error = $objDB->error();	
                if(intval($error[0]) > 0) throw new \Exception("Erro ao criar notificações de presença." . $error[2]);	
                
                $totalNotificacoes = $objDB->count("notificacao", ["codProcessamento" => $codProcessamento]);
                
                //Salva log com resultados do processamento
                $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_NOTIFICACAO, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Lista de Presença: " . $totalNotificacoes . " " . MensagemSistema::get("SUS_NOTIFICACAO"), Constantes::TRACE_SUCESSO, $dataHoraInicio));
                $message = "Processado com sucesso. " . $totalNotificacoes . " notificações criadas.";
				
				$this->container->db->update("camara_processamento", [
					"flgTipo" => "notificacao",
					"flgConcluido" => 1
				], ["codProcessamento" => $codProcessamento]);	
				
				return $response->withJson(["arquivo" => "", "success" => true, "message" => $message, "log" => get_object_vars($sucesso)]);
                
			}catch(\Exception $e){
                //Salva log com resultados do processamento
                $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_NOTIFICACAO, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Lista de Presença: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                
                return $response->withStatus(500)->withJson(["arquivo" => "", "success" => false, "message" => MensagemSistema::get("ERR_NOTIFICACAO"), "log" => get_object_vars($erro)]);		
            }
        }
		
		public function criaProposicoes($codProcessamento){
			$sql = "INSERT IGNORE INTO proposicao (tipo, numero, ano, objeto, resumo, tipoProposicao, tema, ementa, url, flgAtivo, dataHoraEvento)
				SELECT r.sigla, r.Numero, r.Ano, r.ObjVotacao, r.Resumo, 
				(SELECT p.tipoProposicao FROM camara_proposicao p WHERE p.tipo = r.Sigla AND p.ano = r.Ano AND p.numero = r.Numero LIMIT 0, 1), 
				(SELECT p.tema FROM camara_proposicao p WHERE p.tipo = r.Sigla AND p.ano = r.Ano AND p.numero = r.Numero LIMIT 0, 1), 
				(SELECT p.Ementa FROM camara_proposicao p WHERE p.tipo = r.Sigla AND p.ano = r.Ano AND p.numero = r.Numero LIMIT 0, 1), 
				(SELECT p.LinkInteiroTeor FROM camara_proposicao p WHERE p.tipo = r.Sigla AND p.ano = r.Ano AND p.numero = r.Numero LIMIT 0, 1), 
				0,
				CONCAT(data, ' ', hora)
				FROM camara_proposicaoresumo r
				WHERE r.codProcessamento = " . $codProcessamento;
				
			$objDB = $this->getDB();
			
			$objDB->query($sql);
			$error = $objDB->error();				
			if(intval($error[0]) > 0) throw new \Exception("Erro ao criar proposições." . $error[2]);    
		}
        
        public function votacao(ServerRequestInterface $request, ResponseInterface $response, array $args){
            //Início do Processamento

            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $codProcessamento = 0;  

			try{

			    $parametros = $request->getQueryParams();
                //Recupera código de processamento
				if(!isset($parametros["codProcessamento"])){
					$codProcessamento = $this->getUltimoCodigoProcessamento();
				}else{
					$codProcessamento = $parametros["codProcessamento"];
				}   
				
				//Cria proposições
				$this->criaProposicoes($codProcessamento);
				
				$objDB = $this->getDB();
				
				$sql = "INSERT IGNORE INTO camara_deputado (ideCadastro, nome, nomeParlamentar, partido, uf)
					SELECT ideCadastro, Nome, Nome, Partido, Uf
					FROM (SELECT ideCadastro, Nome, Partido, Uf, MAX(codProcessamento) AS codProcessamento
							FROM camara_votacaoproposicao
							GROUP BY ideCadastro DESC
						) AS ids
					ORDER BY Nome
					";
					
				$objDB->query($sql);
                
				$sql = "INSERT IGNORE INTO notificacao (codProcessamento, 
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
                    SELECT v.codProcessamento,
                        1,
                        " . Constantes::INSTITUICAO_CAMARA . ",
                        (SELECT p1.oidPartido FROM partido p1 WHERE p1.sigla = v.Partido OR p1.nome = v.Partido) AS oidPartido,
                        v.ideCadastro,
                        v.UF,
						CASE TRIM(v.Voto)
							WHEN '-' THEN CONCAT('Não votou. ', (SELECT p.nomeProposicao FROM camara_proposicao p WHERE p.numero = v.numero AND p.ano = v.ano AND p.tipo = v.sigla))
							ELSE CONCAT('Votou ', v.Voto, '. ', (SELECT p.nomeProposicao FROM camara_proposicao p WHERE p.numero = v.numero AND p.ano = v.ano AND p.tipo = v.sigla)) 
						END AS titulo,
                        (SELECT p.ementa FROM camara_proposicao p WHERE p.numero = v.numero AND p.ano = v.ano AND p.tipo = v.sigla) AS texto,
                        (SELECT p.linkInteiroTeor FROM camara_proposicao p WHERE p.numero = v.numero AND p.ano = v.ano AND p.tipo = v.sigla) AS url,
                        (SELECT p.tipoProposicao FROM camara_proposicao p WHERE p.numero = v.numero AND p.ano = v.ano AND p.tipo = v.sigla) AS tipo,
                        (SELECT p.tema FROM camara_proposicao p WHERE p.numero = v.numero AND p.ano = v.ano AND p.tipo = v.sigla) AS tema,
                        CONCAT(v.Data, ' ', v.Hora) as dataHoraEvento,
                        v.sigla,
                        v.numero,
                        v.ano,
						CASE TRIM(v.Voto)
                            WHEN 'Sim' THEN 3
                            WHEN 'Não' THEN 4
                            WHEN 'Abstenção' THEN 6
							WHEN 'Obstrução' THEN 7
							WHEN '-' THEN 8
							ELSE NULL
                        END AS oidTipoNotificacao,
						v.ObjVotacao
                    FROM camara_votacaoproposicao v
                        INNER JOIN camara_deputado d ON d.ideCadastro = v.ideCadastro
                        -- INNER JOIN camara_partido pa ON v.Partido = pa.siglaPartido
                        WHERE v.codProcessamento = " . $codProcessamento;
    
                
                $objDB->query($sql);
                $error = $objDB->error();				
                if(intval($error[0]) > 0) throw new \Exception("Erro ao criar notificações de votação." . $error[2]);    
                
                $totalNotificacoes = $objDB->count("notificacao", ["codProcessamento" => $codProcessamento]);
                
                //Salva log com resultados do processamento
                $sucesso = $this->objLogger->addSucesso(new Sucesso($codProcessamento, Constantes::PROCESSA_NOTIFICACAO, __FILE__, __LINE__, Constantes::CODIGO_SUCESSO, Constantes::SUCESSO, "Lista de proposições votadas em plenário: " . $totalNotificacoes . " " . MensagemSistema::get("SUS_NOTIFICACAO"), Constantes::TRACE_SUCESSO, $dataHoraInicio));
				return $response->withJson(["arquivo" => "", "success" => true, "message" => ("Processado com sucesso. " . $totalNotificacoes . " notificações criadas."), "log" => get_object_vars($sucesso)]);
                
			}catch(\Exception $e){
                //Salva log com resultados do processamento
                $erro = $this->objLogger->addErro(new Erro($codProcessamento, Constantes::PROCESSA_NOTIFICACAO, $e->getFile(), $e->getLine(), $e->getCode(), Constantes::ERRO, "Lista de proposições votadas em plenário: " . $e->getMessage(), $e->getTraceAsString(), $dataHoraInicio));
                
                return $response->withStatus(500)->withJson(["arquivo" => "", "success" => false, "message" => MensagemSistema::get("ERR_NOTIFICACAO"), "log" => get_object_vars($erro)]);		
            }                
        }        

	}
