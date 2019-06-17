(function () {
    'use strict';

    angular
        .module('SrCidadao')
        .factory('TarefaService', TarefaService);    
        
    function TarefaService($q, $http, configuracao, ConfigurationService) {  
    
        var service = {};

        service.getTarefas = getTarefas;  
        service.getUrl = getUrl;  
        service.addLog = addLog;  

        return service;
                
        function getTarefas(callback){ 
            $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/tarefas/todas')
                .success(function(response) {
                    if (response) {
                        callback(true, response);
                    }
                })
                .error(function() { //Erro
                    callback(false);
                });
        } 
        
        function addLog(data, callback){ 
            $http.post(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/tarefas/log', data)
                .success(function(response) {
                    if (response) {
                        callback(true, response);
                    }
                })
                .error(function() { //Erro
                    callback(false);
                });
        }         
        
        function getUrl(url, callback){ 
            $http.get(url)
                .success(function(response) {
                    if (response) {
                        callback(true, response);
                    }
                })
                .error(function() { //Erro
                    callback(false);
                });
        }         
    }
})();