(function () {
    'use strict';

    angular
        .module('SrCidadao')
        .factory('DownloadService', DownloadService);

    function DownloadService($q, $http, configuracao, ConfigurationService) {

        var service = {};

        service.criaCodigoProcessamento = criaCodigoProcessamento;
        service.downloadDeputados = downloadDeputados;
        service.downloadPartidos = downloadPartidos;
        service.downloadPresenca = downloadPresenca;
        service.downloadProposicoes = downloadProposicoes;
        service.downloadDetalhesProposicoes = downloadDetalhesProposicoes;
        service.downloadVotos = downloadVotos;
        service.criaCodigoProcessamentoSenado = criaCodigoProcessamentoSenado;
        service.downloadSenadores = downloadSenadores;
        service.downloadMaterias = downloadMaterias;
        service.downloadDetMaterias = downloadDetMaterias;
        service.downloadVotacoes = downloadVotacoes;
        
        service.etapaConcluida = etapaConcluida;
        
        service.getLogs = getLogs;

        return service;

        function getLogs(callback){
            $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/processamentos/logs')
                .success(function(response) {
                    if (response) {
                        callback(true, response);
                    }
                })
                .error(function() { //Erro
                    callback(false);
                });
        }

        function criaCodigoProcessamento(callback){
            $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/processamentos/codigo')
                .success(function(response) {
                    if (response) {
                        callback(true, response);
                    }
                })
                .error(function() { //Erro
                    callback(false);
                });
        }
        
        function etapaConcluida(codProcessamento, flgTipo, callback){
            $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/processamentos/concluido?codProcessamento=' + codProcessamento + '&flgTipo=' + flgTipo)
                .success(function(response) {
                    if (response) {
                        callback(true, response);
                    }
                })
                .error(function() { //Erro
                    callback(false);
                });
        }

        
       function criaCodigoProcessamentoSenado(callback){
           $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/senado/processamentos/codigo')
             .success(function(response) {
              if (response) {

                  callback(true, response);
              }
          })
          .error(function() { //Erro
              callback(false);
          });
}

        function downloadDeputados(codProcessamento, callback){
            $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/download/deputados?codProcessamento=' + codProcessamento)
                .success(function(response) {
                    if (response) {
                        callback(true, response);
                    }
                })
                .error(function(response) { //Erro
                    callback(false, response);
                });
        }

        function downloadPartidos(codProcessamento, callback){
            $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/download/partidos?codProcessamento=' + codProcessamento)
                .success(function(response) {
                    if (response) {
                        callback(true, response);
                    }
                })
                .error(function(response) { //Erro
                    callback(false, response);
                });
        }

        function downloadPresenca(codProcessamento, dataInicial, dataFinal, callback){
            $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/download/presencas?codProcessamento=' + codProcessamento + '&dataInicial=' + dataInicial + '&dataFinal=' + dataFinal, { timeout: ConfigurationService.getTimeout() })
                .success(function(response) {
                    if (response) {
                        callback(true, response);
                    }
                })
                .error(function(response) { //Erro
                    callback(false, response);
                });
        }

        function downloadProposicoes(codProcessamento, anoInicial, anoFinal, callback){
            $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/download/proposicoes/votadas?codProcessamento=' + codProcessamento + '&anoInicial=' + anoInicial + '&anoFinal=' + anoFinal, { timeout: ConfigurationService.getTimeout() })
                .success(function(response) {
                    if (response) {
                        callback(true, response);
                    }
                })
                .error(function(response) { //Erro
                    callback(false, response);
                });
        }

        function downloadDetalhesProposicoes(codProcessamento, dataInicial, dataFinal, callback){
            $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/download/proposicoes?codProcessamento=' + codProcessamento + '&dataInicial=' + dataInicial + '&dataFinal=' + dataFinal, { timeout: ConfigurationService.getTimeout() })
                .success(function(response) {
                    if (response) {
                        callback(true, response);
                    }
                })
                .error(function(response) { //Erro
                    callback(false, response);
                });
        }

        function downloadVotos(codProcessamento, dataInicial, dataFinal, callback){
            $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/download/proposicoes/votacoes?codProcessamento=' + codProcessamento + '&dataInicial=' + dataInicial + '&dataFinal=' + dataFinal, { timeout: ConfigurationService.getTimeout() })
                .success(function(response) {
                    if (response) {
                        callback(true, response);
                    }
                })
                .error(function(response) { //Erro
                    callback(false, response);
                });
        }


              //realiza o download dos senadores - victor
                function downloadSenadores(codProcessamento, callback){
                    $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/download/senado/senadores?codProcessamento=' + codProcessamento)
                        .success(function(response) {
                            if (response) {
                                callback(true, response);
                            }
                        })
                        .error(function(response) { //Erro
                            callback(false, response);
                        });
                }

                function downloadMaterias(codProcessamento, data, callback){
                  var date = moment(data).format('YYYYMMDD');
                  console.log(date);
                  $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/download/senado/materias?codProcessamento=' + codProcessamento + '&tramitando=N&data='+ date)
                  .success(function(response) {
                    if (response) {
                        callback(true, response);
                      }
                    })
                    .error(function(response) { //Erro
                      callback(false, response);
                    });
                }

                function downloadDetMaterias(codProcessamento, callback){
                  $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/download/senado/materia/download?codProcessamento=' + codProcessamento)
                  .success(function(response) {
                    if (response) {
                      callback(true, response);
                    }
                  })
                  .error(function(response) { //Erro
                    callback(false, response);
                  });
              }

              function downloadVotacoes(codProcessamento, callback){
                $http.get(ConfigurationService.getApiEndPoint() + ConfigurationService.getApiVersion() + '/download/senado/materia/votacao?codProcessamento=' + codProcessamento)
                  .success(function(response) {
                      if (response) {
                          callback(true, response);
                        }
                      })
                  .error(function(response) { //Erro
                      callback(false, response);
                    });
           }
    }
})();
