(function () {
    'use strict';

    angular
        .module('SrCidadao')
        .factory('ProcessaService', ProcessaService);

    function ProcessaService($q, $http, configuracao, ConfigurationService) {

        var service = {};

        service.processaDeputados = processaDeputados;
        service.processaPartidos = processaPartidos;
        service.processaPresenca = processaPresenca;
        service.processaProposicoes = processaProposicoes;
        service.processaDetalhesProposicoes = processaDetalhesProposicoes;
        service.processaVotos = processaVotos;
        service.processaSenadores = processaSenadores;
        service.processaMaterias = processaMaterias;
        service.processaDetMaterias = processaDetMaterias;
        service.processaVotacoes = processaVotacoes;

        return service;

        function processaDeputados(codProcessamento, callback){
            $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/processa/deputados?codProcessamento=' + codProcessamento)
                .success(function(response) {
                    if (response) {
                        callback(true, response);
                    }
                })
                .error(function() { //Erro
                    callback(false);
                });
        }

        function processaPartidos(codProcessamento, callback){
            $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/processa/partidos?codProcessamento=' + codProcessamento)
                .success(function(response) {
                    console.log(response);
                    if (response) {
                        callback(true, response);
                    }
                })
                .error(function(response) { //Erro
                    console.log(response);
                    callback(false, response);
                });
        }

        function processaPresenca(codProcessamento, dataInicial, dataFinal, callback){
            $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/processa/presencas?codProcessamento=' + codProcessamento + '&dataInicial=' + dataInicial + '&dataFinal=' + dataFinal, { timeout: ConfigurationService.getTimeout() })
                .success(function(response) {
                    if (response) {
                        callback(true, response);
                    }
                })
                .error(function(response) { //Erro
                    callback(false, response);
                });
        }

        function processaProposicoes(codProcessamento, anoInicial, anoFinal, callback){
            $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/processa/proposicoes/votadas?codProcessamento=' + codProcessamento + '&anoInicial=' + anoInicial + '&anoFinal=' + anoFinal, { timeout: ConfigurationService.getTimeout() })
                .success(function(response) {
                    if (response) {
                        callback(true, response);
                    }
                })
                .error(function(response) { //Erro
                    callback(false, response);
                });
        }

        function processaDetalhesProposicoes(codProcessamento, dataInicial, dataFinal, callback){
            $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/processa/proposicoes?codProcessamento=' + codProcessamento + '&dataInicial=' + dataInicial + '&dataFinal=' + dataFinal, { timeout: ConfigurationService.getTimeout() })
                .success(function(response) {
                    if (response) {
                        callback(true, response);
                    }
                })
                .error(function(response) { //Erro
                    callback(false, response);
                });
        }

        function processaVotos(codProcessamento, dataInicial, dataFinal, callback){
            $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/processa/proposicoes/votacoes?codProcessamento=' + codProcessamento + '&dataInicial=' + dataInicial + '&dataFinal=' + dataFinal, { timeout: ConfigurationService.getTimeout() })
                .success(function(response) {
                    if (response) {
                        callback(true, response);
                    }
                })
                .error(function(response) { //Erro
                    callback(false, response);
                });
        }

        function processaSenadores(codProcessamento, callback){
          $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/processa/senado/senadores?codProcessamento=' + codProcessamento)
              .success(function(response) {
                  if (response) {
                      callback(true, response);
                  }
              })
              .error(function() { //Erro
                  callback(false);
              });
      }

      function processaMaterias(codProcessamento, callback){
          $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/processa/senado/materias?codProcessamento=' + codProcessamento)
              .success(function(response) {
                  if (response) {
                      callback(true, response);
                  }
              })
              .error(function() { //Erro
                  callback(false);
              });
      }

      function processaDetMaterias(codProcessamento, callback){
          $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/processa/senado/materia/processa?codProcessamento=' + codProcessamento)
              .success(function(response) {
                  if (response) {
                      callback(true, response);
                  }
              })
              .error(function() { //Erro
                  callback(false);
              });
      }

      function processaVotacoes(codProcessamento, callback){
          $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/processa/senado/materia/votacao?codProcessamento=' + codProcessamento)
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
