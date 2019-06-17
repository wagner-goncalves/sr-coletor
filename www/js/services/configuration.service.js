(function () {
    'use strict';

    angular
        .module('SrCidadao')
        .factory('ConfigurationService', ConfigurationService);    
        
    function ConfigurationService(configuracao) {  
    
        var service = {};

        service.getApiEndPoint = getApiEndPoint;
        service.getRemoteApiEndPoint = getRemoteApiEndPoint;
        service.getApiVersion = getApiVersion;
        service.getSiteUrl = getSiteUrl;
		service.getTimeout = getTimeout;

        return service;     

        function getSiteUrl(){        
            return configuracao.siteUrl;    
        }   

        function getApiEndPoint(){        
            return configuracao.services;    
        }  
        
        function getRemoteApiEndPoint(){        
            return configuracao.remoteServices;    
        }          
        
        function getApiVersion(){        
            return configuracao.apiVersion;    
        }  
		
        function getTimeout(){        
            return configuracao.timeout;    
        }  		
        
        function desenvolvimento(){       
            if(window.cordova) return false;
            else return true;
        }                       
      
    }
})();