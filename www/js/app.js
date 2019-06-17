angular
    .module('SrCidadao', ['ngRoute', 'ui.bootstrap', 'angularMoment', 'ngMap', 'chart.js'])
    .constant("configuracao", {
        "services": "http://coletor.srcidadao.dev.br/public/",
        "siteUrl": "http://coletor.srcidadao.dev.br/www/",
		"remoteServicesDev": "http://servicos.srcidadao.dev.br/public/",		
		
        //"services": "http://coletor.srcidadao.com.br/public/",
       // "remoteServices": "http://servicos.srcidadao.com.br/public/",
        //"siteUrl": "http://coletor.srcidadao.com.br/www/",	
		
        "apiVersion": "v1",
        "timeout": 1800000
    })
    .run(run)
    .config(config);

    function config($httpProvider, $routeProvider) {
        $routeProvider
            .when("/painel", {
                templateUrl: "templates/painel/painel.html",
                controller : 'PainelCtrl',
                controllerAs: 'vm'
             })
             .when("/painel-senado", {
                 templateUrl: "templates/painel-senado/painel-senado.html",
                 controller : 'PainelSenadoCtrl',
                 controllerAs: 'vm'
              })
            .when("/tarefa", {
                templateUrl: "templates/tarefa/tarefa.html",
                controller : 'TarefaCtrl',
                controllerAs: 'vm'
             })
             .when("/graficos", {
                templateUrl: "templates/graficos/graficos.html",
                controller : 'GraficosCtrl',
                controllerAs: 'vm'
             })
            .otherwise("/404", {
                templateUrl: "templates/error/404.html"
            });
    }

    function run($rootScope, $templateCache) {
        $rootScope.$on('$viewContentLoaded', function(){
            $templateCache.removeAll();
        });
    }
