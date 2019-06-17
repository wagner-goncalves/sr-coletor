<?php

	if(function_exists('date_default_timezone_set')) {
		date_default_timezone_set('America/Sao_Paulo');
	}
	
	set_time_limit(310);
	
	function __autoload($class_name){
		$pastas = array(
			"lib/medoo/", 
			"plugins/logger/", 
			"plugins/downloader/", 
			"plugins/util/", 
			"plugins/processor/camara/", 
			"plugins/downloader/camara/", 
		);	
		$achouArquivo = false;
		foreach($pastas as $pasta){
			if(file_exists($pasta . $class_name . ".php")){
				require_once($pasta . $class_name . ".php");
				$achouArquivo = true;
				break;
			}
		}
	}
	
	
	$dataInicio = "01/06/2016";
	$dataFim = date("d/m/Y");
	$objDownloader = new Downloader();
	$objDownloader->criarCodigoProcessamento();
	$objProcessor = new Processor();

	//Rodar uma vez por ano
	$objDownloader->obterPartidosCD();
	$objProcessor->obterPartidosCD();
	
	//Rodar uma vez por mês
	$objDownloader->obterDeputados();
	$objProcessor->obterDeputados();
	
	//Rodar todos os dias no final do dia 
	$objDownloader->listarPresencasDia($dataInicio, $dataFim);
	$objProcessor->listarPresencasDia($dataInicio, $dataFim);
	
	//Rodar todos os dias
	$objDownloader->listarProposicoesVotadasEmPlenario(2016);
	$objProcessor->listarProposicoesVotadasEmPlenario(2016);
	
	//Rodar todos os dias
	$objDownloader->obterProposicaoPorID($dataInicio, $dataFim);
	$objProcessor->obterProposicaoPorID($dataInicio, $dataFim);
	
	//Rodar todos os dias
	$objDownloader->obterVotacaoProposicao("01/06/2016", date("d/m/Y"));
	$objProcessor->obterVotacaoProposicao("01/06/2016", date("d/m/Y"));
	
	$objDownloader->getLogger()->getMensagens(true);
	$objProcessor->getLogger()->getMensagens(true);
?>