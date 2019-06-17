<?php

namespace SR\Consolidador\Camara;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SR\Downloader\Camara\Downloader;
use SR\Downloader\Camara\Notifier;
use SR\Processor\Camara\Processor;

class Consolidador
{

    protected $container;
    private $objLogger = 0;
    public $codProcessamento = 0;
    public $dataHoraInicio = null;

    public function __construct($container)
    {
        $this->objLogger = new \SR\Logger\Logger();
        $this->container = $container;
        $this->dataHoraInicio = date("Y-m-d H:i:s"); //Início da execução da função
    }

    public function getLogger()
    {
        return $this->objLogger;
    }

    public function getDB()
    {
        return $this->container->db;
    }

    public function dadosComuns(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $retorno = array();
        try {

            $objDownloader = new Downloader($this->container);
            $objProcessor = new Processor($this->container);
            $objNotifier = new Notifier($this->container);

            //http://coletor.srcidadao.com.br/public/v1/consolidador/dia?dataInicial=08/06/2018&dataFinal=18/06/2018

            //Novo código de processamento
            $codProcessamento = $this->container->db->insert("camara_processamento", array("dataHora" => date("Y-m-d H:i:s")));

            //Atualiza lista de partidos
            $objDownloader->obterPartidosCD($request, $response, $args);
            $objProcessor->obterPartidosCD($request, $response, $args);
            echo "OK obterPartidosCD\n";

            //Atualiza lista de deputados
            $objDownloader->obterDeputados($request, $response, $args);
            $objProcessor->obterDeputados($request, $response, $args);
            echo "OK obterDeputados\n";

            return $response->withJson($retorno);

        } catch (\Exception $e) {
            print_r($e);
        }
    }

    public function atualiza(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $retorno = array();
        try {

            $objDownloader = new Downloader($this->container);
            $objProcessor = new Processor($this->container);
            $objNotifier = new Notifier($this->container);

            //http://coletor.srcidadao.com.br/public/v1/consolidador/atualiza?dataInicial=08/06/2018&dataFinal=18/06/2018

            //Novo código de processamento
            $codProcessamento = $this->container->db->insert("camara_processamento", array("dataHora" => date("Y-m-d H:i:s")));
            echo "OK codProcessamento\n";


            //Listas de proposições votadas em plenário no ano
            $objDownloader->listarProposicoesVotadasEmPlenario($request, $response, $args);
            $objProcessor->listarProposicoesVotadasEmPlenario($request, $response, $args);
            echo "OK listarProposicoesVotadasEmPlenario\n";

            //Detalhes das proposições votadas em plenário no ano
            $objDownloader->obterProposicaoPorID($request, $response, $args);
            $objProcessor->obterProposicaoPorID($request, $response, $args);
            echo "OK obterProposicaoPorID\n";

            //Detalhes das proposições votadas em plenário no ano
            $objDownloader->obterVotacaoProposicao($request, $response, $args);
            $objProcessor->obterVotacaoProposicao($request, $response, $args);
            $objNotifier->votacao($request, $response, $args);
            echo "OK obterVotacaoProposicao\n";


            //Lista de presença
            $objDownloader->listarPresencasDia($request, $response, $args);
            $objProcessor->listarPresencasDia($request, $response, $args);
            $objNotifier->presenca($request, $response, $args);
            echo "OK listarPresencasDia\n";


            return $response->withJson($retorno);

        } catch (\Exception $e) {
            print_r($e);
        }
    }

    public function batch(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        try {

            $objDB = $this->getDB();

            $sql = "CALL lmCreateTrainingSetIdeologiaPoliticos()";
            $stmt = $objDB->pdo->prepare($sql);
            $stmt->execute();

            $sql = "CALL processaResumoPoliticos()";
            $stmt = $objDB->pdo->prepare($sql);
            $stmt->execute();

            $sql = "CALL atualizaUsuarioProposicao()";
            $stmt = $objDB->pdo->prepare($sql);
            $stmt->execute();

        } catch (\Exception $e) {
            return $response->withStatus(500)->withJson([
                "success" => false,
                $retorno,
            ]);
        }
    }

}
