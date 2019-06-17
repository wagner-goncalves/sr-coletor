(function () {
    'use strict';

    angular
        .module('SrCidadao')
        .controller('PainelCtrl', Controller);

    function Controller($scope, $document, $uibModal, $interval, $filter, DownloadService, ProcessaService, NotificaService , ConfigurationService) {

        var vm = this;
        vm.initController = initController;
        
        //Variáveis
        vm.itensLog = [];
        
        vm.pageInfo = {
            execucao : {running : false, inicio : new Date()}
        }
        
        vm.execInfo = {
            deputados : {tempo : 1440, running : false, interval : undefined, ultimaExecucao : undefined},
            partidos : {tempo : 1490 * 15, running : false, interval : undefined, ultimaExecucao : undefined},
            presenca : {tempo : 1450, running : false, interval : undefined, ultimaExecucao : undefined, dataInicial : moment().format("DD/MM/YYYY"), dataFinal : moment().format("DD/MM/YYYY")},
            proposicoes : {tempo : 1460, running : false, interval : undefined, ultimaExecucao : undefined, anoInicial : moment().format("YYYY"), anoFinal : moment().format("YYYY")},
            detalhesProposicoes : {tempo : 1470, running : false, interval : undefined, ultimaExecucao : undefined, dataInicial : moment().format("DD/MM/YYYY"), dataFinal : moment().format("DD/MM/YYYY")},
            votos : {tempo : 1480, running : false, interval : undefined, ultimaExecucao : undefined, dataInicial : moment().format("DD/MM/YYYY"), dataFinal : moment().format("DD/MM/YYYY")}
        }      
        
        //Funções
        vm.criaCodigoProcessamento = criaCodigoProcessamento;
        vm.downloadDeputados = downloadDeputados;
        vm.downloadPartidos = downloadPartidos;
        vm.downloadPresenca = downloadPresenca;
        vm.downloadProposicoes = downloadProposicoes;
        vm.downloadDetalhesProposicoes = downloadDetalhesProposicoes;
        vm.downloadVotos = downloadVotos;
        vm.iniciaProcessamento = iniciaProcessamento;
        vm.cancelaProcessamento = cancelaProcessamento;
        vm.iniciaPresenca = iniciaPresenca;
        
        vm.notificaPresenca = notificaPresenca;
        
        vm.initController();

        function initController() {
            getLogs();
        }
        
        function iniciaProcessamento(){
            
            vm.pageInfo.execucao.running = true;
            vm.pageInfo.execucao.inicio = new Date();
            
            angular.forEach(vm.execInfo, function(assunto, key) {
                assunto.ultimaExecucao = new Date(); //Inicia contadores
            });             
            
            //iniciaDeputados();
            //iniciaPartidos();

            vm.execInfo.deputados.interval = $interval(iniciaDeputados, vm.execInfo.deputados.tempo * 60 * 1000);
            vm.execInfo.partidos.interval = $interval(iniciaPartidos, vm.execInfo.partidos.tempo * 60 * 1000);
            vm.execInfo.presenca.interval = $interval(iniciaPresenca, vm.execInfo.presenca.tempo * 60 * 1000);
            vm.execInfo.proposicoes.interval = $interval(iniciaProposicoes, vm.execInfo.proposicoes.tempo * 60 * 1000);
            vm.execInfo.detalhesProposicoes.interval = $interval(iniciaDetalhesProposicoes, vm.execInfo.detalhesProposicoes.tempo * 60 * 1000);
            vm.execInfo.votos.interval = $interval(iniciaVotos, vm.execInfo.votos.tempo * 60 * 1000);
        }

        function reiniciaDatas(){
            angular.forEach(vm.execInfo, function(assunto, key) {
                if(assunto.dataInicial) assunto.dataInicial = moment().format("DD/MM/YYYY");
                if(assunto.dataFinal) assunto.dataFinal = moment().format("DD/MM/YYYY");
            });  
        }

        function iniciaDeputados(){
            vm.execInfo.deputados.running = true; //Inicio do processamento
            downloadDeputados(function(){
                vm.execInfo.deputados.ultimaExecucao = new Date();
                vm.execInfo.deputados.running = false; //Fim do processamento
            });
        }

        function iniciaPartidos(){
            vm.execInfo.partidos.running = true; //Inicio do processamento
            downloadPartidos(function(){
                vm.execInfo.partidos.ultimaExecucao = new Date();
                vm.execInfo.partidos.running = false; //Fim do processamento   
            });
        }

        function iniciaPresenca(){
            vm.execInfo.presenca.running = true; //Inicio do processamento

            var parametros = {
                processamento : 0, 
                flgTipo : "",
                dataInicial : vm.execInfo.presenca.dataInicial, 
                dataFinal : vm.execInfo.presenca.dataFinal
            };

            var finalizaProcessamento = function(){
                vm.execInfo.presenca.ultimaExecucao = new Date();
                vm.execInfo.presenca.running = false; //Fim do processamento
                reiniciaDatas();   
            };

            //Cria código processamento
            criaCodigoProcessamento(function(successCodigo, processamento){
                if(successCodigo){ //Criou código com sucesso
                    parametros.processamento = processamento.codProcessamento;

                    var urlsProcessamento = {
                        urlDownload : ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + 
                            '/download/presencas?codProcessamento=' + processamento.codProcessamento + '&dataInicial=' + processamento.dataInicial + '&dataFinal=' + processamento.dataFinal,
                        urlProcessamento : ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + 
                            '/processa/presencas?codProcessamento=' + processamento.codProcessamento + '&dataInicial=' + processamento.dataInicial + '&dataFinal=' + processamento.dataFinal,
                        urlNotificacao : ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + 
                            '/notifica/presenca?codProcessamento=' + processamento.codProcessamento
                    }

                    console.log(urlsProcessamento);

                    DownloadService.downloadPresenca(
                        parametros.processamento, 
                        parametros.dataInicial, 
                        parametros.dataFinal, 
                        function(successDownload, arquivosBaixados){
                           angular.forEach(arquivosBaixados, function(arquivo, key) {
                                if(arquivo && arquivo.success){
                                    adicionaLog(true, arquivo);
                                }else{
                                    adicionaLog(false, arquivo);
                                }
                            });
                        }
                    );

                    //Só processa se acabou o download
                    var verificaDownloadConcluido = function(parametros){
                        DownloadService.etapaConcluida(parametros.processamento, "download", function(success, resposta){
                            if(resposta.success && resposta.flgTipo == "download"){//Já acabou a etapa anterior
                                    ProcessaService.processaPresenca(
                                        parametros.processamento, 
                                        parametros.dataInicial, 
                                        parametros.dataFinal, 
                                        function(successProcessa, arquivosBaixados){
                                            if(successProcessa){
                                                //Processa vários retornos
                                                angular.forEach(arquivosBaixados, function(arquivo, key) {
                                                    if(arquivo && arquivo.success){
                                                        adicionaLog(true, arquivo);
                                                    }else{
                                                        adicionaLog(false, arquivo);
                                                    }
                                                });
                                            }
                                        }
                                    );	
                            }else{//Não acabou - Verificar novamente
                               $timeout(verificaDownloadConcluido(parametros) , 5000);
                            }
                        });                        
                    };
                    $timeout(verificaDownloadConcluido(parametros, processaPresenca), 5000);

                    //Só notifica se acabou o processamento
                    var verificaProcessamentoConcluido = function(parametros){
                        DownloadService.etapaConcluida(parametros.processamento, "processamento", function(success, resposta){
                            if(resposta.success && resposta.flgTipo == "processamento"){//Já acabou a etapa anterior
                                NotificaService.notificaPresenca(
                                    parametros.processamento, 
                                    function(success, response){
                                        if(success && response && response.success){
                                            adicionaLog(true, response);
                                        }else{
                                            adicionaLog(false, response);
                                        }

                                        finalizaProcessamento(); //Informa o fim do processamento
                                    }
                                );
                            }else{//Não acabou - Verificar novamente
                               $timeout(verificaProcessamentoConcluido(parametros) , 5000);
                            }
                        });                        
                    };
                    $timeout(verificaProcessamentoConcluido(parametros, processaPresenca), 5000);                         


                }else{
                    finalizaProcessamento();
                }
            });
        }     

        function iniciaProposicoes(){
            vm.execInfo.proposicoes.running = true; //Inicio do processamento

            downloadProposicoes(function(){
                vm.execInfo.proposicoes.ultimaExecucao = new Date();
                vm.execInfo.proposicoes.running = false; //Fim do processamento  
                reiniciaDatas(); 
            });
        }  

        function iniciaDetalhesProposicoes(){

            vm.execInfo.detalhesProposicoes.running = true; //Inicio do processamento
            downloadDetalhesProposicoes(function(){
                vm.execInfo.detalhesProposicoes.ultimaExecucao = new Date();
                vm.execInfo.detalhesProposicoes.running = false; //Fim do processamento 
                reiniciaDatas();  
            });
        }

        function iniciaVotos(){
            vm.execInfo.votos.running = true; //Inicio do processamento

            downloadVotos(function(){
                vm.execInfo.votos.ultimaExecucao = new Date();
                vm.execInfo.votos.running = false; //Fim do processamento   
                reiniciaDatas();
            });
        }     
        
        function cancelaProcessamento(){
            vm.pageInfo.execucao.running = false;
            angular.forEach(vm.execInfo, function(value, key) {
                if(value.interval){ 
                    $interval.cancel(value.interval);
                    value.running = false;
                }
            });            
        }
        
        //Cria código de processamento no banco
        function criaCodigoProcessamento(callback){
            DownloadService.criaCodigoProcessamento(function(success, response){
                if(!success || !response.success){
                    var title = "Erro";
                    var body = "Erro ao criar código de processamento.";
                    var error = true;
                    openModal(title, body, error);
                    callback(false, response);
                }else{
                    callback(true, response);
                } 
            });
        }
        
        //Recupera logs gravados em banco de dados
        function getLogs(){
            DownloadService.getLogs(function(success, response){
                if(success && response){    
                    vm.itensLog = response;
                }
            });
        }
        
        //Recupera XML da Câmara dos Deputados e insere no banco - INFO DEPUTADOS
        function downloadDeputados(callback){
            if(angular.isUndefined(callback)) callback = function(){vm.execInfo.deputados.running = false;}
            vm.execInfo.deputados.running = true;
            criaCodigoProcessamento(function(success, response){
                if(success){ //Criou código com sucesso
                    DownloadService.downloadDeputados(response.codProcessamento, function(successDownload, responseDownload){
                        if(successDownload && responseDownload && responseDownload.success){    
                            adicionaLog(true, responseDownload);
                            ProcessaService.processaDeputados(response.codProcessamento, function(successProcessa, responseProcessa){
                                if(successProcessa && responseProcessa && responseProcessa.success){    
                                    adicionaLog(true, responseProcessa);
                                }else{
                                    adicionaLog(false, responseProcessa);
                                }
                                callback(); //Informa o fim do processamento
                            });
                        }else{
                            adicionaLog(false, responseDownload); 
                            callback(); //Informa o fim do processamento
                        }
                    });
                }else{
                    callback(); //Informa o fim do processamento    
                }
            });
        }    
        
        //Recupera XML da Câmara dos Deputados e insere no banco - INFO PARTIDOS        
        function downloadPartidos(callback){
            if(angular.isUndefined(callback)) callback = function(){vm.execInfo.partidos.running = false;}
            vm.execInfo.partidos.running = true;
            criaCodigoProcessamento(function(success, response){
                if(success){ //Criou código com sucesso
                    DownloadService.downloadPartidos(response.codProcessamento, function(successDownload, responseDownload){
                        if(successDownload && responseDownload && responseDownload.success){    
                            adicionaLog(true, responseDownload);
                            ProcessaService.processaPartidos(response.codProcessamento, function(successProcessa, responseProcessa){
                                if(successProcessa && responseProcessa && responseProcessa.success){  
                                    adicionaLog(true, responseProcessa);       
                                }else{
                                    adicionaLog(false, responseProcessa); 
                                }
                                callback(); //Informa o fim do processamento
                            });
                        }else{
                            adicionaLog(false, responseDownload); 
                            callback(); //Informa o fim do processamento
                        }
                    });
                }else{
                    callback(); //Informa o fim do processamento    
                }
            });
        } 
        
        //Recupera XML da Câmara dos Deputados e insere no banco - INFO PRESENCAO        
        function downloadPresenca(parametros){
 
        }
        
        function processaPresenca(parametros){
           
        }

        function notificaPresenca(parametros){

        }
        
        //Recupera XML da Câmara dos Deputados e insere no banco - INFO PROPOSICOES VOTADAS        
        function downloadProposicoes(callback){
            if(angular.isUndefined(callback)) callback = function(){vm.execInfo.proposicoes.running = false;}
            vm.execInfo.proposicoes.running = true;
            criaCodigoProcessamento(function(success, response){
                if(success){ //Criou código com sucesso
                
                    var anoInicial =  vm.execInfo.proposicoes.anoInicial;
                    var anoFinal = vm.execInfo.proposicoes.anoFinal;
                    
                    var urlsProcessamento = {
                        urlDownload : ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/download/proposicoes/votadas?codProcessamento=' + response.codProcessamento + '&anoInicial=' + anoInicial + '&anoFinal=' + anoFinal,
                        urlProcessamento : ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/processa/proposicoes/votadas?codProcessamento=' + response.codProcessamento + '&anoInicial=' + anoInicial + '&anoFinal=' + anoFinal,
                    }
                    console.log(urlsProcessamento);
                
                    DownloadService.downloadProposicoes(response.codProcessamento, anoInicial, anoFinal, function(successDownload, responseDownload){
                        if(successDownload && responseDownload){
                            //Processa vários retornos
                            angular.forEach(responseDownload, function(umaResposta, key) {
                                if(umaResposta && umaResposta.success){
                                    adicionaLog(true, umaResposta);
                                }else{
                                    adicionaLog(false, umaResposta);
                                    callback(); //Informa o fim do processamento
                                }
                            }); 
							
							ProcessaService.processaProposicoes(response.codProcessamento, anoInicial, anoFinal, function(successProcessa, responseProcessa){
								if(successProcessa){
									//Processa vários retornos
									angular.forEach(responseProcessa, function(outraResposta, key) {
										if(outraResposta && outraResposta.success){
											adicionaLog(true, outraResposta);
										}else{
											adicionaLog(false, outraResposta);
										}
									});
								}
								callback(); //Informa o fim do processamento
							});
                        }else{
                            adicionaLog(false, responseDownload); 
                            callback(); //Informa o fim do processamento
                        }                        
                    });
                }else{
                    callback(); //Informa o fim do processamento    
                }
            });
        }          
              
        //Recupera XML da Câmara dos Deputados e insere no banco - DETALHES PROPOSICOES VOTADAS
        function downloadDetalhesProposicoes(callback){
            if(angular.isUndefined(callback)) callback = function(){vm.execInfo.detalhesProposicoes.running = false;}
            vm.execInfo.detalhesProposicoes.running = true;
            criaCodigoProcessamento(function(success, response){
                if(success){ //Criou código com sucesso
                
                    var dataInicial =  vm.execInfo.detalhesProposicoes.dataInicial;
                    var dataFinal = vm.execInfo.detalhesProposicoes.dataFinal;

                    var urlsProcessamento = {
                        urlDownload : ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/download/proposicoes?codProcessamento=' + response.codProcessamento + '&dataInicial=' + dataInicial + '&dataFinal=' + dataFinal,
                        urlProcessamento : ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/processa/proposicoes?codProcessamento=' + response.codProcessamento + '&dataInicial=' + dataInicial + '&dataFinal=' + dataFinal,
                    }
                    console.log(urlsProcessamento);
                
                    DownloadService.downloadDetalhesProposicoes(response.codProcessamento, dataInicial, dataFinal, function(successDownload, responseDownload){
                        if(successDownload && responseDownload){
                            //Processa vários retornos
                            angular.forEach(responseDownload, function(umaResposta, key) {
                                if(umaResposta && umaResposta.success){
                                    adicionaLog(true, umaResposta);                                    
                                }else{
                                    adicionaLog(false, umaResposta);
                                    callback(); //Informa o fim do processamento
                                }
                            }); 
							ProcessaService.processaDetalhesProposicoes(response.codProcessamento, dataInicial, dataFinal, function(successProcessa, responseProcessa){
								if(successProcessa){
									//Processa vários retornos
									angular.forEach(responseProcessa, function(outraResposta, key) {
										if(outraResposta && outraResposta.success){
											adicionaLog(true, outraResposta);
										}else{
											adicionaLog(false, outraResposta);
										}
									});
								}
								callback(); //Informa o fim do processamento
							});							
                        }else{
                            adicionaLog(false, responseDownload); 
                            callback(); //Informa o fim do processamento
                        }                        
                    });
                }else{
                    callback(); //Informa o fim do processamento    
                }
            });
        }  
        
        //Recupera XML da Câmara dos Deputados e insere no banco - VOTOS
        function downloadVotos(callback){
            if(angular.isUndefined(callback)) callback = function(){vm.execInfo.votos.running = false;}
            vm.execInfo.votos.running = true;
            criaCodigoProcessamento(function(success, response){
                if(success){ //Criou código com sucesso
                
                    var dataInicial = vm.execInfo.votos.dataInicial;
                    var dataFinal = vm.execInfo.votos.dataFinal;
                    
                    var urlsProcessamento = {
                        urlDownload : ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/download/proposicoes/votacoes?codProcessamento=' + response.codProcessamento + '&dataInicial=' + dataInicial + '&dataFinal=' + dataFinal,
                        urlProcessamento : ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/processa/proposicoes/votacoes?codProcessamento=' + response.codProcessamento + '&dataInicial=' + dataInicial + '&dataFinal=' + dataFinal,
                        urlNotificacao : ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/notifica/proposicoes/votacoes?codProcessamento=' + response.codProcessamento
                    }
                    console.log(urlsProcessamento);  
                
                    DownloadService.downloadVotos(response.codProcessamento, dataInicial, dataFinal, function(successDownload, responseDownload){
                        if(successDownload && responseDownload){
                            //Processa vários retornos
                            angular.forEach(responseDownload, function(umaResposta, key) {
                                if(umaResposta && umaResposta.success){
                                    adicionaLog(true, umaResposta); 
                                }else{
                                    adicionaLog(false, umaResposta);
                                    callback(); //Informa o fim do processamento
                                }
                            }); 
							
							ProcessaService.processaVotos(response.codProcessamento, dataInicial, dataFinal, function(successProcessa, responseProcessa){
								if(successProcessa){
									//Processa vários retornos
									angular.forEach(responseProcessa, function(outraResposta, key) {
										if(outraResposta && outraResposta.success){
											adicionaLog(true, outraResposta);
										}else{
											adicionaLog(false, outraResposta);
										}
									});
								}
								
								notificaVotacao(response.codProcessamento, callback); //Cria notificações no banco
								//callback(); //Informa o fim do processamento
							});							
							
                        }else{
                            adicionaLog(false, responseDownload); 
                            callback(); //Informa o fim do processamento
                        }                        
                    });
                }else{
                    callback(); //Informa o fim do processamento    
                }
            });

        }     
        
        function notificaVotacao(codProcessamento, callback){
            NotificaService.notificaVotacao(codProcessamento, function(success, response){
                if(success && response && response.success){
                    adicionaLog(true, response);
                }else{
                    adicionaLog(false, response);
                }

                callback(); //Informa o fim do processamento
            });
        }           
        
        //Cria uma linha na tabela de logs
        function adicionaLog(success, response){
            vm.itensLog.unshift(response.log);
        }      
        
        //Abre modal genérico
        function openModal(title, body, error){
            var modalInstance = $uibModal.open({
                animation: true,
                ariaLabelledBy: 'modal-title',
                ariaDescribedBy: 'modal-body',
                templateUrl: ConfigurationService.getSiteUrl() + 'templates/genericos/modal.html',
                controller: function($scope, $uibModalInstance){
                    $scope.modalTitle = title;
                    $scope.modalBody = body;   
                    $scope.modalError = error;
                    $scope.closeModal = function(){
                        $uibModalInstance.dismiss('cancel');    
                    }    
                }
            });
        } 
    }

})();