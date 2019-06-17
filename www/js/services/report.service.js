(function () {
    'use strict';

    angular
        .module('SrCidadao')
        .factory('ReportService', ReportService);    
        
    function ReportService($q, $http, configuracao, ConfigurationService) {  
    
        var service = {};

        service.lastUser = lastUser;      
        service.appUseLocation = appUseLocation; 
        service.topLike = topLike; 

        return service;
                
        function lastUser(callback){ 
            $http.get(ConfigurationService.getRemoteApiEndPoint() + ConfigurationService.getApiVersion() + '/report/last-user')
                .success(function(response) {
                    if (response) {
                        callback(true, response);
                    }
                })
                .error(function() { //Erro
                    callback(false);
                });
        }  
        
        function appUseLocation(params, callback){ 
            $http.get(ConfigurationService.getRemoteApiEndPoint() + ConfigurationService.getApiVersion() + '/report/app-use-location', params)
                .success(function(response) {
                    if (response) {
                        callback(true, response);
                    }
                })
                .error(function() { //Erro
                    callback(false);
                });
        }  
        
        function topLike(params, callback){ 
            $http.get(ConfigurationService.getRemoteApiEndPoint() + ConfigurationService.getApiVersion() + '/report/top-like', params)
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