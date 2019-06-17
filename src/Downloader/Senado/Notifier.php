<?php
    namespace SR\Downloader\Senado;

	use Psr\Http\Message\ServerRequestInterface;
	use Psr\Http\Message\ResponseInterface;
	use SR\Logger\Sucesso;
	use SR\Logger\Erro;
	use SR\Logger\Logger;
	use SR\Downloader\Senado\Config;
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
        
        public function votacao(ServerRequestInterface $request, ResponseInterface $response, array $args){
            //Início do Processamento
            $dataHoraInicio = date("Y-m-d H:i:s"); 
            $codProcessamento = 0;  

			try{

                $parametros = $request->getQueryParams();
                //Recupera código de processamento
                $codProcessamento = $parametros["codProcessamento"];     
                
				$sql = "INSERT IGNORE INTO notificacao (codProcessamento, 
                                oidInteresse,
                                oidInstituicao,
                                oidPartido, 
                                oidPolitico, 
                                uf,
                                titulo, 
                                justificativa,
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
                            " . Constantes::INTERESSE_VOTACAO . ",
                            " . Constantes::INSTITUICAO_SENADO . ",
                            (SELECT p1.oidPartido FROM partido p1 WHERE p1.sigla = s.SiglaPartidoParlamentar AND p1.flgAtivo = 1) AS oidPartido, 
                            s.CodigoParlamentar, s.UfParlamentar,
                            CASE TRIM(v.DescricaoVoto)
                                WHEN 'Sim' THEN CONCAT('Votou SIM. ', m.SiglaSubtipoMateria, ' ', m.NumeroMateria, '/', m.AnoMateria)
                                WHEN 'Não' THEN CONCAT('Votou NÃO. ', m.SiglaSubtipoMateria, ' ', m.NumeroMateria, '/', m.AnoMateria)                          
                                WHEN 'NCom' THEN CONCAT('Não votou. ', m.SiglaSubtipoMateria, ' ', m.NumeroMateria, '/', m.AnoMateria)
                                WHEN 'P-NRV' THEN CONCAT('Presente mas não votou. ', m.SiglaSubtipoMateria, ' ', m.NumeroMateria, '/', m.AnoMateria)
                                WHEN 'P-OD' THEN CONCAT('Votou obstrução. ', m.SiglaSubtipoMateria, ' ', m.NumeroMateria, '/', m.AnoMateria)
                                WHEN 'AP' THEN CONCAT('Ausência justificada - Em atividade política. ', CONCAT(m.SiglaSubtipoMateria, ' ', m.NumeroMateria, '/', m.AnoMateria))
                                WHEN 'LAP' THEN CONCAT('Ausência justificada - Licença paternidade. ', CONCAT(m.SiglaSubtipoMateria, ' ', m.NumeroMateria, '/', m.AnoMateria))
                                WHEN 'LC' THEN CONCAT('Ausência justificada - Candidatura à Presidência/Vice. ', CONCAT(m.SiglaSubtipoMateria, ' ', m.NumeroMateria, '/', m.AnoMateria))
                                WHEN 'LG' THEN CONCAT('Ausência justificada - Licença à Gestante. ', CONCAT(m.SiglaSubtipoMateria, ' ', m.NumeroMateria, '/', m.AnoMateria))
                                WHEN 'MIS' THEN CONCAT('Ausência justificada - Em missão. ', CONCAT(m.SiglaSubtipoMateria, ' ', m.NumeroMateria, '/', m.AnoMateria))
                                WHEN 'REP' THEN CONCAT('Ausência justificada - Em representação da casa. ', CONCAT(m.SiglaSubtipoMateria, ' ', m.NumeroMateria, '/', m.AnoMateria))
				                -- WHEN ('AP' OR 'LAP' OR 'LC' OR 'LG' OR 'MIS' OR 'REP') THEN CONCAT('Ausência justificada. ', m.SiglaSubtipoMateria, ' ', m.NumeroMateria, '/', m.AnoMateria)
                                WHEN 'Votou' THEN CONCAT('Voto não disponível. ', m.SiglaSubtipoMateria, ' ', m.NumeroMateria, '/', m.AnoMateria)
                                ELSE CONCAT('Votou ', v.DescricaoVoto, '. ', m.SiglaSubtipoMateria, ' ', m.NumeroMateria, '/', m.AnoMateria) 
                            END AS titulo,
                            CASE TRIM(v.DescricaoVoto)
                                WHEN 'AP' THEN 'Em atividade política. '
                                WHEN 'LAP' THEN 'Licença paternidade. '
                                WHEN 'LC' THEN 'Candidatura à Presidência/Vice. '
                                WHEN 'LG' THEN 'Licença à Gestante. '
                                WHEN 'MIS' THEN 'Em missão. '
                                WHEN 'Votou' THEN NULL
                                WHEN 'REP' THEN 'Em representação da casa. '
                                ELSE NULL
                            END AS justificativa,                    
                            d.EmentaMateria AS texto,
                            '' AS url,
                            m.DescricaoSubtipoMateria AS tipo,
                            a.Descricao AS tema,
                            v.DataHoraSessao AS dataHoraEvento,
                            m.SiglaSubtipoMateria AS siglaTipo,
                            m.NumeroMateria AS numero,
                            m.AnoMateria AS ano,
                            CASE TRIM(v.DescricaoVoto)
                                WHEN 'Sim' THEN 3
                                WHEN 'Não' THEN 4
                                WHEN 'P-NRV' THEN 6
                                WHEN 'P-OD' THEN 7
                                WHEN ('REP' OR 'MIS' OR 'LG' OR 'LC' OR 'LAP' OR 'AP') THEN 5
                                WHEN 'NCom' THEN 8
                                WHEN ('Votou') THEN 9
                                ELSE NULL
                    END AS oidTipoNotificacao,
                            m.DescricaoSubtipoMateria  AS objeto			
                            FROM senado_votoparlamentar v
                            INNER JOIN senado_senador s ON s.CodigoParlamentar = v.CodigoParlamentar
                            INNER JOIN politico p ON p.oidPolitico = s.CodigoParlamentar
                            INNER JOIN senado_materia m ON m.CodigoMateria = v.CodigoMateria
                            LEFT JOIN senado_detalhemateria d ON d.CodigoMateria = m.CodigoMateria
                            LEFT JOIN senado_materiaassunto a ON a.CodigoMateria = m.CodigoMateria
                            WHERE p.oidInstituicao = " . Constantes::INSTITUICAO_SENADO . "  
							-- AND v.DescricaoVoto != 'Votou' 
							AND v.codProcessamento = " . $codProcessamento;

                $objDB = $this->getDB();
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
