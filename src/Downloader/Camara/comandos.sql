INSERT INTO camara_processamento SET dataHora = CURRENT_TIMESTAMP();

-- Depende: camara_processamento
TRUNCATE camara_partido;

ALTER TABLE camara_partido CHANGE COLUMN dataCriacao dataCriacao CHAR(10) NULL DEFAULT NULL, CHANGE COLUMN dataExtincao dataExtincao CHAR(10) NULL DEFAULT NULL ;

LOAD XML LOCAL INFILE 'D:\ObterPartidosCD.xml'
REPLACE
INTO TABLE camara_partido
ROWS IDENTIFIED BY '<partido>'
SET 
codProcessamento = (SELECT MAX(camara_processamento.codProcessamento) FROM camara_processamento),
dataCriacao = (CASE WHEN STR_TO_DATE(TRIM(dataCriacao), '%d/%m/%Y') = '0000-00-00' THEN NULL ELSE STR_TO_DATE(TRIM(dataCriacao), '%d/%m/%Y') END),
dataExtincao = (CASE WHEN STR_TO_DATE(TRIM(dataExtincao), '%d/%m/%Y') = '0000-00-00' THEN NULL ELSE STR_TO_DATE(TRIM(dataExtincao), '%d/%m/%Y') END);

ALTER TABLE camara_partido CHANGE COLUMN dataCriacao dataCriacao DATE NULL DEFAULT NULL, CHANGE COLUMN dataExtincao dataExtincao DATE NULL DEFAULT NULL ;

-- Depende: camara_processamento
TRUNCATE camara_deputado;
LOAD XML LOCAL INFILE 'D:\ObterDeputados.xml'
REPLACE
INTO TABLE camara_deputado
ROWS IDENTIFIED BY '<deputado>'
SET codProcessamento = (SELECT MAX(camara_processamento.codProcessamento) FROM camara_processamento),
partido = TRIM(partido);


-- Depende: camara_processamento, camara_partido, camara_deputado
TRUNCATE camara_presenca;
ALTER TABLE camara_presenca CHANGE COLUMN camara_presenca.data camara_presenca.data CHAR(10) NULL DEFAULT NULL;
LOAD XML LOCAL INFILE 'D:\ListarPresencasDia.xml'
REPLACE
INTO TABLE camara_presenca
ROWS IDENTIFIED BY '<parlamentar>'
SET codProcessamento = (SELECT MAX(camara_processamento.codProcessamento) FROM camara_processamento),
camara_presenca.data = STR_TO_DATE(TRIM(camara_presenca.data), '%d/%m/%Y'),
siglaPartido = TRIM(siglaPartido),
ideCadastro = (SELECT camara_deputado.ideCadastro FROM camara_deputado WHERE camara_deputado.nomeParlamentar = TRIM(SUBSTRING_INDEX(camara_presenca.nomeParlamentar, '-', 1)) LIMIT 1);
ALTER TABLE camara_presenca CHANGE COLUMN camara_presenca.data camara_presenca.data DATE NULL DEFAULT NULL;

-- Depende: camara_processamento
TRUNCATE camara_proposicaoplenario;
ALTER TABLE camara_proposicaoplenario CHANGE COLUMN dataVotacao dataVotacao CHAR(10) NULL DEFAULT NULL;
LOAD XML LOCAL INFILE 'D:\ListarProposicoesVotadasEmPlenario.xml'
REPLACE
INTO TABLE camara_proposicaoplenario
ROWS IDENTIFIED BY '<proposicao>'
SET codProcessamento = (SELECT MAX(camara_processamento.codProcessamento) FROM camara_processamento),
dataVotacao = STR_TO_DATE(dataVotacao, '%d/%m/%Y');
ALTER TABLE camara_proposicaoplenario CHANGE COLUMN dataVotacao dataVotacao DATE NULL DEFAULT NULL;

-- Depende: camara_processamento
TRUNCATE camara_siglastipoproposicao;
LOAD XML LOCAL INFILE 'D:\ListarSiglasTipoProposicao.xml'
REPLACE
INTO TABLE camara_siglastipoproposicao
ROWS IDENTIFIED BY '<sigla>'
SET codProcessamento = (SELECT MAX(camara_processamento.codProcessamento) FROM camara_processamento),
tipoSigla = TRIM(tipoSigla);

-- Depende: camara_processamento, camara_siglastipoproposicao, camara_partido
TRUNCATE camara_proposicaoresumo;
ALTER TABLE camara_proposicaoresumo CHANGE COLUMN camara_proposicaoresumo.Data camara_proposicaoresumo.Data CHAR(10) NULL DEFAULT NULL;
LOAD XML LOCAL INFILE 'D:\ObterVotacaoProposicao.xml'
REPLACE
INTO TABLE camara_proposicaoresumo
ROWS IDENTIFIED BY '<Votacao>'
SET codProcessamento = (SELECT MAX(camara_processamento.codProcessamento) FROM camara_processamento),
camara_proposicaoresumo.Data = STR_TO_DATE(camara_proposicaoresumo.Data, '%d/%m/%Y');
ALTER TABLE camara_proposicaoresumo CHANGE COLUMN camara_proposicaoresumo.Data camara_proposicaoresumo.Data DATE NULL DEFAULT NULL;


-- Depende: camara_processamento, camara_siglastipoproposicao, camara_partido
TRUNCATE camara_votacaoproposicao;
LOAD XML LOCAL INFILE 'D:\ObterVotacaoProposicao.xml'
REPLACE
INTO TABLE camara_votacaoproposicao
ROWS IDENTIFIED BY '<Deputado>'
SET codProcessamento = (SELECT MAX(camara_processamento.codProcessamento) FROM camara_processamento),
Partido = TRIM(Partido),
Voto = TRIM(Voto);

TRUNCATE camara_tipoautor;
LOAD XML LOCAL INFILE 'D:\ListarTiposAutores.xml'
REPLACE
INTO TABLE camara_tipoautor
ROWS IDENTIFIED BY '<TipoAutor>'
SET codProcessamento = (SELECT MAX(camara_processamento.codProcessamento) FROM camara_processamento);

TRUNCATE camara_proposicao;
ALTER TABLE camara_proposicao CHANGE COLUMN camara_proposicao.Data camara_proposicao.Data CHAR(10) NULL DEFAULT NULL, CHANGE COLUMN DataApresentacao DataApresentacao CHAR(10) NULL DEFAULT NULL;
LOAD XML LOCAL INFILE 'D:\ObterProposicaoPorID.xml'
REPLACE
INTO TABLE camara_proposicao
ROWS IDENTIFIED BY '<proposicao>'
SET codProcessamento = (SELECT MAX(camara_processamento.codProcessamento) FROM camara_processamento),
tipo = TRIM(tipo),
idProposicaoPrincipal = (CASE WHEN (SELECT idProposicaoPrincipal REGEXP '[0-9]') = 1 THEN idProposicaoPrincipal ELSE NULL END),
partidoAutor = TRIM(partidoAutor),
DataApresentacao = STR_TO_DATE(DataApresentacao, '%d/%m/%Y'),
camara_proposicao.Data = STR_TO_DATE(camara_proposicao.Data, '%d/%m/%Y');
ALTER TABLE camara_proposicao CHANGE COLUMN camara_proposicao.Data camara_proposicao.Data DATE NULL DEFAULT NULL, CHANGE COLUMN DataApresentacao DataApresentacao DATE NULL DEFAULT NULL;



