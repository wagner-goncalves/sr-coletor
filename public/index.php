<?php
	require '../vendor/autoload.php';

    set_time_limit(60 * 30);

    use \SR\Config\Config;

    //Recupera variáveis do ambiente
    $dotenv = new Dotenv\Dotenv(__DIR__ . "/../private/", ".config");
    $dotenv->load();

	$app = new \Slim\App(Config::getAppSettings());
    Config::setContainer($app->getContainer());

	$app->get('/v1/processamentos/codigo', 'SR\Downloader\Camara\Downloader:criarCodigoProcessamento');
	$app->get('/v1/processamentos/concluido', 'SR\Downloader\Camara\Downloader:processamentoConcluido');
	
	$app->get('/v1/processamentos/mg/codigo', 'SR\Downloader\Estado\MG\Downloader:criarCodigoProcessamento');
	
    $app->get('/v1/processamentos/logs', 'SR\Downloader\Camara\Downloader:getLogs');

    $app->get('/v1/senado/processamentos/codigo', 'SR\Downloader\Senado\Downloader:criarCodigoProcessamento'); 
    $app->get('/v1/tse/processamentos/codigo', 'SR\Processor\TSE\Processor:criarCodigoProcessamento');

	$app->group("/v1/download", function() use ($app){
		$app->get('/partidos', 'SR\Downloader\Camara\Downloader:obterPartidosCD'); // http://dev.downloader.com.br/download/partidos
		$app->get('/deputados', 'SR\Downloader\Camara\Downloader:obterDeputados'); // http://dev.downloader.com.br/download/deputados
		$app->get('/presencas', 'SR\Downloader\Camara\Downloader:listarPresencasDia'); // Parâmetros: http://dev.downloader.com.br/download/presencas?dataInicial=01/06/2016&dataFinal=29/06/2016
		$app->get('/proposicoes/votadas', 'SR\Downloader\Camara\Downloader:listarProposicoesVotadasEmPlenario'); // Parâmetros: http://dev.downloader.com.br/download/proposicoes/votadas?anoInicial=2016&anoFinal=2016
		$app->get('/proposicoes', 'SR\Downloader\Camara\Downloader:obterProposicaoPorID');  // Parâmetros: http://dev.downloader.com.br/download/proposicoes?dataInicial=01/06/2016&dataFinal=29/06/2016
		$app->get('/proposicoes/votacoes', 'SR\Downloader\Camara\Downloader:obterVotacaoProposicao'); // Parâmetros: http://dev.downloader.com.br/download/proposicoes/votacoes?dataInicial=01/06/2016&dataFinal=29/06/2016

		$app->get('/senado/senadores', 'SR\Downloader\Senado\Downloader:obterSenadores'); // http://coletor.srcidadao.dev.br/public/v1/download/senadores
        $app->get('/senado/materias', 'SR\Downloader\Senado\Downloader:obterMaterias'); // http://coletor.srcidadao.dev.br/public/v1/download/materias
        $app->get('/senado/materia/download', 'SR\Downloader\Senado\Downloader:obterMateriaPorID'); // http://coletor.srcidadao.dev.br/public/v1/download/senadores/materias
		$app->get('/senado/materia/votacao', 'SR\Downloader\Senado\Downloader:obterVotacaoMateria'); // http://coletor.srcidadao.dev.br/public/v1/download/materia/votacao?codProcessamento=1
		

		$app->get('/estado/mg/deputados', 'SR\Downloader\Estado\MG\Downloader:obterDeputados');
		$app->get('/estado/mg/proposicoes', 'SR\Downloader\Estado\MG\Downloader:obterProposicoes');
	});

	$app->group("/v1/processa", function() use ($app){
		$app->get('/partidos', 'SR\Processor\Camara\Processor:obterPartidosCD'); // http://dev.downloader.com.br/processa/partidos
		$app->get('/deputados', 'SR\Processor\Camara\Processor:obterDeputados'); // http://dev.downloader.com.br/processa/deputados
		$app->get('/presencas', 'SR\Processor\Camara\Processor:listarPresencasDia'); // Parâmetros: http://dev.downloader.com.br/processa/presencas?dataInicial=01/06/2016&dataFinal=29/06/2016
		$app->get('/proposicoes/votadas', 'SR\Processor\Camara\Processor:listarProposicoesVotadasEmPlenario'); // Parâmetros: http://dev.downloader.com.br/processa/proposicoes/votadas?anoInicial=2016&anoFinal=2016
		$app->get('/proposicoes', 'SR\Processor\Camara\Processor:obterProposicaoPorID');  // Parâmetros: http://dev.downloader.com.br/processa/proposicoes?dataInicial=01/06/2016&dataFinal=29/06/2016
		$app->get('/proposicoes/votacoes', 'SR\Processor\Camara\Processor:obterVotacaoProposicao'); // Parâmetros: http://dev.downloader.com.br/processa/proposicoes/votacoes?dataInicial=01/06/2016&dataFinal=29/06/2016

		$app->get('/senado/senadores', 'SR\Processor\Senado\Processor:obterSenadores'); // http://coletor.srcidadao.dev.br/public/v1/processa/senadores
        $app->get('/senado/materias', 'SR\Processor\Senado\Processor:obterMaterias'); // http://coletor.srcidadao.dev.br/public/v1/processa/materias
		$app->get('/senado/materia/processa', 'SR\Processor\Senado\Processor:obterMateriaPorID');  // http://coletor.srcidadao.dev.br/public/v1/processa/materias/{id}
        $app->get('/senado/materia/votacao', 'SR\Processor\Senado\Processor:obterVotacaoMateria'); // http://coletor.srcidadao.dev.br/public/v1/processa/materia/votacao?codProcessamento=1

        $app->get('/tse/candidatos', 'SR\Processor\TSE\Processor:consultaCand');
        $app->get('/tse/bens-candidatos', 'SR\Processor\TSE\Processor:bemCandidato');

        $app->get('/tse/prestacao-contas/2006', 'SR\Processor\TSE\Processor:prestacaoCandidato2006');
        $app->get('/tse/prestacao-contas/2010', 'SR\Processor\TSE\Processor:prestacaoCandidato2010');
        $app->get('/tse/prestacao-contas/2014', 'SR\Processor\TSE\Processor:prestacaoCandidato2014');

        $app->get('/tse/resultado-eleicoes/2006', 'SR\Processor\TSE\Processor:resultadoEleicao2006');
        $app->get('/tse/resultado-eleicoes/2010', 'SR\Processor\TSE\Processor:resultadoEleicao2010');
        $app->get('/tse/resultado-eleicoes/2014', 'SR\Processor\TSE\Processor:resultadoEleicao2014');


        $app->get('/tse/prestacao-contas/relacional', 'SR\Processor\TSE\Processor:convertePrestacaoContasRelacional');
        $app->get('/tse/resultado-eleicao/relacional', 'SR\Processor\TSE\Processor:converteResutadoEleicaoRelacional');
	});

	$app->group("/v1/notifica", function() use ($app){
		$app->get('/presenca', 'SR\Downloader\Camara\Notifier:presenca');
		$app->get('/proposicoes/votacoes', 'SR\Downloader\Camara\Notifier:votacao');

        $app->get('/proposicoes/votacoes/senado', 'SR\Downloader\Senado\Notifier:votacao');
	});
	
	$app->group("/v1/consolidador/camara", function() use ($app){
		$app->get('/dados-comuns', 'SR\Consolidador\Camara\Consolidador:dadosComuns');
		$app->get('/atualiza', 'SR\Consolidador\Camara\Consolidador:atualiza');
		$app->get('/batch', 'SR\Consolidador\Camara\Consolidador:batch');
	});

	$app->group("/v1/consolidador/senado", function() use ($app){
		$app->get('/atualiza', 'SR\Consolidador\Senado\Consolidador:atualiza');
		$app->get('/batch', 'SR\Consolidador\Senado\Consolidador:batch');
	});	

	$app->group("/v1/tarefas", function() use ($app){
        $app->get('/todas', 'SR\Downloader\Camara\Tarefa:getAll');
        $app->post('/log', 'SR\Downloader\Camara\Tarefa:addlog');
	});

	$app->run();
?>
