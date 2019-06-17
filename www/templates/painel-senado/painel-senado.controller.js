(function () {
    'use strict';

    angular
        .module('SrCidadao')
        .controller('PainelSenadoCtrl', Controller);

    function Controller($scope, $document, $uibModal, $interval, $filter, DownloadService, ProcessaService, NotificaService , ConfigurationService) {

        var vm = this;
        vm.initController = initController;

        //Variáveis
        vm.itensLog = [];

        vm.pageInfo = {
            execucao : {running : false, inicio : new Date()}
        }

        vm.execInfo = {
            senadores : {tempo : 1440, running : false, interval : undefined, ultimaExecucao : undefined},
            materias : {tempo : 1450, running : false, interval : undefined, ultimaExecucao : undefined, dataInicial : moment().format("DD/MM/YYYY"), dataFinal : moment().format("DD/MM/YYYY")},
            partidos : {tempo : 1490 * 15, running : false, interval : undefined, ultimaExecucao : undefined},
            detMateria : {tempo : 1450, running : false, interval : undefined, ultimaExecucao : undefined, dataInicial : moment().format("DD/MM/YYYY"), dataFinal : moment().format("DD/MM/YYYY")},
            votacoes : {tempo : 1460, running : false, interval : undefined, ultimaExecucao : undefined, anoInicial : moment().format("YYYY"), anoFinal : moment().format("YYYY")}
        }

        //Cód. processamento obrigatório do senado
        $scope.codProcessamento = '';

        //Funções
        vm.criaCodigoProcessamentoSenado = criaCodigoProcessamentoSenado;
        vm.downloadSenadores = downloadSenadores;
        vm.downloadMaterias = downloadMaterias;
        vm.downloadDetMateria = downloadDetMateria;
        vm.downloadVotacoes = downloadVotacoes;


        vm.initController();

        function initController() {
            getLogs();
        }

        //Cria código de processamento no banco/No senado este codigo de processamento será usado nas outras funçoes então ele é obrigatorio
        function criaCodigoProcessamentoSenado(){
            DownloadService.criaCodigoProcessamentoSenado(function(success, response){
                if(!success || !response.success){
                    var title = "Erro";
                    var body = "Erro ao criar código de processamento.";
                    var error = true;
                    openModal(title, body, error);
                }else{
                    $scope.codProcessamento = response.codProcessamento;
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

        //Recupera XML do senado insere no banco - INFO Senadores
        function downloadSenadores(callback){
            if(angular.isUndefined(callback)) callback = function(){vm.execInfo.senadores.running = false;}
            vm.execInfo.senadores.running = true;
                if($scope.codProcessamento != ""){ //Criou código com sucesso
                    DownloadService.downloadSenadores($scope.codProcessamento, function(successDownload, responseDownload){
                        if(successDownload && responseDownload && responseDownload.success){
                            adicionaLog(true, responseDownload);
                            ProcessaService.processaSenadores($scope.codProcessamento, function(successProcessa, responseProcessa){
                                if(successProcessa && responseProcessa && responseProcessa.success){
                                    adicionaLog(true, responseProcessa);
                                    vm.execInfo.senadores.running = false;
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
        }


        //Recupera XML das materias insere no banco - INFO Senadores
        function downloadMaterias(callback){
            if(angular.isUndefined(callback)) callback = function(){vm.execInfo.materias.running = false;}
            vm.execInfo.materias.running = true;
                if($scope.codProcessamento != ""){ //Criou código com sucesso
                    DownloadService.downloadMaterias($scope.codProcessamento,vm.execInfo.materias.dataInicial, function(successDownload, responseDownload){
                        if(successDownload && responseDownload && responseDownload.success){
                            adicionaLog(true, responseDownload);
                            ProcessaService.processaMaterias($scope.codProcessamento, function(successProcessa, responseProcessa){
                                if(successProcessa && responseProcessa && responseProcessa.success){
                                    adicionaLog(true, responseProcessa);
                                    vm.execInfo.materias.running = false;
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
        }

        //Recupera XML do detalhe das materias insere no banco - INFO Senadores
        function downloadDetMateria(callback){
            if(angular.isUndefined(callback)) callback = function(){vm.execInfo.detMateria.running = false;}
            vm.execInfo.detMateria.running = true;
                if($scope.codProcessamento != ""){ //Criou código com sucesso
                    DownloadService.downloadDetMaterias($scope.codProcessamento, function(successDownload, responseDownload){
                        if(successDownload && responseDownload && responseDownload.success){
                            adicionaLog(true, responseDownload);
                            ProcessaService.processaDetMaterias($scope.codProcessamento, function(successProcessa, responseProcessa){
                                if(successProcessa && responseProcessa && responseProcessa.success){
                                    adicionaLog(true, responseProcessa);
                                    vm.execInfo.detMateria.running = false;
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
        }

        //Recupera XML do detalhe das materias insere no banco - INFO Senadores
        function downloadVotacoes(callback){
            if(angular.isUndefined(callback)) callback = function(){vm.execInfo.votacoes.running = false;}
            vm.execInfo.votacoes.running = true;
                if($scope.codProcessamento != ""){ //Criou código com sucesso
                    DownloadService.downloadVotacoes($scope.codProcessamento, function(successDownload, responseDownload){
                        if(successDownload && responseDownload && responseDownload.success){
                            adicionaLog(true, responseDownload);
                            ProcessaService.processaVotacoes($scope.codProcessamento, function(successProcessa, responseProcessa){
                                if(successProcessa && responseProcessa && responseProcessa.success){
                                    adicionaLog(true, responseProcessa);
                                    vm.execInfo.votacoes.running = false;
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
