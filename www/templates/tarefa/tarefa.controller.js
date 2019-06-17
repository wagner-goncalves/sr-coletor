(function () {
    'use strict';

    angular
        .module('SrCidadao')
        .controller('TarefaCtrl', Controller);

    function Controller($scope, $document, $interval, ConfigurationService, TarefaService) {

        var vm = this;
        vm.initController = initController;
        vm.getTarefas = getTarefas;
        vm.iniciaProcessamento = iniciaProcessamento;
        vm.cancelaProcessamento = cancelaProcessamento;
        
        //Variáveis
        vm.tarefas = [];
        vm.itensLog = [];
        vm.pageInfo = {
            execucao : {running : false, inicio : new Date()}
        }        
        
        //Funções
        
        vm.initController();

        function initController() {
            vm.getTarefas();
        }
        
        function iniciaProcessamento(){
            vm.pageInfo.execucao.running = true;
            angular.forEach(vm.tarefas, function(tarefa, key) {
                var intervalo = parseInt(tarefa.intervalo);
                intervalo = intervalo * 1000;
                tarefa.interval = $interval(function(){
                    var logTarefa = {
                        oidTarefa : tarefa.oidTarefa,
                        dataHoraInicio : moment().format("YYYY-MM-DD HH:mm:ss")
                    }

                    tarefa.running = true;
                    TarefaService.getUrl(tarefa.url, function(success, response){
                        logTarefa.dataHoraFim = moment().format("YYYY-MM-DD HH:mm:ss");
                        logTarefa.resposta = JSON.stringify(response);
                        
                        TarefaService.addLog(logTarefa, function(sucessLog, responseLog){
                            adicionaLog(responseLog);
                            tarefa.running = false;
                        });
                    });
                }, intervalo);
            });    
        }
        
        function cancelaProcessamento(){
            vm.pageInfo.execucao.running = false;
            angular.forEach(vm.tarefas, function(tarefa, key) {
                
              if (angular.isDefined(tarefa.interval)) {
                $interval.cancel(tarefa.interval);
                tarefa.interval = undefined;
                tarefa.running = false;
              }                

            });    
        }        
        
        //Recupera tarefas gravados em banco de dados
        function getTarefas(){
            TarefaService.getTarefas(function(success, response){
                if(success && response && response.tarefas){    
                    vm.tarefas = response.tarefas;
                }
            });
        }     
        
        //Cria uma linha na tabela de logs
        function adicionaLog(response){
            vm.itensLog.unshift(response.tarefa);
        }            

    }

})();