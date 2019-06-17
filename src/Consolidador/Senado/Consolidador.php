<?php

namespace SR\Consolidador\Senado;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SR\Downloader\Senado\Downloader;
use SR\Downloader\Senado\Notifier;
use SR\Processor\Senado\Processor;

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

    public function atualiza(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        $retorno = array();
        try {

            $objDownloader = new Downloader($this->container);
            $objProcessor = new Processor($this->container);
            $objNotifier = new Notifier($this->container);

            //http://coletor.srcidadao.com.br/public/v1/consolidador/dia?data=20181230

            //Novo código de processamento
            $codProcessamento = $this->container->db->insert("senado_processamento", array("dataHora" => date("Y-m-d H:i:s")));            
            echo "OK codProcessamento\n";

            $objDownloader->obterSenadores($request, $response, $args);
            $objProcessor->obterSenadores($request, $response, $args);
            echo "OK obterSenadores\n";

            $objDownloader->obterMaterias($request, $response, $args);
            $objProcessor->obterMaterias($request, $response, $args);
            echo "OK obterMaterias\n";

            $objDownloader->obterMateriaPorID($request, $response, $args);
            $objProcessor->obterMateriaPorID($request, $response, $args);
            echo "OK obterMateriaPorID\n";

            $objDownloader->obterVotacaoMateria($request, $response, $args);
            $objProcessor->obterVotacaoMateria($request, $response, $args); 
            echo "OK obterVotacaoMateria\n";

        } catch (\Exception $e) {
            print_r($e);
        }
    }

    public function batch(ServerRequestInterface $request, ResponseInterface $response, array $args)
    {
        try {



        } catch (\Exception $e) {
            print_r($e);
        }
    }

}
