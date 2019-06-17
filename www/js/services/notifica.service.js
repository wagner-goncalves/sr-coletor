(function () {
    'use strict';

    angular
        .module('SrCidadao')
        .factory('NotificaService', NotificaService);    
        
    function NotificaService($q, $http, configuracao, ConfigurationService) {  
    
        var service = {};

        service.notificaPresenca = notificaPresenca;      
        service.notificaVotacao = notificaVotacao;   

        return service;
                
        function notificaPresenca(codProcessamento, callback){ 
            $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/notifica/presenca?codProcessamento=' + codProcessamento)
                .success(function(response) {
                    if (response) {
                        callback(true, response);
                    }
                })
                .error(function() { //Erro
                    callback(false);
                });
        }  
        
        function notificaVotacao(codProcessamento, callback){ 
            $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/notifica/proposicoes/votacoes?codProcessamento=' + codProcessamento)
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